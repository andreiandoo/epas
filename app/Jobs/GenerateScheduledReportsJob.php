<?php

namespace App\Jobs;

use App\Models\AnalyticsReport;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class GenerateScheduledReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AnalyticsService $service): void
    {
        $reports = AnalyticsReport::whereNotNull('schedule')
            ->where(function ($q) {
                $q->whereNull('last_generated_at')
                  ->orWhere('last_generated_at', '<', now()->subDay());
            })
            ->get();

        foreach ($reports as $report) {
            if ($this->shouldGenerate($report)) {
                $result = $service->generateReport($report);

                // Send report to user
                // Mail::to($report->user)->send(new ReportGenerated($report, $result));
            }
        }
    }

    protected function shouldGenerate(AnalyticsReport $report): bool
    {
        $schedule = $report->schedule;
        $frequency = $schedule['frequency'] ?? 'daily';

        return match ($frequency) {
            'daily' => true,
            'weekly' => now()->dayOfWeek === ($schedule['day'] ?? 1),
            'monthly' => now()->day === ($schedule['day'] ?? 1),
            default => false,
        };
    }
}
