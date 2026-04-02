<?php

namespace App\Jobs;

use App\Enums\SaleStatus;
use App\Models\Cashless\CashlessAccount;
use App\Models\Cashless\CashlessReportSnapshot;
use App\Models\Cashless\CashlessSale;
use App\Models\FestivalEdition;
use App\Models\WristbandTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AggregateCashlessReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private ?int $editionId = null,
    ) {}

    public function handle(): void
    {
        $editions = $this->editionId
            ? FestivalEdition::where('id', $this->editionId)->get()
            : FestivalEdition::where('status', 'active')->get();

        foreach ($editions as $edition) {
            $this->aggregateForEdition($edition);
        }
    }

    private function aggregateForEdition(FestivalEdition $edition): void
    {
        $now = now();
        $periodStart = $now->copy()->subMinutes(5);

        // Hourly sales snapshot
        $hourlySales = CashlessSale::where('festival_edition_id', $edition->id)
            ->where('status', SaleStatus::Completed)
            ->whereDate('sold_at', today())
            ->selectRaw('
                COUNT(*) as sales_count,
                COALESCE(SUM(total_cents), 0) as revenue_cents,
                COALESCE(SUM(commission_cents), 0) as commission_cents,
                COALESCE(SUM(tip_cents), 0) as tips_cents
            ')
            ->first();

        CashlessReportSnapshot::updateOrCreate(
            [
                'festival_edition_id' => $edition->id,
                'report_type'         => 'daily_sales',
                'period_start'        => today()->startOfDay(),
                'period_end'          => today()->endOfDay(),
            ],
            [
                'tenant_id'  => $edition->tenant_id,
                'dimensions' => ['date' => today()->toDateString()],
                'metrics'    => [
                    'sales_count'      => (int) $hourlySales->sales_count,
                    'revenue_cents'    => (int) $hourlySales->revenue_cents,
                    'commission_cents' => (int) $hourlySales->commission_cents,
                    'tips_cents'       => (int) $hourlySales->tips_cents,
                ],
            ]
        );

        // Top-up totals snapshot
        $topups = WristbandTransaction::where('festival_edition_id', $edition->id)
            ->where('transaction_type', 'topup')
            ->whereDate('created_at', today())
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount_cents), 0) as total_cents')
            ->first();

        CashlessReportSnapshot::updateOrCreate(
            [
                'festival_edition_id' => $edition->id,
                'report_type'         => 'daily_topups',
                'period_start'        => today()->startOfDay(),
                'period_end'          => today()->endOfDay(),
            ],
            [
                'tenant_id'  => $edition->tenant_id,
                'dimensions' => ['date' => today()->toDateString()],
                'metrics'    => [
                    'count'       => (int) $topups->count,
                    'total_cents' => (int) $topups->total_cents,
                ],
            ]
        );

        // Active accounts snapshot
        $accounts = CashlessAccount::where('festival_edition_id', $edition->id)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN balance_cents > 0 THEN 1 ELSE 0 END) as with_balance,
                COALESCE(SUM(balance_cents), 0) as total_balance_cents
            ')
            ->first();

        CashlessReportSnapshot::updateOrCreate(
            [
                'festival_edition_id' => $edition->id,
                'report_type'         => 'accounts_snapshot',
                'period_start'        => $periodStart,
                'period_end'          => $now,
            ],
            [
                'tenant_id'  => $edition->tenant_id,
                'dimensions' => ['snapshot_at' => $now->toIso8601String()],
                'metrics'    => [
                    'total_accounts'      => (int) $accounts->total,
                    'with_balance'        => (int) $accounts->with_balance,
                    'total_balance_cents' => (int) $accounts->total_balance_cents,
                ],
            ]
        );
    }
}
