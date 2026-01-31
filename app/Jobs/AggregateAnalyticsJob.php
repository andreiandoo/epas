<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\Analytics\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AggregateAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?Carbon $date = null)
    {
        $this->date = $date ?? now()->subDay();
    }

    public function handle(AnalyticsService $service): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $service->aggregateMetrics($tenant->id, $this->date);
        }
    }
}
