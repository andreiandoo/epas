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
     * Delete an invoice / proforma from the accounting provider.
     * Used when a proforma is superseded by a fiscal invoice so we don't
     * leave orphan proformas behind in Oblio.
     *
     * Adapters that don't support deletion (or where deletion is illegal
     * for fiscal invoices per local law) should return
     * {success: false, message: '...', supported: false} instead of throwing.
     *
     * @param string $externalRef Full reference like "SERIES/NUMBER"
     * @param string $docType 'invoice' | 'proforma'
     * @return array {success: bool, message: string, supported: bool}
     */
    public function deleteInvoice(string $externalRef, string $docType = 'invoice'): array;

    /**
     * Look up the current state of an invoice / proforma at the provider.
     *
     * Contract:
     *   ['exists' => true,  'canceled' => bool, 'has_credit_note' => bool, 'raw' => ...]
     *      - live if !canceled
     *      - stornoed if canceled && has_credit_note (a credit note was issued)
     *      - canceled if canceled && !has_credit_note (plain cancel, no NC)
     *   ['exists' => false, 'reason'   => 'not_found']          - deleted / missing
     *   ['exists' => null,  'reason'   => 'error', 'message' => ...] - transient (network/auth)
     *
     * Adapters that can't reach the provider (mocks, offline drivers) must
     * return exists=null with a reason so callers never mistake "unknown"
     * for "gone".
     *
     * @param string      $externalRef Full reference like "SERIES/NUMBER"
     * @param string      $docType 'invoice' | 'proforma'
     * @param string|null $issueDate ISO date (YYYY-MM-DD) to narrow the
     *                    provider-side lookup window. Recommended when
     *                    the caller has it — the OblioAdapter listing
     *                    query has a 100-item cap.
     */
    public function getInvoiceStatus(string $externalRef, string $docType = 'invoice', ?string $issueDate = null): array;

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
