<?php

namespace Tests\Feature\Shorts;

use App\Http\Controllers\Api\TenantClient\ShortsController;
use App\Models\Order;
use App\Models\Short;
use App\Models\ShortEvent;
use App\Services\Shorts\ShortAttributionService;
use App\Services\Shorts\ShortFeedService;
use App\Services\Shorts\ShortPayload;
use App\Services\Shorts\ShortTelemetryService;
use Illuminate\Http\Request;

/**
 * B1 — shoppable shorts and last-touch conversion attribution.
 */
class ShortsAttributionTest extends ShortsTestCase
{
    public function test_cta_click_counts_immediately_and_returns_the_offer_to_honour(): void
    {
        $short = $this->makeShort([
            'event_id' => 42,
            'cta_type' => 'buy_tickets',
            'cta_ticket_type_id' => 7,
            'promo_code' => 'SHORT10',
        ]);

        $request = Request::create('/', 'POST', ['feed' => 'for_you', 'session_id' => 'sess-9']);
        $response = $this->controller()->ctaClick($request, $short->id);

        $data = $response->getData(true)['data'];

        $this->assertSame(42, $data['checkout']['event_id']);
        $this->assertSame(7, $data['checkout']['ticket_type_id']);
        $this->assertSame('SHORT10', $data['checkout']['promo_code']);
        $this->assertSame($short->id, $data['checkout']['source_short_id']);
        $this->assertSame('for_you', $data['checkout']['source_feed']);

        // Counted straight away, not queued behind the batched telemetry.
        $this->assertSame(1, $short->fresh()->cta_clicks);
        $this->assertSame(1, ShortEvent::query()->where('type', 'cta_click')->count());
    }

    public function test_a_paid_order_credits_the_short_once(): void
    {
        $short = $this->makeShort();

        $order = Order::create([
            'status' => 'pending',
            'total' => 150.50,
            'currency' => 'RON',
            'source_short_id' => $short->id,
            'source_feed' => 'for_you',
        ]);

        $order->update(['status' => 'paid']);

        $short->refresh();
        $this->assertSame(1, $short->conversions);
        $this->assertSame(15050, $short->revenue_cents);
        $this->assertSame('RON', $short->revenue_currency);
        $this->assertNotNull($order->fresh()->short_attributed_at);
    }

    public function test_repeated_paid_transitions_do_not_double_credit(): void
    {
        $short = $this->makeShort();

        $order = Order::create([
            'status' => 'pending',
            'total' => 100,
            'currency' => 'RON',
            'source_short_id' => $short->id,
        ]);

        // Webhook retries are normal: pending → paid → confirmed → completed.
        $order->update(['status' => 'paid']);
        $order->update(['status' => 'confirmed']);
        $order->update(['status' => 'completed']);

        $this->assertSame(1, $short->fresh()->conversions);
        $this->assertSame(10000, $short->fresh()->revenue_cents);
    }

    public function test_a_refund_takes_the_credit_back(): void
    {
        $short = $this->makeShort();

        $order = Order::create([
            'status' => 'pending',
            'total' => 80,
            'currency' => 'RON',
            'source_short_id' => $short->id,
        ]);

        $order->update(['status' => 'paid']);
        $this->assertSame(1, $short->fresh()->conversions);

        $order->update(['status' => 'refunded']);

        $short->refresh();
        $this->assertSame(0, $short->conversions);
        $this->assertSame(0, $short->revenue_cents);
        $this->assertNull($order->fresh()->short_attributed_at);
    }

    public function test_aggregates_never_go_negative(): void
    {
        $short = $this->makeShort();

        $order = Order::create([
            'status' => 'paid',
            'total' => 50,
            'currency' => 'RON',
            'source_short_id' => $short->id,
        ]);

        $attribution = app(ShortAttributionService::class);

        // Force the aggregates below the order value — as an aggregate rebuild or
        // a manual edit could — then reverse. The floor must hold at zero rather
        // than wrapping into a negative count.
        $short->forceFill(['revenue_cents' => 100, 'conversions' => 0])->save();
        $order->forceFill(['short_attributed_at' => now()])->saveQuietly();

        $attribution->reverse($order->fresh());

        $short->refresh();
        $this->assertSame(0, $short->conversions);
        $this->assertSame(0, $short->revenue_cents);
    }

    public function test_an_order_with_no_short_is_left_alone(): void
    {
        $short = $this->makeShort();

        $order = Order::create(['status' => 'pending', 'total' => 20, 'currency' => 'RON']);
        $order->update(['status' => 'paid']);

        $this->assertSame(0, $short->fresh()->conversions);
        $this->assertNull($order->fresh()->short_attributed_at);
    }

    public function test_a_deleted_short_stops_being_rechecked(): void
    {
        $order = Order::create([
            'status' => 'pending',
            'total' => 30,
            'currency' => 'RON',
            'source_short_id' => 999999,
        ]);

        $order->update(['status' => 'paid']);

        // Stamped even though nothing was credited, so later status changes do
        // not re-run the lookup forever.
        $this->assertNotNull($order->fresh()->short_attributed_at);
    }

    public function test_rates_are_derived_not_stored(): void
    {
        $short = $this->makeShort();
        $short->forceFill(['views' => 1000, 'cta_clicks' => 50, 'conversions' => 5])->save();

        $rates = app(ShortAttributionService::class)->rates($short);

        $this->assertSame(0.05, $rates['ctr']);
        $this->assertSame(0.1, $rates['cvr']);
    }

    public function test_rates_handle_a_short_nobody_has_seen(): void
    {
        $rates = app(ShortAttributionService::class)->rates($this->makeShort());

        $this->assertSame(0.0, $rates['ctr']);
        $this->assertSame(0.0, $rates['cvr']);
    }

    protected function controller(): ShortsController
    {
        return new ShortsController(
            app(ShortFeedService::class),
            app(ShortPayload::class),
            app(ShortTelemetryService::class),
        );
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
