<?php

namespace App\Jobs\Tracking;

use App\Models\FeatureStore\FsPersonChannelAffinity;
use App\Models\FeatureStore\FsPersonEmailMetrics;
use App\Models\Platform\CoreCustomer;
use App\Models\Tracking\TxEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculateEngagementMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 1800;

    public function __construct(
        protected ?int $tenantId = null,
        protected ?int $personId = null
    ) {
        $this->onQueue('tracking-low');
    }

    public function handle(): void
    {
        Log::info('CalculateEngagementMetricsJob: Starting', [
            'tenant_id' => $this->tenantId ?? 'all',
            'person_id' => $this->personId ?? 'all',
        ]);

        if ($this->personId) {
            $this->processPersonMetrics($this->tenantId, $this->personId);
        } else {
            $this->processAllPersons();
        }

        Log::info('CalculateEngagementMetricsJob: Completed');
    }

    protected function processAllPersons(): void
    {
        $query = CoreCustomer::query();

        if ($this->tenantId) {
            $query->fromTenant($this->tenantId);
        }

        $query->notMerged()
            ->notAnonymized()
            ->whereNotNull('email_hash');

        $count = 0;
        $query->cursor()->each(function ($person) use (&$count) {
            // Get primary tenant for this person
            $tenantId = $person->primary_tenant_id ?? $person->first_tenant_id;
            if ($tenantId) {
                $this->processPersonMetrics($tenantId, $person->id);
                $count++;

                if ($count % 100 === 0) {
                    Log::info("CalculateEngagementMetricsJob: Processed {$count} persons");
                }
            }
        });

        Log::info("CalculateEngagementMetricsJob: Total {$count} persons processed");
    }

    protected function processPersonMetrics(int $tenantId, int $personId): void
    {
        $this->calculateEmailMetrics($tenantId, $personId);
        $this->calculateChannelAffinity($tenantId, $personId);
    }

    protected function calculateEmailMetrics(int $tenantId, int $personId): void
    {
        $person = CoreCustomer::find($personId);
        if (!$person) {
            return;
        }

        // Get email events from TX tracking
        $emailEvents = TxEvent::where('tenant_id', $tenantId)
            ->where('person_id', $personId)
            ->whereIn('event_name', ['email_sent', 'email_opened', 'email_clicked'])
            ->where('occurred_at', '>=', now()->subDays(90))
            ->get();

        $sent7d = $emailEvents->where('event_name', 'email_sent')
            ->where('occurred_at', '>=', now()->subDays(7))->count();
        $sent30d = $emailEvents->where('event_name', 'email_sent')
            ->where('occurred_at', '>=', now()->subDays(30))->count();
        $sent90d = $emailEvents->where('event_name', 'email_sent')->count();

        $opened30d = $emailEvents->where('event_name', 'email_opened')
            ->where('occurred_at', '>=', now()->subDays(30))->count();
        $clicked30d = $emailEvents->where('event_name', 'email_clicked')
            ->where('occurred_at', '>=', now()->subDays(30))->count();

        // Also use CoreCustomer's email metrics as fallback
        if ($sent30d === 0 && $person->emails_sent > 0) {
            $sent30d = min($person->emails_sent, 10); // Estimate
            $sent90d = $person->emails_sent;
            $opened30d = (int) ($person->emails_opened * 0.3); // Estimate 30-day portion
            $clicked30d = (int) ($person->emails_clicked * 0.3);
        }

        // Calculate rates
        $openRate30d = $sent30d > 0 ? $opened30d / $sent30d : 0;
        $openRate90d = $sent90d > 0 ? $emailEvents->where('event_name', 'email_opened')->count() / $sent90d : 0;
        $clickRate30d = $sent30d > 0 ? $clicked30d / $sent30d : 0;

        // Get last engagement
        $lastEngagement = $emailEvents->whereIn('event_name', ['email_opened', 'email_clicked'])
            ->sortByDesc('occurred_at')
            ->first();
        $lastEngagementAt = $lastEngagement?->occurred_at ?? $person->last_email_opened_at;
        $daysSinceEngagement = $lastEngagementAt ? $lastEngagementAt->diffInDays(now()) : 90;

        // Calculate fatigue score
        $fatigueScore = FsPersonEmailMetrics::calculateFatigueScore(
            $openRate30d,
            $openRate90d,
            $sent7d,
            $daysSinceEngagement,
            $clickRate30d
        );

        // Determine trend
        $trend = FsPersonEmailMetrics::determineTrend($openRate30d, $openRate90d, $daysSinceEngagement);

        // Calculate optimal frequency (based on engagement)
        $optimalFrequency = $this->calculateOptimalFrequency($openRate30d, $sent7d, $fatigueScore);

        // Determine preferred send times from open events
        $preferredHours = $this->extractPreferredHours($emailEvents->where('event_name', 'email_opened'));
        $preferredDays = $this->extractPreferredDays($emailEvents->where('event_name', 'email_opened'));

        FsPersonEmailMetrics::updateOrCreate(
            ['tenant_id' => $tenantId, 'person_id' => $personId],
            [
                'sent_last_7_days' => $sent7d,
                'sent_last_30_days' => $sent30d,
                'sent_last_90_days' => $sent90d,
                'opened_last_30_days' => $opened30d,
                'clicked_last_30_days' => $clicked30d,
                'engagement_trend' => $trend,
                'open_rate_30d' => $openRate30d,
                'open_rate_90d' => $openRate90d,
                'click_rate_30d' => $clickRate30d,
                'fatigue_score' => $fatigueScore,
                'optimal_frequency_per_week' => $optimalFrequency,
                'preferred_send_hours' => $preferredHours,
                'preferred_send_days' => $preferredDays,
                'last_engagement_at' => $lastEngagementAt,
            ]
        );
    }

    protected function calculateOptimalFrequency(float $openRate, int $weeklyEmails, float $fatigueScore): float
    {
        // Base frequency on engagement
        if ($openRate >= 0.5 && $fatigueScore < 30) {
            return 4.0; // High engagement, low fatigue = can send more
        } elseif ($openRate >= 0.3 && $fatigueScore < 50) {
            return 3.0;
        } elseif ($openRate >= 0.15 && $fatigueScore < 70) {
            return 2.0;
        } elseif ($fatigueScore >= 70) {
            return 0.5; // High fatigue = reduce significantly
        }

        return 1.0; // Default weekly
    }

    protected function extractPreferredHours($openEvents): array
    {
        if ($openEvents->isEmpty()) {
            return [];
        }

        $hourCounts = [];
        foreach ($openEvents as $event) {
            $hour = (int) $event->occurred_at->format('G');
            $hourCounts[$hour] = ($hourCounts[$hour] ?? 0) + 1;
        }

        arsort($hourCounts);
        return array_slice(array_keys($hourCounts), 0, 3);
    }

    protected function extractPreferredDays($openEvents): array
    {
        if ($openEvents->isEmpty()) {
            return [];
        }

        $dayCounts = [];
        foreach ($openEvents as $event) {
            $day = (int) $event->occurred_at->format('w');
            $dayCounts[$day] = ($dayCounts[$day] ?? 0) + 1;
        }

        arsort($dayCounts);
        return array_slice(array_keys($dayCounts), 0, 3);
    }

    protected function calculateChannelAffinity(int $tenantId, int $personId): void
    {
        // Get all events for this person
        $events = TxEvent::where('tenant_id', $tenantId)
            ->where('person_id', $personId)
            ->whereNotNull('context')
            ->get();

        if ($events->isEmpty()) {
            return;
        }

        $channelStats = [];

        foreach ($events as $event) {
            $context = $event->context ?? [];
            $utmSource = $context['utm_source'] ?? null;
            $utmMedium = $context['utm_medium'] ?? null;
            $referrer = $context['referrer'] ?? null;

            $channel = FsPersonChannelAffinity::determineChannel($utmSource, $utmMedium, $referrer);

            if (!isset($channelStats[$channel])) {
                $channelStats[$channel] = [
                    'interactions' => 0,
                    'conversions' => 0,
                    'revenue' => 0,
                    'last_at' => null,
                ];
            }

            $channelStats[$channel]['interactions']++;

            if ($event->event_name === 'order_completed') {
                $channelStats[$channel]['conversions']++;
                $channelStats[$channel]['revenue'] += $event->payload['gross_amount'] ?? 0;
            }

            $eventTime = $event->occurred_at;
            if (!$channelStats[$channel]['last_at'] || $eventTime > $channelStats[$channel]['last_at']) {
                $channelStats[$channel]['last_at'] = $eventTime;
            }
        }

        // Save channel affinity data
        foreach ($channelStats as $channel => $stats) {
            $conversionRate = $stats['interactions'] > 0
                ? $stats['conversions'] / $stats['interactions']
                : 0;

            FsPersonChannelAffinity::updateOrCreate(
                ['tenant_id' => $tenantId, 'person_id' => $personId, 'channel' => $channel],
                [
                    'interaction_count' => $stats['interactions'],
                    'conversion_count' => $stats['conversions'],
                    'conversion_rate' => $conversionRate,
                    'revenue_attributed' => $stats['revenue'],
                    'last_interaction_at' => $stats['last_at'],
                ]
            );
        }
    }
}
