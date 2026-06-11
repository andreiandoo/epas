<?php

namespace App\Services\Cashless;

use App\Enums\ProductType;
use App\Models\VendorProduct;
use App\Models\VendorProductCategory;

class ProductImportService
{
    /**
     * Import products from a CSV file.
     *
     * Expected CSV columns:
     * name, type, category, weight_volume, unit_measure, sale_price, vat_rate, is_age_restricted, sku
     *
     * @return array{created: int, updated: int, errors: int, error_details: array}
     */
    public function importFromCsv(string $filePath, int $vendorId, int $editionId): array
    {
        if (! file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        // Handle UTF-8 BOM
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $lines = array_filter(explode("\n", $content), fn ($l) => trim($l) !== '');

        if (count($lines) < 2) {
            throw new \InvalidArgumentException('CSV file must have at least a header row and one data row.');
        }

        $header = str_getcsv(array_shift($lines));
        $header = array_map(fn ($h) => strtolower(trim($h)), $header);

        $requiredColumns = ['name'];
        foreach ($requiredColumns as $col) {
            if (! in_array($col, $header)) {
                throw new \InvalidArgumentException("Missing required column: {$col}");
            }
        }

        $created = 0;
        $updated = 0;
        $errors = 0;
        $errorDetails = [];

        foreach ($lines as $lineNumber => $line) {
            $row = str_getcsv($line);
            $data = [];

            foreach ($header as $i => $col) {
                $data[$col] = $row[$i] ?? null;
            }

            try {
                $this->processRow($data, $vendorId, $editionId, $created, $updated);
            } catch (\Throwable $e) {
                $errors++;
                $errorDetails[] = [
                    'line'    => $lineNumber + 2,
                    'name'    => $data['name'] ?? 'unknown',
                    'error'   => $e->getMessage(),
                ];
            }
        }

        return [
            'created'       => $created,
            'updated'       => $updated,
            'errors'        => $errors,
            'error_details' => $errorDetails,
        ];
    }

    private function processRow(array $data, int $vendorId, int $editionId, int &$created, int &$updated): void
    {
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            throw new \InvalidArgumentException('Product name is required.');
        }

        // Resolve category
        $categoryId = null;
        if (! empty($data['category'])) {
            $category = VendorProductCategory::firstOrCreate(
                [
                    'vendor_id'           => $vendorId,
                    'festival_edition_id' => $editionId,
                    'name'                => trim($data['category']),
                ],
                [
                    'slug'     => \Illuminate\Support\Str::slug(trim($data['category'])),
                    'is_active' => true,
                ]
            );
            $categoryId = $category->id;
        }

        // Resolve type
        $type = null;
        if (! empty($data['type'])) {
            $type = ProductType::tryFrom(strtolower(trim($data['type'])));
        }

        // Parse price (accept both cents and decimal)
        $priceCents = $this->parsePriceToCents($data['sale_price'] ?? $data['price'] ?? null);

        // Check for existing product by SKU or name
        $existing = null;
        if (! empty($data['sku'])) {
            $existing = VendorProduct::where('vendor_id', $vendorId)
                ->where('sku', trim($data['sku']))
                ->first();
        }

        if (! $existing) {
            $existing = VendorProduct::where('vendor_id', $vendorId)
                ->where('festival_edition_id', $editionId)
                ->where('name', $name)
                ->first();
        }

        $productData = [
            'vendor_id'                => $vendorId,
            'festival_edition_id'      => $editionId,
            'vendor_product_category_id' => $categoryId,
            'name'                     => $name,
            'slug'                     => \Illuminate\Support\Str::slug($name),
            'type'                     => $type?->value,
            'weight_volume'            => ! empty($data['weight_volume']) ? (float) $data['weight_volume'] : null,
            'unit_measure'             => ! empty($data['unit_measure']) ? trim($data['unit_measure']) : null,
            'price_cents'              => $priceCents,
            'sale_price_cents'         => $priceCents,
            'vat_rate'                 => ! empty($data['vat_rate']) ? (float) $data['vat_rate'] : 19.00,
            'vat_included'             => true,
            'is_age_restricted'        => $this->parseBool($data['is_age_restricted'] ?? 'false'),
            'min_age'                  => 18,
            'sgr_cents'                => ! empty($data['sgr_cents']) ? (int) $data['sgr_cents'] : 0,
            'sku'                      => ! empty($data['sku']) ? trim($data['sku']) : null,
            'is_available'             => true,
            'currency'                 => 'RON',
        ];

        if ($existing) {
            $existing->update($productData);
            $updated++;
        } else {
            VendorProduct::create($productData);
            $created++;
        }
    }

    private function parsePriceToCents(?string $value): int
    {
        if (empty($value)) {
            return 0;
        }

        $value = trim($value);
        // If it looks like a decimal price (has . or , with decimals)
        $value = str_replace(',', '.', $value);

        if (str_contains($value, '.')) {
            return (int) round((float) $value * 100);
        }

        // Already in cents
        return (int) $value;
    }

    private function parseBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['true', '1', 'yes', 'da']);
    }
}
