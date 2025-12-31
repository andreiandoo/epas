<?php

namespace App\Services\Tax;

use App\Models\Tax\LocalTax;
use App\Models\Tax\TaxImportLog;
use App\Models\EventType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\UploadedFile;

class TaxImportService
{
    protected array $validCountries = [];
    protected array $eventTypes = [];

    public function __construct()
    {
        $this->validCountries = require resource_path('data/countries.php');
    }

    /**
     * Import local taxes from CSV file
     */
    public function importFromCsv(
        int $tenantId,
        UploadedFile $file,
        ?int $userId = null,
        array $options = []
    ): TaxImportLog {
        // Create import log
        $log = TaxImportLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'filename' => $file->getClientOriginalName(),
            'status' => 'pending',
        ]);

        try {
            $log->markAsProcessing();

            // Load event types for validation
            $this->eventTypes = EventType::all()->pluck('id', 'slug')->toArray();

            // Parse CSV
            $rows = $this->parseCsv($file);
            $log->update(['total_rows' => count($rows)]);

            // Process rows
            $this->processRows($tenantId, $rows, $log, $options);

            $log->markAsCompleted();
        } catch (\Exception $e) {
            $log->markAsFailed([['row' => 0, 'message' => $e->getMessage()]]);
        }

        return $log->fresh();
    }

    /**
     * Parse CSV file into array
     */
    protected function parseCsv(UploadedFile $file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');
        $headers = null;
        $rowNum = 0;

        while (($data = fgetcsv($handle)) !== false) {
            $rowNum++;

            // Skip empty rows
            if (empty(array_filter($data))) {
                continue;
            }

            // First non-empty row is headers
            if ($headers === null) {
                $headers = array_map('strtolower', array_map('trim', $data));
                continue;
            }

            // Map data to headers
            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = $data[$i] ?? null;
            }
            $row['_row_num'] = $rowNum;
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Process imported rows
     */
    protected function processRows(int $tenantId, array $rows, TaxImportLog $log, array $options): void
    {
        $skipDuplicates = $options['skip_duplicates'] ?? true;
        $updateExisting = $options['update_existing'] ?? false;

        foreach ($rows as $row) {
            try {
                $this->processRow($tenantId, $row, $skipDuplicates, $updateExisting);
                $log->incrementImported();
            } catch (\Exception $e) {
                $log->addError($row['_row_num'], $e->getMessage());
                $log->incrementFailed();
            }
        }
    }

    /**
     * Process a single row
     */
    protected function processRow(int $tenantId, array $row, bool $skipDuplicates, bool $updateExisting): void
    {
        // Normalize field names
        $data = $this->normalizeRow($row);

        // Validate
        $this->validateRow($data);

        // Check for existing
        $existing = LocalTax::forTenant($tenantId)
            ->where('country', $data['country'])
            ->where('county', $data['county'])
            ->where('city', $data['city'])
            ->first();

        if ($existing) {
            if ($skipDuplicates && !$updateExisting) {
                throw new \Exception("Duplicate entry for {$data['country']}/{$data['county']}/{$data['city']}");
            }

            if ($updateExisting) {
                $existing->update([
                    'value' => $data['value'],
                    'explanation' => $data['explanation'] ?? $existing->explanation,
                    'source_url' => $data['source_url'] ?? $existing->source_url,
                    'priority' => $data['priority'] ?? $existing->priority,
                ]);
                return;
            }
        }

        // Create new tax
        $tax = LocalTax::create([
            'tenant_id' => $tenantId,
            'country' => $data['country'],
            'county' => $data['county'] ?: null,
            'city' => $data['city'] ?: null,
            'value' => $data['value'],
            'explanation' => $data['explanation'] ?? null,
            'source_url' => $data['source_url'] ?? null,
            'priority' => $data['priority'] ?? 0,
            'is_active' => true,
        ]);

        // Handle event types
        if (!empty($data['event_types'])) {
            $eventTypeIds = $this->parseEventTypes($data['event_types']);
            if (!empty($eventTypeIds)) {
                $tax->eventTypes()->sync($eventTypeIds);
            }
        }
    }

    /**
     * Normalize row field names
     */
    protected function normalizeRow(array $row): array
    {
        $mapping = [
            'country' => ['country', 'country_name', 'nation'],
            'county' => ['county', 'state', 'region', 'province', 'county_name', 'state_name'],
            'city' => ['city', 'city_name', 'municipality', 'town'],
            'value' => ['value', 'rate', 'tax_rate', 'percent', 'percentage'],
            'explanation' => ['explanation', 'description', 'notes', 'note'],
            'source_url' => ['source_url', 'source', 'url', 'reference', 'link'],
            'priority' => ['priority', 'order', 'sort_order'],
            'event_types' => ['event_types', 'event_type', 'types', 'categories'],
        ];

        $normalized = [];
        foreach ($mapping as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($row[$alias]) && $row[$alias] !== '') {
                    $normalized[$field] = trim($row[$alias]);
                    break;
                }
            }
        }

        return $normalized;
    }

    /**
     * Validate row data
     */
    protected function validateRow(array $data): void
    {
        $validator = Validator::make($data, [
            'country' => 'required|string|max:100',
            'county' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'value' => 'required|numeric|min:0|max:100',
            'explanation' => 'nullable|string',
            'source_url' => 'nullable|url|max:500',
            'priority' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            throw new \Exception(implode(', ', $validator->errors()->all()));
        }

        // Validate country
        if (!in_array($data['country'], $this->validCountries)) {
            throw new \Exception("Invalid country: {$data['country']}");
        }
    }

    /**
     * Parse event types from string
     */
    protected function parseEventTypes(string $eventTypes): array
    {
        $ids = [];
        $types = array_map('trim', explode(',', $eventTypes));

        foreach ($types as $type) {
            if (is_numeric($type)) {
                $ids[] = (int) $type;
            } elseif (isset($this->eventTypes[strtolower($type)])) {
                $ids[] = $this->eventTypes[strtolower($type)];
            }
        }

        return array_filter($ids);
    }

    /**
     * Generate sample CSV content
     */
    public function generateSampleCsv(): string
    {
        $headers = ['country', 'county', 'city', 'value', 'explanation', 'source_url', 'priority', 'event_types'];
        $sample = [
            ['Romania', 'Cluj', 'Cluj-Napoca', '5.00', 'Local entertainment tax', 'https://example.com', '0', ''],
            ['Romania', 'Bucuresti', '', '3.50', 'County-wide tax', '', '0', 'concert,festival'],
            ['United States', 'California', 'Los Angeles', '9.50', 'City sales tax', '', '0', ''],
        ];

        $output = implode(',', $headers) . "\n";
        foreach ($sample as $row) {
            $output .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $row)) . "\n";
        }

        return $output;
    }

    /**
     * Get import history for tenant
     */
    public function getImportHistory(int $tenantId, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return TaxImportLog::forTenant($tenantId)
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get();
    }
}
