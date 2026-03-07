<?php

namespace App\Services\Accounting\Adapters;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Keez Accounting Adapter
 *
 * Integrates with Keez.ro API for automated invoice generation.
 * Auth: OAuth2 client_credentials flow with 60-minute token TTL.
 *
 * @see https://app.keez.ro/help/api/
 */
class KeezAdapter implements AccountingAdapterInterface
{
    protected bool $authenticated = false;
    protected array $credentials = [];
    protected string $baseUrl = 'https://staging.keez.ro';
    protected string $applicationId = '';
    protected string $clientSecret = '';
    protected string $clientEid = '';
    protected ?string $accessToken = null;
    protected string $tokenType = 'Bearer';

    /**
     * {@inheritdoc}
     */
    public function authenticate(array $credentials): array
    {
        if (empty($credentials['application_id']) || empty($credentials['client_secret']) || empty($credentials['client_eid'])) {
            return [
                'success' => false,
                'message' => 'Missing Keez application_id, client_secret, or client_eid',
            ];
        }

        $this->applicationId = $credentials['application_id'];
        $this->clientSecret = $credentials['client_secret'];
        $this->clientEid = $credentials['client_eid'];
        $this->credentials = $credentials;

        // Set base URL based on environment
        $environment = $credentials['environment'] ?? 'staging';
        $this->baseUrl = $environment === 'production'
            ? 'https://app.keez.ro'
            : 'https://staging.keez.ro';

        // Get OAuth2 token
        $tokenResult = $this->getAccessToken();

        if (!$tokenResult) {
            return [
                'success' => false,
                'message' => 'Failed to obtain Keez OAuth2 token',
            ];
        }

        $this->authenticated = true;

        return [
            'success' => true,
            'message' => 'Keez authentication successful',
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
            $this->ensureToken();

            // Test with partners endpoint
            $response = Http::withToken($this->accessToken)
                ->get("{$this->baseUrl}/api/v1.0/public-api/{$this->clientEid}/partners", [
                    'pageSize' => 1,
                ]);

            if ($response->successful()) {
                return [
                    'connected' => true,
                    'message' => 'Keez connection successful',
                    'details' => [
                        'client_eid' => $this->clientEid,
                    ],
                ];
            }

            return [
                'connected' => false,
                'message' => 'Keez connection failed: HTTP ' . $response->status(),
                'details' => [],
            ];

        } catch (\Exception $e) {
            return [
                'connected' => false,
                'message' => 'Keez connection test failed: ' . $e->getMessage(),
                'details' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     *
     * Keez auto-creates partners during invoice submission (partner data embedded in invoice body).
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
     * Keez handles products within invoice details.
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
            $this->ensureToken();

            $customer = $invoice['customer'] ?? [];
            $lines = $invoice['lines'] ?? [];

            // Calculate totals
            $netAmount = 0;
            $vatAmount = 0;
            $invoiceDetails = [];

            foreach ($lines as $i => $line) {
                $qty = (float) ($line['quantity'] ?? 1);
                $price = (float) ($line['unit_price'] ?? 0);
                $taxRate = (float) ($line['tax_rate'] ?? 19);
                $lineNet = $qty * $price;
                $lineVat = round($lineNet * $taxRate / 100, 2);

                $netAmount += $lineNet;
                $vatAmount += $lineVat;

                $invoiceDetails[] = [
                    'itemExternalId' => (string) ($i + 1),
                    'measureUnitId' => 1,
                    'quantity' => $qty,
                    'unitPrice' => $price,
                    'originalNetAmount' => $lineNet,
                    'vatAmount' => $lineVat,
                    'grossAmount' => $lineNet + $lineVat,
                ];
            }

            $grossAmount = $netAmount + $vatAmount;

            // Format dates as YYYYMMDD integers
            $documentDate = (int) str_replace('-', '', $invoice['issue_date'] ?? date('Y-m-d'));
            $dueDate = (int) str_replace('-', '', $invoice['due_date'] ?? date('Y-m-d', strtotime('+30 days')));

            // Build partner data
            $isLegal = !empty($customer['vat_number']);
            $vatNumber = $customer['vat_number'] ?? '';
            $taxAttribute = '';
            if ($isLegal && str_starts_with($vatNumber, 'RO')) {
                $taxAttribute = 'RO';
                $vatNumber = substr($vatNumber, 2);
            }

            $payload = [
                'series' => $invoice['series'] ?? 'FACT',
                'documentDate' => $documentDate,
                'dueDate' => $dueDate,
                'vatOnCollection' => false,
                'currencyCode' => $invoice['currency'] ?? 'RON',
                'paymentTypeId' => 1,
                'originalNetAmount' => round($netAmount, 2),
                'originalVatAmount' => round($vatAmount, 2),
                'netAmount' => round($netAmount, 2),
                'vatAmount' => round($vatAmount, 2),
                'grossAmount' => round($grossAmount, 2),
                'partner' => [
                    'isLegalPerson' => $isLegal,
                    'partnerName' => $customer['name'] ?? '',
                    'identificationNumber' => $vatNumber,
                    'taxAttribute' => $taxAttribute,
                    'countryCode' => $customer['address']['country'] ?? 'RO',
                    'countryName' => 'Romania',
                    'countyCode' => $customer['address']['county_code'] ?? '',
                    'countyName' => $customer['address']['county'] ?? '',
                    'cityName' => $customer['address']['city'] ?? '',
                    'addressDetails' => $customer['address']['street'] ?? '',
                    'registrationNumber' => $customer['reg_number'] ?? '',
                ],
                'invoiceDetails' => $invoiceDetails,
            ];

            $response = Http::withToken($this->accessToken)
                ->post("{$this->baseUrl}/api/v1.0/public-api/{$this->clientEid}/invoices", $payload);

            if ($response->successful()) {
                $data = $response->json();
                $externalId = $data['externalId'] ?? $data['id'] ?? null;

                return [
                    'external_ref' => (string) $externalId,
                    'invoice_number' => ($invoice['series'] ?? 'FACT') . ' ' . ($data['number'] ?? $externalId),
                    'details' => $data,
                ];
            }

            $errorBody = $response->json();
            $errorMsg = $errorBody['message'] ?? $errorBody['title'] ?? 'HTTP ' . $response->status();
            throw new \RuntimeException('Keez invoice creation failed: ' . $errorMsg);

        } catch (\Exception $e) {
            Log::error('Keez createInvoice failed', [
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
            $this->ensureToken();

            $response = Http::withToken($this->accessToken)
                ->get("{$this->baseUrl}/api/v1.0/public-api/{$this->clientEid}/invoices/{$externalRef}/pdf");

            if ($response->successful()) {
                return [
                    'pdf_url' => null,
                    'pdf_content' => $response->body(),
                ];
            }

            return [
                'pdf_url' => null,
                'pdf_content' => null,
            ];

        } catch (\Exception $e) {
            Log::error('Keez getInvoicePdf failed', [
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
            $this->ensureToken();

            // Keez creates credit notes as invoices with negative amounts
            $refundInvoice = $refund;
            $refundInvoice['series'] = $refund['series'] ?? 'STORNO';

            // Negate amounts
            foreach ($refundInvoice['lines'] ?? [] as &$line) {
                $line['unit_price'] = -abs($line['unit_price'] ?? 0);
            }

            $result = $this->createInvoice($refundInvoice);

            return [
                'external_ref' => $result['external_ref'],
                'credit_note_number' => $result['invoice_number'],
            ];

        } catch (\Exception $e) {
            Log::error('Keez createCreditNote failed', [
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
            $this->ensureToken();

            $response = Http::withToken($this->accessToken)
                ->get("{$this->baseUrl}/api/v1.0/public-api/{$this->clientEid}/partners");

            if ($response->successful()) {
                $data = $response->json();
                $partners = $data['items'] ?? $data ?? [];

                return array_map(function ($partner) {
                    return [
                        'id' => $partner['externalId'] ?? $partner['identificationNumber'] ?? '',
                        'name' => $partner['partnerName'] ?? '',
                        'vat_number' => ($partner['taxAttribute'] ?? '') . ($partner['identificationNumber'] ?? ''),
                        'reg_number' => $partner['registrationNumber'] ?? null,
                        'email' => null,
                        'address' => [
                            'street' => $partner['addressDetails'] ?? '',
                            'city' => $partner['cityName'] ?? '',
                            'county' => $partner['countyName'] ?? '',
                            'country' => $partner['countryCode'] ?? 'RO',
                        ],
                    ];
                }, $partners);
            }

            return [];

        } catch (\Exception $e) {
            Log::error('Keez getCustomers failed', ['error' => $e->getMessage()]);
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
            $this->ensureToken();

            $response = Http::withToken($this->accessToken)
                ->get("{$this->baseUrl}/api/v1.0/public-api/{$this->clientEid}/products");

            if ($response->successful()) {
                $data = $response->json();
                $products = $data['items'] ?? $data ?? [];

                return array_map(function ($product) {
                    return [
                        'id' => $product['externalId'] ?? $product['name'] ?? '',
                        'name' => $product['name'] ?? '',
                        'code' => $product['code'] ?? '',
                        'price' => $product['unitPrice'] ?? 0,
                        'unit' => $product['measureUnit'] ?? 'buc',
                        'vat_percentage' => $product['vatRate'] ?? 19,
                    ];
                }, $products);
            }

            return [];

        } catch (\Exception $e) {
            Log::error('Keez getProducts failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Submit invoice to eFactura via Keez (built-in eFactura support)
     */
    public function submitEFactura(string $externalRef): array
    {
        if (!$this->authenticated) {
            throw new \RuntimeException('Not authenticated');
        }

        try {
            $this->ensureToken();

            $response = Http::withToken($this->accessToken)
                ->post("{$this->baseUrl}/api/v1.0/public-api/{$this->clientEid}/invoices/efactura/submitted", [
                    'externalId' => $externalRef,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Invoice submitted to eFactura via Keez',
                ];
            }

            return [
                'success' => false,
                'message' => 'eFactura submission failed: HTTP ' . $response->status(),
            ];

        } catch (\Exception $e) {
            Log::error('Keez submitEFactura failed', [
                'error' => $e->getMessage(),
                'external_ref' => $externalRef,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtain OAuth2 access token via client_credentials grant
     */
    protected function getAccessToken(): bool
    {
        $cacheKey = "keez_token_{$this->applicationId}";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached) {
            $this->accessToken = $cached['access_token'];
            $this->tokenType = $cached['token_type'] ?? 'Bearer';
            return true;
        }

        try {
            $response = Http::asForm()->post("{$this->baseUrl}/idp/connect/token", [
                'client_id' => "app{$this->applicationId}",
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials',
                'scope' => 'public-api',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->accessToken = $data['access_token'];
                $this->tokenType = $data['token_type'] ?? 'Bearer';

                // Cache for 55 minutes (token expires in 60)
                $expiresIn = ($data['expires_in'] ?? 3600) - 300;
                Cache::put($cacheKey, $data, max($expiresIn, 60));

                return true;
            }

            Log::error('Keez OAuth2 token request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Keez OAuth2 token request exception', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Ensure we have a valid access token
     */
    protected function ensureToken(): void
    {
        if (!$this->accessToken) {
            if (!$this->getAccessToken()) {
                throw new \RuntimeException('Failed to obtain Keez access token');
            }
        }
    }
}
