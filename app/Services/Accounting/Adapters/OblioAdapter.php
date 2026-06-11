<?php

namespace App\Services\Accounting\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Oblio.eu Accounting Adapter
 *
 * Integrates with Oblio.eu API for automated invoice generation.
 * No sandbox environment — use draft invoices for testing.
 *
 * @see https://www.oblio.eu/api
 */
class OblioAdapter implements AccountingAdapterInterface
{
    protected bool $authenticated = false;
    protected array $credentials = [];
    protected string $baseUrl = 'https://www.oblio.eu';
    protected string $accessToken = '';
    protected string $cif = '';
    protected string $seriesName = '';
    protected string $proformaSeriesName = '';

    /**
     * {@inheritdoc}
     */
    public function authenticate(array $credentials): array
    {
        if (empty($credentials['client_id']) || empty($credentials['client_secret'])) {
            return [
                'success' => false,
                'message' => 'Lipsesc Client ID (email) sau Client Secret (API token) Oblio.',
            ];
        }

        $this->credentials = $credentials;
        $this->cif = $credentials['cif'] ?? '';
        $this->seriesName = $credentials['series_name'] ?? 'FACT';
        $this->proformaSeriesName = $credentials['proforma_series_name'] ?? '';

        try {
            $token = $this->getAccessToken();

            if ($token) {
                $this->authenticated = true;
                return [
                    'success' => true,
                    'message' => 'Autentificare Oblio.eu reușită.',
                ];
            }

            return [
                'success' => false,
                'message' => 'Autentificare Oblio.eu eșuată: token invalid.',
            ];
        } catch (\Exception $e) {
            Log::error('Oblio authentication failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Autentificare eșuată: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get OAuth2 access token (cached for 55 minutes)
     */
    protected function getAccessToken(): ?string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $cacheKey = 'oblio_token_' . md5($this->credentials['client_id']);

        $token = Cache::get($cacheKey);

        if ($token) {
            $this->accessToken = $token;
            return $token;
        }

        $response = Http::asForm()->post("{$this->baseUrl}/api/authorize/token", [
            'client_id' => $this->credentials['client_id'],
            'client_secret' => $this->credentials['client_secret'],
            'grant_type' => 'client_credentials',
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $token = $data['access_token'] ?? null;

            if ($token) {
                $this->accessToken = $token;
                Cache::put($cacheKey, $token, now()->addMinutes(55));
                return $token;
            }
        }

        $error = $response->json('statusMessage') ?? $response->body();
        throw new \RuntimeException("Oblio token request failed: {$error}");
    }

    /**
     * Make authenticated API request
     */
    protected function apiRequest(string $method, string $endpoint, array $data = []): array
    {
        $token = $this->getAccessToken();

        $request = Http::withToken($token)
            ->acceptJson();

        if ($method === 'GET') {
            $response = $request->get("{$this->baseUrl}{$endpoint}", $data);
        } else {
            $response = $request->post("{$this->baseUrl}{$endpoint}", $data);
        }

        if ($response->status() === 401) {
            // Token expired, clear cache and retry once
            $cacheKey = 'oblio_token_' . md5($this->credentials['client_id']);
            Cache::forget($cacheKey);
            $this->accessToken = '';

            $token = $this->getAccessToken();
            $request = Http::withToken($token)->acceptJson();

            if ($method === 'GET') {
                $response = $request->get("{$this->baseUrl}{$endpoint}", $data);
            } else {
                $response = $request->post("{$this->baseUrl}{$endpoint}", $data);
            }
        }

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $error = $response->json('statusMessage') ?? $response->body();
        $status = $response->status();
        throw new \RuntimeException("Oblio API error ({$status}): {$error}");
    }

    /**
     * {@inheritdoc}
     */
    public function testConnection(): array
    {
        if (!$this->authenticated) {
            return [
                'connected' => false,
                'message' => 'Nu este autentificat.',
                'details' => [],
            ];
        }

        try {
            // Test by fetching invoice series
            $result = $this->apiRequest('GET', '/api/nomenclature/series', [
                'cif' => $this->cif,
            ]);

            return [
                'connected' => true,
                'message' => 'Conexiune Oblio.eu reușită.',
                'details' => [
                    'series_count' => count($result['data'] ?? []),
                    'cif' => $this->cif,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'message' => 'Conexiune eșuată: ' . $e->getMessage(),
                'details' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     * Oblio auto-creates clients with save=1, so we just return the data.
     */
    public function ensureCustomer(array $customer): array
    {
        return [
            'customer_id' => $customer['vat_number'] ?? $customer['name'],
            'created' => false,
        ];
    }

    /**
     * {@inheritdoc}
     * Oblio auto-creates products during invoice creation.
     */
    public function ensureProducts(array $lines): array
    {
        $results = [];
        foreach ($lines as $line) {
            $results[] = [
                'product_id' => $line['product_name'] ?? $line['description'] ?? 'unknown',
                'created' => false,
            ];
        }
        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function createInvoice(array $invoice): array
    {
        if (!$this->authenticated) {
            throw new \RuntimeException('Nu este autentificat.');
        }

        try {
            $customer = $invoice['customer'] ?? [];
            $isDraft = $invoice['is_draft'] ?? false;
            $docType = $invoice['doc_type'] ?? 'invoice'; // 'invoice' or 'proforma'

            // Use proforma series if sending proforma, otherwise fiscal series
            if ($docType === 'proforma') {
                $resolvedSeries = $this->proformaSeriesName ?: ($invoice['series'] ?? $this->seriesName ?: 'PF');
            } else {
                $resolvedSeries = $this->seriesName ?: ($invoice['series'] ?? 'FACT');
            }

            // Determine API endpoint based on doc type
            $endpoint = $docType === 'proforma' ? '/api/docs/proforma' : '/api/docs/invoice';

            $payload = [
                'cif' => $this->cif ?: ($invoice['seller_vat'] ?? ''),
                'seriesName' => $resolvedSeries,
                'issueDate' => $invoice['issue_date'] ?? date('Y-m-d'),
                'dueDate' => $invoice['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
                'currency' => $invoice['currency'] ?? 'RON',
                'isDraft' => $isDraft,
                // Decide save/autocomplete based on whether the CIF looks
                // like an actual Romanian fiscal code (digits only, optional
                // "RO" prefix, 2–10 digits long). Anything else — empty,
                // "vanzare online", "FĂRĂ CIF", etc. — means B2C: don't
                // save in Oblio's customer list (otherwise Oblio matches by
                // name and reuses old data) and don't ANAF-lookup.
                'client' => (function () use ($customer) {
                    $cif = trim((string) ($customer['vat_number'] ?? ''));
                    $isRealCif = (bool) preg_match('/^(RO)?[0-9]{2,10}$/i', $cif);
                    return [
                        'name' => $customer['name'] ?? '',
                        'cif' => $cif,
                        'rc' => $customer['reg_number'] ?? '',
                        'address' => $customer['address']['street'] ?? '',
                        'city' => $customer['address']['city'] ?? '',
                        'county' => $customer['address']['county'] ?? '',
                        'country' => $customer['address']['country'] ?? 'Romania',
                        'email' => $customer['email'] ?? '',
                        'save' => $isRealCif ? 1 : 0,
                        'autocomplete' => $isRealCif ? 1 : 0,
                    ];
                })(),
                'products' => [],
            ];

            // Diagnostic: log the exact client block that goes to Oblio so we
            // can confirm general_client invoices ship cif='vanzare online'
            // with save=0 + autocomplete=0. Remove after verification.
            \Log::info('[OblioAdapter.createInvoice] outbound client block', [
                'docType' => $docType,
                'series' => $resolvedSeries,
                'client' => $payload['client'],
            ]);

            // Add line items
            foreach ($invoice['lines'] as $line) {
                $payload['products'][] = [
                    'name' => $line['product_name'] ?? $line['description'] ?? 'Serviciu',
                    'description' => $line['description'] ?? '',
                    'quantity' => (float) ($line['quantity'] ?? 1),
                    'price' => (float) ($line['unit_price'] ?? 0),
                    'measuringUnit' => $line['unit'] ?? 'buc',
                    'vatName' => $this->getVatName($line['tax_rate'] ?? 19),
                    'vatPercentage' => (int) ($line['tax_rate'] ?? 19),
                    'currency' => $invoice['currency'] ?? 'RON',
                    'productType' => 'Serviciu',
                    'save' => 1,
                ];
            }

            // Add idempotency key if invoice number is available
            if (!empty($invoice['number'])) {
                $payload['idempotencyKey'] = $docType . '_' . $invoice['number'] . '_' . md5(json_encode($payload['products']));
            }

            $result = $this->apiRequest('POST', $endpoint, $payload);

            $data = $result['data'] ?? $result;

            return [
                'external_ref' => ($data['seriesName'] ?? $resolvedSeries) . '/' . ($data['number'] ?? ''),
                'invoice_number' => ($data['seriesName'] ?? $resolvedSeries) . ($data['number'] ?? ''),
                'doc_type' => $docType,
                'details' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Oblio createInvoice failed', [
                'error' => $e->getMessage(),
                'invoice' => $invoice['number'] ?? 'unknown',
            ]);

            throw $e;
        }
    }

    /**
     * Get VAT name for Oblio based on rate
     */
    protected function getVatName(int $rate): string
    {
        return match ($rate) {
            0 => 'Normala',
            5 => 'Redusa',
            9 => 'Redusa',
            19 => 'Normala',
            default => 'Normala',
        };
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoicePdf(string $externalRef, string $docType = 'invoice'): array
    {
        if (!$this->authenticated) {
            throw new \RuntimeException('Nu este autentificat.');
        }

        try {
            $parts = explode('/', $externalRef);
            $seriesName = $parts[0] ?? $this->seriesName;
            $number = $parts[1] ?? $externalRef;

            $endpoint = $docType === 'proforma' ? '/api/docs/proforma' : '/api/docs/invoice';

            $result = $this->apiRequest('GET', $endpoint, [
                'cif' => $this->cif,
                'seriesName' => $seriesName,
                'number' => $number,
            ]);

            $data = $result['data'] ?? $result;

            return [
                'pdf_url' => $data['link'] ?? null,
                'pdf_content' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Oblio getInvoicePdf failed', [
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
            throw new \RuntimeException('Nu este autentificat.');
        }

        try {
            $parts = explode('/', $invoiceExternalRef);
            $seriesName = $parts[0] ?? $this->seriesName;
            $number = $parts[1] ?? $invoiceExternalRef;

            $payload = [
                'cif' => $this->cif,
                'seriesName' => $seriesName,
                'number' => $number,
            ];

            $result = $this->apiRequest('POST', '/api/docs/storno', $payload);
            $data = $result['data'] ?? $result;

            return [
                'external_ref' => ($data['seriesName'] ?? $seriesName) . '/' . ($data['number'] ?? ''),
                'credit_note_number' => ($data['seriesName'] ?? '') . ($data['number'] ?? ''),
            ];
        } catch (\Exception $e) {
            Log::error('Oblio createCreditNote failed', [
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
            throw new \RuntimeException('Nu este autentificat.');
        }

        try {
            $result = $this->apiRequest('GET', '/api/nomenclature/clients', [
                'cif' => $this->cif,
            ]);

            $clients = $result['data'] ?? [];

            return array_map(function ($client) {
                return [
                    'id' => $client['cif'] ?? $client['name'],
                    'name' => $client['name'] ?? '',
                    'vat_number' => $client['cif'] ?? null,
                    'reg_number' => $client['rc'] ?? null,
                    'email' => $client['email'] ?? null,
                    'address' => [
                        'street' => $client['address'] ?? '',
                        'city' => $client['city'] ?? '',
                        'county' => $client['county'] ?? '',
                        'country' => $client['country'] ?? 'Romania',
                    ],
                ];
            }, $clients);
        } catch (\Exception $e) {
            Log::error('Oblio getCustomers failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getProducts(): array
    {
        if (!$this->authenticated) {
            throw new \RuntimeException('Nu este autentificat.');
        }

        try {
            $result = $this->apiRequest('GET', '/api/nomenclature/products', [
                'cif' => $this->cif,
            ]);

            $products = $result['data'] ?? [];

            return array_map(function ($product) {
                return [
                    'id' => $product['name'] ?? '',
                    'name' => $product['name'] ?? '',
                    'code' => $product['code'] ?? '',
                    'price' => $product['price'] ?? 0,
                    'unit' => $product['measuringUnit'] ?? 'buc',
                    'vat_percentage' => $product['vatPercentage'] ?? 19,
                ];
            }, $products);
        } catch (\Exception $e) {
            Log::error('Oblio getProducts failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Submit invoice to e-Factura via Oblio
     */
    public function submitToEFactura(string $seriesName, string $number): array
    {
        if (!$this->authenticated) {
            throw new \RuntimeException('Nu este autentificat.');
        }

        $result = $this->apiRequest('POST', '/api/docs/einvoice', [
            'cif' => $this->cif,
            'seriesName' => $seriesName,
            'number' => $number,
        ]);

        return $result['data'] ?? $result;
    }

    /**
     * Get available invoice series
     */
    public function getSeries(): array
    {
        if (!$this->authenticated) {
            throw new \RuntimeException('Nu este autentificat.');
        }

        $result = $this->apiRequest('GET', '/api/nomenclature/series', [
            'cif' => $this->cif,
        ]);

        return $result['data'] ?? [];
    }
}
