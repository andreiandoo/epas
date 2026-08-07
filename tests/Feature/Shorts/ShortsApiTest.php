<?php

namespace Tests\Feature\Shorts;

use App\Http\Controllers\Api\TenantClient\ShortsController;
use App\Models\MarketplaceCustomer;
use App\Models\Short;
use App\Models\ShortEvent;
use App\Models\ShortLike;
use App\Models\ShortSave;
use App\Services\Shorts\ShortFeedService;
use App\Services\Shorts\ShortPayload;
use App\Services\Shorts\ShortTelemetryService;
use Illuminate\Http\Request;

/**
 * Covers the controller layer without booting the marketplace middleware stack
 * (which needs the full tenant schema): controllers are exercised directly
 * against the scoped schema, so routing config stays out of the assertions
 * while the request/response contract is still verified.
 */
class ShortsApiTest extends ShortsTestCase
{
    public function test_feed_endpoint_returns_the_documented_payload_shape(): void
    {
        $short = $this->makeShort([
            'title' => 'UNTOLD aftermovie',
            'caption' => 'See you next year',
            'hashtags' => ['untold', 'cluj'],
            'cta_type' => 'buy_tickets',
            'cta_label' => 'Ia bilet',
            'cta_ticket_type_id' => 55,
            'promo_code' => 'SHORT10',
        ]);

        // Aggregates are deliberately not mass-assignable — only the rollup job
        // and the interaction toggles are allowed to move them.
        $short->forceFill(['likes' => 12, 'views' => 340])->save();

        $response = $this->publicController()->index(Request::create('/api/tenant-client/shorts', 'GET'));

        $data = $response->getData(true)['data'];

        $this->assertSame('for_you', $data['feed']);
        $this->assertCount(1, $data['items']);

        $item = $data['items'][0];
        $this->assertSame($short->id, $item['id']);
        $this->assertSame('upload', $item['source']);
        $this->assertSame(['untold', 'cluj'], $item['hashtags']);
        $this->assertSame('9:16', $item['aspect']);
        $this->assertSame(12, $item['stats']['likes']);
        $this->assertSame('buy_tickets', $item['cta']['type']);
        $this->assertSame('SHORT10', $item['cta']['promo_code']);
        $this->assertFalse($item['viewer']['liked']);
        $this->assertArrayHasKey('hls_url', $item['playback']);
    }

    public function test_show_endpoint_404s_on_an_unpublished_short(): void
    {
        $short = $this->makeShort(['status' => Short::STATUS_DRAFT]);

        $response = $this->publicController()->show(
            Request::create("/api/tenant-client/shorts/{$short->id}", 'GET'),
            $short->id,
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_telemetry_endpoint_accepts_a_guest_batch(): void
    {
        $short = $this->makeShort();

        $request = Request::create('/api/tenant-client/shorts/events', 'POST', [
            'session_id' => 'guest-session',
            'events' => [
                ['short_id' => $short->id, 'type' => 'impression', 'feed' => 'for_you'],
                ['short_id' => $short->id, 'type' => 'view', 'watch_ms' => 4000, 'watch_ratio' => 0.6, 'feed' => 'for_you'],
            ],
        ]);

        $response = $this->publicController()->events($request);

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame(2, $response->getData(true)['data']['accepted']);
        $this->assertSame(2, ShortEvent::query()->where('session_id', 'guest-session')->count());
    }

    public function test_like_toggle_is_idempotent_and_moves_the_counter_both_ways(): void
    {
        $short = $this->makeShort();
        $customer = $this->makeCustomer();

        $controller = $this->customerController();

        $on = $controller->toggleLike($this->authed($customer), $short->id);
        $this->assertTrue($on->getData(true)['data']['active']);
        $this->assertSame(1, $on->getData(true)['data']['count']);
        $this->assertSame(1, ShortLike::query()->count());

        $off = $controller->toggleLike($this->authed($customer), $short->id);
        $this->assertFalse($off->getData(true)['data']['active']);
        $this->assertSame(0, $off->getData(true)['data']['count']);
        $this->assertSame(0, ShortLike::query()->count());

        // Both directions are mirrored into the telemetry stream.
        $this->assertSame(1, ShortEvent::query()->where('type', 'like')->count());
        $this->assertSame(1, ShortEvent::query()->where('type', 'unlike')->count());
    }

    public function test_save_toggle_marks_the_viewer_state_in_the_feed(): void
    {
        $short = $this->makeShort();
        $customer = $this->makeCustomer();

        $this->customerController()->toggleSave($this->authed($customer), $short->id);

        $this->assertSame(1, ShortSave::query()->count());

        $feed = app(ShortFeedService::class)->page(customer: $customer);

        $this->assertTrue($feed['items'][0]['viewer']['saved']);
        $this->assertFalse($feed['items'][0]['viewer']['liked']);
    }

    public function test_like_toggle_404s_for_a_missing_short(): void
    {
        $customer = $this->makeCustomer();

        $response = $this->customerController()->toggleLike($this->authed($customer), 999999);

        $this->assertSame(404, $response->getStatusCode());
    }

    protected function publicController(): ShortsController
    {
        return new ShortsController(
            app(ShortFeedService::class),
            app(ShortPayload::class),
            app(ShortTelemetryService::class),
        );
    }

    protected function customerController(): \App\Http\Controllers\Api\MarketplaceClient\Customer\ShortsController
    {
        return new \App\Http\Controllers\Api\MarketplaceClient\Customer\ShortsController(
            app(ShortFeedService::class),
            app(ShortPayload::class),
        );
    }

    protected function authed(MarketplaceCustomer $customer, string $method = 'POST'): Request
    {
        $request = Request::create('/', $method);
        $request->setUserResolver(fn () => $customer);

        return $request;
    }

    protected function makeCustomer(): MarketplaceCustomer
    {
        return MarketplaceCustomer::create([
            'email' => 'viewer-'.uniqid().'@example.test',
            'first_name' => 'Test',
            'last_name' => 'Viewer',
            'status' => 'active',
        ]);
    }

    protected function makeShort(array $attributes = []): Short
    {
        return Short::create(array_merge([
            'source' => Short::SOURCE_UPLOAD,
            'ready' => true,
            'status' => Short::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ], $attributes));
    }
}
