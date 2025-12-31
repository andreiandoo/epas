<?php

namespace App\Services\Accounting\Adapters;

/**
 * Accounting Provider Adapter Interface
 *
 * Unified interface for all accounting system integrations
 */
interface AccountingAdapterInterface
{
    /**
     * Authenticate with the provider
     *
     * @param array $credentials
     * @return array {success: bool, message: string}
     */
    public function authenticate(array $credentials): array;

    /**
     * Test connection
     *
     * @return array {connected: bool, message: string, details: array}
     */
    public function testConnection(): array;

    /**
     * Ensure customer exists in accounting system
     *
     * @param array $customer {name, email, vat_number, address, etc.}
     * @return array {customer_id: string, created: bool}
     */
    public function ensureCustomer(array $customer): array;

    /**
     * Ensure products exist in accounting system
     *
     * @param array $lines [{product_name, quantity, unit_price, tax_rate}]
     * @return array [{product_id: string, created: bool}]
     */
    public function ensureProducts(array $lines): array;

    /**
     * Create invoice
     *
     * @param array $invoice {customer, lines, totals, series, etc.}
     * @return array {external_ref: string, invoice_number: string, details: array}
     */
    public function createInvoice(array $invoice): array;

    /**
     * Get invoice PDF
     *
     * @param string $externalRef
     * @return array {pdf_url: string|null, pdf_content: string|null}
     */
    public function getInvoicePdf(string $externalRef): array;

    /**
     * Create credit note
     *
     * @param string $invoiceExternalRef
     * @param array $refund {amount, reason, lines}
     * @return array {external_ref: string, credit_note_number: string}
     */
    public function createCreditNote(string $invoiceExternalRef, array $refund): array;

    /**
     * Get customer list (for sync/import)
     *
     * @return array [{id, name, vat_number, ...}]
     */
    public function getCustomers(): array;

    /**
     * Get product list (for sync/import)
     *
     * @return array [{id, name, code, price, ...}]
     */
    public function getProducts(): array;
}
