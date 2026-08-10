<?php

namespace App\Jobs\Shorts;

use App\Models\Short;
use App\Services\Video\VideoProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Re-reads an asset's real state from the video provider after a "ready"
 * webhook and stamps duration/dimensions on the matching shorts.
 *
 * Publication is deliberately NOT touched here: an asset becoming playable and
 * a human deciding to publish it are separate events.
 */
class SyncShortPlaybackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public int $timeout = 60;

    public function __construct(
        protected string $assetId,
        protected ?string $providerKey = null,
    ) {}

    public function handle(): void
    {
        $provider = $this->provider();

        if (! $provider || ! $provider->isConfigured()) {
            Log::warning('SyncShortPlaybackJob: no configured provider', ['asset_id' => $this->assetId]);

            return;
        }

        $playback = $provider->getPlayback($this->assetId);

        $updated = Short::query()
            ->where('provider_asset_id', $this->assetId)
            ->update(array_filter([
                'ready' => $playback['ready'],
                'duration' => $playback['duration'],
                'width' => $playback['width'],
                'height' => $playback['height'],
            ], fn ($value) => $value !== null));

        if ($updated === 0) {
            Log::warning('SyncShortPlaybackJob: no short matches asset', ['asset_id' => $this->assetId]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SyncShortPlaybackJob failed', [
            'asset_id' => $this->assetId,
            'error' => $e->getMessage(),
        ]);
    }

    protected function provider(): ?VideoProvider
    {
        if ($this->providerKey) {
            $binding = 'video.provider.'.$this->providerKey;

            if (app()->bound($binding)) {
                $instance = app($binding);

                return $instance instanceof VideoProvider ? $instance : null;
            }
        }

        return app(VideoProvider::class);
    }
}
