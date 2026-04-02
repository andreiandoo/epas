<?php

namespace App\Filament\Vendor\Pages;

use App\Enums\VendorUserRole;
use App\Models\Cashless\CashlessSale;
use App\Models\VendorSaleItem;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Dashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.vendor.pages.dashboard';

    protected static ?string $title = 'Dashboard';

    public function getViewData(): array
    {
        $employee = Auth::guard('vendor_employee')->user();
        $vendorId = $employee->vendor_id;

        // For member role, show only their own sales
        $isMember = $employee->role === 'member' || $employee->role === VendorUserRole::Member->value;

        // Get active edition (most recent active one for this vendor)
        $edition = $employee->vendor->editions()
            ->whereHas('edition', fn ($q) => $q->where('status', 'active'))
            ->with('edition')
            ->first();

        $editionId = $edition?->festival_edition_id;

        if (! $editionId) {
            return [
                'hasEdition' => false,
                'employee'   => $employee,
            ];
        }

        // Today's stats
        $todayQuery = CashlessSale::where('vendor_id', $vendorId)
            ->where('festival_edition_id', $editionId)
            ->where('status', 'completed')
            ->whereDate('sold_at', today());

        if ($isMember) {
            $todayQuery->where('vendor_employee_id', $employee->id);
        }

        $todayStats = $todayQuery->selectRaw('
            COUNT(*) as sales_count,
            COALESCE(SUM(total_cents), 0) as revenue_cents,
            COALESCE(SUM(tip_cents), 0) as tips_cents,
            COALESCE(SUM(items_count), 0) as items_sold
        ')->first();

        // Edition totals (manager/supervisor only)
        $editionStats = null;
        if (! $isMember) {
            $editionStats = CashlessSale::where('vendor_id', $vendorId)
                ->where('festival_edition_id', $editionId)
                ->where('status', 'completed')
                ->selectRaw('
                    COUNT(*) as sales_count,
                    COALESCE(SUM(total_cents), 0) as revenue_cents,
                    COALESCE(SUM(commission_cents), 0) as commission_cents,
                    COALESCE(SUM(tip_cents), 0) as tips_cents
                ')->first();
        }

        // Top products today
        $topProducts = VendorSaleItem::where('vendor_id', $vendorId)
            ->where('festival_edition_id', $editionId)
            ->whereDate('created_at', today())
            ->when($isMember, fn ($q) => $q->where('vendor_employee_id', $employee->id))
            ->selectRaw('product_name, SUM(quantity) as total_qty, SUM(total_cents) as total_cents')
            ->groupBy('product_name')
            ->orderByDesc('total_cents')
            ->limit(10)
            ->get();

        return [
            'hasEdition'    => true,
            'employee'      => $employee,
            'edition'       => $edition->edition,
            'todayStats'    => $todayStats,
            'editionStats'  => $editionStats,
            'topProducts'   => $topProducts,
            'isMember'      => $isMember,
        ];
    }
}
