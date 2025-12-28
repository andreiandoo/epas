<?php
namespace App\Filament\Resources\Customers\Widgets;

use App\Models\Order;
use App\Models\Customer;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class CustomerPurchaseHourChart extends ChartWidget
{
    protected ?string $heading = 'Ore preferate de cumpărare';
    public ?Customer $record = null;

    protected function getType(): string { return 'bar'; }

    protected function getData(): array
    {
        $customerId = $this->record->id;

        $rows = Order::query()
            ->selectRaw('EXTRACT(HOUR FROM created_at) as h, COUNT(*) as c')
            ->where('customer_id', $customerId)
            ->groupBy(DB::raw('EXTRACT(HOUR FROM created_at)'))
            ->orderBy(DB::raw('EXTRACT(HOUR FROM created_at)'))
            ->pluck('c','h')
            ->all();

        $labels = range(0,23);
        $data = array_map(fn($h) => (int)($rows[$h] ?? 0), $labels);

        return [
            'datasets' => [[ 'label' => 'Orders', 'data' => $data ]],
            'labels'   => array_map(fn($h) => sprintf('%02d:00', $h), $labels),
        ];
    }
}
