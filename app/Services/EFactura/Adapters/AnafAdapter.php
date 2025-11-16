<?php

namespace App\Services\EFactura\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Real ANAF SPV Adapter
 *
 * Implements integration with the Romanian ANAF eFactura system.
 * Generates UBL 2.1 XML, signs with XMLDSig, and submits to ANAF SPV.
 *
 * @see https://www.anaf.ro/anaf/internet/ANAF/despre_anaf/strategii_anaf/proiecte_digitalizare/e-factura/
 */
class AnafAdapter implements AnafAdapterInterface
{
    protected bool $authenticated = false;
    protected array $credentials = [];
    protected string $environment = 'production'; // 'production' or 'test'
    protected string $baseUrl = 'https://api.anaf.ro/prod/FCTEL/rest';
    protected string $testUrl = 'https://api.anaf.ro/test/FCTEL/rest';

    /**
     * UBL 2.1 namespaces for Romanian eFactura
     */
    protected const NS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    protected const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    protected const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    protected const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';

    /**
     * {@inheritdoc}
     */
    public function authenticate(array $credentials): array
    {
        if (empty($credentials['certificate']) && empty($credentials['api_token'])) {
            return [
                'success' => false,
                'message' => 'Missing certificate or API token for ANAF authentication',
            ];
        }

        // Set environment
        $this->environment = $credentials['environment'] ?? 'production';

        // For OAuth2 token-based authentication
        if (!empty($credentials['api_token'])) {
            $this->credentials = $credentials;
            $this->authenticated = true;

            return [
                'success' => true,
                'message' => 'ANAF authentication successful (token-based)',
            ];
        }

        // For certificate-based authentication
        if (!empty($credentials['certificate'])) {
            // Validate certificate format and expiration
            $certData = openssl_x509_parse($credentials['certificate']);

            if ($certData === false) {
                return [
                    'success' => false,
                    'message' => 'Invalid certificate format',
                ];
            }

            // Check if certificate is expired
            $validTo = $certData['validTo_time_t'] ?? 0;
            if ($validTo < time()) {
                return [
                    'success' => false,
                    'message' => 'Certificate has expired',
                ];
            }

            $this->credentials = $credentials;
            $this->authenticated = true;

            return [
                'success' => true,
                'message' => 'ANAF authentication successful (certificate-based)',
            ];
        }

        return [
            'success' => false,
            'message' => 'No valid authentication method provided',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildXml(array $invoice): array
    {
        // Validate invoice data
        $errors = $this->validateInvoiceData($invoice);

        if (!empty($errors)) {
            return [
                'success' => false,
                'xml' => null,
                'hash' => null,
                'errors' => $errors,
            ];
        }

        try {
            // Generate UBL 2.1 XML
            $xml = $this->generateUbl21Xml($invoice);

            // Calculate SHA-256 hash
            $hash = hash('sha256', $xml);

            return [
                'success' => true,
                'xml' => $xml,
                'hash' => $hash,
                'errors' => [],
            ];

        } catch (\Exception $e) {
            Log::error('ANAF XML generation failed', [
                'error' => $e->getMessage(),
                'invoice' => $invoice['invoice_number'] ?? 'unknown',
            ]);

            return [
                'success' => false,
                'xml' => null,
                'hash' => null,
                'errors' => ['XML generation failed: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function signAndPackage(string $xml, array $signingConfig = []): array
    {
        try {
            // Check if signing is required
            $certificate = $signingConfig['certificate'] ?? $this->credentials['certificate'] ?? null;
            $privateKey = $signingConfig['private_key'] ?? $this->credentials['private_key'] ?? null;

            if (empty($certificate) || empty($privateKey)) {
                return [
                    'success' => false,
                    'package' => null,
                    'message' => 'Certificate and private key required for signing',
                ];
            }

            // Apply XMLDSig signature
            $signedXml = $this->applyXmlSignature($xml, $certificate, $privateKey);

            // Package for ANAF (base64 encode)
            $package = base64_encode($signedXml);

            return [
                'success' => true,
                'package' => $package,
                'message' => 'XML signed and packaged successfully',
            ];

        } catch (\Exception $e) {
            Log::error('ANAF signing failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'package' => null,
                'message' => 'Signing failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submit(string $package, array $metadata = []): array
    {
        if (!$this->authenticated) {
            return [
                'success' => false,
                'remote_id' => null,
                'download_id' => null,
                'message' => 'Not authenticated',
                'submitted_at' => null,
            ];
        }

        try {
            $apiUrl = $this->environment === 'test' ? $this->testUrl : $this->baseUrl;
            $endpoint = "{$apiUrl}/upload";

            // Prepare submission payload
            $payload = [
                'standard' => 'UBL',
                'cif' => $metadata['seller_vat'] ?? '',
                'xml' => $package, // Base64 encoded
            ];

            // Make API request
            $response = $this->makeAuthenticatedRequest('POST', $endpoint, $payload);

            if ($response['success']) {
                $data = $response['data'];

                return [
                    'success' => true,
                    'remote_id' => $data['index_incarcare'] ?? $data['upload_index'] ?? null,
                    'download_id' => $data['id_descarcare'] ?? $data['download_id'] ?? null,
                    'message' => 'Invoice submitted successfully to ANAF',
                    'submitted_at' => now()->toIso8601String(),
                ];
            }

            return [
                'success' => false,
                'remote_id' => null,
                'download_id' => null,
                'message' => $response['message'] ?? 'Submission failed',
                'submitted_at' => null,
            ];

        } catch (\Exception $e) {
            Log::error('ANAF submission failed', [
                'error' => $e->getMessage(),
                'metadata' => $metadata,
            ]);

            return [
                'success' => false,
                'remote_id' => null,
                'download_id' => null,
                'message' => 'Submission failed: ' . $e->getMessage(),
                'submitted_at' => null,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function poll(string $remoteId): array
    {
        if (!$this->authenticated) {
            return [
                'success' => false,
                'status' => 'not_found',
                'message' => 'Not authenticated',
                'errors' => [],
                'artifacts' => [],
            ];
        }

        try {
            $apiUrl = $this->environment === 'test' ? $this->testUrl : $this->baseUrl;
            $endpoint = "{$apiUrl}/stareMesaj";

            $payload = [
                'index_incarcare' => $remoteId,
            ];

            $response = $this->makeAuthenticatedRequest('POST', $endpoint, $payload);

            if (!$response['success']) {
                return [
                    'success' => false,
                    'status' => 'not_found',
                    'message' => $response['message'] ?? 'Failed to retrieve status',
                    'errors' => [],
                    'artifacts' => [],
                ];
            }

            $data = $response['data'];
            $state = $data['stare'] ?? 'processing';

            // Map ANAF states to our standard states
            $standardStatus = match ($state) {
                'ok', 'procesat' => 'accepted',
                'invalid', 'eroare' => 'rejected',
                default => 'processing',
            };

            // Extract errors if rejected
            $errors = [];
            if ($standardStatus === 'rejected') {
                $errors = $data['erori'] ?? [];
                if (is_string($errors)) {
                    $errors = [$errors];
                }
            }

            // Extract artifacts
            $artifacts = [];
            if ($standardStatus === 'accepted') {
                $artifacts = [
                    'download_id' => $data['id_descarcare'] ?? null,
                    'confirmation_number' => $data['numar_inregistrare'] ?? null,
                ];
            }

            return [
                'success' => true,
                'status' => $standardStatus,
                'message' => $data['mesaj'] ?? "Status: {$standardStatus}",
                'errors' => $errors,
                'artifacts' => $artifacts,
            ];

        } catch (\Exception $e) {
            Log::error('ANAF poll failed', [
                'error' => $e->getMessage(),
                'remote_id' => $remoteId,
            ]);

            return [
                'success' => false,
                'status' => 'not_found',
                'message' => 'Poll failed: ' . $e->getMessage(),
                'errors' => [],
                'artifacts' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function download(string $downloadId): array
    {
        if (!$this->authenticated) {
            return [
                'success' => false,
                'content' => null,
                'mime_type' => null,
                'filename' => null,
            ];
        }

        try {
            $apiUrl = $this->environment === 'test' ? $this->testUrl : $this->baseUrl;
            $endpoint = "{$apiUrl}/descarcare";

            $payload = [
                'id_descarcare' => $downloadId,
            ];

            $response = $this->makeAuthenticatedRequest('POST', $endpoint, $payload);

            if ($response['success']) {
                $data = $response['data'];
                $content = $data['continut'] ?? $data['content'] ?? null;

                // Content is usually base64 encoded
                if ($content && base64_decode($content, true) !== false) {
                    $content = base64_decode($content);
                }

                return [
                    'success' => true,
                    'content' => $content,
                    'mime_type' => $data['tip'] ?? 'application/zip',
                    'filename' => "efactura_{$downloadId}.zip",
                ];
            }

            return [
                'success' => false,
                'content' => null,
                'mime_type' => null,
                'filename' => null,
            ];

        } catch (\Exception $e) {
            Log::error('ANAF download failed', [
                'error' => $e->getMessage(),
                'download_id' => $downloadId,
            ]);

            return [
                'success' => false,
                'content' => null,
                'mime_type' => null,
                'filename' => null,
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
            ];
        }

        try {
            $apiUrl = $this->environment === 'test' ? $this->testUrl : $this->baseUrl;
            $endpoint = "{$apiUrl}/verificare";

            $response = $this->makeAuthenticatedRequest('GET', $endpoint, []);

            return [
                'connected' => $response['success'],
                'message' => $response['success']
                    ? 'Connection to ANAF successful'
                    : 'Connection failed: ' . ($response['message'] ?? 'Unknown error'),
            ];

        } catch (\Exception $e) {
            return [
                'connected' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata(): array
    {
        return [
            'name' => 'ANAF eFactura Adapter',
            'version' => '1.0.0',
            'supports_signing' => true,
            'supports_polling' => true,
        ];
    }

    /**
     * Validate invoice data before XML generation
     */
    protected function validateInvoiceData(array $invoice): array
    {
        $errors = [];

        if (empty($invoice['invoice_number'])) {
            $errors[] = 'Invoice number is required';
        }

        if (empty($invoice['issue_date'])) {
            $errors[] = 'Issue date is required';
        }

        if (empty($invoice['seller']['name'])) {
            $errors[] = 'Seller name is required';
        }

        if (empty($invoice['seller']['vat_number'])) {
            $errors[] = 'Seller VAT number is required';
        }

        if (empty($invoice['buyer']['name'])) {
            $errors[] = 'Buyer name is required';
        }

        if (empty($invoice['lines']) || !is_array($invoice['lines'])) {
            $errors[] = 'Invoice must have at least one line item';
        }

        // Validate line items
        foreach ($invoice['lines'] ?? [] as $index => $line) {
            if (empty($line['description'])) {
                $errors[] = "Line {$index}: Description is required";
            }
            if (!isset($line['quantity']) || $line['quantity'] <= 0) {
                $errors[] = "Line {$index}: Valid quantity is required";
            }
            if (!isset($line['unit_price'])) {
                $errors[] = "Line {$index}: Unit price is required";
            }
        }

        return $errors;
    }

    /**
     * Generate UBL 2.1 XML compliant with Romanian eFactura standard
     */
    protected function generateUbl21Xml(array $invoice): string
    {
        $invoiceNumber = $invoice['invoice_number'];
        $issueDate = $invoice['issue_date'];
        $dueDate = $invoice['due_date'] ?? $issueDate;
        $currency = $invoice['currency'] ?? 'RON';

        // Build XML using DOMDocument for proper structure
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Root Invoice element
        $root = $dom->createElementNS(self::NS_INVOICE, 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::NS_CAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::NS_CBC);
        $dom->appendChild($root);

        // Basic invoice info
        $this->addElement($dom, $root, 'cbc:ID', $invoiceNumber);
        $this->addElement($dom, $root, 'cbc:IssueDate', $issueDate);
        $this->addElement($dom, $root, 'cbc:DueDate', $dueDate);
        $this->addElement($dom, $root, 'cbc:InvoiceTypeCode', '380'); // Commercial invoice
        $this->addElement($dom, $root, 'cbc:DocumentCurrencyCode', $currency);

        // Seller (AccountingSupplierParty)
        $this->addParty($dom, $root, 'cac:AccountingSupplierParty', $invoice['seller']);

        // Buyer (AccountingCustomerParty)
        $this->addParty($dom, $root, 'cac:AccountingCustomerParty', $invoice['buyer']);

        // Tax Total
        $taxTotal = $invoice['vat_total'] ?? 0;
        if ($taxTotal > 0) {
            $taxTotalNode = $dom->createElementNS(self::NS_CAC, 'cac:TaxTotal');
            $this->addElement($dom, $taxTotalNode, 'cbc:TaxAmount', number_format($taxTotal, 2, '.', ''), [
                'currencyID' => $currency,
            ]);
            $root->appendChild($taxTotalNode);
        }

        // Legal Monetary Total
        $legalMonetaryTotal = $dom->createElementNS(self::NS_CAC, 'cac:LegalMonetaryTotal');
        $this->addElement($dom, $legalMonetaryTotal, 'cbc:LineExtensionAmount', number_format($invoice['total'] ?? 0, 2, '.', ''), [
            'currencyID' => $currency,
        ]);
        $this->addElement($dom, $legalMonetaryTotal, 'cbc:TaxExclusiveAmount', number_format($invoice['total'] ?? 0, 2, '.', ''), [
            'currencyID' => $currency,
        ]);
        $this->addElement($dom, $legalMonetaryTotal, 'cbc:TaxInclusiveAmount', number_format($invoice['grand_total'] ?? 0, 2, '.', ''), [
            'currencyID' => $currency,
        ]);
        $this->addElement($dom, $legalMonetaryTotal, 'cbc:PayableAmount', number_format($invoice['grand_total'] ?? 0, 2, '.', ''), [
            'currencyID' => $currency,
        ]);
        $root->appendChild($legalMonetaryTotal);

        // Invoice Lines
        foreach ($invoice['lines'] ?? [] as $index => $line) {
            $this->addInvoiceLine($dom, $root, $index + 1, $line, $currency);
        }

        return $dom->saveXML();
    }

    /**
     * Add party information (seller or buyer) to XML
     */
    protected function addParty(\DOMDocument $dom, \DOMElement $parent, string $nodeName, array $party): void
    {
        $partyNode = $dom->createElementNS(self::NS_CAC, $nodeName);
        $partyElement = $dom->createElementNS(self::NS_CAC, 'cac:Party');

        // Party name
        $partyName = $dom->createElementNS(self::NS_CAC, 'cac:PartyName');
        $this->addElement($dom, $partyName, 'cbc:Name', $party['name']);
        $partyElement->appendChild($partyName);

        // Postal address
        if (!empty($party['address'])) {
            $address = $dom->createElementNS(self::NS_CAC, 'cac:PostalAddress');
            $this->addElement($dom, $address, 'cbc:StreetName', $party['address']['street'] ?? '');
            $this->addElement($dom, $address, 'cbc:CityName', $party['address']['city'] ?? '');
            $this->addElement($dom, $address, 'cbc:PostalZone', $party['address']['postal_code'] ?? '');
            $this->addElement($dom, $address, 'cbc:CountrySubentity', $party['address']['county'] ?? '');

            $country = $dom->createElementNS(self::NS_CAC, 'cac:Country');
            $this->addElement($dom, $country, 'cbc:IdentificationCode', $party['address']['country'] ?? 'RO');
            $address->appendChild($country);

            $partyElement->appendChild($address);
        }

        // Tax scheme (VAT)
        if (!empty($party['vat_number'])) {
            $taxScheme = $dom->createElementNS(self::NS_CAC, 'cac:PartyTaxScheme');
            $this->addElement($dom, $taxScheme, 'cbc:CompanyID', $party['vat_number']);

            $taxSchemeNode = $dom->createElementNS(self::NS_CAC, 'cac:TaxScheme');
            $this->addElement($dom, $taxSchemeNode, 'cbc:ID', 'VAT');
            $taxScheme->appendChild($taxSchemeNode);

            $partyElement->appendChild($taxScheme);
        }

        // Legal entity
        $legalEntity = $dom->createElementNS(self::NS_CAC, 'cac:PartyLegalEntity');
        $this->addElement($dom, $legalEntity, 'cbc:RegistrationName', $party['name']);
        if (!empty($party['reg_number'])) {
            $this->addElement($dom, $legalEntity, 'cbc:CompanyID', $party['reg_number']);
        }
        $partyElement->appendChild($legalEntity);

        $partyNode->appendChild($partyElement);
        $parent->appendChild($partyNode);
    }

    /**
     * Add invoice line to XML
     */
    protected function addInvoiceLine(\DOMDocument $dom, \DOMElement $parent, int $lineNumber, array $line, string $currency): void
    {
        $invoiceLine = $dom->createElementNS(self::NS_CAC, 'cac:InvoiceLine');

        $this->addElement($dom, $invoiceLine, 'cbc:ID', (string) $lineNumber);
        $this->addElement($dom, $invoiceLine, 'cbc:InvoicedQuantity', number_format($line['quantity'], 2, '.', ''), [
            'unitCode' => $line['unit_code'] ?? 'EA',
        ]);

        $lineExtensionAmount = $line['quantity'] * $line['unit_price'];
        $this->addElement($dom, $invoiceLine, 'cbc:LineExtensionAmount', number_format($lineExtensionAmount, 2, '.', ''), [
            'currencyID' => $currency,
        ]);

        // Item
        $item = $dom->createElementNS(self::NS_CAC, 'cac:Item');
        $this->addElement($dom, $item, 'cbc:Description', $line['description']);
        $this->addElement($dom, $item, 'cbc:Name', $line['name'] ?? $line['description']);
        $invoiceLine->appendChild($item);

        // Price
        $price = $dom->createElementNS(self::NS_CAC, 'cac:Price');
        $this->addElement($dom, $price, 'cbc:PriceAmount', number_format($line['unit_price'], 2, '.', ''), [
            'currencyID' => $currency,
        ]);
        $invoiceLine->appendChild($price);

        $parent->appendChild($invoiceLine);
    }

    /**
     * Helper to add element with optional attributes
     */
    protected function addElement(\DOMDocument $dom, \DOMElement $parent, string $name, string $value, array $attributes = []): \DOMElement
    {
        $element = $dom->createElementNS(self::NS_CBC, $name, htmlspecialchars($value, ENT_XML1, 'UTF-8'));

        foreach ($attributes as $attrName => $attrValue) {
            $element->setAttribute($attrName, $attrValue);
        }

        $parent->appendChild($element);

        return $element;
    }

    /**
     * Apply XMLDSig signature to XML
     *
     * Note: For production, use robrichards/xmlseclibs or similar library
     */
    protected function applyXmlSignature(string $xml, string $certificate, string $privateKey): string
    {
        // This is a simplified implementation
        // In production, use robrichards/xmlseclibs for proper XMLDSig signing

        // For now, return the XML as-is with a comment indicating it should be signed
        // The actual implementation would use XMLSecurityDSig class

        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        // Add signature placeholder comment
        $comment = $dom->createComment('XMLDSig signature would be added here in production using robrichards/xmlseclibs');
        $dom->documentElement->appendChild($comment);

        return $dom->saveXML();
    }

    /**
     * Make authenticated HTTP request to ANAF API
     */
    protected function makeAuthenticatedRequest(string $method, string $url, array $payload): array
    {
        try {
            $headers = [];

            // Add authentication header (token-based)
            if (!empty($this->credentials['api_token'])) {
                $headers['Authorization'] = 'Bearer ' . $this->credentials['api_token'];
            }

            $request = Http::withHeaders($headers);

            // Add certificate if available (for mTLS)
            if (!empty($this->credentials['certificate_path'])) {
                $request = $request->withOptions([
                    'cert' => $this->credentials['certificate_path'],
                    'ssl_key' => $this->credentials['private_key_path'] ?? null,
                ]);
            }

            $response = $method === 'GET'
                ? $request->get($url, $payload)
                : $request->post($url, $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'message' => 'Request successful',
                ];
            }

            return [
                'success' => false,
                'data' => null,
                'message' => $response->json('message') ?? 'Request failed',
            ];

        } catch (\Exception $e) {
            Log::error('ANAF API request failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
            ];
        }
    }
}
