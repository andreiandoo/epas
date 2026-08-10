<?php

namespace App\Jobs\Shorts;

use App\Models\Short;
use App\Models\ShortCaption;
use App\Services\Video\BunnyStreamProvider;
use App\Services\Video\VideoProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Attaches subtitles to a short once its asset is ready (B6).
 *
 * Order of preference:
 *   1. captions the video provider already generated (free, no extra service);
 *   2. a transcription driver, when one is configured;
 *   3. nothing — captions are an enhancement, never a blocker on publishing.
 *
 * TODO(owner): step 2 has no driver yet. Whisper/AssemblyAI plug into
 * transcribe(); config keys are already in services.captions.
 */
class GenerateCaptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        protected int $shortId,
        protected ?string $language = null,
    ) {}

    public function handle(VideoProvider $provider): void
    {
        $short = Short::find($this->shortId);

        if (! $short || ! $short->ready || ! $short->provider_asset_id) {
            return;
        }

        $language = $this->language ?? $short->language ?? config('app.locale', 'ro');

        if (ShortCaption::query()->where('short_id', $short->id)->where('language', $language)->exists()) {
            return;
        }

        $vtt = $this->fromProvider($provider, $short, $language) ?? $this->transcribe($short, $language);

        if (! $vtt) {
            return;
        }

        $path = "shorts/captions/{$short->id}-{$language}.vtt";
        Storage::disk('public')->put($path, $vtt);

        ShortCaption::query()->updateOrCreate(
            ['short_id' => $short->id, 'language' => $language],
            ['vtt_path' => $path, 'auto_generated' => true],
        );
    }

    /**
     * Bunny Stream can hold caption tracks against a video; if one is already
     * there, that is the cheapest possible source.
     */
    protected function fromProvider(VideoProvider $provider, Short $short, string $language): ?string
    {
        if (! $provider instanceof BunnyStreamProvider || ! $provider->isConfigured()) {
            return null;
        }

        try {
            $url = $provider->signedHls($short->provider_asset_id);

            if (! $url) {
                return null;
            }

            $captionUrl = str_replace('/playlist.m3u8', "/captions/{$language}.vtt", $url);
            $response = Http::timeout(15)->get($captionUrl);

            return $response->successful() && str_contains($response->body(), 'WEBVTT')
                ? $response->body()
                : null;
        } catch (\Throwable $e) {
            Log::debug('GenerateCaptionsJob: provider captions unavailable', [
                'short_id' => $short->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * TODO(owner): no transcription driver is configured. Returning null here
     * means the short simply ships without captions rather than blocking.
     */
    protected function transcribe(Short $short, string $language): ?string
    {
        if (! config('services.captions.driver')) {
            return null;
        }

        Log::info('GenerateCaptionsJob: transcription driver configured but not implemented', [
            'short_id' => $short->id,
            'driver' => config('services.captions.driver'),
        ]);

        return null;
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateCaptionsJob failed', ['short_id' => $this->shortId, 'error' => $e->getMessage()]);
    }
}
