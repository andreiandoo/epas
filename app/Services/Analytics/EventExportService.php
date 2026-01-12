<?php

namespace App\Services\Analytics;

use App\Models\Event;
use App\Models\EventGoal;
use App\Models\EventMilestone;
use App\Models\EventAnalyticsDaily;
use App\Models\Order;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class EventExportService
{
    protected EventAnalyticsService $analyticsService;

    public function __construct(EventAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Export analytics data to CSV
     */
    public function exportToCsv(Event $event, array $options = []): string
    {
        $period = $options['period'] ?? '30d';
        $sections = $options['sections'] ?? ['daily', 'traffic', 'milestones', 'sales'];

        $dateRange = $this->getDateRange($period);
        $filename = $this->generateFilename($event, 'csv');
        $filepath = "exports/{$filename}";

        $csvContent = [];

        // Header info
        $csvContent[] = ['Event Analytics Report'];
        $csvContent[] = ['Event', $event->title_translated ?? $event->title];
        $csvContent[] = ['Period', $dateRange['start']->format('Y-m-d') . ' to ' . $dateRange['end']->format('Y-m-d')];
        $csvContent[] = ['Generated', now()->format('Y-m-d H:i:s')];
        $csvContent[] = [];

        // Daily analytics
        if (in_array('daily', $sections)) {
            $csvContent[] = ['DAILY ANALYTICS'];
            $csvContent[] = ['Date', 'Page Views', 'Unique Visitors', 'Tickets Sold', 'Revenue', 'Conversion Rate'];

            $dailyData = EventAnalyticsDaily::where('event_id', $event->id)
                ->whereBetween('date', [$dateRange['start'], $dateRange['end']])
                ->orderBy('date')
                ->get();

            foreach ($dailyData as $day) {
                $csvContent[] = [
                    $day->date->format('Y-m-d'),
                    $day->page_views ?? 0,
                    $day->unique_visitors ?? 0,
                    $day->tickets_sold ?? 0,
                    number_format(($day->revenue ?? 0), 2),
                    number_format(($day->conversion_rate ?? 0), 2) . '%',
                ];
            }
            $csvContent[] = [];
        }

        // Traffic sources
        if (in_array('traffic', $sections)) {
            $csvContent[] = ['TRAFFIC SOURCES'];
            $csvContent[] = ['Source', 'Visitors', 'Percentage', 'Conversions', 'Revenue'];

            $trafficSources = $this->analyticsService->getTrafficSources($event, $dateRange);
            foreach ($trafficSources as $source) {
                $csvContent[] = [
                    $source['name'],
                    $source['visitors'],
                    $source['percent'] . '%',
                    $source['conversions'],
                    number_format($source['revenue'], 2),
                ];
            }
            $csvContent[] = [];
        }

        // Milestones
        if (in_array('milestones', $sections)) {
            $csvContent[] = ['MILESTONES / CAMPAIGNS'];
            $csvContent[] = ['Type', 'Title', 'Start Date', 'End Date', 'Budget', 'Attributed Revenue', 'ROI', 'CAC'];

            $milestones = EventMilestone::where('event_id', $event->id)->orderBy('start_date', 'desc')->get();
            foreach ($milestones as $milestone) {
                $csvContent[] = [
                    $milestone->getTypeLabel(),
                    $milestone->title,
                    $milestone->start_date->format('Y-m-d'),
                    $milestone->end_date?->format('Y-m-d') ?? '-',
                    number_format($milestone->budget ?? 0, 2),
                    number_format($milestone->attributed_revenue ?? 0, 2),
                    $milestone->roi ? number_format($milestone->roi, 1) . '%' : '-',
                    $milestone->cac ? number_format($milestone->cac, 2) : '-',
                ];
            }
            $csvContent[] = [];
        }

        // Recent sales
        if (in_array('sales', $sections)) {
            $csvContent[] = ['RECENT SALES'];
            $csvContent[] = ['Date', 'Order ID', 'Customer', 'Tickets', 'Amount', 'Source'];

            $orders = Order::where('marketplace_event_id', $event->id)
                ->whereIn('status', ['paid', 'confirmed', 'completed'])
                ->orderBy('paid_at', 'desc')
                ->limit(100)
                ->get();

            foreach ($orders as $order) {
                $csvContent[] = [
                    $order->paid_at?->format('Y-m-d H:i') ?? $order->created_at->format('Y-m-d H:i'),
                    $order->order_number ?? $order->id,
                    $this->maskEmail($order->customer_email),
                    $order->tickets()->count(),
                    number_format($order->total, 2),
                    $order->source ?? 'Direct',
                ];
            }
            $csvContent[] = [];
        }

        // Goals
        if (in_array('goals', $sections)) {
            $csvContent[] = ['GOALS'];
            $csvContent[] = ['Type', 'Name', 'Target', 'Current', 'Progress', 'Status'];

            $goals = EventGoal::where('event_id', $event->id)->get();
            foreach ($goals as $goal) {
                $csvContent[] = [
                    $goal->type_label,
                    $goal->name ?? '-',
                    $goal->formatted_target,
                    $goal->formatted_current,
                    number_format($goal->progress_percent, 1) . '%',
                    ucfirst($goal->status),
                ];
            }
        }

        // Convert to CSV string
        $csv = $this->arrayToCsv($csvContent);

        // Store file
        Storage::disk('local')->put($filepath, $csv);

        return Storage::disk('local')->path($filepath);
    }

    /**
     * Export analytics data to PDF
     */
    public function exportToPdf(Event $event, array $options = []): string
    {
        $period = $options['period'] ?? '30d';
        $sections = $options['sections'] ?? ['overview', 'chart', 'traffic', 'milestones', 'goals'];
        $includeComparison = $options['include_comparison'] ?? true;

        $dateRange = $this->getDateRange($period);
        $filename = $this->generateFilename($event, 'pdf');
        $filepath = "exports/{$filename}";

        // Gather all data
        $data = [
            'event' => $event,
            'period' => $period,
            'date_range' => $dateRange,
            'generated_at' => now(),
            'sections' => $sections,
        ];

        if (in_array('overview', $sections)) {
            $data['overview'] = $this->analyticsService->getOverviewStats($event, $dateRange);
            if ($includeComparison) {
                $data['comparison'] = $this->analyticsService->getPeriodComparison($event, $period);
            }
        }

        if (in_array('chart', $sections)) {
            $data['chart_data'] = $this->analyticsService->getChartData($event, $dateRange);
        }

        if (in_array('traffic', $sections)) {
            $data['traffic_sources'] = $this->analyticsService->getTrafficSources($event, $dateRange);
        }

        if (in_array('milestones', $sections)) {
            $data['milestones'] = $this->analyticsService->getMilestonesWithMetrics($event);
        }

        if (in_array('goals', $sections)) {
            $data['goals'] = EventGoal::where('event_id', $event->id)->get();
        }

        if (in_array('top_locations', $sections)) {
            $data['top_locations'] = $this->analyticsService->getTopLocations($event, $dateRange);
        }

        if (in_array('funnel', $sections)) {
            $data['funnel'] = $this->analyticsService->getFunnelMetrics($event, $dateRange);
        }

        // Generate PDF
        $pdf = Pdf::loadView('exports.event-analytics-report', $data);
        $pdf->setPaper('a4', 'portrait');

        // Store file
        Storage::disk('local')->put($filepath, $pdf->output());

        return Storage::disk('local')->path($filepath);
    }

    /**
     * Generate report data for email
     */
    public function generateReportData(Event $event, array $dateRange, array $sections = []): array
    {
        $sections = $sections ?: ['overview', 'chart', 'traffic', 'milestones', 'goals'];

        $data = [
            'event' => [
                'id' => $event->id,
                'name' => $event->title_translated ?? $event->title,
                'date' => $event->start_date?->format('M d, Y'),
                'days_until' => $event->days_until,
                'status' => $event->status_label,
            ],
            'period' => [
                'start' => $dateRange['start']->format('M d, Y'),
                'end' => $dateRange['end']->format('M d, Y'),
            ],
        ];

        if (in_array('overview', $sections)) {
            $data['overview'] = $this->analyticsService->getOverviewStats($event, $dateRange);
        }

        if (in_array('traffic', $sections)) {
            $data['traffic_sources'] = array_slice($this->analyticsService->getTrafficSources($event, $dateRange), 0, 5);
        }

        if (in_array('milestones', $sections)) {
            $milestones = $this->analyticsService->getMilestonesWithMetrics($event);
            $data['milestones'] = array_slice($milestones, 0, 5);
            $data['milestones_summary'] = [
                'total' => count($milestones),
                'active' => count(array_filter($milestones, fn($m) => $m['is_active'])),
                'total_budget' => array_sum(array_column($milestones, 'budget')),
                'total_attributed' => array_sum(array_column($milestones, 'attributed_revenue')),
            ];
        }

        if (in_array('goals', $sections)) {
            $goals = EventGoal::where('event_id', $event->id)->get();
            $data['goals'] = $goals->map(fn($g) => [
                'type' => $g->type,
                'type_label' => $g->type_label,
                'name' => $g->name,
                'target' => $g->formatted_target,
                'current' => $g->formatted_current,
                'progress' => $g->progress_percent,
                'status' => $g->status,
            ])->toArray();
        }

        if (in_array('top_locations', $sections)) {
            $data['top_locations'] = array_slice($this->analyticsService->getTopLocations($event, $dateRange), 0, 5);
        }

        return $data;
    }

    /**
     * Export sales data to CSV
     */
    public function exportSalesToCsv(Event $event, array $dateRange = null): string
    {
        $filename = $this->generateFilename($event, 'csv', 'sales');
        $filepath = "exports/{$filename}";

        $query = Order::where('marketplace_event_id', $event->id)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->with(['tickets.ticketType', 'marketplaceCustomer'])
            ->orderBy('paid_at', 'desc');

        if ($dateRange) {
            $query->whereBetween('paid_at', [$dateRange['start'], $dateRange['end']]);
        }

        $orders = $query->get();

        $csvContent = [];
        $csvContent[] = ['Sales Export - ' . ($event->title_translated ?? $event->title)];
        $csvContent[] = ['Generated', now()->format('Y-m-d H:i:s')];
        $csvContent[] = [];
        $csvContent[] = [
            'Order ID',
            'Order Number',
            'Date',
            'Customer Email',
            'Customer Name',
            'Tickets',
            'Ticket Types',
            'Subtotal',
            'Commission',
            'Total',
            'Currency',
            'Payment Method',
            'Source',
            'UTM Source',
            'UTM Campaign',
            'Status',
        ];

        foreach ($orders as $order) {
            $ticketTypes = $order->tickets->groupBy('ticket_type_id')->map(function ($tickets) {
                $type = $tickets->first()->ticketType;
                return ($type?->name ?? 'Unknown') . ' x' . $tickets->count();
            })->implode(', ');

            $csvContent[] = [
                $order->id,
                $order->order_number ?? '',
                $order->paid_at?->format('Y-m-d H:i:s') ?? $order->created_at->format('Y-m-d H:i:s'),
                $order->customer_email,
                $order->customer_name ?? $order->marketplaceCustomer?->name ?? '',
                $order->tickets->count(),
                $ticketTypes,
                number_format($order->subtotal ?? $order->total, 2),
                number_format($order->commission_amount ?? 0, 2),
                number_format($order->total, 2),
                $order->currency ?? 'EUR',
                $order->payment_processor ?? '',
                $order->source ?? 'Direct',
                $order->metadata['utm_source'] ?? $order->meta['utm_source'] ?? '',
                $order->metadata['utm_campaign'] ?? $order->meta['utm_campaign'] ?? '',
                $order->status,
            ];
        }

        $csv = $this->arrayToCsv($csvContent);
        Storage::disk('local')->put($filepath, $csv);

        return Storage::disk('local')->path($filepath);
    }

    /**
     * Get download URL for export file
     */
    public function getDownloadUrl(string $filepath): string
    {
        $filename = basename($filepath);
        return route('api.organizer.analytics.download', ['filename' => $filename]);
    }

    /**
     * Clean up old export files (older than 24 hours)
     */
    public function cleanupOldExports(): int
    {
        $files = Storage::disk('local')->files('exports');
        $deleted = 0;

        foreach ($files as $file) {
            $lastModified = Storage::disk('local')->lastModified($file);
            if ($lastModified < now()->subDay()->timestamp) {
                Storage::disk('local')->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /* Helper methods */

    protected function getDateRange(string $period): array
    {
        return match ($period) {
            '7d' => ['start' => now()->subDays(7)->startOfDay(), 'end' => now()->endOfDay()],
            '30d' => ['start' => now()->subDays(30)->startOfDay(), 'end' => now()->endOfDay()],
            '90d' => ['start' => now()->subDays(90)->startOfDay(), 'end' => now()->endOfDay()],
            'all' => ['start' => now()->subYear()->startOfDay(), 'end' => now()->endOfDay()],
            default => ['start' => now()->subDays(30)->startOfDay(), 'end' => now()->endOfDay()],
        };
    }

    protected function generateFilename(Event $event, string $extension, string $type = 'analytics'): string
    {
        $slug = Str::slug($event->title_translated ?? $event->title ?? 'event');
        $date = now()->format('Y-m-d_His');
        return "{$slug}_{$type}_{$date}.{$extension}";
    }

    protected function arrayToCsv(array $data): string
    {
        $output = fopen('php://temp', 'r+');

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        // Add BOM for Excel UTF-8 compatibility
        return "\xEF\xBB\xBF" . $csv;
    }

    protected function maskEmail(string $email): string
    {
        if (empty($email)) {
            return '';
        }
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***';
        }
        return substr($parts[0], 0, 2) . '***@' . $parts[1];
    }
}
