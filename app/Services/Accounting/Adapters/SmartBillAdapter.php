<?php

namespace App\Services\Accounting\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SmartBill Accounting Adapter
 *
 * Integrates with SmartBill Cloud API for automated invoice generation
 * in Romanian accounting systems.
 *
 * @see https://www.smartbill.ro/api/
 */
class SmartBillAdapter implements AccountingAdapterInterface
{
    protected bool $authenticated = false;
    protected array $credentials = [];
    protected string $baseUrl = 'https://ws.smartbill.ro/SBORO/api';
    protected string $username = '';
    protected string $token = '';

    /**
     * {@inheritdoc}
     */
    public function authenticate(array $credentials): array
    {
        if (empty($credentials['username']) || empty($credentials['token'])) {
            return [
                'success' => false,
                'message' => 'Missing SmartBill username or API token',
            ];
        }

        $this->username = $credentials['username'];
        $this->token = $credentials['token'];
        $this->credentials = $credentials;

        // Test authentication by fetching company info
        try {
            $response = Http::withBasicAuth($this->username, $this->token)
                ->get("{$this->baseUrl}/company");

            if ($response->successful()) {
                $this->authenticated = true;

                return [
                    'success' => true,
                    'message' => 'SmartBill authentication successful',
                ];
            }

            return [
                'success' => false,
                'message' => 'SmartBill authentication failed: ' . ($response->json('errorText') ?? 'Invalid credentials'),
            ];

        } catch (\Exception $e) {
            Log::error('SmartBill authentication failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Authentication failed: ' . $e->getMessage(),
            ];
        }
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
            $response = Http::withBasicAuth($this->username, $this->token)
                ->get("{$this->baseUrl}/company");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'connected' => true,
                    'message' => 'Connection successful',
                    'details' => [
                        'company_name' => $data['name'] ?? 'Unknown',
                        'vat_code' => $data['vatCode'] ?? null,
                    ],
                ];
            }

            return [
                'connected' => false,
                'message' => 'Connection failed: ' . ($response->json('errorText') ?? 'Unknown error'),
                'details' => [],
            ];

        } catch (\Exception $e) {
            return [
                'connected' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
                'details' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function ensureCustomer(array $customer): array
    {
        if (!$this->authenticated) {
            throw new \RuntimeException('Not authenticated');
        }

        try {
            // Check if customer already exists by VAT number
            if (!empty($customer['vat_number'])) {
                $existing = $this->findCustomerByVat($customer['vat_number']);

                if ($existing) {
                    return [
                        'customer_id' => $existing['id'],
                        'created' => false,
                    ];
                }
            }

            // Create new customer
            $payload = [
                'name' => $customer['name'],
                'vatCode' => $customer['vat_number'] ?? '',
                'regCom' => $customer['reg_number'] ?? '',
                'address' => $customer['address']['street'] ?? '',
                'city' => $customer['address']['city'] ?? '',
                'county' => $customer['address']['county'] ?? '',
                'country' => $customer['address']['country'] ?? 'Romania',
                'email' => $customer['email'] ?? '',
                'phone' => $customer['phone'] ?? '',
                'isTaxPayer' => !empty($customer['vat_number']),
            ];

            $response = Http::withBasicAuth($this->username, $this->token)
                ->post("{$this->baseUrl}/client", $payload);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'customer_id' => $data['id'] ?? $customer['vat_number'],
                    'created' => true,
                ];
            }

            // If customer already exists, SmartBill returns specific error
            if ($response->status() === 400 && str_contains($response->body(), 'exists')) {
                return [
                    'customer_id' => $customer['vat_number'],
                    'created' => false,
                ];
            }

            throw new \RuntimeException('Failed to create customer: ' . ($response->json('errorText') ?? 'Unknown error'));

        } catch (\Exception $e) {
            Log::error('SmartBill ensureCustomer failed', [
                'error' => $e->getMessage(),
                'customer' => $customer['name'] ?? 'unknown',
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function ensureProducts(array $lines): array
    {
        if (!$this->authenticated) {
            throw new \RuntimeException('Not authenticated');
        }

        $results = [];

        try {
            foreach ($lines as $line) {
                $productName = $line['product_name'] ?? $line['description'] ?? 'Unknown Product';

                // Check if product exists
                $existing = $this->findProductByName($productName);

                if ($existing) {
                    $results[] = [
                        'product_id' => $existing['id'],
                        'created' => false,
                    ];
                    continue;
                }

                // Create new product
                $payload = [
                    'name' => $productName,
                    'code' => $line['product_code'] ?? strtoupper(substr(md5($productName), 0, 8)),
                    'measuringUnit' => $line['unit'] ?? 'buc',
                    'currency' => 'RON',
                    'price' => $line['unit_price'] ?? 0,
                    'vatPercentage' => $line['tax_rate'] ?? 19, // Default Romanian VAT
                    'productType' => 'Serviciu', // Default to service
                ];

                $response = Http::withBasicAuth($this->username, $this->token)
                    ->post("{$this->baseUrl}/products", $payload);

                if ($response->successful()) {
                    $data = $response->json();

                    $results[] = [
                        'product_id' => $data['id'] ?? $payload['code'],
                        'created' => true,
                    ];
                } else {
                    // If product exists, that's OK
                    $results[] = [
                        'product_id' => $payload['code'],
                        'created' => false,
                    ];
                }
            }

            return $results;

        } catch (\Exception $e) {
            Log::error('SmartBill ensureProducts failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
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
            // Ensure customer exists first
            $customerResult = $this->ensureCustomer($invoice['customer']);

            // Ensure products exist
            $this->ensureProducts($invoice['lines']);

            // Build invoice payload
            $payload = [
                'companyVatCode' => $invoice['seller_vat'] ?? $this->credentials['company_vat'] ?? '',
                'client' => [
                    'name' => $invoice['customer']['name'],
                    'vatCode' => $invoice['customer']['vat_number'] ?? '',
                    'regCom' => $invoice['customer']['reg_number'] ?? '',
                    'address' => $invoice['customer']['address']['street'] ?? '',
                    'city' => $invoice['customer']['address']['city'] ?? '',
                    'county' => $invoice['customer']['address']['county'] ?? '',
                    'country' => $invoice['customer']['address']['country'] ?? 'Romania',
                    'email' => $invoice['customer']['email'] ?? '',
                    'isTaxPayer' => !empty($invoice['customer']['vat_number']),
                ],
                'issueDate' => $invoice['issue_date'] ?? date('Y-m-d'),
                'dueDate' => $invoice['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
                'seriesName' => $invoice['series'] ?? 'EPAS',
                'number' => $invoice['number'] ?? null, // SmartBill auto-generates if null
                'currency' => $invoice['currency'] ?? 'RON',
                'precision' => 2,
                'products' => [],
                'isDraft' => $invoice['is_draft'] ?? false,
            ];

            // Add line items
            foreach ($invoice['lines'] as $line) {
                $payload['products'][] = [
                    'name' => $line['product_name'] ?? $line['description'],
                    'code' => $line['product_code'] ?? '',
                    'quantity' => $line['quantity'],
                    'price' => $line['unit_price'],
                    'measuringUnit' => $line['unit'] ?? 'buc',
                    'vatPercentage' => $line['tax_rate'] ?? 19,
                    'currency' => $invoice['currency'] ?? 'RON',
                    'productType' => 'Serviciu',
                ];
            }

            // Add payment information if provided
            if (!empty($invoice['payment'])) {
                $payload['payment'] = [
                    'isCash' => $invoice['payment']['is_cash'] ?? false,
                    'value' => $invoice['payment']['amount'] ?? 0,
                ];
            }

            $response = Http::withBasicAuth($this->username, $this->token)
                ->post("{$this->baseUrl}/invoice", $payload);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'external_ref' => $data['url'] ?? $data['number'] ?? 'unknown',
                    'invoice_number' => $data['number'] ?? $data['series'] . '-' . ($data['number'] ?? ''),
                    'details' => $data,
                ];
            }

            throw new \RuntimeException('Failed to create invoice: ' . ($response->json('errorText') ?? 'Unknown error'));

        } catch (\Exception $e) {
            Log::error('SmartBill createInvoice failed', [
                'error' => $e->getMessage(),
                'invoice' => $invoice['invoice_number'] ?? 'unknown',
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
            // SmartBill uses series and number for PDF retrieval
            // Parse external ref if it contains both
            $parts = explode('/', $externalRef);
            $series = $parts[0] ?? 'EPAS';
            $number = $parts[1] ?? $externalRef;

            $response = Http::withBasicAuth($this->username, $this->token)
                ->get("{$this->baseUrl}/invoice/pdf", [
                    'cif' => $this->credentials['company_vat'] ?? '',
                    'seriesname' => $series,
                    'number' => $number,
                ]);

            if ($response->successful()) {
                // SmartBill returns PDF as base64
                $pdfContent = base64_decode($response->body());

                return [
                    'pdf_url' => null, // SmartBill doesn't provide direct URLs
                    'pdf_content' => $pdfContent,
                ];
            }

            return [
                'pdf_url' => null,
                'pdf_content' => null,
            ];

        } catch (\Exception $e) {
            Log::error('SmartBill getInvoicePdf failed', [
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
            // Parse invoice reference
            $parts = explode('/', $invoiceExternalRef);
            $series = $parts[0] ?? 'EPAS';
            $number = $parts[1] ?? $invoiceExternalRef;

            $payload = [
                'companyVatCode' => $this->credentials['company_vat'] ?? '',
                'issueDate' => date('Y-m-d'),
                'seriesName' => $refund['series'] ?? $series,
                'refInvoiceSeriesName' => $series,
                'refInvoiceNumber' => $number,
                'precision' => 2,
                'products' => [],
            ];

            // Add refund line items
            foreach ($refund['lines'] ?? [] as $line) {
                $payload['products'][] = [
                    'name' => $line['product_name'] ?? $line['description'],
                    'quantity' => $line['quantity'],
                    'price' => $line['unit_price'],
                    'measuringUnit' => $line['unit'] ?? 'buc',
                    'vatPercentage' => $line['tax_rate'] ?? 19,
                ];
            }

            $response = Http::withBasicAuth($this->username, $this->token)
                ->post("{$this->baseUrl}/estimate", $payload); // SmartBill uses same endpoint

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'external_ref' => $data['url'] ?? $data['number'],
                    'credit_note_number' => $data['number'],
                ];
            }

            throw new \RuntimeException('Failed to create credit note: ' . ($response->json('errorText') ?? 'Unknown error'));

        } catch (\Exception $e) {
            Log::error('SmartBill createCreditNote failed', [
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
            $response = Http::withBasicAuth($this->username, $this->token)
                ->get("{$this->baseUrl}/clients", [
                    'cif' => $this->credentials['company_vat'] ?? '',
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return array_map(function ($customer) {
                    return [
                        'id' => $customer['vatCode'] ?? $customer['name'],
                        'name' => $customer['name'],
                        'vat_number' => $customer['vatCode'] ?? null,
                        'reg_number' => $customer['regCom'] ?? null,
                        'email' => $customer['email'] ?? null,
                        'address' => [
                            'street' => $customer['address'] ?? '',
                            'city' => $customer['city'] ?? '',
                            'county' => $customer['county'] ?? '',
                            'country' => $customer['country'] ?? 'Romania',
                        ],
                    ];
                }, $data['list'] ?? []);
            }

            return [];

        } catch (\Exception $e) {
            Log::error('SmartBill getCustomers failed', [
                'error' => $e->getMessage(),
            ]);

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
            $response = Http::withBasicAuth($this->username, $this->token)
                ->get("{$this->baseUrl}/products", [
                    'cif' => $this->credentials['company_vat'] ?? '',
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return array_map(function ($product) {
                    return [
                        'id' => $product['code'] ?? $product['name'],
                        'name' => $product['name'],
                        'code' => $product['code'] ?? '',
                        'price' => $product['price'] ?? 0,
                        'unit' => $product['measuringUnit'] ?? 'buc',
                        'vat_percentage' => $product['vatPercentage'] ?? 19,
                    ];
                }, $data['list'] ?? []);
            }

            return [];

        } catch (\Exception $e) {
            Log::error('SmartBill getProducts failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Find customer by VAT number
     */
    protected function findCustomerByVat(string $vatNumber): ?array
    {
        try {
            $customers = $this->getCustomers();

            foreach ($customers as $customer) {
                if ($customer['vat_number'] === $vatNumber) {
                    return $customer;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('SmartBill findCustomerByVat failed', [
                'error' => $e->getMessage(),
                'vat_number' => $vatNumber,
            ]);

            return null;
        }
    }

    /**
     * Find product by name
     */
    protected function findProductByName(string $name): ?array
    {
        try {
            $products = $this->getProducts();

            foreach ($products as $product) {
                if ($product['name'] === $name) {
                    return $product;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('SmartBill findProductByName failed', [
                'error' => $e->getMessage(),
                'name' => $name,
            ]);

            return null;
        }
    }
}
