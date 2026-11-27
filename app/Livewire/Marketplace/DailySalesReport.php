<?php

namespace App\Livewire\Marketplace;

use App\Services\Marketplace\DailySalesReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "Raport vânzări pe zi" on the marketplace dashboard (super-admin only).
 * Lazy: the page renders first and the table arrives right after; picking
 * another day re-renders only this component.
 */
#[Lazy]
class DailySalesReport extends Component
{
    // Same query-string name as before the split, so old links keep the day.
    #[Url(as: 'selectedDailyReportDate')]
    public string $date = '';

    public function placeholder(): string
    {
        return <<<'HTML'
        <section class="ep-card">
            <div class="ep-card-h"><h2>Vânzări pe zi, pe evenimente</h2></div>
            <div class="ep-hint animate-pulse" style="text-align: center; padding: 28px;">Se încarcă raportul…</div>
        </section>
        HTML;
    }

    public function render()
    {
        // The marketplace comes from the logged-in admin, never from the client.
        $admin = Auth::guard('marketplace_admin')->user();
        $marketplace = $admin?->marketplaceClient;
        if (!$marketplace || !$admin->isSuperAdmin()) {
            return <<<'HTML'
            <div></div>
            HTML;
        }

        $tz = 'Europe/Bucharest';
        $maxDate = Carbon::now($tz)->format('Y-m-d');
        $minDate = Carbon::now($tz)->subDays(30)->format('Y-m-d');
        $date = $this->date ?: $maxDate;
        if ($date < $minDate) {
            $date = $minDate;
        }
        if ($date > $maxDate) {
            $date = $maxDate;
        }
        $this->date = $date;

        return view('livewire.marketplace.daily-sales-report', [
            'dailyEventReport' => app(DailySalesReportService::class)->rowsCached($marketplace->id, $date),
            'dailyReportDate' => $date,
            'dailyReportMinDate' => $minDate,
            'dailyReportMaxDate' => $maxDate,
            // VAT on that day's (net) commission; 0 before the marketplace became a VAT payer.
            'dailyReportVatRate' => $marketplace->vatRate(Carbon::createFromFormat('Y-m-d', $date, $tz)->endOfDay()),
        ]);
    }
}
