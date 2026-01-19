<?php

namespace App\Services\Analytics;

use App\Models\Event;
use App\Models\EventGoal;
use App\Models\EventReportSchedule;
use App\Mail\EventAnalyticsReport;
use App\Mail\GoalAlertNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ScheduledReportService
{
    protected EventExportService $exportService;
    protected EventAnalyticsService $analyticsService;

    public function __construct(
        EventExportService $exportService,
        EventAnalyticsService $analyticsService
    ) {
        $this->exportService = $exportService;
        $this->analyticsService = $analyticsService;
    }

    /**
     * Process all due report schedules
     */
    public function processDueReports(): array
    {
        $sent = 0;
        $failed = 0;

        $dueSchedules = EventReportSchedule::due()
            ->with('event')
            ->get();

        foreach ($dueSchedules as $schedule) {
            try {
                $this->sendScheduledReport($schedule);
                $schedule->markSent();
                $sent++;
            } catch (\Exception $e) {
                Log::error('Failed to send scheduled report', [
                    'schedule_id' => $schedule->id,
                    'event_id' => $schedule->event_id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Send a scheduled report
     */
    public function sendScheduledReport(EventReportSchedule $schedule): void
    {
        $event = $schedule->event;
        if (!$event) {
            throw new \Exception('Event not found for schedule');
        }

        $period = $schedule->getReportPeriod();
        $comparison = $schedule->include_comparison ? $schedule->getComparisonPeriod() : null;

        // Generate report data
        $reportData = $this->exportService->generateReportData(
            $event,
            $period,
            $schedule->sections ?? EventReportSchedule::DEFAULT_SECTIONS
        );

        // Add comparison data if enabled
        if ($comparison) {
            $reportData['comparison'] = $this->analyticsService->getPeriodComparison(
                $event,
                $this->getPeriodString($period)
            );
        }

        // Add schedule info
        $reportData['schedule'] = [
            'frequency' => $schedule->frequency_label,
            'period_start' => $period['start']->format('M d, Y'),
            'period_end' => $period['end']->format('M d, Y'),
        ];

        // Prepare attachments if format is PDF or CSV
        $attachments = [];
        if ($schedule->format === EventReportSchedule::FORMAT_PDF) {
            $attachments[] = $this->exportService->exportToPdf($event, [
                'period' => $this->getPeriodString($period),
                'sections' => $schedule->sections,
                'include_comparison' => $schedule->include_comparison,
            ]);
        } elseif ($schedule->format === EventReportSchedule::FORMAT_CSV) {
            $attachments[] = $this->exportService->exportToCsv($event, [
                'period' => $this->getPeriodString($period),
                'sections' => ['daily', 'traffic', 'milestones', 'sales'],
            ]);
        }

        // Send to all recipients
        foreach ($schedule->recipients as $email) {
            Mail::to($email)->send(new EventAnalyticsReport(
                $event,
                $reportData,
                $attachments
            ));
        }

        Log::info('Scheduled report sent', [
            'schedule_id' => $schedule->id,
            'event_id' => $event->id,
            'recipients' => count($schedule->recipients),
        ]);
    }

    /**
     * Process goal alerts
     */
    public function processGoalAlerts(): array
    {
        $sent = 0;
        $failed = 0;

        $goals = EventGoal::needingAlertCheck()
            ->with('event')
            ->get();

        foreach ($goals as $goal) {
            try {
                // Update progress first
                $goal->updateProgress();

                // Check for pending alerts
                $pendingAlerts = $goal->getPendingAlerts();

                foreach ($pendingAlerts as $threshold) {
                    $this->sendGoalAlert($goal, $threshold);
                    $goal->markAlertSent($threshold);
                    $sent++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to process goal alert', [
                    'goal_id' => $goal->id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Send goal alert notification
     */
    public function sendGoalAlert(EventGoal $goal, int $threshold): void
    {
        $event = $goal->event;
        if (!$event) {
            return;
        }

        // Determine recipient
        $recipient = $goal->alert_email
            ?? $event->marketplaceOrganizer?->email
            ?? $event->marketplaceOrganizer?->user?->email;

        if (!$recipient) {
            Log::warning('No recipient for goal alert', ['goal_id' => $goal->id]);
            return;
        }

        // Prepare alert data
        $alertData = [
            'event_name' => $event->title_translated ?? $event->title,
            'goal_type' => $goal->type_label,
            'goal_name' => $goal->name,
            'threshold' => $threshold,
            'current_progress' => $goal->progress_percent,
            'current_value' => $goal->formatted_current,
            'target_value' => $goal->formatted_target,
            'remaining' => $goal->formatted_target, // We'd need to calculate this properly
            'is_achieved' => $threshold >= 100,
            'days_remaining' => $goal->days_remaining,
        ];

        if ($goal->email_alerts) {
            Mail::to($recipient)->send(new GoalAlertNotification($goal, $alertData));
        }

        // In-app notification could be added here via a notification service
        if ($goal->in_app_alerts) {
            // $this->notificationService->createGoalAlert($goal, $alertData);
        }

        Log::info('Goal alert sent', [
            'goal_id' => $goal->id,
            'threshold' => $threshold,
            'recipient' => $recipient,
        ]);
    }

    /**
     * Create default report schedule for an event
     */
    public function createDefaultSchedule(Event $event, string $email): EventReportSchedule
    {
        return EventReportSchedule::create([
            'event_id' => $event->id,
            'marketplace_organizer_id' => $event->marketplace_organizer_id,
            'frequency' => EventReportSchedule::FREQ_WEEKLY,
            'day_of_week' => 1, // Monday
            'send_at' => '09:00:00',
            'timezone' => 'Europe/Bucharest',
            'recipients' => [$email],
            'sections' => EventReportSchedule::DEFAULT_SECTIONS,
            'format' => EventReportSchedule::FORMAT_EMAIL,
            'include_comparison' => true,
            'is_active' => true,
            'next_send_at' => now()->next('Monday')->setTime(9, 0),
        ]);
    }

    /**
     * Create default goals for an event
     */
    public function createDefaultGoals(Event $event, array $targets): array
    {
        $goals = [];

        if (isset($targets['revenue'])) {
            $goals[] = EventGoal::create([
                'event_id' => $event->id,
                'type' => EventGoal::TYPE_REVENUE,
                'name' => 'Revenue Target',
                'target_value' => (int) ($targets['revenue'] * 100), // Convert to cents
                'alert_thresholds' => EventGoal::DEFAULT_THRESHOLDS,
                'email_alerts' => true,
                'in_app_alerts' => true,
            ]);
        }

        if (isset($targets['tickets'])) {
            $goals[] = EventGoal::create([
                'event_id' => $event->id,
                'type' => EventGoal::TYPE_TICKETS,
                'name' => 'Tickets Target',
                'target_value' => $targets['tickets'],
                'alert_thresholds' => EventGoal::DEFAULT_THRESHOLDS,
                'email_alerts' => true,
                'in_app_alerts' => true,
            ]);
        }

        if (isset($targets['visitors'])) {
            $goals[] = EventGoal::create([
                'event_id' => $event->id,
                'type' => EventGoal::TYPE_VISITORS,
                'name' => 'Visitors Target',
                'target_value' => $targets['visitors'],
                'alert_thresholds' => [50, 100],
                'email_alerts' => false,
                'in_app_alerts' => true,
            ]);
        }

        return $goals;
    }

    /**
     * Update all goals for an event
     */
    public function updateEventGoals(Event $event): void
    {
        EventGoal::where('event_id', $event->id)
            ->active()
            ->get()
            ->each(fn ($goal) => $goal->updateProgress());
    }

    /**
     * Convert date range to period string
     */
    protected function getPeriodString(array $period): string
    {
        $days = $period['start']->diffInDays($period['end']);

        return match (true) {
            $days <= 7 => '7d',
            $days <= 30 => '30d',
            $days <= 90 => '90d',
            default => 'all',
        };
    }
}
