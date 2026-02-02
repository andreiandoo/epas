<?php

namespace App\Services\EFactura\Adapters;

/**
 * ANAF SPV Adapter Interface
 *
 * Provides vendor-agnostic abstraction for interacting with the ANAF eFactura system.
 * Implementations can use official APIs, third-party libraries, or mock data for testing.
 */
interface AnafAdapterInterface
{
    /**
     * Authenticate with ANAF using provided credentials
     *
     * @param array $credentials Contains authentication data (certificate, password, etc.)
     * @return array ['success' => bool, 'message' => string]
     */
    public function authenticate(array $credentials): array;

    /**
     * Build eFactura XML from invoice data
     *
     * Transforms internal invoice structure to ANAF-compliant XML format
     * following the Romanian national profile (UBL or CII).
     *
     * @param array $invoice Complete invoice data structure
     * @return array [
     *   'success' => bool,
     *   'xml' => string (XML content),
     *   'hash' => string (SHA256 of XML),
     *   'errors' => array (validation errors if any)
     * ]
     */
    public function buildXml(array $invoice): array;

    /**
     * Sign and package XML for submission
     *
     * Applies digital signature if required and packages according to ANAF specs.
     *
     * @param string $xml Raw XML content
     * @param array $signingConfig Certificate details, alias, password
     * @return array [
     *   'success' => bool,
     *   'package' => string (signed/packaged content),
     *   'message' => string
     * ]
     */
    public function signAndPackage(string $xml, array $signingConfig = []): array;

    /**
     * Submit eFactura package to ANAF SPV
     *
     * @param string $package Signed XML package ready for submission
     * @param array $metadata Additional submission metadata (tenant_id, invoice_id, etc.)
     * @return array [
     *   'success' => bool,
     *   'remote_id' => string (ANAF tracking ID),
     *   'download_id' => string|null (for later retrieval),
     *   'message' => string,
     *   'submitted_at' => string (ISO8601 timestamp)
     * ]
     */
    public function submit(string $package, array $metadata = []): array;

    /**
     * Poll submission status from ANAF
     *
     * Queries ANAF for the current status of a submitted invoice.
     *
     * @param string $remoteId ANAF tracking ID from submit()
     * @return array [
     *   'success' => bool,
     *   'status' => string ('processing'|'accepted'|'rejected'),
     *   'message' => string,
     *   'errors' => array (ANAF validation errors if rejected),
     *   'artifacts' => array (download_id, pdf_url, etc.)
     * ]
     */
    public function poll(string $remoteId): array;

    /**
     * Download processed invoice or receipt from ANAF
     *
     * @param string $downloadId Download identifier from poll() artifacts
     * @return array [
     *   'success' => bool,
     *   'content' => string (file content),
     *   'mime_type' => string,
     *   'filename' => string
     * ]
     */
    public function download(string $downloadId): array;

    /**
     * Test connection to ANAF SPV
     *
     * @return array ['connected' => bool, 'message' => string]
     */
    public function testConnection(): array;

    /**
     * Get adapter metadata and capabilities
     *
     * @return array [
     *   'name' => string,
     *   'version' => string,
     *   'supports_signing' => bool,
     *   'supports_polling' => bool
     * ]
     */
    public function getMetadata(): array;
}
