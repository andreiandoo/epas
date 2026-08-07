<?php

namespace Tests\Feature\Shorts;

use App\Jobs\Shorts\SyncShortPlaybackJob;
use App\Models\Short;
use App\Services\Video\BunnyStreamProvider;
use App\Services\Video\NullVideoProvider;
use App\Services\Video\VideoProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BunnyStreamProviderTest extends ShortsTestCase
{
    public function test_container_falls_back_to_the_null_provider_on_placeholder_config(): void
    {
        config(['services.video.driver' => 'bunny']);
        config(['services.bunny.stream_library_id' => '']);
        config(['services.bunny.stream_api_key' => '']);

        app()->forgetInstance(VideoProvider::class);

        $this->assertInstanceOf(NullVideoProvider::class, app(VideoProvider::class));
    }

    public function test_direct_upload_returns_a_tus_session_signed_for_the_created_asset(): void
    {
        Http::fake([
            'video.bunnycdn.com/library/*/videos' => Http::response(['guid' => 'guid-123'], 200),
        ]);

        $session = $this->provider()->createDirectUpload(['title' => 'aftermovie']);

        $this->assertSame('guid-123', $session['asset_id']);
        $this->assertSame('https://video.bunnycdn.com/tusupload', $session['upload_url']);
        $this->assertSame('lib-1', $session['tus_headers']['LibraryId']);
        $this->assertSame('guid-123', $session['tus_headers']['VideoId']);

        $expire = $session['tus_headers']['AuthorizationExpire'];
        $this->assertSame(
            hash('sha256', 'lib-1'.'api-key'.$expire.'guid-123'),
            $session['tus_headers']['AuthorizationSignature'],
        );
    }

    public function test_playback_reads_the_provider_as_the_authority_on_readiness(): void
    {
        Http::fake([
            'video.bunnycdn.com/library/*/videos/guid-123' => Http::response([
                'status' => 4, 'length' => 18, 'width' => 1080, 'height' => 1920,
            ], 200),
        ]);

        $playback = $this->provider()->getPlayback('guid-123');

        $this->assertTrue($playback['ready']);
        $this->assertSame(18, $playback['duration']);
        $this->assertSame(1920, $playback['height']);
    }

    public function test_signed_urls_carry_a_token_and_an_expiry(): void
    {
        $url = $this->provider()->signedHls('guid-123', ttl: 600);

        $this->assertStringStartsWith('https://vz-test.b-cdn.net/guid-123/playlist.m3u8?token=', $url);
        $this->assertStringContainsString('&expires=', $url);

        // base64url alphabet only — the token must survive a URL round-trip.
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $query['token']);
    }

    public function test_webhook_verification_rejects_a_wrong_or_missing_secret(): void
    {
        $provider = $this->provider();

        $this->assertTrue($provider->verifyWebhook(Request::create('/w?secret=hook-secret', 'POST')));
        $this->assertFalse($provider->verifyWebhook(Request::create('/w?secret=nope', 'POST')));
        $this->assertFalse($provider->verifyWebhook(Request::create('/w', 'POST')));
    }

    public function test_webhook_payload_maps_provider_status_codes_to_states(): void
    {
        $provider = $this->provider();

        $ready = $provider->parseWebhook(Request::create('/w', 'POST', ['VideoGuid' => 'g1', 'Status' => 4]));
        $failed = $provider->parseWebhook(Request::create('/w', 'POST', ['VideoGuid' => 'g1', 'Status' => 5]));
        $pending = $provider->parseWebhook(Request::create('/w', 'POST', ['VideoGuid' => 'g1', 'Status' => 2]));

        $this->assertSame('ready', $ready['state']);
        $this->assertSame('failed', $failed['state']);
        $this->assertSame('pending', $pending['state']);
        $this->assertSame('g1', $ready['asset_id']);
    }

    public function test_sync_job_stamps_playback_metadata_without_publishing(): void
    {
        Http::fake([
            'video.bunnycdn.com/library/*/videos/guid-sync' => Http::response([
                'status' => 4, 'length' => 22, 'width' => 1080, 'height' => 1920,
            ], 200),
        ]);

        $this->bindConfiguredProvider();

        $short = Short::create([
            'source' => Short::SOURCE_UPLOAD,
            'video_provider' => 'bunny',
            'provider_asset_id' => 'guid-sync',
            'status' => Short::STATUS_DRAFT,
            'ready' => false,
        ]);

        (new SyncShortPlaybackJob('guid-sync'))->handle();

        $short->refresh();

        $this->assertTrue($short->ready);
        $this->assertSame(22, $short->duration);
        $this->assertSame(1920, $short->height);
        $this->assertSame(Short::STATUS_DRAFT, $short->status, 'a ready asset must not publish itself');
    }

    protected function provider(): BunnyStreamProvider
    {
        return new BunnyStreamProvider(
            library: 'lib-1',
            apiKey: 'api-key',
            pullZone: 'vz-test.b-cdn.net',
            tokenKey: 'token-key',
            webhookSecret: 'hook-secret',
        );
    }

    protected function bindConfiguredProvider(): void
    {
        app()->instance(VideoProvider::class, $this->provider());
    }
}
