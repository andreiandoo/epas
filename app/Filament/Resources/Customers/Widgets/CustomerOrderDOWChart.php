<?php

namespace App\Filament\Resources\Customers\Widgets;
use App\Models\Customer;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;


class CustomerOrderDOWChart extends ChartWidget
{
    protected ?string $heading = 'Cumpărături pe zile (L-D)';
    public ?Customer $record = null;
    protected function getType(): string { return 'bar'; }

    protected function getData(): array
    {
        $rows = \App\Models\Order::query()
            ->selectRaw('EXTRACT(DOW FROM created_at) as d, COUNT(*) as c')
            ->where('customer_id', $this->record->id)
            ->groupBy(DB::raw('EXTRACT(DOW FROM created_at)'))
            ->pluck('c','d')->all();

        $labels = ['D','L','Ma','Mi','J','V','S']; // Postgres: 0=Sunday
        $mapIdx = [1,2,3,4,5,6,0];
        $data = array_map(fn($i) => (int)($rows[$i] ?? 0), $mapIdx);

        return [
            'datasets' => [[ 'label' => 'Orders', 'data' => $data ]],
            'labels'   => $labels,
        ];
    }
}