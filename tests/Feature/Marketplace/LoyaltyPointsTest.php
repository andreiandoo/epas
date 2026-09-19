<?php

namespace Tests\Feature\Marketplace;

use App\Models\Gamification\CustomerPoints;
use App\Models\Gamification\GamificationConfig;
use App\Models\Gamification\LoyaltyPendingEarning;
use App\Models\Gamification\PointsTransaction;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use App\Observers\LoyaltyOrderObserver;
use App\Services\Gamification\MarketplaceLoyaltyService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Marketplace loyalty points end to end, on SQLite in memory: earning (pending → credited after the activity),
 * paying with points at checkout, refunds and unpaid orders, referrals, birthdays, expiry, and the switch that keeps
 * other marketplaces untouched. The full migration set is Postgres-only, so the few tables involved are built here and
 * the loyalty migration itself is run on top of them. Only the loyalty observer listens to orders.
 *
 * Run: DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor/bin/phpunit --filter LoyaltyPointsTest
 * (the admin panels read the Vite manifest when the app boots: build the assets first.)
 */
class LoyaltyPointsTest extends TestCase
{
    protected MarketplaceLoyaltyService $loyalty;
    protected int $clientId;
    protected GamificationConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Builds its own schema: run with DB_CONNECTION=sqlite DB_DATABASE=:memory:');
        }
        $this->buildSchema();
        (require base_path('database/migrations/2026_09_19_120000_add_marketplace_loyalty.php'))->up();

        Order::flushEventListeners();
        MarketplaceCustomer::flushEventListeners();
        Order::observe(LoyaltyOrderObserver::class);

        $this->clientId = DB::table('marketplace_clients')->insertGetId(['name' => 'bilete.online', 'slug' => 'bilete-online', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $ms = DB::table('microservices')->insertGetId(['slug' => 'gamification', 'name' => json_encode(['en' => 'Gamification']), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('marketplace_client_microservices')->insert(['marketplace_client_id' => $this->clientId, 'microservice_id' => $ms, 'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->config = GamificationConfig::create([
            'marketplace_client_id' => $this->clientId,
            'point_value' => 0.01,
            'currency' => 'RON',
            'earn_percentage' => 0.5,
            'earn_on_subtotal' => true,
            'min_order_for_earning' => 0,
            'min_redeem_points' => 200,
            'max_redeem_percentage' => 20,
            'max_redeem_points_per_order' => 1000,
            'birthday_bonus_points' => 150,
            'signup_bonus_points' => 0,
            'referral_bonus_points' => 300,
            'referred_bonus_points' => 200,
            'referral_min_order' => 100,
            'referral_max_per_year' => 10,
            'points_expire_days' => 365,
            'expire_on_inactivity' => true,
            'inactivity_days' => 365,
            'points_confirm_days' => 2,
            'points_name' => 'puncte',
            'points_name_singular' => 'punct',
            'is_active' => true,
            'auto_rewards_enabled' => true,
        ]);
        $this->loyalty = new MarketplaceLoyaltyService();
        $this->app->instance(MarketplaceLoyaltyService::class, $this->loyalty);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------------ rules and amounts

    public function test_earn_rule_is_the_share_of_the_value_returned(): void
    {
        $this->assertSame(50, $this->config->pointsForLei(100));
        $this->assertSame(0, $this->config->pointsForLei(1.99));
        $this->assertSame(50, $this->config->pointsPer100Lei());
        $this->assertSame('1 punct la fiecare 2 lei', $this->config->earnRateLabel());
        // the tenant path counts in bani and agrees at 1 point = 0.01 lei
        $this->assertSame(50, $this->config->calculateEarnedPoints(10000));

        $this->config->earn_percentage = 1;
        $this->assertSame('1 punct la fiecare leu', $this->config->earnRateLabel());
        $this->config->earn_percentage = 0.3;
        $this->assertSame('3 puncte la fiecare 10 lei', $this->config->earnRateLabel());
    }

    public function test_redeemable_points_follow_percentage_cap_and_balance(): void
    {
        $this->assertSame(1000, $this->loyalty->redeemablePoints($this->config, 100, 5000)); // 20% = 2000, cap 1000
        $this->assertSame(1000, $this->loyalty->redeemablePoints($this->config, 50, 5000));  // 20% = 1000
        $this->assertSame(600, $this->loyalty->redeemablePoints($this->config, 30, 5000));   // 20% = 600
        $this->assertSame(250, $this->loyalty->redeemablePoints($this->config, 100, 250));   // balance
        $this->assertSame(0, $this->loyalty->redeemablePoints($this->config, 100, 150));     // under the 200 minimum
    }

    public function test_quote_checks_account_email_and_minimum(): void
    {
        $client = MarketplaceClient::find($this->clientId);
        $ana = $this->customer('ana@example.test');
        $this->giveBalance($ana, 1500);

        $q = $this->loyalty->quote($client, $ana, 'ANA@example.test ', 100, 5000);
        $this->assertSame([1000, 10.0, null], [$q['points'], $q['discount'], $q['error']]);

        $this->assertSame('Intră în cont ca să folosești punctele.', $this->loyalty->quote($client, null, 'ana@example.test', 100, 500)['error']);
        $this->assertStringContainsString('adresa de email a contului', $this->loyalty->quote($client, $ana, 'alt@example.test', 100, 500)['error']);

        $poor = $this->customer('poor@example.test');
        $this->giveBalance($poor, 150);
        $this->assertStringContainsString('minimum 200', $this->loyalty->quote($client, $poor, 'poor@example.test', 100, 150)['error']);

        $this->assertSame(0, $this->loyalty->quote($client, $ana, 'ana@example.test', 100, 0)['points']);
    }

    // ------------------------------------------------------------------ an order's life

    public function test_paid_order_with_points_then_credited_after_the_activity(): void
    {
        Carbon::setTestNow('2026-10-01 10:00:00');
        $ana = $this->customer('ana@example.test');
        $this->giveBalance($ana, 1500);

        // checkout: 100 lei tickets + 2 lei commission, 10 lei paid with 1000 points
        $order = $this->order($ana, ['subtotal' => 100, 'commission_amount' => 2, 'total' => 92, 'points_used' => 1000, 'points_discount' => 10, 'status' => 'pending']);
        $this->loyalty->spendForOrder($order, 1000);
        $cp = $this->account($ana);
        $this->assertSame([500, 1000], [(int) $cp->current_balance, (int) $cp->total_spent]);

        // an activity on 5 October
        DB::table('activity_bookings')->insert(['order_id' => $order->id, 'booking_date' => '2026-10-05', 'created_at' => now(), 'updated_at' => now()]);

        // paid → waiting: earns on the 90 lei paid in money for the tickets
        $order->update(['status' => 'completed', 'payment_status' => 'paid', 'paid_at' => now()]);
        $pending = LoyaltyPendingEarning::where('order_id', $order->id)->first();
        $this->assertNotNull($pending);
        $this->assertSame([45, '90.00', 'pending'], [$pending->points, (string) $pending->base_amount, $pending->status]);
        $this->assertSame('2026-10-07 23:59:59', $pending->available_at->format('Y-m-d H:i:s'));
        $this->assertSame(45, (int) $this->account($ana)->pending_points);
        $this->assertSame(45, $this->loyalty->pendingPoints($this->clientId, $ana->id));

        // paying twice (callback retried) changes nothing
        $order->update(['status' => 'pending']);
        $order->update(['status' => 'completed']);
        $this->assertSame(1, LoyaltyPendingEarning::where('order_id', $order->id)->count());
        $this->assertSame(45, (int) $this->account($ana)->pending_points);

        // before the date: nothing; after: credited, with an expiry date
        $this->assertSame(0, $this->loyalty->confirmDue());
        Carbon::setTestNow('2026-10-08 01:00:00');
        $this->assertSame(1, $this->loyalty->confirmDue());
        $cp = $this->account($ana);
        $this->assertSame([545, 0, 45], [(int) $cp->current_balance, (int) $cp->pending_points, (int) $cp->total_earned - 1500]);
        $earned = PointsTransaction::where('reference_type', 'order')->where('reference_id', $order->id)->where('type', 'earned')->first();
        $this->assertSame('2027-10-08', $earned->expires_at->format('Y-m-d'));
        $this->assertSame(0, $this->loyalty->confirmDue()); // idempotent

        // refunded afterwards: the 45 are taken back, the 1000 used come back
        $order->update(['status' => 'refunded']);
        $cp = $this->account($ana);
        $this->assertSame(1500, (int) $cp->current_balance);
        $this->assertSame(1, PointsTransaction::where('reference_id', $order->id)->where('action_type', 'purchase_reversal')->count());
        $this->assertSame(1, PointsTransaction::where('reference_id', $order->id)->where('type', 'refunded')->count());
        $order->update(['status' => 'completed']);
        $order->update(['status' => 'refunded']);
        $this->assertSame(1500, (int) $this->account($ana)->current_balance); // nothing twice
    }

    public function test_refund_before_the_activity_cancels_the_pending_points(): void
    {
        $ana = $this->customer('ana@example.test');
        $order = $this->order($ana, ['subtotal' => 200, 'total' => 204, 'status' => 'completed', 'paid_at' => now()]);
        $this->assertSame(100, (int) $this->account($ana)->pending_points);

        $order->update(['status' => 'refunded']);
        $this->assertSame('cancelled', LoyaltyPendingEarning::where('order_id', $order->id)->value('status'));
        $this->assertSame([0, 0], [(int) $this->account($ana)->pending_points, (int) $this->account($ana)->current_balance]);
        Carbon::setTestNow(now()->addDays(30));
        $this->assertSame(0, $this->loyalty->confirmDue());
        $this->assertSame(0, (int) $this->account($ana)->current_balance);
    }

    public function test_unpaid_order_gives_the_points_back(): void
    {
        $ana = $this->customer('ana@example.test');
        $this->giveBalance($ana, 800);
        $order = $this->order($ana, ['subtotal' => 100, 'total' => 97, 'points_used' => 500, 'points_discount' => 5, 'status' => 'pending']);
        $this->loyalty->spendForOrder($order, 500);
        $this->assertSame(300, (int) $this->account($ana)->current_balance);

        $order->update(['status' => 'expired', 'payment_status' => 'expired']);
        $this->assertSame(800, (int) $this->account($ana)->current_balance);
        $this->assertSame(0, LoyaltyPendingEarning::where('order_id', $order->id)->count());
        $order->update(['status' => 'cancelled']);
        $this->assertSame(800, (int) $this->account($ana)->current_balance);
    }

    public function test_spending_more_than_the_balance_fails(): void
    {
        $ana = $this->customer('ana@example.test');
        $this->giveBalance($ana, 300);
        $order = $this->order($ana, ['subtotal' => 100, 'total' => 97, 'points_used' => 500, 'points_discount' => 5, 'status' => 'pending']);
        $this->expectException(\RuntimeException::class);
        $this->loyalty->spendForOrder($order, 500);
    }

    public function test_ticket_office_and_test_orders_earn_nothing(): void
    {
        $ana = $this->customer('ana@example.test');
        $this->order($ana, ['subtotal' => 100, 'total' => 100, 'status' => 'completed', 'source' => 'pos_app', 'paid_at' => now()]);
        $this->order($ana, ['subtotal' => 100, 'total' => 0, 'status' => 'completed', 'source' => 'test_order', 'payment_status' => 'test', 'paid_at' => now()]);
        $this->assertSame(0, LoyaltyPendingEarning::count());
    }

    // ------------------------------------------------------------------ referrals

    public function test_referral_credits_both_on_the_friends_first_confirmed_order(): void
    {
        Carbon::setTestNow('2026-10-01 10:00:00');
        $ioana = $this->customer('ioana@example.test');
        $codeId = DB::table('marketplace_referral_codes')->insertGetId(['marketplace_client_id' => $this->clientId, 'marketplace_customer_id' => $ioana->id, 'code' => 'IOANA123', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $dan = $this->customer('dan@example.test');
        $this->assertFalse($this->loyalty->attachReferral($this->clientId, $ioana->id, 'IOANA123')); // not yourself
        $this->assertTrue($this->loyalty->attachReferral($this->clientId, $dan->id, 'IOANA123'));
        $this->assertFalse($this->loyalty->attachReferral($this->clientId, $dan->id, 'IOANA123')); // once

        // a 60 lei order is under the 100 lei minimum: points yes, referral no
        $small = $this->order($dan, ['subtotal' => 60, 'total' => 61.2, 'status' => 'completed', 'paid_at' => now()]);
        Carbon::setTestNow('2026-10-12 10:00:00');
        $this->loyalty->confirmDue();
        $this->assertSame('registered', DB::table('marketplace_referrals')->where('referred_id', $dan->id)->value('status'));

        $order = $this->order($dan, ['subtotal' => 120, 'total' => 122.4, 'status' => 'completed', 'paid_at' => now()]);
        Carbon::setTestNow('2026-10-22 10:00:00');
        $this->loyalty->confirmDue();

        $ref = DB::table('marketplace_referrals')->where('referred_id', $dan->id)->first();
        $this->assertSame(['converted', $order->id, 300], [$ref->status, (int) $ref->order_id, (int) $ref->points_awarded]);
        $this->assertSame(300, (int) $this->account($ioana)->current_balance);
        $this->assertSame(1, (int) $this->account($ioana)->referral_count);
        $this->assertSame(30 + 60 + 200, (int) $this->account($dan)->current_balance); // 60 lei → 30, 120 lei → 60, welcome 200
        $this->assertSame(1, (int) DB::table('marketplace_referral_codes')->where('id', $codeId)->value('conversions'));

        // someone who already bought isn't a new customer
        $old = $this->customer('old@example.test');
        $this->order($old, ['subtotal' => 50, 'total' => 51, 'status' => 'completed', 'paid_at' => now()]);
        $this->assertFalse($this->loyalty->attachReferral($this->clientId, $old->id, 'IOANA123'));
    }

    public function test_referral_yearly_cap(): void
    {
        $this->config->update(['referral_max_per_year' => 1]);
        $this->loyalty = new MarketplaceLoyaltyService();
        $this->app->instance(MarketplaceLoyaltyService::class, $this->loyalty);
        $ioana = $this->customer('ioana@example.test');
        DB::table('marketplace_referral_codes')->insert(['marketplace_client_id' => $this->clientId, 'marketplace_customer_id' => $ioana->id, 'code' => 'CAP1', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['a@example.test', 'b@example.test'] as $email) {
            $friend = $this->customer($email);
            $this->loyalty->attachReferral($this->clientId, $friend->id, 'CAP1');
            $this->order($friend, ['subtotal' => 150, 'total' => 153, 'status' => 'completed', 'paid_at' => now()]);
        }
        Carbon::setTestNow(now()->addDays(10));
        $this->loyalty->confirmDue();
        $this->assertSame(300, (int) $this->account($ioana)->current_balance);
        $this->assertSame(1, DB::table('marketplace_referrals')->where('status', 'converted')->count());
    }

    // ------------------------------------------------------------------ birthdays and expiry

    public function test_birthday_bonus_once_a_year_and_only_for_buyers(): void
    {
        Carbon::setTestNow('2026-03-14 09:00:00');
        $buyer = $this->customer('buyer@example.test', '1990-03-14');
        $this->order($buyer, ['subtotal' => 10, 'total' => 10.2, 'status' => 'completed', 'paid_at' => now()->subMonth()]);
        $this->customer('nobuy@example.test', '1991-03-14');
        $this->customer('other@example.test', '1990-05-01');

        $this->assertSame(1, $this->loyalty->awardBirthdays(now()));
        $this->assertSame(150, (int) $this->account($buyer)->current_balance);
        $this->assertSame(0, $this->loyalty->awardBirthdays(now()));

        Carbon::setTestNow('2027-03-14 09:00:00');
        $this->assertSame(1, $this->loyalty->awardBirthdays(now()));

        // 29 February, in a year without it
        Carbon::setTestNow('2027-02-28 09:00:00');
        $leap = $this->customer('leap@example.test', '2000-02-29');
        $this->order($leap, ['subtotal' => 10, 'total' => 10.2, 'status' => 'completed', 'paid_at' => now()->subMonth()]);
        $this->assertSame(1, $this->loyalty->awardBirthdays(now()));
    }

    public function test_expiry_takes_the_oldest_unused_points(): void
    {
        Carbon::setTestNow('2026-01-10 10:00:00');
        $ana = $this->customer('ana@example.test');
        $cp = CustomerPoints::getOrCreateForMarketplace($this->clientId, $ana->id);
        $this->ledger($cp, 'earned', 100, '2026-01-10', '2027-01-10');
        $this->ledger($cp, 'earned', 200, '2026-06-01', '2027-06-01');
        $this->ledger($cp, 'spent', -30, '2026-07-01');
        $cp->update(['current_balance' => 270, 'last_spent_at' => '2026-07-01']);

        Carbon::setTestNow('2026-12-20 10:00:00');
        $this->assertSame(70, $this->loyalty->expiringSoon($this->account($ana), 30)); // 100 earned first, 30 used
        Carbon::setTestNow('2027-01-11 10:00:00');
        $this->assertSame(70, $this->loyalty->expirePoints());
        $this->assertSame(200, (int) $this->account($ana)->current_balance);
        $this->assertSame(0, $this->loyalty->expirePoints()); // idempotent
        Carbon::setTestNow('2027-06-02 10:00:00');
        $this->assertSame(200, $this->loyalty->expirePoints());
        $this->assertSame(0, (int) $this->account($ana)->current_balance);
    }

    public function test_inactive_account_expires_whole_balance(): void
    {
        Carbon::setTestNow('2026-01-10 10:00:00');
        $ana = $this->customer('ana@example.test');
        $cp = CustomerPoints::getOrCreateForMarketplace($this->clientId, $ana->id);
        $this->ledger($cp, 'adjusted', 400, '2026-01-10'); // no expiry date on its own
        $cp->update(['current_balance' => 400, 'last_earned_at' => '2026-01-10']);
        Carbon::setTestNow('2027-02-01 10:00:00');
        $this->assertSame(400, $this->loyalty->expirePoints());
        $this->assertSame(0, (int) $this->account($ana)->current_balance);
    }

    // ------------------------------------------------------------------ switch

    public function test_nothing_happens_when_the_marketplace_has_not_switched_it_on(): void
    {
        $this->config->update(['auto_rewards_enabled' => false]);
        $this->loyalty = new MarketplaceLoyaltyService();
        $this->app->instance(MarketplaceLoyaltyService::class, $this->loyalty);
        $ana = $this->customer('ana@example.test', now()->format('Y-m-d'));
        $this->giveBalance($ana, 1000);
        $this->order($ana, ['subtotal' => 100, 'total' => 102, 'status' => 'completed', 'paid_at' => now()]);

        $this->assertSame(0, LoyaltyPendingEarning::count());
        $this->assertNull($this->loyalty->publicSettings($this->clientId));
        $this->assertSame('Programul de puncte nu este activ acum.', $this->loyalty->quote(MarketplaceClient::find($this->clientId), $ana, 'ana@example.test', 100, 500)['error']);
        $this->assertSame(0, $this->loyalty->awardBirthdays());
        $this->assertSame(0, $this->loyalty->expirePoints());
        $this->assertSame(1000, (int) $this->account($ana)->current_balance);

        // on, but the microservice is off
        $this->config->update(['auto_rewards_enabled' => true]);
        DB::table('marketplace_client_microservices')->update(['status' => 'inactive']);
        $this->assertNull((new MarketplaceLoyaltyService())->config($this->clientId));
    }

    public function test_public_settings(): void
    {
        $s = $this->loyalty->publicSettings($this->clientId);
        $this->assertSame([true, 100, 50, '1 punct la fiecare 2 lei', 200, 20.0, 1000, 150, 300, 200, 100.0, 365, 2], [
            $s['enabled'], $s['points_per_lei'], $s['earn_points_per_100_lei'], $s['earn_rate_label'], $s['min_redeem_points'],
            $s['max_redeem_percentage'], $s['max_redeem_points_per_order'], $s['birthday_bonus_points'], $s['referral_bonus_points'],
            $s['referred_bonus_points'], $s['referral_min_order'], $s['points_expire_days'], $s['confirm_days'],
        ]);
    }

    public function test_report(): void
    {
        $ana = $this->customer('ana@example.test');
        $this->giveBalance($ana, 2000);
        $this->order($ana, ['subtotal' => 100, 'commission_amount' => 2, 'total' => 92, 'points_used' => 1000, 'points_discount' => 10, 'status' => 'completed', 'paid_at' => now()]);
        $this->order($ana, ['subtotal' => 400, 'commission_amount' => 8, 'total' => 408, 'status' => 'completed', 'paid_at' => now()]);
        $r = $this->loyalty->report($this->config, now()->startOfMonth(), now()->endOfMonth());
        $this->assertSame([500.0, 10.0, 10.0, 100.0, 2.0], [$r['sales_lei'], $r['commission_lei'], $r['redeemed_lei'], $r['cost_share'], $r['cost_per_100_lei']]);
        $this->assertSame(45 + 200, $r['pending_points']);
    }

    // ------------------------------------------------------------------ helpers

    protected function customer(string $email, ?string $birthDate = null): MarketplaceCustomer
    {
        return MarketplaceCustomer::create(['marketplace_client_id' => $this->clientId, 'email' => $email, 'first_name' => 'T', 'last_name' => 'Q', 'birth_date' => $birthDate, 'status' => 'active']);
    }

    protected function order(MarketplaceCustomer $c, array $attrs): Order
    {
        static $n = 0;
        $n++;
        return Order::create($attrs + [
            'marketplace_client_id' => $this->clientId,
            'marketplace_customer_id' => $c->id,
            'order_number' => 'ACT-TEST' . $n,
            'status' => 'pending',
            'payment_status' => 'pending',
            'subtotal' => 0,
            'discount_amount' => 0,
            'commission_amount' => 0,
            'total' => 0,
            'currency' => 'RON',
            'source' => 'marketplace_activity',
            'customer_email' => $c->email,
        ]);
    }

    protected function giveBalance(MarketplaceCustomer $c, int $points): void
    {
        $cp = CustomerPoints::getOrCreateForMarketplace($this->clientId, $c->id);
        $cp->update(['current_balance' => $points, 'total_earned' => $points]);
    }

    protected function account(MarketplaceCustomer $c): CustomerPoints
    {
        return CustomerPoints::where('marketplace_client_id', $this->clientId)->where('marketplace_customer_id', $c->id)->firstOrFail();
    }

    protected function ledger(CustomerPoints $cp, string $type, int $points, string $at, ?string $expires = null): void
    {
        PointsTransaction::create(['marketplace_client_id' => $this->clientId, 'marketplace_customer_id' => $cp->marketplace_customer_id, 'type' => $type, 'points' => $points, 'balance_after' => 0, 'action_type' => 'test', 'description' => ['ro' => 't'], 'expires_at' => $expires])
            ->forceFill(['created_at' => $at])->save();
    }

    protected function buildSchema(): void
    {
        Schema::create('marketplace_clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('slug')->nullable(); $t->string('domain')->nullable(); $t->string('status')->nullable();
            $t->string('api_key')->nullable(); $t->string('api_secret')->nullable(); $t->text('settings')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('microservices', function (Blueprint $t) {
            $t->id(); $t->string('slug'); $t->text('name')->nullable(); $t->timestamps();
        });
        Schema::create('marketplace_client_microservices', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('marketplace_client_id'); $t->unsignedBigInteger('microservice_id'); $t->string('status')->nullable();
            $t->boolean('is_active')->default(true); $t->timestamp('activated_at')->nullable(); $t->timestamp('expires_at')->nullable();
            $t->text('settings')->nullable(); $t->text('usage_stats')->nullable(); $t->boolean('is_default')->default(false); $t->integer('sort_order')->default(0);
            $t->decimal('billing_amount', 10, 2)->nullable(); $t->string('billing_cycle')->nullable(); $t->timestamps();
        });
        Schema::create('marketplace_customers', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('marketplace_client_id'); $t->string('email'); $t->string('first_name')->nullable(); $t->string('last_name')->nullable();
            $t->string('phone')->nullable(); $t->string('password')->nullable(); $t->date('birth_date')->nullable(); $t->string('status')->nullable();
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('gamification_configs', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->unsignedBigInteger('marketplace_client_id')->nullable();
            $t->decimal('point_value', 10, 2)->default(0.01); $t->string('currency', 3)->default('RON'); $t->decimal('earn_percentage', 5, 2)->default(5);
            $t->boolean('earn_on_subtotal')->default(true); $t->decimal('min_order_for_earning', 10, 2)->default(0); $t->integer('min_redeem_points')->default(100);
            $t->decimal('max_redeem_percentage', 5, 2)->default(50); $t->integer('max_redeem_points_per_order')->nullable();
            $t->integer('birthday_bonus_points')->default(100); $t->integer('signup_bonus_points')->default(50); $t->integer('referral_bonus_points')->default(200);
            $t->integer('referred_bonus_points')->default(100); $t->integer('points_expire_days')->nullable(); $t->boolean('expire_on_inactivity')->default(false);
            $t->integer('inactivity_days')->default(365); $t->string('points_name')->default('puncte'); $t->string('points_name_singular')->default('punct');
            $t->string('icon')->default('star'); $t->text('tiers')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('customer_points', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('marketplace_client_id')->nullable(); $t->unsignedBigInteger('marketplace_customer_id')->nullable();
            foreach (['total_earned', 'total_spent', 'total_expired', 'current_balance', 'pending_points', 'tier_points', 'referral_count', 'referral_points_earned'] as $c) {
                $t->integer($c)->default(0);
            }
            $t->string('current_tier')->nullable(); $t->timestamp('tier_updated_at')->nullable(); $t->timestamp('last_earned_at')->nullable();
            $t->timestamp('last_spent_at')->nullable(); $t->timestamp('points_expire_at')->nullable(); $t->string('referral_code', 20)->nullable()->unique(); $t->timestamps();
        });
        Schema::create('points_transactions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('marketplace_client_id')->nullable(); $t->unsignedBigInteger('marketplace_customer_id')->nullable();
            $t->enum('type', ['earned', 'spent', 'expired', 'adjusted', 'refunded'])->default('earned'); $t->integer('points'); $t->integer('balance_after');
            $t->string('action_type', 50)->nullable(); $t->string('reference_type')->nullable(); $t->unsignedBigInteger('reference_id')->nullable();
            $t->json('description'); $t->text('admin_note')->nullable(); $t->json('metadata')->nullable(); $t->timestamp('expires_at')->nullable();
            $t->boolean('is_expired')->default(false); $t->unsignedBigInteger('reversed_transaction_id')->nullable(); $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('order_id')->nullable(); $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('marketplace_client_id')->nullable(); $t->unsignedBigInteger('marketplace_customer_id')->nullable();
            $t->unsignedBigInteger('marketplace_organizer_id')->nullable(); $t->unsignedBigInteger('event_id')->nullable(); $t->string('order_number')->nullable();
            $t->string('status')->nullable(); $t->string('payment_status')->nullable(); $t->decimal('subtotal', 12, 2)->default(0); $t->decimal('discount_amount', 12, 2)->default(0);
            $t->decimal('promo_discount', 12, 2)->nullable(); $t->decimal('commission_rate', 5, 2)->default(0); $t->decimal('commission_amount', 12, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0); $t->integer('total_cents')->nullable(); $t->string('currency', 3)->nullable(); $t->string('source')->nullable();
            $t->string('customer_email')->nullable(); $t->string('customer_name')->nullable(); $t->text('meta')->nullable();
            $t->timestamp('paid_at')->nullable(); $t->timestamp('expires_at')->nullable(); $t->timestamps();
        });
        Schema::create('tickets', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('order_id')->nullable(); $t->unsignedBigInteger('event_id')->nullable(); $t->timestamps();
        });
        Schema::create('events', function (Blueprint $t) {
            $t->id(); $t->date('event_date')->nullable(); $t->timestamps();
        });
        Schema::create('activity_bookings', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('order_id')->nullable(); $t->date('booking_date')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('marketplace_referral_codes', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('marketplace_client_id'); $t->unsignedBigInteger('marketplace_customer_id'); $t->string('code', 20)->unique();
            $t->integer('clicks')->default(0); $t->integer('signups')->default(0); $t->integer('conversions')->default(0); $t->decimal('total_value', 12, 2)->default(0);
            $t->integer('points_earned')->default(0); $t->integer('pending_points')->default(0); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('marketplace_referrals', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('marketplace_client_id'); $t->unsignedBigInteger('referral_code_id'); $t->unsignedBigInteger('referrer_id');
            $t->unsignedBigInteger('referred_id')->nullable(); $t->string('status', 20)->default('pending'); $t->string('ip_address', 45)->nullable();
            $t->string('user_agent')->nullable(); $t->string('source', 50)->nullable(); $t->timestamp('clicked_at')->nullable(); $t->timestamp('registered_at')->nullable();
            $t->timestamp('converted_at')->nullable(); $t->timestamp('expires_at')->nullable(); $t->unsignedBigInteger('order_id')->nullable();
            $t->decimal('order_value', 12, 2)->nullable(); $t->integer('points_awarded')->default(0); $t->unsignedBigInteger('points_transaction_id')->nullable(); $t->timestamps();
        });
    }
}
