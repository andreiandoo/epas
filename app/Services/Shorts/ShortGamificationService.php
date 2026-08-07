<?php

namespace App\Services\Shorts;

use App\Models\MarketplaceCustomer;
use App\Models\ShortStreak;
use App\Services\Identity\IdentityBridge;
use Illuminate\Support\Facades\DB;

/**
 * Points and streaks for shorts activity (D11).
 *
 * The existing Gamification ledger hangs off `Customer`, but the mobile app's
 * buyer is a `MarketplaceCustomer` and the bridge between them is still open
 * (see IdentityBridge). So state is kept marketplace-side in `short_streaks`
 * today, and forwarded to the real ledger the moment the bridge exists — the
 * call sites do not change.
 */
class ShortGamificationService
{
    public const SOURCE_WATCH = 'short_watch';

    public const SOURCE_SHARE = 'short_share';

    public const SOURCE_CREATE = 'short_create';

    public function __construct(private readonly IdentityBridge $identity) {}

    /**
     * First watch of the day: extends the streak and pays out.
     *
     * @return array{points: int, streak: int, awarded: bool}
     */
    public function recordDailyWatch(MarketplaceCustomer $customer): array
    {
        $streak = $this->streakFor($customer);
        $today = now()->toDateString();

        if ($streak->last_watch_date?->toDateString() === $today) {
            // Already counted today — the streak is per day, not per short.
            return ['points' => 0, 'streak' => $streak->current_streak, 'awarded' => false];
        }

        $continued = $streak->last_watch_date?->toDateString() === now()->subDay()->toDateString();
        $current = $continued ? $streak->current_streak + 1 : 1;

        // A longer streak is worth more, but capped so week-100 is not absurd.
        $points = (int) config('shorts.gamification.watch_points', 5)
            + min($current, (int) config('shorts.gamification.streak_bonus_cap', 10));

        $granted = $this->grant($customer, $streak, $points, self::SOURCE_WATCH);

        $streak->forceFill([
            'current_streak' => $current,
            'longest_streak' => max($current, $streak->longest_streak),
            'last_watch_date' => $today,
        ])->save();

        return ['points' => $granted, 'streak' => $current, 'awarded' => $granted > 0];
    }

    /**
     * @return array{points: int, awarded: bool}
     */
    public function recordShare(MarketplaceCustomer $customer): array
    {
        $streak = $this->streakFor($customer);
        $points = (int) config('shorts.gamification.share_points', 10);
        $granted = $this->grant($customer, $streak, $points, self::SOURCE_SHARE);

        return ['points' => $granted, 'awarded' => $granted > 0];
    }

    /**
     * @return array{points: int, awarded: bool}
     */
    public function recordApprovedUgc(MarketplaceCustomer $customer): array
    {
        $streak = $this->streakFor($customer);
        $points = (int) config('shorts.gamification.ugc_points', 50);
        $granted = $this->grant($customer, $streak, $points, self::SOURCE_CREATE);

        return ['points' => $granted, 'awarded' => $granted > 0];
    }

    public function streakFor(MarketplaceCustomer $customer): ShortStreak
    {
        return ShortStreak::query()->firstOrCreate(
            ['marketplace_customer_id' => $customer->id],
            [],
        );
    }

    /**
     * Apply the daily cap, write the marketplace-side ledger, and forward to the
     * real points ledger when the identity bridge is available.
     *
     * @return int Points actually granted (0 when the daily cap is exhausted).
     */
    protected function grant(MarketplaceCustomer $customer, ShortStreak $streak, int $points, string $source): int
    {
        $cap = (int) config('shorts.gamification.daily_cap', 100);
        $today = now()->toDateString();

        $spentToday = $streak->points_today_date?->toDateString() === $today ? $streak->points_today : 0;
        $allowed = max(0, $cap - $spentToday);
        $granted = min($points, $allowed);

        if ($granted === 0) {
            return 0;
        }

        $streak->forceFill([
            'points_today' => $spentToday + $granted,
            'points_today_date' => $today,
            'total_points' => $streak->total_points + $granted,
        ])->save();

        $this->forwardToLoyaltyLedger($customer, $granted, $source);

        return $granted;
    }

    /**
     * TODO(owner): needs the MarketplaceCustomer ↔ Customer bridge
     * (docs/plans/friends-social.md §0). Until the link column exists this is a
     * no-op and the points live only in short_streaks.
     */
    protected function forwardToLoyaltyLedger(MarketplaceCustomer $customer, int $points, string $source): void
    {
        if (! $this->identity->isAvailable()) {
            return;
        }

        $target = $this->identity->resolve($customer);

        if (! $target) {
            return;
        }

        DB::table('points_transactions')->insert([
            'customer_id' => $target->id,
            'points' => $points,
            'type' => 'earned',
            'source' => $source,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
