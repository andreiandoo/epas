<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnafService
{
    private string $apiUrl = 'https://webservicesp.anaf.ro/api/PlatitorTvaRest/v9/tva';

    /**
     * Lookup company information by CUI (Romanian Tax ID)
     *
     * @param string $cui The CUI to search for
     * @return array|null Company data or null if not found/error
     */
    public function lookupByCui(string $cui): ?array
    {
        try {
            // Clean the CUI - remove spaces, 'RO' prefix if present
            $cui = $this->cleanCui($cui);

            // Make the API request - ANAF v9 format
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($this->apiUrl, [[
                    'cui' => $cui, // String format for v9
                    'data' => now()->format('Y-m-d'),
                ]]);

            // Check response
            if (!$response->successful()) {
                Log::warning('ANAF API request failed', [
                    'cui' => $cui,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();

            // Check if company was found
            if (!isset($data['found']) || empty($data['found'])) {
                Log::info('ANAF company not found', [
                    'cui' => $cui,
                    'response' => $data
                ]);
                return null;
            }

            // Get the first result
            $company = $data['found'][0];

            // Check if the company has basic data
            if (!isset($company['date_generale'])) {
                return null;
            }

            return $this->formatCompanyData($company);

        } catch (\Exception $e) {
            Log::error('ANAF API error', [
                'cui' => $cui ?? null,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Clean and normalize CUI
     */
    private function cleanCui(string $cui): string
    {
        // Remove spaces
        $cui = trim($cui);
        $cui = str_replace(' ', '', $cui);

        // Remove 'RO' prefix if present
        if (stripos($cui, 'RO') === 0) {
            $cui = substr($cui, 2);
        }

        return $cui;
    }

    /**
     * Format the ANAF response into a standardized array
     */
    private function formatCompanyData(array $company): array
    {
        $general = $company['date_generale'] ?? [];
        $address = $general['adresa'] ?? '';

        // Parse the address - ANAF returns it in a specific format
        $parsedAddress = $this->parseAddress($address);

        return [
            'cui' => $general['cui'] ?? null,
            'company_name' => $general['denumire'] ?? null,
            'reg_com' => $general['nrRegCom'] ?? null,
            'address' => $address,
            'parsed_address' => $parsedAddress,
            'city' => $parsedAddress['city'] ?? null,
            'state' => $parsedAddress['county'] ?? null,
            'country' => 'Romania',
            'phone' => $general['telefon'] ?? null,
            'fax' => $general['fax'] ?? null,
            'vat_payer' => isset($company['inregistrare_scop_Tva']) &&
                          $company['inregistrare_scop_Tva']['scpTVA'] === true,
            'is_active' => ($general['stare'] ?? '') === 'ACTIVA',
            'raw_data' => $company, // Keep raw data for reference
        ];
    }

    /**
     * Parse Romanian address string
     * ANAF format: "JUDET City, STRADA Name NR. 123"
     */
    private function parseAddress(string $address): array
    {
        $result = [
            'county' => null,
            'city' => null,
            'street' => null,
        ];

        if (empty($address)) {
            return $result;
        }

        // Try to extract county (JUDET)
        if (preg_match('/JUDET\s+([^,]+)/i', $address, $matches)) {
            $result['county'] = trim($matches[1]);
        }

        // Try to extract city - usually after county and before comma or STRADA
        if (preg_match('/JUDET\s+[^,]+,\s*([^,]+?)(?:,|\s+STRADA)/i', $address, $matches)) {
            $result['city'] = trim($matches[1]);
        } elseif (preg_match('/^([^,]+),/i', $address, $matches)) {
            // If no JUDET, try first part before comma
            $result['city'] = trim($matches[1]);
        }

        // Try to extract street
        if (preg_match('/STRADA\s+(.+?)(?:NR\.|$)/i', $address, $matches)) {
            $result['street'] = trim($matches[1]);
        }

        return $result;
    }

    /**
     * Validate Romanian CUI format
     */
    public function isValidCui(string $cui): bool
    {
        $cui = $this->cleanCui($cui);

        // Romanian CUI should be numeric and between 2-10 digits
        if (!is_numeric($cui)) {
            return false;
        }

        $length = strlen($cui);
        return $length >= 2 && $length <= 10;
    }
}
