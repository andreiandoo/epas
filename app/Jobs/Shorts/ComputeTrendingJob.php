<?php

namespace App\Jobs\Shorts;

use App\Models\Short;
use App\Models\ShortEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Recomputes `shorts.trending_score` (D4).
 *
 * Trending is velocity relative to a baseline, not raw totals: a short with a
 * million lifetime views is not trending, and a new one picking up fast is —
 * ranking on totals is how a feed ossifies around its oldest hits.
 *
 * Score = (recent engagement per hour) / (older engagement per hour + 1), so a
 * short that just started moving scores high even from a small absolute base,
 * while a steady performer sits near 1.
 */
class ComputeTrendingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        protected int $recentHours = 6,
        protected int $baselineHours = 48,
    ) {}

    public function handle(): void
    {
        $now = now();
        $recentSince = $now->copy()->subHours($this->recentHours);
        $baselineSince = $now->copy()->subHours($this->baselineHours);

        $recent = $this->engagementSince($recentSince);
        $baseline = $this->engagementSince($baselineSince);

        // Everything not seen in the baseline window decays to zero rather than
        // keeping yesterday's score forever.
        Short::query()->where('trending_score', '>', 0)->update(['trending_score' => 0]);

        $baselineWindow = max(1, $this->baselineHours - $this->recentHours);

        foreach ($recent as $shortId => $recentCount) {
            $recentRate = $recentCount / max(1, $this->recentHours);

            $olderCount = max(0, ($baseline[$shortId] ?? 0) - $recentCount);
            $olderRate = $olderCount / $baselineWindow;

            $score = round($recentRate / ($olderRate + 1), 3);

            Short::query()->whereKey($shortId)->update(['trending_score' => min($score, 99999.999)]);
        }

        Log::info('ComputeTrendingJob: recomputed trending scores', ['shorts' => count($recent)]);
    }

    /**
     * Engagement (views + completions + likes + shares) per short since a moment.
     *
     * @return array<int, int>
     */
    protected function engagementSince(\DateTimeInterface $since): array
    {
        return ShortEvent::query()
            ->selectRaw('short_id, COUNT(*) as total')
            ->where('created_at', '>=', $since)
            ->whereIn('type', [
                ShortEvent::TYPE_VIEW,
                ShortEvent::TYPE_COMPLETE,
                ShortEvent::TYPE_LIKE,
                ShortEvent::TYPE_SHARE,
            ])
            ->groupBy('short_id')
            ->pluck('total', 'short_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ComputeTrendingJob failed', ['error' => $e->getMessage()]);
    }
}
