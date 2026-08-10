<?php

namespace App\Http\Controllers\Api\Shorts;

use App\Http\Controllers\Controller;
use App\Jobs\Shorts\SyncShortPlaybackJob;
use App\Models\Short;
use App\Services\Video\VideoProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Callback target for the managed video provider ("asset ready").
 *
 * The callback is treated strictly as a trigger: the provider API is re-read by
 * SyncShortPlaybackJob, which is authoritative. That keeps a spoofed or replayed
 * webhook from being able to state facts about an asset.
 */
class VideoWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider): Response
    {
        $resolved = $this->resolveProvider($provider);

        if (! $resolved || ! $resolved->verifyWebhook($request)) {
            Log::warning('Shorts: rejected video webhook', ['provider' => $provider]);

            return response()->noContent(403);
        }

        $parsed = $resolved->parseWebhook($request);
        $assetId = $parsed['asset_id'] ?? null;

        if (! $assetId) {
            return response()->noContent(422);
        }

        match ($parsed['state']) {
            'ready' => SyncShortPlaybackJob::dispatch($assetId, $provider),
            'failed' => $this->markFailed($assetId),
            default => null,
        };

        return response()->noContent();
    }

    protected function resolveProvider(string $provider): ?VideoProvider
    {
        $binding = 'video.provider.'.$provider;

        if (! app()->bound($binding)) {
            return null;
        }

        $instance = app($binding);

        return $instance instanceof VideoProvider ? $instance : null;
    }

    protected function markFailed(string $assetId): void
    {
        Short::query()
            ->where('provider_asset_id', $assetId)
            ->update(['status' => Short::STATUS_REJECTED, 'ready' => false]);

        Log::warning('Shorts: provider reported an encoding failure', ['asset_id' => $assetId]);
    }
}
