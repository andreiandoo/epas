<?php

namespace App\Services\Accounting\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FGO Accounting Adapter
 *
 * Integrates with FGO.ro API for automated invoice generation.
 * Auth: SHA-1 hash per request = SHA1(CodUnic + CheiePrivata + contextValue) uppercase
 *
 * @see https://api-testuat.fgo.ro/v1/files/specificatii-api-latest.pdf
 */
class FgoAdapter implements AccountingAdapterInterface
{
    protected bool $authenticated = false;
    protected array $credentials = [];
    protected string $baseUrl = 'https://api-testuat.fgo.ro/v1';
    protected string $codUnic = '';
    protected string $cheiePrivata = '';
    protected string $serie = 'FACT';

    /**
     * {@inheritdoc}
     */
    public function authenticate(array $credentials): array
    {
        if (empty($credentials['cod_unic']) || empty($credentials['cheie_privata'])) {
            return [
                'success' => false,
                'message' => 'Missing FGO CodUnic or CheiePrivata',
            ];
        }

        $this->codUnic = $credentials['cod_unic'];
        $this->cheiePrivata = $credentials['cheie_privata'];
        $this->serie = $credentials['serie'] ?? 'FACT';
        $this->credentials = $credentials;

        // Set base URL based on environment
        $environment = $credentials['environment'] ?? 'test';
        $this->baseUrl = $environment === 'production'
            ? 'https://api.fgo.ro/v1'
            : 'https://api-testuat.fgo.ro/v1';

        $this->authenticated = true;

        return [
            'success' => true,
            'message' => 'FGO credentials stored successfully',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function testConnection(): array
    {
        if (!$this->authenticated) {
            return [
                'connected' => false,
                'message' => 'Not authenticated',
                'details' => [],
            ];
        }

        try {
            // Test with nomenclator endpoint (no context value needed for hash)
            $hash = $this->generateHash();

            $response = Http::post("{$this->baseUrl}/nomenclator/tipfactura", [
                'CodUnic' => $this->codUnic,
                'Hash' => $hash,
            ]);

            $data = $response->json();

            if ($response->successful() && ($data['Success'] ?? false)) {
                return [
                    'connected' => true,
                    'message' => 'FGO connection successful',
                    'details' => [
                        'company_cui' => $this->codUnic,
                    ],
                ];
            }

            return [
                'connected' => false,
                'message' => 'FGO connection failed: ' . ($data['Message'] ?? 'Unknown error'),
                'details' => [],
            ];

        } catch (\Exception $e) {
            return [
                'connected' => false,
                'message' => 'FGO connection test failed: ' . $e->getMessage(),
                'details' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     *
     * FGO auto-creates clients during invoice emission, no separate endpoint needed.
     */
    public function ensureCustomer(array $customer): array
    {
        return [
            'customer_id' => $customer['vat_number'] ?? $customer['name'] ?? 'unknown',
            'created' => false,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * FGO auto-creates products by Denumire during invoice emission.
     */
    public function ensureProducts(array $lines): array
    {
        return array_map(function ($line) {
            return [
                'product_id' => $line['product_name'] ?? $line['description'] ?? 'unknown',
                'created' => false,
            ];
        }, $lines);
    }

    /**
     * {@inheritdoc}
     */
    public function createInvoice(array $invoice): array
    {
        if (!$this->authenticated) {
            throw new \RuntimeException('Not authenticated');
        }

        try {
            $customer = $invoice['customer'] ?? [];
            $clientDenumire = $customer['name'] ?? '';

            // Hash context for invoice creation = Client Denumire
            $hash = $this->generateHash($clientDenumire);

            // Determine client type: PF (person) or PJ (company)
            $clientTip = !empty($customer['vat_number']) ? 'PJ' : 'PF';

            // Build payload
            $payload = [
                'CodUnic' => $this->codUnic,
                'Hash' => $hash,
                'Serie' => $invoice['series'] ?? $this->serie,
                'Valuta' => $invoice['currency'] ?? 'RON',
                'TipFactura' => 'Factura',
                'DataEmitere' => $invoice['issue_date'] ?? date('Y-m-d'),
                'DataScadenta' => $invoice['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
                'Client' => [
                    'Denumire' => $clientDenumire,
                    'CodUnic' => $customer['vat_number'] ?? '',
                    'NrRegCom' => $customer['reg_number'] ?? '',
                    'Tara' => $customer['address']['country'] ?? 'Romania',
                    'Judet' => $customer['address']['county'] ?? '',
                    'Localitate' => $customer['address']['city'] ?? '',
                    'Adresa' => $customer['address']['street'] ?? '',
                    'Tip' => $clientTip,
                    'Email' => $customer['email'] ?? '',
                ],
                'Continut' => [],
            ];

            // Add line items
            foreach ($invoice['lines'] as $i => $line) {
                $payload['Continut'][] = [
                    'Denumire' => $line['product_name'] ?? $line['description'] ?? 'Serviciu',
                    'NrProduse' => $line['quantity'] ?? 1,
                    'PretUnitar' => $line['unit_price'] ?? 0,
                    'UM' => $line['unit'] ?? 'buc',
                    'CotaTVA' => $line['tax_rate'] ?? 19,
                ];
            }

            $response = Http::post("{$this->baseUrl}/factura/emitere", $payload);
            $data = $response->json();

            if ($response->successful() && ($data['Success'] ?? false)) {
                $factura = $data['Factura'] ?? [];

                return [
                    'external_ref' => ($factura['Serie'] ?? $this->serie) . '/' . ($factura['Numar'] ?? ''),
                    'invoice_number' => ($factura['Serie'] ?? '') . ' ' . ($factura['Numar'] ?? ''),
                    'details' => [
                        'link' => $factura['Link'] ?? null,
                        'serie' => $factura['Serie'] ?? null,
                        'numar' => $factura['Numar'] ?? null,
                    ],
                ];
            }

            throw new \RuntimeException('FGO invoice creation failed: ' . ($data['Message'] ?? 'Unknown error'));

        } catch (\Exception $e) {
            Log::error('FGO createInvoice failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoicePdf(string $externalRef): array
    {
        if (!$this->authenticated) {
            throw new \RuntimeException('Not authenticated');
        }

        try {
            // Parse external ref: "SERIE/NUMAR"
            $parts = explode('/', $externalRef);
            $serie = $parts[0] ?? $this->serie;
            $numar = $parts[1] ?? $externalRef;

            // Hash context for print = invoice Numar
            $hash = $this->generateHash($numar);

            $response = Http::post("{$this->baseUrl}/factura/print", [
                'CodUnic' => $this->codUnic,
                'Hash' => $hash,
                'Numar' => $numar,
                'Serie' => $serie,
            ]);

            $data = $response->json();

            if ($response->successful() && ($data['Success'] ?? false)) {
                $link = $data['Factura']['Link'] ?? null;

                return [
                    'pdf_url' => $link,
                    'pdf_content' => null,
                ];
            }

            return [
                'pdf_url' => null,
                'pdf_content' => null,
            ];

        } catch (\Exception $e) {
            Log::error('FGO getInvoicePdf failed', [
                'error' => $e->getMessage(),
                'external_ref' => $externalRef,
            ]);

            return [
                'pdf_url' => null,
                'pdf_content' => null,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createCreditNote(string $invoiceExternalRef, array $refund): array
    {
        if (!$this->authenticated) {
            throw new \RuntimeException('Not authenticated');
        }

        try {
            // Parse invoice ref: "SERIE/NUMAR"
            $parts = explode('/', $invoiceExternalRef);
            $serie = $parts[0] ?? $this->serie;
            $numar = $parts[1] ?? $invoiceExternalRef;

            // Hash context for stornare = invoice Numar
            $hash = $this->generateHash($numar);

            $payload = [
                'CodUnic' => $this->codUnic,
                'Hash' => $hash,
                'Numar' => $numar,
                'Serie' => $serie,
            ];

            // Optional: custom credit note series/number
            if (!empty($refund['series'])) {
                $payload['SerieStorno'] = $refund['series'];
            }
            if (!empty($refund['number'])) {
                $payload['NumarStorno'] = $refund['number'];
            }

            $response = Http::post("{$this->baseUrl}/factura/stornare", $payload);
            $data = $response->json();

            if ($response->successful() && ($data['Success'] ?? false)) {
                $factura = $data['Factura'] ?? [];

                return [
                    'external_ref' => ($factura['Serie'] ?? '') . '/' . ($factura['Numar'] ?? ''),
                    'credit_note_number' => ($factura['Serie'] ?? '') . ' ' . ($factura['Numar'] ?? ''),
                ];
            }

            throw new \RuntimeException('FGO credit note failed: ' . ($data['Message'] ?? 'Unknown error'));

        } catch (\Exception $e) {
            Log::error('FGO createCreditNote failed', [
                'error' => $e->getMessage(),
                'invoice_ref' => $invoiceExternalRef,
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomers(): array
    {
        if (!$this->authenticated) {
            throw new \RuntimeException('Not authenticated');
        }

        try {
            $hash = $this->generateHash();

            $response = Http::post("{$this->baseUrl}/client/list", [
                'CodUnic' => $this->codUnic,
                'Hash' => $hash,
                'NrPagina' => 1,
                'NrArticole' => 100,
            ]);

            $data = $response->json();

            if ($response->successful() && ($data['Success'] ?? false)) {
                return array_map(function ($client) {
                    return [
                        'id' => $client['CodUnic'] ?? $client['Denumire'],
                        'name' => $client['Denumire'] ?? '',
                        'vat_number' => $client['CodUnic'] ?? null,
                        'reg_number' => $client['NrRegCom'] ?? null,
                        'email' => $client['Email'] ?? null,
                        'address' => [
                            'street' => $client['Adresa'] ?? '',
                            'city' => $client['Localitate'] ?? '',
                            'county' => $client['Judet'] ?? '',
                            'country' => $client['Tara'] ?? 'Romania',
                        ],
                    ];
                }, $data['Clienti'] ?? []);
            }

            return [];

        } catch (\Exception $e) {
            Log::error('FGO getCustomers failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getProducts(): array
    {
        if (!$this->authenticated) {
            throw new \RuntimeException('Not authenticated');
        }

        try {
            $hash = $this->generateHash();

            $response = Http::post("{$this->baseUrl}/articol/list", [
                'CodUnic' => $this->codUnic,
                'Hash' => $hash,
                'NrPagina' => 1,
                'NrArticole' => 100,
            ]);

            $data = $response->json();

            if ($response->successful() && ($data['Success'] ?? false)) {
                return array_map(function ($articol) {
                    return [
                        'id' => $articol['Cod'] ?? $articol['Denumire'],
                        'name' => $articol['Denumire'] ?? '',
                        'code' => $articol['Cod'] ?? '',
                        'price' => $articol['PretUnitar'] ?? 0,
                        'unit' => $articol['UM'] ?? 'buc',
                        'vat_percentage' => $articol['CotaTVA'] ?? 19,
                    ];
                }, $data['Articole'] ?? []);
            }

            return [];

        } catch (\Exception $e) {
            Log::error('FGO getProducts failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Generate SHA-1 hash for FGO API authentication
     *
     * Hash = strtoupper(SHA1(CodUnic + CheiePrivata + contextValue))
     */
    protected function generateHash(string $contextValue = ''): string
    {
        return strtoupper(sha1($this->codUnic . $this->cheiePrivata . $contextValue));
    }
}
