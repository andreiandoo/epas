<?php

namespace App\Services\Gamification;

use App\Models\ActivityBooking;
use App\Models\Event;
use App\Models\Gamification\CustomerPoints;
use App\Models\Gamification\GamificationConfig;
use App\Models\Gamification\LoyaltyPendingEarning;
use App\Models\Gamification\PointsTransaction;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Loyalty points for marketplaces (bilete.online first). Runs only where the admin switched it on
 * (gamification_configs.auto_rewards_enabled + the "gamification" microservice + the config active), so other
 * marketplaces keep exactly what they had.
 *
 * Money: 1 point is worth point_value lei. Points paid at checkout lower the order total and are stored in
 * orders.points_used / points_discount, never in discount_amount (that one is a promo code funded by the organizer and
 * comes off the organizer's settlement). The organizer is paid the full ticket price; the marketplace funds the points.
 *
 * Earning: a paid order earns earn_percentage% of the ticket value paid in money (after promo codes and points,
 * without commission, fees or insurance), as points. They wait in loyalty_pending_earnings until the activity has taken
 * place plus points_confirm_days, then move to the ledger (points_transactions); a refund before that cancels them, a
 * refund after takes them back. A referred customer's first confirmed order also credits both referral bonuses.
 *
 * Ledger rows use the table's own types: earned (purchase, birthday, referral, referred), spent (checkout), refunded
 * (points of an unpaid or refunded order given back), adjusted (points of a refunded order taken back), expired.
 * Expiry is first-in first-out: spending uses the oldest points first, so what expires is what was earned before the
 * cut-off and not used since.
 */
class MarketplaceLoyaltyService
{
    public const PAID_STATUSES = ['paid', 'confirmed', 'completed'];
    /** order sources that never earn points: test and imported orders, sales at the venue's ticket office */
    public const SKIP_SOURCES = ['test_order', 'legacy_import', 'external_import', 'pos_app'];

    /** @var array<int, GamificationConfig|null> */
    protected array $configs = [];

    // ================================================================ configuration

    /**
     * The programme of a marketplace, only when it runs on its own (active, switched on, microservice active).
     */
    public function config(MarketplaceClient|int|null $client): ?GamificationConfig
    {
        if (!$client) {
            return null;
        }
        $clientId = $client instanceof MarketplaceClient ? $client->id : (int) $client;
        if (array_key_exists($clientId, $this->configs)) {
            return $this->configs[$clientId];
        }

        $config = GamificationConfig::where('marketplace_client_id', $clientId)
            ->where('is_active', true)
            ->where('auto_rewards_enabled', true)
            ->first();

        if ($config) {
            $model = $client instanceof MarketplaceClient ? $client : MarketplaceClient::find($clientId);
            if (!$model || !$model->hasMicroservice('gamification')) {
                $config = null;
            }
        }

        return $this->configs[$clientId] = $config;
    }

    /**
     * What the site needs to show and compute points (cart, checkout, activity pages). Null when the programme is off.
     */
    public function publicSettings(MarketplaceClient|int|null $client): ?array
    {
        $c = $this->config($client);
        if (!$c) {
            return null;
        }
        $pointValue = (float) ($c->point_value ?: 0.01);
        $name = is_array($c->points_name) ? ($c->points_name['ro'] ?? 'puncte') : ($c->points_name ?: 'puncte');
        $singular = is_array($c->points_name_singular) ? ($c->points_name_singular['ro'] ?? 'punct') : ($c->points_name_singular ?: 'punct');

        return [
            'enabled' => true,
            'points_name' => $name,
            'points_name_singular' => $singular,
            'point_value' => $pointValue,
            'points_per_lei' => $pointValue > 0 ? (int) round(1 / $pointValue) : 100,
            'earn_percentage' => (float) $c->earn_percentage,
            'earn_points_per_100_lei' => $c->pointsPer100Lei(),
            'earn_rate_label' => $c->earnRateLabel(),
            'earn_on' => $c->earn_on_subtotal ? 'tickets' : 'total',
            'min_order_for_earning' => (float) ($c->min_order_for_earning ?? 0),
            'min_redeem_points' => (int) ($c->min_redeem_points ?? 0),
            'max_redeem_percentage' => (float) ($c->max_redeem_percentage ?? 0),
            'max_redeem_points_per_order' => (int) ($c->max_redeem_points_per_order ?? 0),
            'birthday_bonus_points' => (int) ($c->birthday_bonus_points ?? 0),
            'referral_bonus_points' => (int) ($c->referral_bonus_points ?? 0),
            'referred_bonus_points' => (int) ($c->referred_bonus_points ?? 0),
            'referral_min_order' => (float) ($c->referral_min_order ?? 0),
            'referral_max_per_year' => (int) ($c->referral_max_per_year ?? 0),
            'points_expire_days' => (int) ($c->points_expire_days ?? 0),
            'expire_on_inactivity' => (bool) $c->expire_on_inactivity,
            'inactivity_days' => (int) ($c->inactivity_days ?? 0),
            'confirm_days' => (int) ($c->points_confirm_days ?? 2),
        ];
    }

    // ================================================================ amounts

    /**
     * Most points an order may use: max_redeem_percentage of the ticket value, the per-order cap, the balance.
     */
    public function redeemablePoints(GamificationConfig $c, float $ticketValue, int $balance): int
    {
        $pointValue = (float) ($c->point_value ?: 0.01);
        if ($ticketValue <= 0 || $balance <= 0 || $pointValue <= 0) {
            return 0;
        }
        $minBalance = (int) ($c->min_redeem_points ?? 0);
        if ($balance < $minBalance) {
            return 0;
        }
        $percent = (float) ($c->max_redeem_percentage ?? 0);
        $max = $percent > 0 ? (int) floor(round($ticketValue * $percent / 100 / $pointValue, 6)) : 0;
        $cap = (int) ($c->max_redeem_points_per_order ?? 0);
        if ($cap > 0) {
            $max = min($max, $cap);
        }

        return max(0, min($max, $balance));
    }

    /**
     * The ticket value paid in money, on which an order earns points.
     */
    public function earnBase(Order $order, ?GamificationConfig $c = null): float
    {
        $c ??= $this->config($order->marketplace_client_id);
        if ($c && !$c->earn_on_subtotal) {
            return max(0, round((float) $order->total, 2));
        }

        return max(0, round((float) $order->subtotal - (float) $order->discount_amount - (float) $order->points_discount, 2));
    }

    // ================================================================ checkout

    /**
     * Check a request to pay with points. $ticketValue is the ticket value after promo codes. Returns the points and
     * the discount the order gets (the request is lowered to what the rules allow), or an error for the customer.
     *
     * @return array{points:int, discount:float, error:?string}
     */
    public function quote(MarketplaceClient $client, ?MarketplaceCustomer $customer, string $checkoutEmail, float $ticketValue, int $requested): array
    {
        $none = ['points' => 0, 'discount' => 0.0, 'error' => null];
        if ($requested <= 0) {
            return $none;
        }
        $c = $this->config($client);
        if (!$c) {
            return ['points' => 0, 'discount' => 0.0, 'error' => 'Programul de puncte nu este activ acum.'];
        }
        if (!$customer || (int) $customer->marketplace_client_id !== (int) $client->id) {
            return ['points' => 0, 'discount' => 0.0, 'error' => 'Intră în cont ca să folosești punctele.'];
        }
        if (mb_strtolower(trim($customer->email)) !== mb_strtolower(trim($checkoutEmail))) {
            return ['points' => 0, 'discount' => 0.0, 'error' => 'Punctele se pot folosi doar la comenzile făcute cu adresa de email a contului tău.'];
        }

        $balance = (int) (CustomerPoints::where('marketplace_client_id', $client->id)
            ->where('marketplace_customer_id', $customer->id)
            ->value('current_balance') ?? 0);
        $min = (int) ($c->min_redeem_points ?? 0);
        if ($balance < max(1, $min)) {
            return ['points' => 0, 'discount' => 0.0, 'error' => 'Poți folosi punctele de la minimum ' . max(1, $min) . ' puncte în cont.'];
        }

        $points = min($requested, $this->redeemablePoints($c, $ticketValue, $balance));
        if ($points <= 0) {
            return ['points' => 0, 'discount' => 0.0, 'error' => 'Punctele nu se pot folosi la această comandă.'];
        }

        return ['points' => $points, 'discount' => round($points * (float) ($c->point_value ?: 0.01), 2), 'error' => null];
    }

    /**
     * Take the points of a new order from the customer's balance. Call inside the checkout transaction, after the
     * order exists; throws when the balance no longer covers them (another order used them meanwhile).
     */
    public function spendForOrder(Order $order, int $points): PointsTransaction
    {
        $cp = $this->lockedAccount((int) $order->marketplace_client_id, (int) $order->marketplace_customer_id);
        if ((int) $cp->current_balance < $points) {
            throw new RuntimeException('Soldul de puncte s-a schimbat între timp. Reîncarcă pagina și încearcă din nou.');
        }

        return $this->write($cp, PointsTransaction::TYPE_SPENT, -$points, 'checkout', [
            'reference_type' => 'order',
            'reference_id' => $order->id,
            'description' => ['ro' => 'Folosite la comanda #' . $order->order_number, 'en' => 'Used on order #' . $order->order_number],
            'metadata' => ['order_number' => $order->order_number, 'discount' => (float) $order->points_discount],
        ]);
    }

    // ================================================================ order lifecycle

    /**
     * A marketplace order was paid: its points wait until the activity has taken place.
     */
    public function onOrderPaid(Order $order): void
    {
        if (!$order->marketplace_client_id || !$order->marketplace_customer_id) {
            return;
        }
        if (in_array($order->source ?? '', self::SKIP_SOURCES, true) || $order->payment_status === 'test') {
            return;
        }
        $c = $this->config($order->marketplace_client_id);
        if (!$c) {
            return;
        }

        $base = $this->earnBase($order, $c);
        $points = $c->pointsForLei($base);
        $availableAt = $this->confirmationDate($order, $c);

        $row = LoyaltyPendingEarning::firstOrCreate(
            ['order_id' => $order->id],
            [
                'marketplace_client_id' => $order->marketplace_client_id,
                'marketplace_customer_id' => $order->marketplace_customer_id,
                'points' => $points,
                'base_amount' => $base,
                'available_at' => $availableAt,
                'status' => LoyaltyPendingEarning::STATUS_PENDING,
            ]
        );

        if ($row->wasRecentlyCreated && $points > 0) {
            CustomerPoints::getOrCreateForMarketplace((int) $order->marketplace_client_id, (int) $order->marketplace_customer_id);
            CustomerPoints::where('marketplace_client_id', $order->marketplace_client_id)
                ->where('marketplace_customer_id', $order->marketplace_customer_id)
                ->increment('pending_points', $points);
        }
    }

    /**
     * An order that was never paid closed (expired, failed, cancelled): give back the points it had reserved.
     */
    public function onOrderClosedUnpaid(Order $order): void
    {
        $this->restoreUsedPoints($order, 'Puncte returnate: comanda #' . $order->order_number . ' nu a fost plătită');
    }

    /**
     * A paid order was refunded or cancelled: cancel or take back what it earned, give back the points it used.
     */
    public function onOrderReversed(Order $order): void
    {
        $pending = LoyaltyPendingEarning::where('order_id', $order->id)->first();
        if ($pending) {
            DB::transaction(function () use ($pending, $order) {
                $pending = LoyaltyPendingEarning::whereKey($pending->id)->lockForUpdate()->first();
                if ($pending->status === LoyaltyPendingEarning::STATUS_PENDING) {
                    $pending->update(['status' => LoyaltyPendingEarning::STATUS_CANCELLED]);
                    if ($pending->points > 0) {
                        CustomerPoints::where('marketplace_client_id', $pending->marketplace_client_id)
                            ->where('marketplace_customer_id', $pending->marketplace_customer_id)
                            ->where('pending_points', '>=', $pending->points)
                            ->decrement('pending_points', $pending->points);
                    }
                } elseif ($pending->status === LoyaltyPendingEarning::STATUS_CREDITED && $pending->points > 0) {
                    $already = PointsTransaction::where('reference_type', 'order')
                        ->where('reference_id', $order->id)
                        ->where('action_type', 'purchase_reversal')
                        ->exists();
                    if (!$already) {
                        $cp = $this->lockedAccount((int) $pending->marketplace_client_id, (int) $pending->marketplace_customer_id);
                        $take = min((int) $pending->points, max(0, (int) $cp->current_balance));
                        if ($take > 0) {
                            $this->write($cp, PointsTransaction::TYPE_ADJUSTED, -$take, 'purchase_reversal', [
                                'reference_type' => 'order',
                                'reference_id' => $order->id,
                                'description' => ['ro' => 'Punctele comenzii #' . $order->order_number . ' retrase după retur', 'en' => 'Points of order #' . $order->order_number . ' taken back after a refund'],
                            ]);
                        }
                    }
                }
            });
        }

        $this->restoreUsedPoints($order, 'Puncte returnate după returul comenzii #' . $order->order_number);
    }

    protected function restoreUsedPoints(Order $order, string $label): void
    {
        if ((int) $order->points_used <= 0 || !$order->marketplace_client_id || !$order->marketplace_customer_id) {
            return;
        }
        DB::transaction(function () use ($order, $label) {
            $spend = PointsTransaction::where('reference_type', 'order')
                ->where('reference_id', $order->id)
                ->where('type', PointsTransaction::TYPE_SPENT)
                ->where('action_type', 'checkout')
                ->lockForUpdate()
                ->first();
            if (!$spend) {
                return;
            }
            $done = PointsTransaction::where('reversed_transaction_id', $spend->id)->exists();
            if ($done) {
                return;
            }
            $cp = $this->lockedAccount((int) $order->marketplace_client_id, (int) $order->marketplace_customer_id);
            $points = abs((int) $spend->points);
            $this->write($cp, PointsTransaction::TYPE_REFUNDED, $points, 'refund', [
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'reversed_transaction_id' => $spend->id,
                'description' => ['ro' => $label, 'en' => 'Points given back for order #' . $order->order_number],
            ]);
        });
    }

    /**
     * When the points of an order can be credited: the activity's end (or date) plus the confirmation days.
     * Without a known date, seven days after payment.
     */
    public function confirmationDate(Order $order, ?GamificationConfig $c = null): Carbon
    {
        $c ??= $this->config($order->marketplace_client_id);
        $days = (int) ($c?->points_confirm_days ?? 2);
        $end = null;

        try {
            $bookingDate = ActivityBooking::where('order_id', $order->id)->max('booking_date');
            if ($bookingDate) {
                $end = Carbon::parse($bookingDate)->endOfDay();
            }

            $eventIds = DB::table('tickets')->where('order_id', $order->id)->whereNotNull('event_id')->distinct()->pluck('event_id')->all();
            if ($order->event_id) {
                $eventIds[] = $order->event_id;
            }
            foreach (Event::whereIn('id', array_unique($eventIds))->get() as $event) {
                $eventEnd = $event->getEffectiveEndDatetime();
                if ($eventEnd && (!$end || $eventEnd->gt($end))) {
                    $end = $eventEnd->copy();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Loyalty: activity date not found', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        $from = $end ?? Carbon::parse($order->paid_at ?? now())->addDays(7);
        if ($from->lt(now())) {
            $from = now();
        }

        return $from->copy()->addDays($days);
    }

    // ================================================================ scheduled work

    /**
     * Credit the points of orders whose activity has taken place. Referral bonuses are credited with the first
     * confirmed order of a referred customer.
     */
    public function confirmDue(int $limit = 500): int
    {
        $credited = 0;
        $clientIds = [];
        foreach ($this->activeConfigs() as $c) {
            $clientIds[] = (int) $c->marketplace_client_id;
        }
        if (!$clientIds) {
            return 0; // programme switched off: what is pending keeps waiting
        }
        $rows = LoyaltyPendingEarning::where('status', LoyaltyPendingEarning::STATUS_PENDING)
            ->whereIn('marketplace_client_id', $clientIds)
            ->where('available_at', '<=', now())
            ->orderBy('available_at')
            ->limit($limit)
            ->get();

        foreach ($rows as $row) {
            try {
                DB::transaction(function () use ($row, &$credited) {
                    $row = LoyaltyPendingEarning::whereKey($row->id)->lockForUpdate()->first();
                    if (!$row || $row->status !== LoyaltyPendingEarning::STATUS_PENDING) {
                        return;
                    }
                    $order = Order::find($row->order_id);
                    $c = $this->config($row->marketplace_client_id);
                    if (!$c) {
                        return;
                    }
                    if (!$order || !in_array($order->status, array_merge(self::PAID_STATUSES, ['partially_refunded']), true)) {
                        // refunded meanwhile: nothing to credit
                        $row->update(['status' => LoyaltyPendingEarning::STATUS_CANCELLED]);
                        $this->dropPending($row);
                        return;
                    }

                    $txId = null;
                    if ($row->points > 0) {
                        $cp = $this->lockedAccount((int) $row->marketplace_client_id, (int) $row->marketplace_customer_id);
                        $cp->pending_points = max(0, (int) $cp->pending_points - (int) $row->points);
                        $tx = $this->write($cp, PointsTransaction::TYPE_EARNED, (int) $row->points, 'purchase', [
                            'reference_type' => 'order',
                            'reference_id' => $order->id,
                            'expires' => true,
                            'description' => ['ro' => 'Puncte pentru comanda #' . $order->order_number, 'en' => 'Points for order #' . $order->order_number],
                            'metadata' => ['order_number' => $order->order_number, 'base_amount' => (float) $row->base_amount],
                        ]);
                        $txId = $tx->id;
                        $credited++;
                    }
                    $row->update(['status' => LoyaltyPendingEarning::STATUS_CREDITED, 'points_transaction_id' => $txId]);

                    $this->convertReferral($c, $order, (float) $row->base_amount);
                });
            } catch (\Throwable $e) {
                Log::error('Loyalty: crediting pending points failed', ['pending_id' => $row->id, 'error' => $e->getMessage()]);
            }
        }

        return $credited;
    }

    protected function dropPending(LoyaltyPendingEarning $row): void
    {
        if ($row->points > 0) {
            CustomerPoints::where('marketplace_client_id', $row->marketplace_client_id)
                ->where('marketplace_customer_id', $row->marketplace_customer_id)
                ->where('pending_points', '>=', $row->points)
                ->decrement('pending_points', $row->points);
        }
    }

    /**
     * The first confirmed order of a referred customer: credit the referrer and the friend, within the referral window,
     * above the minimum order and under the referrer's yearly cap.
     */
    protected function convertReferral(GamificationConfig $c, Order $order, float $orderValue): void
    {
        $referral = DB::table('marketplace_referrals')
            ->where('marketplace_client_id', $order->marketplace_client_id)
            ->where('referred_id', $order->marketplace_customer_id)
            ->where('status', 'registered')
            ->lockForUpdate()
            ->first();
        if (!$referral || (int) $referral->referrer_id === (int) $order->marketplace_customer_id) {
            return;
        }
        $paidAt = Carbon::parse($order->paid_at ?? $order->created_at);
        if ($referral->expires_at && $paidAt->gt(Carbon::parse($referral->expires_at))) {
            return;
        }
        if ($orderValue <= 0 || $orderValue < (float) ($c->referral_min_order ?? 0)) {
            return; // a free ticket doesn't make a referral
        }
        $cap = (int) ($c->referral_max_per_year ?? 0);
        if ($cap > 0) {
            $thisYear = DB::table('marketplace_referrals')
                ->where('marketplace_client_id', $order->marketplace_client_id)
                ->where('referrer_id', $referral->referrer_id)
                ->where('status', 'converted')
                ->where('converted_at', '>=', now()->startOfYear())
                ->count();
            if ($thisYear >= $cap) {
                return;
            }
        }

        $referrerPoints = (int) ($c->referral_bonus_points ?? 0);
        $referredPoints = (int) ($c->referred_bonus_points ?? 0);
        $referrerTx = null;

        if ($referrerPoints > 0) {
            $cp = $this->lockedAccount((int) $order->marketplace_client_id, (int) $referral->referrer_id);
            $referrerTx = $this->write($cp, PointsTransaction::TYPE_EARNED, $referrerPoints, 'referral', [
                'reference_type' => 'marketplace_referral',
                'reference_id' => $referral->id,
                'expires' => true,
                'description' => ['ro' => 'Bonus invitație: un prieten a cumpărat prima activitate', 'en' => 'Referral bonus: a friend made a first purchase'],
            ]);
            $cp->referral_count = (int) $cp->referral_count + 1;
            $cp->referral_points_earned = (int) $cp->referral_points_earned + $referrerPoints;
            $cp->save();
        }
        if ($referredPoints > 0) {
            $cp = $this->lockedAccount((int) $order->marketplace_client_id, (int) $order->marketplace_customer_id);
            $this->write($cp, PointsTransaction::TYPE_EARNED, $referredPoints, 'referred', [
                'reference_type' => 'marketplace_referral',
                'reference_id' => $referral->id,
                'expires' => true,
                'description' => ['ro' => 'Bonus de bun venit: ai fost invitat de un prieten', 'en' => 'Welcome bonus: invited by a friend'],
            ]);
        }

        DB::table('marketplace_referrals')->where('id', $referral->id)->update([
            'status' => 'converted',
            'converted_at' => now(),
            'order_id' => $order->id,
            'order_value' => $orderValue,
            'points_awarded' => $referrerPoints,
            'points_transaction_id' => $referrerTx?->id,
            'updated_at' => now(),
        ]);
        DB::table('marketplace_referral_codes')->where('id', $referral->referral_code_id)->update([
            'conversions' => DB::raw('conversions + 1'),
            'total_value' => DB::raw('total_value + ' . number_format($orderValue, 2, '.', '')),
            'points_earned' => DB::raw('points_earned + ' . $referrerPoints),
            'updated_at' => now(),
        ]);
    }

    /**
     * Birthday bonus, once a calendar year, to customers who have bought at least once (so a fresh account can't
     * collect it). Born on 29 February: on 28 February in other years.
     */
    public function awardBirthdays(?Carbon $today = null): int
    {
        $today ??= now('Europe/Bucharest');
        $awarded = 0;

        foreach ($this->activeConfigs() as $c) {
            $points = (int) ($c->birthday_bonus_points ?? 0);
            if ($points <= 0) {
                continue;
            }
            $days = [[$today->month, $today->day]];
            if ($today->month === 2 && $today->day === 28 && !$today->isLeapYear()) {
                $days[] = [2, 29];
            }

            $query = MarketplaceCustomer::where('marketplace_client_id', $c->marketplace_client_id)
                ->whereNotNull('birth_date')
                ->where(function ($q) use ($days) {
                    foreach ($days as [$m, $d]) {
                        $q->orWhere(function ($qq) use ($m, $d) {
                            $qq->whereMonth('birth_date', $m)->whereDay('birth_date', $d);
                        });
                    }
                })
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))->from('orders')
                        ->whereColumn('orders.marketplace_customer_id', 'marketplace_customers.id')
                        ->whereIn('orders.status', array_merge(self::PAID_STATUSES, ['partially_refunded']));
                });

            foreach ($query->cursor() as $customer) {
                try {
                    DB::transaction(function () use ($c, $customer, $points, $today, &$awarded) {
                        $cp = $this->lockedAccount((int) $c->marketplace_client_id, (int) $customer->id);
                        $had = PointsTransaction::where('marketplace_client_id', $c->marketplace_client_id)
                            ->where('marketplace_customer_id', $customer->id)
                            ->where('action_type', 'birthday')
                            ->where('created_at', '>=', $today->copy()->startOfYear())
                            ->exists();
                        if ($had) {
                            return;
                        }
                        $this->write($cp, PointsTransaction::TYPE_EARNED, $points, 'birthday', [
                            'expires' => true,
                            'description' => ['ro' => 'La mulți ani! Bonus de ziua ta', 'en' => 'Happy birthday! Birthday bonus'],
                        ]);
                        $awarded++;
                    });
                } catch (\Throwable $e) {
                    Log::error('Loyalty: birthday bonus failed', ['customer_id' => $customer->id, 'error' => $e->getMessage()]);
                }
            }
        }

        return $awarded;
    }

    /**
     * Expire what is due: points earned before their expiry date and not used since, and — when the programme says so —
     * the whole balance of an account inactive for inactivity_days.
     */
    public function expirePoints(): int
    {
        $total = 0;
        foreach ($this->activeConfigs() as $c) {
            $byDate = (int) ($c->points_expire_days ?? 0) > 0;
            $byInactivity = (bool) $c->expire_on_inactivity && (int) ($c->inactivity_days ?? 0) > 0;
            if (!$byDate && !$byInactivity) {
                continue;
            }
            $accounts = CustomerPoints::where('marketplace_client_id', $c->marketplace_client_id)
                ->whereNotNull('marketplace_customer_id')
                ->where('current_balance', '>', 0)
                ->pluck('id');

            foreach ($accounts as $id) {
                try {
                    DB::transaction(function () use ($c, $id, $byDate, $byInactivity, &$total) {
                        $cp = CustomerPoints::whereKey($id)->lockForUpdate()->first();
                        if (!$cp || (int) $cp->current_balance <= 0) {
                            return;
                        }
                        $expire = $byDate ? $this->dueByDate($cp, now()) : 0;
                        $label = 'Puncte expirate';
                        if ($byInactivity) {
                            $last = collect([$cp->last_earned_at, $cp->last_spent_at, $cp->created_at])->filter()->max();
                            if ($last && Carbon::parse($last)->lt(now()->subDays((int) $c->inactivity_days))) {
                                $expire = (int) $cp->current_balance;
                                $label = 'Puncte expirate după ' . (int) $c->inactivity_days . ' de zile fără activitate';
                            }
                        }
                        if ($expire > 0) {
                            $this->write($cp, PointsTransaction::TYPE_EXPIRED, -$expire, 'expiration', [
                                'description' => ['ro' => $label, 'en' => 'Points expired'],
                            ]);
                            $total += $expire;
                        }
                    });
                } catch (\Throwable $e) {
                    Log::error('Loyalty: expiring points failed', ['customer_points_id' => $id, 'error' => $e->getMessage()]);
                }
            }
        }

        return $total;
    }

    /**
     * Points of an account that expire by $moment: earned with an expiry date up to it, minus everything used or
     * expired so far (oldest first). Never more than the balance.
     */
    public function dueByDate(CustomerPoints $cp, Carbon $moment): int
    {
        $rows = PointsTransaction::where('marketplace_client_id', $cp->marketplace_client_id)
            ->where('marketplace_customer_id', $cp->marketplace_customer_id);

        $expiring = (int) (clone $rows)->where('type', PointsTransaction::TYPE_EARNED)
            ->whereNotNull('expires_at')->where('expires_at', '<=', $moment)->sum('points');
        if ($expiring <= 0) {
            return 0;
        }
        $used = (int) -(clone $rows)->where('points', '<', 0)->sum('points');
        $given = (int) (clone $rows)->where('type', PointsTransaction::TYPE_REFUNDED)->sum('points');

        return max(0, min((int) $cp->current_balance, $expiring - max(0, $used - $given)));
    }

    /**
     * Points that will expire within $days (for "Expiră curând").
     */
    public function expiringSoon(CustomerPoints $cp, int $days = 30): int
    {
        return $this->dueByDate($cp, now()->addDays($days));
    }

    public function pendingPoints(int $clientId, int $customerId): int
    {
        return (int) LoyaltyPendingEarning::where('marketplace_client_id', $clientId)
            ->where('marketplace_customer_id', $customerId)
            ->where('status', LoyaltyPendingEarning::STATUS_PENDING)
            ->sum('points');
    }

    // ================================================================ referrals

    /**
     * Link a new customer to the friend who invited them (same rules as registration: code active, not your own,
     * not referred before, 30 days to buy). Used when the friend buys without creating an account first.
     */
    public function attachReferral(int $clientId, int $customerId, string $code, string $source = 'checkout'): bool
    {
        $code = trim($code);
        if ($code === '' || !$this->config($clientId)) {
            return false;
        }
        $referralCode = DB::table('marketplace_referral_codes')
            ->where('marketplace_client_id', $clientId)
            ->where('code', $code)
            ->where('is_active', true)
            ->first();
        if (!$referralCode || (int) $referralCode->marketplace_customer_id === $customerId) {
            return false;
        }
        if (DB::table('marketplace_referrals')->where('marketplace_client_id', $clientId)->where('referred_id', $customerId)->exists()) {
            return false;
        }
        // an existing buyer isn't a new customer
        $hasPaid = Order::where('marketplace_client_id', $clientId)
            ->where('marketplace_customer_id', $customerId)
            ->whereIn('status', array_merge(self::PAID_STATUSES, ['partially_refunded', 'refunded']))
            ->exists();
        if ($hasPaid) {
            return false;
        }

        DB::table('marketplace_referrals')->insert([
            'marketplace_client_id' => $clientId,
            'referral_code_id' => $referralCode->id,
            'referrer_id' => $referralCode->marketplace_customer_id,
            'referred_id' => $customerId,
            'status' => 'registered',
            'source' => $source,
            'registered_at' => now(),
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('marketplace_referral_codes')->where('id', $referralCode->id)->increment('signups');

        return true;
    }

    // ================================================================ report

    /**
     * What the programme cost in a period, next to the commission it came out of.
     *
     * @return array<string, float|int>
     */
    public function report(GamificationConfig $c, Carbon $from, Carbon $to): array
    {
        $clientId = (int) $c->marketplace_client_id;
        $pointValue = (float) ($c->point_value ?: 0.01);
        $tx = fn () => PointsTransaction::where('marketplace_client_id', $clientId)->whereBetween('created_at', [$from, $to]);

        $issued = (int) $tx()->where('type', PointsTransaction::TYPE_EARNED)->sum('points');
        $expired = (int) -$tx()->where('type', PointsTransaction::TYPE_EXPIRED)->sum('points');

        $paid = Order::where('marketplace_client_id', $clientId)
            ->whereIn('status', array_merge(self::PAID_STATUSES, ['partially_refunded']))
            ->whereBetween('paid_at', [$from, $to]);
        $redeemedLei = (float) (clone $paid)->sum('points_discount');
        $commission = (float) (clone $paid)->sum('commission_amount');
        $sales = (float) (clone $paid)->sum('subtotal');

        $outstanding = (int) CustomerPoints::where('marketplace_client_id', $clientId)->sum('current_balance');
        $pending = (int) LoyaltyPendingEarning::where('marketplace_client_id', $clientId)
            ->where('status', LoyaltyPendingEarning::STATUS_PENDING)->sum('points');

        return [
            'issued_points' => $issued,
            'issued_lei' => round($issued * $pointValue, 2),
            'redeemed_lei' => round($redeemedLei, 2),
            'expired_points' => $expired,
            'sales_lei' => round($sales, 2),
            'commission_lei' => round($commission, 2),
            'cost_share' => $commission > 0 ? round($redeemedLei / $commission * 100, 1) : 0.0,
            'cost_per_100_lei' => $sales > 0 ? round($redeemedLei / $sales * 100, 2) : 0.0,
            'outstanding_points' => $outstanding,
            'outstanding_lei' => round($outstanding * $pointValue, 2),
            'pending_points' => $pending,
        ];
    }

    // ================================================================ ledger

    /** @return iterable<GamificationConfig> */
    protected function activeConfigs(): iterable
    {
        foreach (GamificationConfig::whereNotNull('marketplace_client_id')->where('is_active', true)->where('auto_rewards_enabled', true)->get() as $row) {
            $c = $this->config((int) $row->marketplace_client_id);
            if ($c) {
                yield $c;
            }
        }
    }

    /**
     * The customer's points account, created if missing and locked for the current transaction.
     */
    protected function lockedAccount(int $clientId, int $customerId): CustomerPoints
    {
        $id = CustomerPoints::getOrCreateForMarketplace($clientId, $customerId)->id;

        return CustomerPoints::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /**
     * One movement in the ledger, with the account's totals kept in step. $points is signed.
     */
    protected function write(CustomerPoints $cp, string $type, int $points, string $action, array $opts = []): PointsTransaction
    {
        $c = $this->config((int) $cp->marketplace_client_id);
        $expiresAt = null;
        if (!empty($opts['expires']) && $c && (int) ($c->points_expire_days ?? 0) > 0) {
            $expiresAt = now()->addDays((int) $c->points_expire_days);
        }
        $balance = max(0, (int) $cp->current_balance + $points);

        $tx = PointsTransaction::create([
            'marketplace_client_id' => $cp->marketplace_client_id,
            'marketplace_customer_id' => $cp->marketplace_customer_id,
            'type' => $type,
            'points' => $points,
            'balance_after' => $balance,
            'action_type' => $action,
            'reference_type' => $opts['reference_type'] ?? null,
            'reference_id' => $opts['reference_id'] ?? null,
            'description' => $opts['description'] ?? ['ro' => '', 'en' => ''],
            'metadata' => $opts['metadata'] ?? null,
            'expires_at' => $expiresAt,
            'reversed_transaction_id' => $opts['reversed_transaction_id'] ?? null,
        ]);

        $cp->current_balance = $balance;
        if ($type === PointsTransaction::TYPE_EARNED) {
            $cp->total_earned = (int) $cp->total_earned + $points;
            $cp->tier_points = (int) $cp->tier_points + $points;
            $cp->last_earned_at = now();
            if ($expiresAt && (!$cp->points_expire_at || $expiresAt->lt($cp->points_expire_at))) {
                $cp->points_expire_at = $expiresAt;
            }
        } elseif ($type === PointsTransaction::TYPE_SPENT) {
            $cp->total_spent = (int) $cp->total_spent + abs($points);
            $cp->last_spent_at = now();
        } elseif ($type === PointsTransaction::TYPE_REFUNDED) {
            $cp->total_spent = max(0, (int) $cp->total_spent - abs($points));
        } elseif ($type === PointsTransaction::TYPE_EXPIRED) {
            $cp->total_expired = (int) $cp->total_expired + abs($points);
        } elseif ($type === PointsTransaction::TYPE_ADJUSTED && $points < 0) {
            $cp->total_earned = max(0, (int) $cp->total_earned + $points);
            $cp->tier_points = max(0, (int) $cp->tier_points + $points);
        }
        $cp->save();

        return $tx;
    }
}
