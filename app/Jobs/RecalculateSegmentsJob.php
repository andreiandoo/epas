<?php

namespace App\Jobs;

use App\Models\CustomerSegment;
use App\Services\CRM\CRMService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateSegmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CRMService $service): void
    {
        $segments = CustomerSegment::where('is_dynamic', true)
            ->where(function ($q) {
                $q->whereNull('last_calculated_at')
                  ->orWhere('last_calculated_at', '<', now()->subHours(
                      config('crm.segments.recalculate_interval_hours', 24)
                  ));
            })
            ->get();

        foreach ($segments as $segment) {
            $service->calculateSegmentMembers($segment);
        }
    }
}
