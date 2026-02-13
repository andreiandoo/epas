<?php

namespace App\Filament\Resources\Costs\Pages;

use App\Filament\Resources\Costs\PlatformCostResource;
use App\Models\PlatformCost;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;
use Carbon\Carbon;

class ListPlatformCosts extends ListRecords
{
    protected static string $resource = PlatformCostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cost_summary')
                ->label(fn () => $this->buildCostSummaryLabel())
                ->color('gray')
                ->disabled()
                ->extraAttributes(['style' => 'cursor: default; opacity: 1;']),

            Actions\CreateAction::make(),
        ];
    }

    protected function buildCostSummaryLabel(): HtmlString
    {
        $costs = PlatformCost::active()->get();

        $monthlyTotal = 0;
        $yearlyTotal = 0;
        $oneTimeTotal = 0;
        $totalToDate = 0;

        $now = Carbon::now();

        foreach ($costs as $cost) {
            $amount = (float) $cost->amount;
            $start = $cost->start_date ? Carbon::parse($cost->start_date) : $cost->created_at;
            $end = $cost->end_date ? Carbon::parse($cost->end_date)->min($now) : $now;

            if ($start->gt($end)) {
                continue;
            }

            match ($cost->billing_cycle) {
                'monthly' => (function () use ($amount, $start, $end, &$monthlyTotal, &$totalToDate) {
                    $monthlyTotal += $amount;
                    // Months elapsed (partial months count as full)
                    $months = $start->diffInMonths($end) + 1;
                    $totalToDate += $amount * $months;
                })(),
                'yearly' => (function () use ($amount, $start, $end, &$yearlyTotal, &$totalToDate) {
                    $yearlyTotal += $amount;
                    // Years elapsed (partial years count as full)
                    $years = $start->diffInYears($end) + 1;
                    $totalToDate += $amount * $years;
                })(),
                'one_time' => (function () use ($amount, &$oneTimeTotal, &$totalToDate) {
                    $oneTimeTotal += $amount;
                    $totalToDate += $amount;
                })(),
                default => null,
            };
        }

        $monthlyEquivalent = $monthlyTotal + ($yearlyTotal / 12);

        $fmt = fn ($v) => number_format($v, 2, '.', ',');

        return new HtmlString("
            <div style='display: flex; align-items: center; gap: 16px; font-size: 12px; line-height: 1.4;'>
                <div style='text-align: center;'>
                    <div style='font-weight: 700; font-size: 15px; color: #f59e0b;'>{$fmt($monthlyTotal)} €</div>
                    <div style='color: #94a3b8;'>monthly</div>
                </div>
                <div style='color: #334155;'>|</div>
                <div style='text-align: center;'>
                    <div style='font-weight: 700; font-size: 15px; color: #3b82f6;'>{$fmt($yearlyTotal)} €</div>
                    <div style='color: #94a3b8;'>yearly</div>
                </div>
                <div style='color: #334155;'>|</div>
                <div style='text-align: center;'>
                    <div style='font-weight: 700; font-size: 15px; color: #64748b;'>{$fmt($oneTimeTotal)} €</div>
                    <div style='color: #94a3b8;'>one-time</div>
                </div>
                <div style='color: #334155;'>|</div>
                <div style='text-align: center;'>
                    <div style='font-weight: 700; font-size: 15px; color: #10b981;'>{$fmt($monthlyEquivalent)} €/mo</div>
                    <div style='color: #94a3b8;'>equiv.</div>
                </div>
                <div style='color: #334155;'>|</div>
                <div style='text-align: center;'>
                    <div style='font-weight: 700; font-size: 15px; color: #ef4444;'>{$fmt($totalToDate)} €</div>
                    <div style='color: #94a3b8;'>total to date</div>
                </div>
            </div>
        ");
    }
}
