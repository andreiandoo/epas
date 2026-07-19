<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\InstallmentAgreement;
use App\Models\InstallmentPayment;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * Dedicated flexible-payments analytics (§18bis.B): revenue by method,
 * receivables, collection/default rates, and upcoming debits.
 */
class FlexiblePaymentAnalytics extends Page
{
    use HasMarketplaceContext;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Analytics plăți flexibile';
    protected static \UnitEnum|string|null $navigationGroup = 'Plăți flexibile';
    protected static ?int $navigationSort = 30;
    protected string $view = 'filament.marketplace.pages.flexible-payment-analytics';

    public array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('flexible-payments');
    }

    public function mount(): void
    {
        $clientId = static::getMarketplaceClient()?->id;
        $base = InstallmentAgreement::query()->where('marketplace_client_id', $clientId);

        $byStatus = (clone $base)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->all();
        $total = array_sum($byStatus);

        $installmentsGmv = (int) (clone $base)->where('plan_type', 'installments')->sum('customer_total_cents');
        $bnplGmv = (int) (clone $base)->where('plan_type', 'bnpl_single')->sum('customer_total_cents');

        // Receivables across active agreements.
        $outstanding = 0;
        (clone $base)->where('status', 'active')->select(['id', 'customer_total_cents'])
            ->chunkById(500, function ($rows) use (&$outstanding) {
                foreach ($rows as $a) {
                    $outstanding += $a->outstandingCents();
                }
            });

        // Upcoming debits (scheduled installments) in the next 7 / 30 days.
        $upcoming = fn (int $days) => InstallmentPayment::query()
            ->whereIn('status', ['scheduled', 'due', 'retrying'])
            ->where('sequence', '>', 0)
            ->whereBetween('due_date', [now(), now()->addDays($days)])
            ->whereHas('agreement', fn ($q) => $q->where('marketplace_client_id', $clientId)->where('status', 'active'));

        $next7 = (clone $upcoming)(7);
        $next30 = (clone $upcoming)(30);

        $collected = (int) InstallmentPayment::query()
            ->where('status', 'paid')
            ->whereHas('agreement', fn ($q) => $q->where('marketplace_client_id', $clientId))
            ->sum('paid_amount_cents');

        $this->data = [
            'total' => $total,
            'by_status' => $byStatus,
            'installments_gmv' => $installmentsGmv / 100,
            'bnpl_gmv' => $bnplGmv / 100,
            'outstanding' => $outstanding / 100,
            'collected' => $collected / 100,
            'completion_rate' => $total > 0 ? round(($byStatus['completed'] ?? 0) / $total * 100, 1) : 0,
            'default_rate' => $total > 0 ? round(($byStatus['defaulted'] ?? 0) / $total * 100, 1) : 0,
            'next7_count' => (clone $next7)->count(),
            'next7_sum' => (int) (clone $next7)->sum('amount_cents') / 100,
            'next30_count' => (clone $next30)->count(),
            'next30_sum' => (int) (clone $next30)->sum('amount_cents') / 100,
        ];
    }
}
