<?php

namespace App\Services\Platform;

use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use App\Models\Platform\PlatformConversion;
use App\Models\Platform\CohortMetric;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AnalyticsExportService
{
    // Export types
    const TYPE_CUSTOMERS = 'customers';
    const TYPE_CHURN_REPORT = 'churn_report';
    const TYPE_COHORT_RETENTION = 'cohort_retention';
    const TYPE_ATTRIBUTION = 'attribution';
    const TYPE_SEGMENTS = 'segments';
    const TYPE_CONVERSIONS = 'conversions';
    const TYPE_TRAFFIC_SOURCES = 'traffic_sources';

    // Export formats
    const FORMAT_CSV = 'csv';
    const FORMAT_XLSX = 'xlsx';
    const FORMAT_JSON = 'json';

    protected ?int $tenantId = null;
    protected ?Carbon $startDate = null;
    protected ?Carbon $endDate = null;

    public function setTenantId(?int $tenantId): self
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    public function setDateRange(?Carbon $startDate, ?Carbon $endDate): self
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        return $this;
    }

    /**
     * Export data in the specified format
     */
    public function export(string $type, string $format = self::FORMAT_CSV): StreamedResponse|array
    {
        $data = $this->getData($type);

        return match ($format) {
            self::FORMAT_CSV => $this->exportCsv($data, $type),
            self::FORMAT_XLSX => $this->exportXlsx($data, $type),
            self::FORMAT_JSON => $data,
            default => throw new \InvalidArgumentException("Unsupported export format: {$format}"),
        };
    }

    /**
     * Get data for export type
     */
    protected function getData(string $type): array
    {
        return match ($type) {
            self::TYPE_CUSTOMERS => $this->getCustomersData(),
            self::TYPE_CHURN_REPORT => $this->getChurnReportData(),
            self::TYPE_COHORT_RETENTION => $this->getCohortRetentionData(),
            self::TYPE_ATTRIBUTION => $this->getAttributionData(),
            self::TYPE_SEGMENTS => $this->getSegmentsData(),
            self::TYPE_CONVERSIONS => $this->getConversionsData(),
            self::TYPE_TRAFFIC_SOURCES => $this->getTrafficSourcesData(),
            default => throw new \InvalidArgumentException("Unsupported export type: {$type}"),
        };
    }

    /**
     * Get customers data for export
     */
    protected function getCustomersData(): array
    {
        $customers = CoreCustomer::query()
            ->when($this->tenantId, fn($q) => $q->fromTenant($this->tenantId))
            ->notMerged()
            ->notAnonymized()
            ->orderByDesc('lifetime_value')
            ->limit(10000)
            ->get();

        $headers = [
            'UUID', 'First Name', 'Last Name', 'Email Hash', 'Country',
            'City', 'Segment', 'RFM Score', 'Health Score', 'Churn Risk',
            'Lifetime Value', 'Total Orders', 'Average Order Value',
            'First Seen', 'Last Seen', 'Last Purchase',
        ];

        $rows = $customers->map(function ($customer) {
            return [
                $customer->uuid,
                $customer->first_name,
                $customer->last_name,
                $customer->email_hash,
                $customer->country_code,
                $customer->city,
                $customer->customer_segment,
                $customer->rfm_score,
                $customer->health_score,
                $customer->churn_risk_score,
                $customer->lifetime_value,
                $customer->total_orders,
                $customer->average_order_value,
                $customer->first_seen_at?->format('Y-m-d H:i:s'),
                $customer->last_seen_at?->format('Y-m-d H:i:s'),
                $customer->last_purchase_at?->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        return [
            'title' => 'Customer Export',
            'headers' => $headers,
            'rows' => $rows,
            'generated_at' => now()->toIso8601String(),
            'total_records' => count($rows),
        ];
    }

    /**
     * Get churn report data for export
     */
    protected function getChurnReportData(): array
    {
        $customers = CoreCustomer::query()
            ->when($this->tenantId, fn($q) => $q->fromTenant($this->tenantId))
            ->notMerged()
            ->notAnonymized()
            ->whereNotNull('churn_risk_score')
            ->orderByDesc('churn_risk_score')
            ->limit(5000)
            ->get();

        $churnService = app(ChurnPredictionService::class);

        $headers = [
            'UUID', 'Name', 'Segment', 'Churn Risk %', 'Risk Level',
            'Lifetime Value', 'Total Orders', 'Days Since Last Seen',
            'Days Since Last Purchase', 'Email Engaged', 'Recommendations',
        ];

        $rows = $customers->map(function ($customer) use ($churnService) {
            $prediction = $churnService->predictChurn($customer);

            $recommendations = collect($prediction['recommendations'] ?? [])
                ->pluck('action')
                ->implode(', ');

            return [
                $customer->uuid,
                $customer->getDisplayName(),
                $customer->customer_segment,
                $prediction['churn_probability'],
                $prediction['risk_level'],
                $customer->lifetime_value,
                $customer->total_orders,
                $customer->last_seen_at ? $customer->last_seen_at->diffInDays(now()) : null,
                $customer->last_purchase_at ? $customer->last_purchase_at->diffInDays(now()) : null,
                $customer->email_unsubscribed_at ? 'No' : 'Yes',
                $recommendations,
            ];
        })->toArray();

        return [
            'title' => 'Churn Risk Report',
            'headers' => $headers,
            'rows' => $rows,
            'generated_at' => now()->toIso8601String(),
            'total_records' => count($rows),
            'summary' => [
                'critical_count' => collect($rows)->where(3, '>=', 80)->count(),
                'high_risk_count' => collect($rows)->whereBetween(3, [60, 80])->count(),
                'medium_risk_count' => collect($rows)->whereBetween(3, [40, 60])->count(),
            ],
        ];
    }

    /**
     * Get cohort retention data for export
     */
    protected function getCohortRetentionData(): array
    {
        $metrics = CohortMetric::query()
            ->when($this->tenantId, fn($q) => $q->where('tenant_id', $this->tenantId))
            ->where('cohort_type', 'month')
            ->orderBy('cohort_period')
            ->orderBy('period_offset')
            ->get();

        $cohorts = $metrics->groupBy('cohort_period');

        // Build headers: Cohort, Initial Customers, then Period 0-12
        $headers = ['Cohort', 'Initial Customers', 'Initial Revenue'];
        for ($i = 0; $i <= 12; $i++) {
            $headers[] = "Month {$i} Retention %";
        }

        $rows = [];
        foreach ($cohorts as $period => $periodMetrics) {
            $row = [$period];

            $initialMetric = $periodMetrics->firstWhere('period_offset', 0);
            $row[] = $initialMetric?->customers_count ?? 0;
            $row[] = $initialMetric?->total_revenue ?? 0;

            $baseCustomers = $initialMetric?->customers_count ?? 1;

            for ($i = 0; $i <= 12; $i++) {
                $metric = $periodMetrics->firstWhere('period_offset', $i);
                if ($metric && $baseCustomers > 0) {
                    $retention = round(($metric->active_customers / $baseCustomers) * 100, 1);
                    $row[] = $retention;
                } else {
                    $row[] = null;
                }
            }

            $rows[] = $row;
        }

        return [
            'title' => 'Cohort Retention Analysis',
            'headers' => $headers,
            'rows' => $rows,
            'generated_at' => now()->toIso8601String(),
            'total_cohorts' => count($rows),
        ];
    }

    /**
     * Get attribution data for export
     */
    protected function getAttributionData(): array
    {
        $startDate = $this->startDate ?? now()->subDays(30);
        $endDate = $this->endDate ?? now();

        $attributionService = app(AttributionModelService::class);
        $report = $attributionService->getChannelAttributionReport($startDate, $endDate, $this->tenantId);

        $headers = [
            'Channel', 'First Touch Value', 'Last Touch Value', 'Linear Value',
            'Time Decay Value', 'Position Based Value', 'Conversions', 'Avg Value',
        ];

        $rows = collect($report['channels'] ?? [])->map(function ($channel, $name) {
            return [
                $name ?: 'Direct',
                $channel['first_touch'] ?? 0,
                $channel['last_touch'] ?? 0,
                $channel['linear'] ?? 0,
                $channel['time_decay'] ?? 0,
                $channel['position_based'] ?? 0,
                $channel['conversions'] ?? 0,
                $channel['avg_value'] ?? 0,
            ];
        })->values()->toArray();

        return [
            'title' => 'Attribution Report',
            'headers' => $headers,
            'rows' => $rows,
            'generated_at' => now()->toIso8601String(),
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'total_channels' => count($rows),
        ];
    }

    /**
     * Get segments data for export
     */
    protected function getSegmentsData(): array
    {
        $segments = CoreCustomer::query()
            ->when($this->tenantId, fn($q) => $q->fromTenant($this->tenantId))
            ->notMerged()
            ->notAnonymized()
            ->whereNotNull('customer_segment')
            ->groupBy('customer_segment')
            ->selectRaw('
                customer_segment,
                COUNT(*) as customer_count,
                SUM(lifetime_value) as total_ltv,
                AVG(lifetime_value) as avg_ltv,
                AVG(total_orders) as avg_orders,
                AVG(rfm_score) as avg_rfm,
                AVG(health_score) as avg_health,
                AVG(churn_risk_score) as avg_churn_risk
            ')
            ->get();

        $headers = [
            'Segment', 'Customer Count', 'Total LTV', 'Avg LTV',
            'Avg Orders', 'Avg RFM Score', 'Avg Health Score', 'Avg Churn Risk',
        ];

        $rows = $segments->map(function ($segment) {
            return [
                $segment->customer_segment,
                $segment->customer_count,
                round($segment->total_ltv, 2),
                round($segment->avg_ltv, 2),
                round($segment->avg_orders, 1),
                round($segment->avg_rfm, 1),
                round($segment->avg_health, 1),
                round($segment->avg_churn_risk, 1),
            ];
        })->toArray();

        return [
            'title' => 'Customer Segments Report',
            'headers' => $headers,
            'rows' => $rows,
            'generated_at' => now()->toIso8601String(),
            'total_segments' => count($rows),
        ];
    }

    /**
     * Get conversions data for export
     */
    protected function getConversionsData(): array
    {
        $startDate = $this->startDate ?? now()->subDays(30);
        $endDate = $this->endDate ?? now();

        $conversions = PlatformConversion::query()
            ->when($this->tenantId, fn($q) => $q->where('tenant_id', $this->tenantId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['adAccount', 'customer'])
            ->orderByDesc('created_at')
            ->limit(10000)
            ->get();

        $headers = [
            'Conversion ID', 'Platform', 'Customer UUID', 'Event Type',
            'Value', 'Currency', 'Status', 'Conversion Time',
            'UTM Source', 'UTM Medium', 'UTM Campaign',
        ];

        $rows = $conversions->map(function ($conversion) {
            return [
                $conversion->conversion_id,
                $conversion->adAccount?->platform ?? 'Unknown',
                $conversion->customer?->uuid,
                $conversion->event_type,
                $conversion->value,
                $conversion->currency,
                $conversion->status,
                $conversion->conversion_time?->format('Y-m-d H:i:s'),
                $conversion->utm_source,
                $conversion->utm_medium,
                $conversion->utm_campaign,
            ];
        })->toArray();

        return [
            'title' => 'Conversions Export',
            'headers' => $headers,
            'rows' => $rows,
            'generated_at' => now()->toIso8601String(),
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'total_records' => count($rows),
            'total_value' => $conversions->sum('value'),
        ];
    }

    /**
     * Get traffic sources data for export
     */
    protected function getTrafficSourcesData(): array
    {
        $startDate = $this->startDate ?? now()->subDays(30);
        $endDate = $this->endDate ?? now();

        $sources = CoreSession::query()
            ->when($this->tenantId, fn($q) => $q->where('tenant_id', $this->tenantId))
            ->whereBetween('started_at', [$startDate, $endDate])
            ->groupBy('utm_source', 'utm_medium', 'utm_campaign')
            ->selectRaw('
                COALESCE(utm_source, "direct") as source,
                utm_medium as medium,
                utm_campaign as campaign,
                COUNT(*) as sessions,
                COUNT(DISTINCT core_customer_id) as unique_visitors,
                SUM(CASE WHEN is_converted = 1 THEN 1 ELSE 0 END) as conversions,
                SUM(CASE WHEN is_converted = 1 THEN total_value ELSE 0 END) as revenue,
                AVG(page_views) as avg_page_views,
                AVG(duration_seconds) as avg_duration
            ')
            ->orderByDesc('sessions')
            ->limit(500)
            ->get();

        $headers = [
            'Source', 'Medium', 'Campaign', 'Sessions', 'Unique Visitors',
            'Conversions', 'Conversion Rate %', 'Revenue', 'Avg Page Views', 'Avg Duration (s)',
        ];

        $rows = $sources->map(function ($source) {
            $conversionRate = $source->sessions > 0
                ? round(($source->conversions / $source->sessions) * 100, 2)
                : 0;

            return [
                $source->source,
                $source->medium,
                $source->campaign,
                $source->sessions,
                $source->unique_visitors,
                $source->conversions,
                $conversionRate,
                round($source->revenue, 2),
                round($source->avg_page_views, 1),
                round($source->avg_duration, 0),
            ];
        })->toArray();

        return [
            'title' => 'Traffic Sources Report',
            'headers' => $headers,
            'rows' => $rows,
            'generated_at' => now()->toIso8601String(),
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'total_sources' => count($rows),
        ];
    }

    /**
     * Export as CSV
     */
    protected function exportCsv(array $data, string $type): StreamedResponse
    {
        $filename = $this->getFilename($type, 'csv');

        return response()->streamDownload(function () use ($data) {
            $output = fopen('php://output', 'w');

            // Add BOM for Excel compatibility
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Write headers
            fputcsv($output, $data['headers']);

            // Write rows
            foreach ($data['rows'] as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Export as XLSX
     */
    protected function exportXlsx(array $data, string $type): StreamedResponse
    {
        $filename = $this->getFilename($type, 'xlsx');

        return response()->streamDownload(function () use ($data) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(substr($data['title'] ?? 'Export', 0, 31));

            // Style header row
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ];

            // Write headers
            $col = 1;
            foreach ($data['headers'] as $header) {
                $sheet->setCellValue([$col, 1], $header);
                $col++;
            }

            // Apply header style
            $lastCol = count($data['headers']);
            $sheet->getStyle([1, 1, $lastCol, 1])->applyFromArray($headerStyle);

            // Write data rows
            $row = 2;
            foreach ($data['rows'] as $rowData) {
                $col = 1;
                foreach ($rowData as $value) {
                    $sheet->setCellValue([$col, $row], $value);
                    $col++;
                }
                $row++;
            }

            // Auto-size columns
            foreach (range(1, $lastCol) as $col) {
                $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            }

            // Add data borders
            if ($row > 2) {
                $sheet->getStyle([1, 2, $lastCol, $row - 1])->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CCCCCC'],
                        ],
                    ],
                ]);
            }

            // Add metadata sheet
            $metaSheet = $spreadsheet->createSheet();
            $metaSheet->setTitle('Metadata');
            $metaSheet->setCellValue('A1', 'Generated At');
            $metaSheet->setCellValue('B1', $data['generated_at'] ?? now()->toIso8601String());
            $metaSheet->setCellValue('A2', 'Total Records');
            $metaSheet->setCellValue('B2', $data['total_records'] ?? count($data['rows']));

            if (isset($data['date_range'])) {
                $metaSheet->setCellValue('A3', 'Date Range');
                $metaSheet->setCellValue('B3', $data['date_range']['start'] . ' to ' . $data['date_range']['end']);
            }

            // Set first sheet as active
            $spreadsheet->setActiveSheetIndex(0);

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Generate filename for export
     */
    protected function getFilename(string $type, string $extension): string
    {
        $typeName = str_replace('_', '-', $type);
        $date = now()->format('Y-m-d-His');

        return "analytics-{$typeName}-{$date}.{$extension}";
    }

    /**
     * Get available export types
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_CUSTOMERS => 'Customer Export',
            self::TYPE_CHURN_REPORT => 'Churn Risk Report',
            self::TYPE_COHORT_RETENTION => 'Cohort Retention Analysis',
            self::TYPE_ATTRIBUTION => 'Attribution Report',
            self::TYPE_SEGMENTS => 'Customer Segments',
            self::TYPE_CONVERSIONS => 'Conversions Export',
            self::TYPE_TRAFFIC_SOURCES => 'Traffic Sources',
        ];
    }

    /**
     * Get available export formats
     */
    public static function getAvailableFormats(): array
    {
        return [
            self::FORMAT_CSV => 'CSV',
            self::FORMAT_XLSX => 'Excel (XLSX)',
            self::FORMAT_JSON => 'JSON',
        ];
    }
}
