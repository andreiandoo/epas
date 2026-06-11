<?php

namespace App\Services\PromoCodes;

use Illuminate\Support\Facades\Log;

/**
 * Import promo codes from CSV
 */
class PromoCodeImportService
{
    public function __construct(
        protected PromoCodeService $promoCodeService
    ) {}

    /**
     * Import promo codes from CSV content
     *
     * @param string $tenantId
     * @param string $csvContent
     * @return array Import results
     */
    public function importFromCSV(string $tenantId, string $csvContent): array
    {
        $lines = explode("\n", trim($csvContent));
        $header = str_getcsv(array_shift($lines));

        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($lines as $lineNumber => $line) {
            if (empty(trim($line))) {
                continue;
            }

            try {
                $data = str_getcsv($line);
                $row = array_combine($header, $data);

                $promoCodeData = [
                    'code' => $row['code'] ?? null,
                    'name' => $row['name'] ?? null,
                    'description' => $row['description'] ?? null,
                    'type' => $row['type'],
                    'value' => (float) $row['value'],
                    'applies_to' => $row['applies_to'] ?? 'cart',
                    'min_purchase_amount' => !empty($row['min_purchase_amount']) ? (float) $row['min_purchase_amount'] : null,
                    'max_discount_amount' => !empty($row['max_discount_amount']) ? (float) $row['max_discount_amount'] : null,
                    'usage_limit' => !empty($row['usage_limit']) ? (int) $row['usage_limit'] : null,
                    'usage_limit_per_customer' => !empty($row['usage_limit_per_customer']) ? (int) $row['usage_limit_per_customer'] : null,
                    'expires_at' => !empty($row['expires_at']) ? $row['expires_at'] : null,
                ];

                $this->promoCodeService->create($tenantId, $promoCodeData);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'line' => $lineNumber + 2, // +2 because of 0-index and header
                    'error' => $e->getMessage(),
                ];
                Log::error('Failed to import promo code', [
                    'line' => $lineNumber + 2,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }
}
