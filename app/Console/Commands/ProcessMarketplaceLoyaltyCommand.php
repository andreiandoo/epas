<?php

namespace App\Console\Commands;

use App\Services\Gamification\MarketplaceLoyaltyService;
use Illuminate\Console\Command;

/**
 * Marketplace loyalty upkeep, hourly: credit the points of orders whose activity has taken place (and the referral
 * bonuses that come with a friend's first order), the birthday bonuses, and expiry. Every step is idempotent, so a
 * rerun or an overlap credits nothing twice. Does nothing for marketplaces that haven't switched automatic rewards on.
 */
class ProcessMarketplaceLoyaltyCommand extends Command
{
    protected $signature = 'loyalty:process
                            {--confirm : Only credit pending purchase points}
                            {--birthdays : Only award birthday bonuses}
                            {--expire : Only expire points}';

    protected $description = 'Marketplace loyalty points: credit pending points, birthday bonuses, expiry';

    public function handle(MarketplaceLoyaltyService $loyalty): int
    {
        $only = array_filter(['confirm' => $this->option('confirm'), 'birthdays' => $this->option('birthdays'), 'expire' => $this->option('expire')]);
        $all = !$only;

        if ($all || isset($only['confirm'])) {
            $this->line('Credited pending purchase points on ' . $loyalty->confirmDue() . ' orders');
        }
        if ($all || isset($only['birthdays'])) {
            $this->line('Birthday bonuses awarded: ' . $loyalty->awardBirthdays());
        }
        if ($all || isset($only['expire'])) {
            $this->line('Points expired: ' . $loyalty->expirePoints());
        }

        return self::SUCCESS;
    }
}
