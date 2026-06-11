<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePromoCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'promo:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-expire promo codes that have passed their expiration date';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expired = DB::table('promo_codes')
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->whereNull('deleted_at')
            ->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);

        if ($expired > 0) {
            $this->info("Expired {$expired} promo code(s)");
        } else {
            $this->info('No promo codes to expire');
        }

        return Command::SUCCESS;
    }
}
