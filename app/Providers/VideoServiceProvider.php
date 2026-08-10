<?php

namespace App\Providers;

use App\Services\Push\LogPushSender;
use App\Services\Push\PushSender;
use App\Services\Video\BunnyStreamProvider;
use App\Services\Video\NullVideoProvider;
use App\Services\Video\NullVideoRenderer;
use App\Services\Video\ShotstackRenderer;
use App\Services\Video\VideoProvider;
use App\Services\Video\VideoRenderer;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the managed-video contract to the driver picked in
 * config('services.video.driver'). Falls back to a no-op provider whenever the
 * chosen driver has no usable credentials, so dev/CI keep booting on the
 * placeholder config shipped in .env.example.
 *
 * Also binds the push contract Shorts depends on. TODO(owner): EPAS has no
 * customer-facing push layer yet, so LogPushSender records what would have been
 * sent — the trigger logic is verifiable, only the transport is missing.
 */
class VideoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PushSender::class, fn () => new LogPushSender);

        $this->app->singleton(VideoProvider::class, function () {
            $driver = config('services.video.driver', 'bunny');

            $provider = match ($driver) {
                'bunny' => new BunnyStreamProvider(
                    library: (string) config('services.bunny.stream_library_id', ''),
                    apiKey: (string) config('services.bunny.stream_api_key', ''),
                    pullZone: (string) config('services.bunny.stream_pull_zone', ''),
                    tokenKey: (string) config('services.bunny.stream_token_key', ''),
                    webhookSecret: (string) config('services.bunny.stream_webhook_secret', ''),
                ),
                default => new NullVideoProvider,
            };

            return $provider->isConfigured() ? $provider : new NullVideoProvider;
        });

        // Render service (B3). Same fallback stance as the provider: without
        // credentials the container binds the null renderer, and auto-generation
        // degrades to the "poster short" path instead of failing.
        $this->app->singleton(VideoRenderer::class, function () {
            $renderer = match (config('services.render.driver')) {
                'shotstack' => new ShotstackRenderer(
                    apiKey: (string) config('services.render.api_key', ''),
                    environment: (string) config('services.render.environment', 'stage'),
                    webhookSecret: (string) config('services.render.webhook_secret', ''),
                ),
                default => new NullVideoRenderer,
            };

            return $renderer->isConfigured() ? $renderer : new NullVideoRenderer;
        });

        // Named binding so webhook routes can address a specific provider even
        // when it is not the active default (e.g. during a migration between
        // providers, when both still deliver callbacks).
        $this->app->bind('video.provider.bunny', fn () => new BunnyStreamProvider(
            library: (string) config('services.bunny.stream_library_id', ''),
            apiKey: (string) config('services.bunny.stream_api_key', ''),
            pullZone: (string) config('services.bunny.stream_pull_zone', ''),
            tokenKey: (string) config('services.bunny.stream_token_key', ''),
            webhookSecret: (string) config('services.bunny.stream_webhook_secret', ''),
        ));
    }
}
