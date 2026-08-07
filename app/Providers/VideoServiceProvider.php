<?php

namespace App\Providers;

use App\Services\Video\BunnyStreamProvider;
use App\Services\Video\NullVideoProvider;
use App\Services\Video\VideoProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the managed-video contract to the driver picked in
 * config('services.video.driver'). Falls back to a no-op provider whenever the
 * chosen driver has no usable credentials, so dev/CI keep booting on the
 * placeholder config shipped in .env.example.
 */
class VideoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
