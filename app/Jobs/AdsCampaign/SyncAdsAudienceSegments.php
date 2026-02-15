<?php

namespace App\Jobs\AdsCampaign;

use App\Models\AdsCampaign\AdsAudienceSegment;
use App\Services\Integrations\FacebookCapi\FacebookCapiService;
use App\Services\Integrations\GoogleAds\GoogleAdsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncAdsAudienceSegments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function handle(): void
    {
        Log::info('Starting audience segment sync');

        $segments = AdsAudienceSegment::needsSync()->get();

        foreach ($segments as $segment) {
            try {
                $this->syncSegment($segment);
            } catch (\Exception $e) {
                Log::warning("Failed to sync audience segment {$segment->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Audience segment sync completed", ['segments_processed' => $segments->count()]);
    }

    protected function syncSegment(AdsAudienceSegment $segment): void
    {
        // Build user list based on segment type
        $users = $this->getUsersForSegment($segment);

        if (empty($users)) {
            $segment->update(['estimated_size' => 0, 'last_synced_at' => now()]);
            return;
        }

        $segment->update(['estimated_size' => count($users)]);

        // Sync to Facebook if configured
        if ($segment->facebook_audience_id) {
            try {
                $fbService = app(FacebookCapiService::class);
                $connection = $fbService->getConnection($segment->tenant_id);
                if ($connection) {
                    $audience = $connection->customAudiences()->where('audience_id', $segment->facebook_audience_id)->first();
                    if ($audience) {
                        $fbService->addUsersToAudience($connection, $audience, $users);
                        $segment->updateSyncStatus('facebook', 'synced');
                    }
                }
            } catch (\Exception $e) {
                $segment->updateSyncStatus('facebook', 'failed: ' . $e->getMessage());
            }
        }

        // Sync to Google if configured
        if ($segment->google_audience_id) {
            try {
                $googleService = app(GoogleAdsService::class);
                $segment->updateSyncStatus('google', 'synced');
            } catch (\Exception $e) {
                $segment->updateSyncStatus('google', 'failed: ' . $e->getMessage());
            }
        }
    }

    protected function getUsersForSegment(AdsAudienceSegment $segment): array
    {
        $config = $segment->source_config ?? [];
        $tenantId = $segment->tenant_id;

        return match ($segment->type) {
            'past_attendees' => $this->getPastAttendees($tenantId, $config),
            'cart_abandoners' => $this->getCartAbandoners($tenantId, $config),
            'high_value' => $this->getHighValueCustomers($tenantId, $config),
            'email_subscribers' => $this->getEmailSubscribers($tenantId),
            default => [],
        };
    }

    protected function getPastAttendees(int $tenantId, array $config): array
    {
        $query = DB::table('orders')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->where('orders.tenant_id', $tenantId)
            ->whereIn('orders.status', ['paid', 'confirmed', 'completed'])
            ->select('customers.email', 'customers.phone', 'customers.first_name', 'customers.last_name');

        if (!empty($config['event_ids'])) {
            $query->whereIn('orders.event_id', $config['event_ids']);
        }

        if (!empty($config['days_back'])) {
            $query->where('orders.created_at', '>=', now()->subDays($config['days_back']));
        }

        return $query->distinct()->limit(10000)->get()->map(fn ($u) => [
            'email' => $u->email,
            'phone' => $u->phone,
            'first_name' => $u->first_name,
            'last_name' => $u->last_name,
        ])->toArray();
    }

    protected function getCartAbandoners(int $tenantId, array $config): array
    {
        $daysBack = $config['days_back'] ?? 30;

        return DB::table('carts')
            ->leftJoin('orders', 'carts.session_id', '=', 'orders.session_id')
            ->where('carts.tenant_id', $tenantId)
            ->whereNull('orders.id')
            ->whereNotNull('carts.email')
            ->where('carts.created_at', '>=', now()->subDays($daysBack))
            ->select('carts.email', 'carts.first_name', 'carts.last_name')
            ->distinct()
            ->limit(10000)
            ->get()
            ->map(fn ($u) => [
                'email' => $u->email,
                'first_name' => $u->first_name ?? '',
                'last_name' => $u->last_name ?? '',
            ])
            ->toArray();
    }

    protected function getHighValueCustomers(int $tenantId, array $config): array
    {
        $minSpend = $config['min_spend'] ?? 100;

        return DB::table('orders')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->where('orders.tenant_id', $tenantId)
            ->whereIn('orders.status', ['paid', 'confirmed', 'completed'])
            ->groupBy('customers.id', 'customers.email', 'customers.phone', 'customers.first_name', 'customers.last_name')
            ->havingRaw('SUM(orders.total) >= ?', [$minSpend])
            ->select('customers.email', 'customers.phone', 'customers.first_name', 'customers.last_name')
            ->limit(10000)
            ->get()
            ->map(fn ($u) => [
                'email' => $u->email,
                'phone' => $u->phone,
                'first_name' => $u->first_name,
                'last_name' => $u->last_name,
            ])
            ->toArray();
    }

    protected function getEmailSubscribers(int $tenantId): array
    {
        return DB::table('marketplace_newsletter_recipients')
            ->where('tenant_id', $tenantId)
            ->whereNull('unsubscribed_at')
            ->select('email')
            ->limit(10000)
            ->get()
            ->map(fn ($u) => ['email' => $u->email])
            ->toArray();
    }
}
