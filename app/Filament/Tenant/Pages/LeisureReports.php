<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Order;
use App\Services\Leisure\ChannelPricingResolver;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class LeisureReports extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static \UnitEnum|string|null $navigationGroup = 'Leisure';
    protected static ?int $navigationSort = 60;
    protected static ?string $title = 'Rapoarte';
    protected static ?string $slug = 'leisure/reports';
    protected string $view = 'filament.tenant.leisure-reports';

    public ?string $from = null;
    public ?string $to = null;

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;
        $type = $tenant?->tenant_type instanceof \App\Enums\TenantType
            ? $tenant->tenant_type->value : (string) $tenant?->tenant_type;
        return $type === 'leisure';
    }

    public function mount(): void
    {
        $this->from = $this->from ?? now()->subDays(30)->toDateString();
        $this->to = $this->to ?? now()->toDateString();
    }

    public function getViewData(): array
    {
        $tenantId = auth()->user()?->tenant?->id;
        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->to)->endOfDay();

        // Time series — orders per day
        $orders = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as orders_count, SUM(total_cents) as total_cents')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $perChannel = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->selectRaw('channel, COUNT(*) as orders_count, SUM(total_cents) as total_cents')
            ->groupBy('channel')
            ->get();

        // Totals
        $totalRevenue = $orders->sum('total_cents');
        $totalOrders = $orders->sum('orders_count');

        return [
            'from' => $this->from,
            'to' => $this->to,
            'orders' => $orders,
            'perChannel' => $perChannel,
            'channels' => ChannelPricingResolver::CHANNELS,
            'totalRevenue' => $totalRevenue,
            'totalOrders' => $totalOrders,
        ];
    }
}
