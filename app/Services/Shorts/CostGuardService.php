<?php

namespace App\Services\Shorts;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Bandwidth guardrails for the Bunny bill (D8).
 *
 * A vertical video feed is the one feature in this product that can turn a
 * traffic spike directly into an unbounded invoice. This turns that into a
 * bounded, self-limiting cost: as usage approaches the cap, quality drops
 * platform-wide before the money runs out.
 */
class CostGuardService
{
    private const USAGE_KEY = 'shorts:bunny:usage_gb';

    /**
     * Record the month-to-date usage read from the provider.
     */
    public function recordUsage(float $gigabytes): void
    {
        Cache::put(self::USAGE_KEY, $gigabytes, now()->addDay());
    }

    public function usageGb(): float
    {
        return (float) Cache::get(self::USAGE_KEY, 0);
    }

    public function capGb(): int
    {
        return (int) config('shorts.cost.monthly_bandwidth_cap_gb', 0);
    }

    /**
     * Share of the monthly cap used so far, projected to the end of the month.
     *
     * Projected rather than current, because "we are at 60% on the 5th" is the
     * problem — not "we are at 60% on the 28th".
     */
    public function projectedUsagePct(): float
    {
        $cap = $this->capGb();

        if ($cap <= 0) {
            return 0.0;
        }

        $dayOfMonth = max(1, (int) now()->format('j'));
        $daysInMonth = (int) now()->daysInMonth;

        $projected = $this->usageGb() / $dayOfMonth * $daysInMonth;

        return round($projected / $cap * 100, 1);
    }

    public function shouldAlert(): bool
    {
        return $this->capGb() > 0
            && $this->projectedUsagePct() >= (float) config('shorts.cost.alert_threshold_pct', 80);
    }

    /**
     * Whether the platform should serve everyone reduced quality right now.
     *
     * Also honours the manual kill switch, so an operator can pull it without
     * waiting for a usage poll.
     */
    public function dataSaverActive(): bool
    {
        if (config('shorts.player.data_saver_global')) {
            return true;
        }

        return $this->capGb() > 0
            && $this->projectedUsagePct() >= (float) config('shorts.cost.data_saver_threshold_pct', 90);
    }

    /**
     * Playback hints handed to the client in the feed payload, so a cost
     * decision reaches every device without an app release.
     *
     * @return array<string, mixed>
     */
    public function playbackHints(): array
    {
        $saving = $this->dataSaverActive();

        return [
            'data_saver' => $saving,
            'max_height' => $saving ? 480 : 1080,
            'prefetch_count' => $saving ? 0 : (int) config('shorts.player.prefetch_count', 2),
        ];
    }

    public function logStatus(): void
    {
        if (! $this->shouldAlert()) {
            return;
        }

        Log::warning('Shorts: Bunny bandwidth projection is over the alert threshold', [
            'usage_gb' => $this->usageGb(),
            'cap_gb' => $this->capGb(),
            'projected_pct' => $this->projectedUsagePct(),
            'data_saver_active' => $this->dataSaverActive(),
        ]);
    }
}
