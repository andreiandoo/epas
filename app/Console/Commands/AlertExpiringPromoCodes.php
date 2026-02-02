<?php

namespace App\Console\Commands;

use App\Services\Alerts\AlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AlertExpiringPromoCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'promo:alert-expiring
                            {--days=7 : Alert for codes expiring within X days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send alerts for promo codes that are about to expire or be depleted';

    public function __construct(
        protected AlertService $alertService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $threshold = now()->addDays($days);

        // Find codes expiring soon
        $expiringCodes = DB::table('promo_codes')
            ->where('status', 'active')
            ->whereBetween('expires_at', [now(), $threshold])
            ->whereNull('deleted_at')
            ->get();

        foreach ($expiringCodes as $code) {
            $daysRemaining = now()->diffInDays($code->expires_at);

            $this->alertService->send([
                'type' => 'promo_code_expiring',
                'severity' => 'low',
                'title' => 'Promo Code Expiring Soon',
                'message' => "Promo code '{$code->code}' will expire in {$daysRemaining} day(s).",
                'metadata' => [
                    'promo_code_id' => $code->id,
                    'code' => $code->code,
                    'expires_at' => $code->expires_at,
                    'days_remaining' => $daysRemaining,
                ],
                'channels' => ['email'],
            ]);
        }

        // Find codes nearly depleted (>90% usage)
        $depletingCodes = DB::table('promo_codes')
            ->where('status', 'active')
            ->whereNotNull('usage_limit')
            ->whereRaw('usage_count >= usage_limit * 0.9')
            ->whereNull('deleted_at')
            ->get();

        foreach ($depletingCodes as $code) {
            $remaining = $code->usage_limit - $code->usage_count;
            $percentUsed = round(($code->usage_count / $code->usage_limit) * 100);

            $this->alertService->send([
                'type' => 'promo_code_nearly_depleted',
                'severity' => 'medium',
                'title' => 'Promo Code Nearly Depleted',
                'message' => "Promo code '{$code->code}' is {$percentUsed}% used ({$remaining} uses remaining).",
                'metadata' => [
                    'promo_code_id' => $code->id,
                    'code' => $code->code,
                    'usage_count' => $code->usage_count,
                    'usage_limit' => $code->usage_limit,
                    'remaining' => $remaining,
                    'percent_used' => $percentUsed,
                ],
                'channels' => ['email'],
            ]);
        }

        $totalAlerts = count($expiringCodes) + count($depletingCodes);

        if ($totalAlerts > 0) {
            $this->info("Sent {$totalAlerts} alert(s)");
        } else {
            $this->info('No alerts to send');
        }

        return Command::SUCCESS;
    }
}
