<?php

namespace App\Jobs\Shorts;

use App\Models\Short;
use App\Models\ShortPosterVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Ends a cover A/B test and promotes the winner (B10).
 *
 * Only decides once every variant has had enough impressions to mean something.
 * Calling a winner at 20 impressions is picking noise, and it permanently
 * discards the variant that might actually have been better.
 */
class PickPosterWinnerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(protected int $minImpressions = 500) {}

    public function handle(): void
    {
        $shortIds = ShortPosterVariant::query()
            ->where('is_winner', false)
            ->distinct()
            ->pluck('short_id');

        foreach ($shortIds as $shortId) {
            $variants = ShortPosterVariant::query()->where('short_id', $shortId)->get();

            if ($variants->count() < 2) {
                continue;
            }

            // Every arm needs the sample, not just the leader.
            if ($variants->min('impressions') < $this->minImpressions) {
                continue;
            }

            $winner = $variants->sortByDesc(fn (ShortPosterVariant $v) => $v->ctr())->first();

            ShortPosterVariant::query()->where('short_id', $shortId)->update(['is_winner' => false]);
            ShortPosterVariant::query()->whereKey($winner->id)->update(['is_winner' => true]);

            // The winning cover becomes the short's actual poster; from here the
            // feed serves it to everyone.
            Short::query()->whereKey($shortId)->update(['poster_path' => $winner->poster_path]);

            Log::info('PickPosterWinnerJob: promoted a winning cover', [
                'short_id' => $shortId,
                'variant_id' => $winner->id,
                'ctr' => round($winner->ctr(), 4),
            ]);
        }
    }
}
