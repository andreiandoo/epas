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
     * ANAF formats vary:
     * - "JUD. ILFOV, MUN. BUCURESTI SECTOR 1, STR. VICTORIEI, NR. 10"
     * - "JUDET ILFOV, BUCURESTI, STRADA VICTORIEI NR. 10"
     * - "SECTOR 1, STR. VICTORIEI, NR. 10, BUCURESTI"
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

        // Try to extract county with various formats
        // Format: "JUD. NAME" or "JUDET NAME" or "JUDETUL NAME"
        if (preg_match('/(?:JUD\.|JUDET|JUDETUL)\s*([A-Z\-\s]+?)(?:,|$)/i', $address, $matches)) {
            $county = trim($matches[1]);
            // Clean up - remove trailing spaces and common suffixes
            $county = preg_replace('/\s+(MUN|ORS|COM|SAT)\.?\s*$/i', '', $county);
            $result['county'] = trim($county);
        }

        // Try to extract city
        // Format: "MUN. NAME" or "ORAS NAME" or "ORS. NAME" or "MUNICIPIUL NAME"
        if (preg_match('/(?:MUN\.|MUNICIPIUL|ORS\.|ORAS|ORASUL)\s*([A-Z\-\s]+?)(?:,|SECTOR|\s+STR|\s+STRADA|$)/i', $address, $matches)) {
            $result['city'] = trim($matches[1]);
        }
        // Also check for sector (Bucharest)
        elseif (preg_match('/SECTOR\s*(\d)/i', $address, $matches)) {
            $result['city'] = 'Bucuresti Sector ' . $matches[1];
            if (!$result['county']) {
                $result['county'] = 'Bucuresti';
            }
        }
        // Try city after county
        elseif (preg_match('/(?:JUD\.|JUDET|JUDETUL)\s*[^,]+,\s*([A-Z\-\s]+?)(?:,|\s+STR|\s+STRADA|$)/i', $address, $matches)) {
            $result['city'] = trim($matches[1]);
        }
        // Fallback: first part before comma if no other match
        elseif (!$result['city'] && preg_match('/^([A-Z\-\s]+?)(?:,|$)/i', $address, $matches)) {
            $city = trim($matches[1]);
            // Don't use if it looks like county or street
            if (!preg_match('/^(JUD|JUDET|STR|STRADA)/i', $city)) {
                $result['city'] = $city;
            }
        }

        // Try to extract street
        if (preg_match('/(?:STR\.|STRADA)\s*(.+?)(?:,?\s*NR\.|,?\s*BL\.|$)/i', $address, $matches)) {
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
