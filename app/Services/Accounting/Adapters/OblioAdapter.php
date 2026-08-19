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
                        'code' => trim((string) ($customer['code'] ?? '')),
                        'cif' => $cif,
                        'rc' => $customer['reg_number'] ?? '',
                        'address' => $customer['address']['street'] ?? '',
                        'city' => $customer['address']['city'] ?? '',
                        'county' => $customer['address']['county'] ?? '',
                        'country' => $customer['address']['country'] ?? 'Romania',
                        'email' => $customer['email'] ?? '',
                        'save' => isset($customer['save']) ? (int) $customer['save'] : ($isRealCif ? 1 : 0),
                        'autocomplete' => isset($customer['autocomplete']) ? (int) $customer['autocomplete'] : ($isRealCif ? 1 : 0),
                    ];
                })(),
                'products' => [],
            ];

            // Optional issuer override — Oblio prints these at the bottom
            // of the generated invoice ("Întocmit de: {name}, CNP {cnp}").
            // Sourced from marketplace settings.invoice_preparer /
            // invoice_preparer_cnp. Sent only when filled; omitting them
            // falls back to whatever's set on the Oblio account itself.
            $issuerName = trim((string) ($invoice['issuer_name'] ?? ''));
            $issuerCnp = trim((string) ($invoice['issuer_cnp'] ?? ''));
            if ($issuerName !== '') {
                $payload['issuerName'] = $issuerName;
            }
            if ($issuerCnp !== '') {
                $payload['issuerCnp'] = $issuerCnp;
            }

            // Diagnostic: log the exact client block that goes to Oblio so we
            // can confirm general_client invoices ship cif='vanzare online'
            // with save=0 + autocomplete=0. Remove after verification.
            \Log::info('[OblioAdapter.createInvoice] outbound client block', [
                'docType' => $docType,
                'series' => $resolvedSeries,
                'client' => $payload['client'],
                'issuerName' => $issuerName ?: '(empty)',
                'issuerCnp' => $issuerCnp !== '' ? '(set)' : '(empty)',
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
     * Check whether an invoice / proforma still exists in Oblio + which
     * voided sub-state it's in.
     *
     * The simple GET /api/docs/invoice endpoint returns success for BOTH
     * live and canceled fiscals — Oblio strips the cancellation flag from
     * that view (verified empirically 2026-08 on AMBFF/2349). The reliable
     * source of truth is /api/docs/invoice/list, which:
     *   - HIDES a canceled invoice from the returned list (Oblio "Anulează")
     *   - INCLUDES stornoed invoices with stornoed="1" (credit note issued)
     *   - INCLUDES live invoices with canceled="0" stornoed="0"
     *
     * Detection algorithm:
     *   1. GET /api/docs/invoice → 404 → DELETED (proforma) / gone
     *   2. GET /api/docs/invoice/list(issuedAfter/issuedBefore = issue_date ± 3d)
     *      - factura găsită în listă → live sau stornoed pe baza flag-urilor
     *      - factura NU în listă (dar simple-GET success) → CANCELED (anulată)
     *
     * Return contract:
     *   ['exists' => true,  'canceled' => bool, 'has_credit_note' => bool, 'raw' => ...]
     *   ['exists' => false, 'reason'   => 'not_found']
     *   ['exists' => null,  'reason'   => 'error', 'message' => ...]
     *
     * @param string      $externalRef "SERIES/NUMBER"
     * @param string      $docType 'invoice' | 'proforma'
     * @param string|null $issueDate ISO date used to narrow the listing
     *                    window. Highly recommended for accuracy — without
     *                    it we fall back to the last 90 days, which risks
     *                    truncation past 100 items and misclassifying a
     *                    live invoice as canceled.
     */
    public function getInvoiceStatus(string $externalRef, string $docType = 'invoice', ?string $issueDate = null): array
    {
        if (!$this->authenticated) {
            return ['exists' => null, 'reason' => 'error', 'message' => 'Nu este autentificat.'];
        }

        $parts = explode('/', $externalRef);
        $seriesName = $parts[0] ?? $this->seriesName;
        $number = $parts[1] ?? $externalRef;

        // Step 1 — simple GET. Confirms existence + surfaces 404 for
        // deleted proformas. Same call as before; response format is
        // documented as {documentType, seriesName, number, total, link,
        // einvoice, collects} with NO cancellation flag.
        $endpoint = $docType === 'proforma' ? '/api/docs/proforma' : '/api/docs/invoice';
        try {
            $simple = $this->apiRequest('GET', $endpoint, [
                'cif' => $this->cif,
                'seriesName' => $seriesName,
                'number' => $number,
            ]);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, '(404)') || stripos($msg, 'not found') !== false || stripos($msg, 'nu exist') !== false) {
                return ['exists' => false, 'reason' => 'not_found'];
            }
            Log::warning('Oblio getInvoiceStatus simple GET failed', [
                'external_ref' => $externalRef,
                'doc_type' => $docType,
                'error' => $msg,
            ]);
            return ['exists' => null, 'reason' => 'error', 'message' => $msg];
        }
        $simpleData = $simple['data'] ?? $simple;

        // Proformas: no listing lookup — Oblio's list endpoint is
        // fiscal-only, so trust the simple GET (proformas either exist
        // and are live, or 404 → deleted, handled above).
        if ($docType === 'proforma') {
            return [
                'exists' => true,
                'canceled' => false,
                'has_credit_note' => false,
                'raw' => $simpleData,
            ];
        }

        // Step 2 — listing lookup for the true cancel/storno state.
        // Range ± 3 days around the invoice's issue date keeps us well
        // under the 100-item page cap for typical Ambilet volume; the 90d
        // fallback is a last resort when the caller didn't tell us when
        // the invoice was issued (see phpdoc warning).
        try {
            [$after, $before] = $this->buildListingWindow($issueDate);
            $listing = $this->apiRequest('GET', '/api/docs/invoice/list', [
                'cif' => $this->cif,
                'seriesName' => $seriesName,
                'issuedAfter' => $after,
                'issuedBefore' => $before,
            ]);
            $rows = $listing['data'] ?? [];
            $windowFull = is_array($rows) && count($rows) >= 100;
            $matched = null;
            foreach (($rows ?? []) as $row) {
                if (($row['seriesName'] ?? '') === $seriesName && (string) ($row['number'] ?? '') === (string) $number) {
                    $matched = $row;
                    break;
                }
            }

            if ($matched === null) {
                // Not in the listing but exists via simple GET → Oblio
                // hides canceled invoices from list. Safeguard: only
                // classify as canceled when we're SURE the window wasn't
                // truncated. If it was, return unknown so the operator
                // sees an explicit "reverify" prompt instead of a wrong
                // "canceled" badge.
                if ($windowFull) {
                    return [
                        'exists' => null,
                        'reason' => 'error',
                        'message' => 'Fereastra de listare Oblio a atins limita (100). Nu pot spune sigur dacă factura e activă sau anulată — trece o issue_date mai precisă sau retestează cu un range mai strâns.',
                    ];
                }
                return [
                    'exists' => true,
                    'canceled' => true,
                    'has_credit_note' => false,
                    'raw' => ['simple' => $simpleData, 'listing' => 'not_present'],
                ];
            }

            // Found in listing — trust its flags. Oblio uses string "0"/"1"
            // for these booleans (verified 2026-08).
            $canceled = ($matched['canceled'] ?? '0') === '1'
                || ($matched['stornoed'] ?? '0') === '1';
            $hasCreditNote = ($matched['stornoed'] ?? '0') === '1';

            return [
                'exists' => true,
                'canceled' => $canceled,
                'has_credit_note' => $hasCreditNote,
                'raw' => $matched,
            ];
        } catch (\Exception $e) {
            Log::warning('Oblio getInvoiceStatus listing lookup failed', [
                'external_ref' => $externalRef,
                'error' => $e->getMessage(),
            ]);
            // Simple GET already confirmed existence — fall back to
            // treating as live rather than lying about a cancel state
            // we couldn't verify.
            return [
                'exists' => true,
                'canceled' => false,
                'has_credit_note' => false,
                'raw' => $simpleData,
            ];
        }
    }

    /**
     * Build (issuedAfter, issuedBefore) window for /api/docs/invoice/list.
     * With an issue date: ± 3 days. Without: last 90 days ending today.
     */
    protected function buildListingWindow(?string $issueDate): array
    {
        try {
            $anchor = $issueDate ? \Carbon\Carbon::parse($issueDate) : \Carbon\Carbon::now();
        } catch (\Throwable $e) {
            $anchor = \Carbon\Carbon::now();
        }
        if ($issueDate) {
            return [
                $anchor->copy()->subDays(3)->format('Y-m-d'),
                $anchor->copy()->addDays(3)->format('Y-m-d'),
            ];
        }
        return [
            $anchor->copy()->subDays(90)->format('Y-m-d'),
            $anchor->format('Y-m-d'),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Oblio supports deletion of proformas via DELETE /api/docs/proforma.
     * Fiscal invoices can only be storno-ed (via createCreditNote), not
     * deleted — trying to delete one would fail server-side; we surface
     * that as supported=false so the caller knows to skip / use storno.
     */
    public function deleteInvoice(string $externalRef, string $docType = 'invoice'): array
    {
        if (!$this->authenticated) {
            return ['success' => false, 'message' => 'Nu este autentificat.', 'supported' => false];
        }
        if ($docType !== 'proforma') {
            return [
                'success' => false,
                'message' => 'Oblio nu permite ștergerea facturilor fiscale — folosește storno (createCreditNote).',
                'supported' => false,
            ];
        }

        try {
            $parts = explode('/', $externalRef);
            $seriesName = $parts[0] ?? $this->proformaSeriesName;
            $number = $parts[1] ?? $externalRef;

            $this->apiRequest('DELETE', '/api/docs/proforma', [
                'cif' => $this->cif,
                'seriesName' => $seriesName,
                'number' => $number,
            ]);

            return ['success' => true, 'message' => 'Proforma ștearsă din Oblio.', 'supported' => true];
        } catch (\Exception $e) {
            Log::warning('Oblio deleteInvoice failed', [
                'error' => $e->getMessage(),
                'external_ref' => $externalRef,
                'doc_type' => $docType,
            ]);
            return ['success' => false, 'message' => $e->getMessage(), 'supported' => true];
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
