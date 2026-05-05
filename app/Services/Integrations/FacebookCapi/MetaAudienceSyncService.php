<?php

namespace App\Services\Integrations\FacebookCapi;

use App\Models\CustomerAudienceSegment;
use App\Models\Integrations\FacebookCapi\FacebookCapiCustomAudience;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceOrganizerAudienceSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MetaAudienceSyncService
{
    public function __construct(protected FacebookCapiService $capi)
    {
    }

    /**
     * Sync one (organizer, segment) subscription to Meta. Idempotent: creates
     * the audience on Meta the first time, then upserts members on subsequent
     * runs.
     *
     * @return array{success:bool, message:string, member_count:int, audience_id?:string}
     */
    public function syncSubscription(MarketplaceOrganizerAudienceSubscription $subscription): array
    {
        $organizer = $subscription->organizer;
        $segment = $subscription->segment;

        if (!$organizer || !$segment) {
            return ['success' => false, 'message' => 'organizer or segment missing', 'member_count' => 0];
        }

        $connection = $this->capi->getConnectionForOrganizer($organizer->id);
        if (!$connection) {
            return $this->markFailed($subscription, 'No active CAPI connection for organizer');
        }
        if (!$connection->ad_account_id) {
            return $this->markFailed($subscription, 'CAPI connection has no ad_account_id; required for Custom Audiences');
        }

        try {
            $users = $this->collectUsersForSegment($organizer, $segment);

            if (empty($users)) {
                $subscription->update([
                    'last_synced_at' => now(),
                    'last_sync_status' => 'empty',
                    'last_sync_error' => null,
                    'member_count' => 0,
                ]);
                return ['success' => true, 'message' => 'No users matched the segment criteria', 'member_count' => 0];
            }

            // Ensure the audience exists on Meta. Re-use an existing one
            // (matched by name) so retries don't proliferate audiences.
            $audienceId = $subscription->meta_audience_id;
            $audienceName = $this->buildAudienceName($organizer, $segment);

            if (!$audienceId) {
                $audience = FacebookCapiCustomAudience::where('connection_id', $connection->id)
                    ->where('name', $audienceName)
                    ->first();

                if (!$audience) {
                    $audience = $this->capi->createCustomAudience(
                        $connection,
                        $audienceName,
                        $segment->description ?? '',
                        'tixello.audience.' . $segment->slug,
                    );
                }
                $audienceId = $audience->audience_id;
            } else {
                $audience = FacebookCapiCustomAudience::where('connection_id', $connection->id)
                    ->where('audience_id', $audienceId)
                    ->first();
                if (!$audience) {
                    // Subscription points to an audience id that's no longer in our
                    // audit table — recreate to be safe.
                    $audience = $this->capi->createCustomAudience(
                        $connection,
                        $audienceName,
                        $segment->description ?? '',
                        'tixello.audience.' . $segment->slug,
                    );
                    $audienceId = $audience->audience_id;
                }
            }

            // Push users in chunks (Meta limit ~10000 per request, we go safer).
            $totalSent = 0;
            foreach (array_chunk($users, 1000) as $chunk) {
                $result = $this->capi->addUsersToAudience($connection, $audience, $chunk);
                $totalSent += (int) ($result['num_received'] ?? count($chunk));
            }

            $subscription->update([
                'meta_audience_id' => $audienceId,
                'meta_audience_name' => $audienceName,
                'last_synced_at' => now(),
                'last_sync_status' => 'ok',
                'last_sync_error' => null,
                'member_count' => count($users),
            ]);

            return [
                'success' => true,
                'message' => "Synced {$totalSent}/" . count($users) . ' members to Meta audience',
                'member_count' => count($users),
                'audience_id' => $audienceId,
            ];
        } catch (\Throwable $e) {
            Log::error('MetaAudienceSync: subscription sync failed', [
                'subscription_id' => $subscription->id,
                'organizer_id' => $organizer->id,
                'segment_slug' => $segment->slug,
                'error' => $e->getMessage(),
            ]);
            return $this->markFailed($subscription, $e->getMessage());
        }
    }

    /**
     * Collect customer rows (email, phone, fn, ln) for a given segment.
     * Returns an array of associative arrays as expected by
     * FacebookCapiService::addUsersToAudience (which hashes them).
     *
     * @return array<int,array{email?:string,phone?:string,first_name?:string,last_name?:string}>
     */
    protected function collectUsersForSegment(MarketplaceOrganizer $organizer, CustomerAudienceSegment $segment): array
    {
        $criteria = $segment->criteria ?? [];
        $type = $criteria['type'] ?? null;

        return match ($type) {
            'orders' => $this->collectByOrderCriteria($organizer, $criteria),
            'ltv_percentile' => $this->collectByLtvPercentile($organizer, $criteria),
            'events' => $this->collectByEventCriteria($organizer, $criteria),
            default => [],
        };
    }

    protected function collectByOrderCriteria(MarketplaceOrganizer $organizer, array $criteria): array
    {
        $query = DB::table('orders')
            ->where('marketplace_organizer_id', $organizer->id)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereNotNull('customer_email');

        if (isset($criteria['has_paid_order_within_days'])) {
            $query->where('paid_at', '>=', now()->subDays((int) $criteria['has_paid_order_within_days']));
        }

        if (isset($criteria['last_purchase_days_ago_min'])) {
            $sub = (clone $query)
                ->select('customer_email', DB::raw('MAX(paid_at) as last_paid'))
                ->groupBy('customer_email');
            $emails = DB::query()
                ->fromSub($sub, 't')
                ->where('last_paid', '<', now()->subDays((int) $criteria['last_purchase_days_ago_min']))
                ->pluck('customer_email');
            return $this->emailsToUserData($emails->all());
        }

        if (isset($criteria['min_paid_orders'])) {
            $query->select('customer_email', DB::raw('COUNT(*) as c'))
                ->groupBy('customer_email')
                ->having(DB::raw('COUNT(*)'), '>=', (int) $criteria['min_paid_orders']);
            $emails = $query->pluck('customer_email');
            return $this->emailsToUserData($emails->all(), $organizer->id);
        }

        $emails = $query->pluck('customer_email')->unique()->values();
        return $this->emailsToUserData($emails->all(), $organizer->id);
    }

    protected function collectByLtvPercentile(MarketplaceOrganizer $organizer, array $criteria): array
    {
        $minOrders = (int) ($criteria['min_orders'] ?? 1);
        $percentileTop = max(1, min(100, (int) ($criteria['percentile_top'] ?? 10)));

        // Compute spend-per-customer for this organizer, then take top X%.
        $rows = DB::table('orders')
            ->where('marketplace_organizer_id', $organizer->id)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereNotNull('customer_email')
            ->select('customer_email', DB::raw('SUM(total) as spent'), DB::raw('COUNT(*) as c'))
            ->groupBy('customer_email')
            ->having(DB::raw('COUNT(*)'), '>=', $minOrders)
            ->orderByDesc('spent')
            ->get();

        $cutoff = (int) ceil($rows->count() * $percentileTop / 100);
        $top = $rows->take(max(1, $cutoff));

        return $this->emailsToUserData($top->pluck('customer_email')->all(), $organizer->id);
    }

    protected function collectByEventCriteria(MarketplaceOrganizer $organizer, array $criteria): array
    {
        $eventTypes = $criteria['event_types'] ?? [];
        $withinDays = (int) ($criteria['within_days'] ?? 30);
        $excludePurchasers = (bool) ($criteria['exclude_purchasers'] ?? false);

        // Resolve organizer's event ids (the join-target for tracking events).
        $organizerEventIds = DB::table('events')
            ->where('marketplace_organizer_id', $organizer->id)
            ->pluck('id');

        if ($organizerEventIds->isEmpty()) {
            return [];
        }

        $visitorIds = DB::table('core_customer_events')
            ->whereIn('marketplace_event_id', $organizerEventIds)
            ->whereIn('event_type', $eventTypes)
            ->where('occurred_at', '>=', now()->subDays($withinDays))
            ->whereNotNull('visitor_id')
            ->pluck('visitor_id')
            ->unique();

        if ($visitorIds->isEmpty()) {
            return [];
        }

        if ($excludePurchasers) {
            $purchasers = DB::table('core_customer_events')
                ->whereIn('marketplace_event_id', $organizerEventIds)
                ->where('event_type', 'purchase')
                ->whereIn('visitor_id', $visitorIds)
                ->pluck('visitor_id')
                ->unique()
                ->all();
            $visitorIds = $visitorIds->diff($purchasers)->values();
        }

        if ($visitorIds->isEmpty()) {
            return [];
        }

        // Resolve emails for these visitors via core_customers.
        $emails = DB::table('core_customers')
            ->whereIn('visitor_id', $visitorIds)
            ->whereNotNull('email')
            ->pluck('email')
            ->unique()
            ->values();

        return $this->emailsToUserData($emails->all());
    }

    /**
     * Build user_data rows from a list of emails by enriching with phone/name
     * from the most recent paid order for each (per organizer when given).
     *
     * @param array<int,string> $emails
     * @return array<int,array<string,string>>
     */
    protected function emailsToUserData(array $emails, ?int $organizerId = null): array
    {
        if (empty($emails)) {
            return [];
        }

        $query = DB::table('orders')
            ->whereIn('customer_email', $emails)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereNotNull('customer_email');
        if ($organizerId) {
            $query->where('marketplace_organizer_id', $organizerId);
        }

        $rowsByEmail = $query
            ->select('customer_email', 'customer_name', 'customer_phone')
            ->orderByDesc('paid_at')
            ->get()
            ->groupBy('customer_email');

        $out = [];
        foreach ($emails as $email) {
            $first = $rowsByEmail[$email][0] ?? null;
            $name = trim((string) ($first->customer_name ?? ''));
            $parts = $name !== '' ? preg_split('/\s+/', $name, 2) : ['', ''];
            $out[] = array_filter([
                'email' => $email,
                'phone' => $first?->customer_phone ?? '',
                'first_name' => $parts[0] ?? '',
                'last_name' => $parts[1] ?? '',
            ], fn ($v) => $v !== '' && $v !== null);
        }
        return $out;
    }

    protected function buildAudienceName(MarketplaceOrganizer $organizer, CustomerAudienceSegment $segment): string
    {
        return sprintf('Tixello — %s — %s', $segment->name, $organizer->name ?? ('Org#' . $organizer->id));
    }

    protected function markFailed(MarketplaceOrganizerAudienceSubscription $subscription, string $error): array
    {
        $subscription->update([
            'last_synced_at' => now(),
            'last_sync_status' => 'failed',
            'last_sync_error' => mb_substr($error, 0, 1000),
        ]);
        return ['success' => false, 'message' => $error, 'member_count' => 0];
    }
}
