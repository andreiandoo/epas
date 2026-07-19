<?php

namespace App\Services\PaymentProcessors;

/**
 * Opt-in capability for payment processors that can store a reusable
 * card mandate/token at checkout (on-session, SCA) and later charge it
 * off-session (MIT — Merchant Initiated Transaction).
 *
 * This is the foundation of the Flexible Payments microservice
 * (installments / BNPL auto-debit). Processors that do NOT implement
 * this interface simply cannot offer installments/BNPL — the eligibility
 * layer hides those methods for the corresponding marketplace.
 *
 * v1 implementers: Stripe, Netopia. EuPlatesc / PayU deliberately omit it.
 *
 * All money is passed as MAJOR currency units (e.g. RON, not bani) to stay
 * consistent with PaymentProcessorInterface::createPayment(). Internally
 * each processor converts to the smallest unit as needed.
 */
interface SupportsTokenizedPayments
{
    /**
     * Whether this processor instance is configured AND able to tokenize.
     * The engine calls this before offering installments/BNPL.
     */
    public function supportsTokenization(): bool;

    /**
     * Whether the processor can capture a reusable mandate WITHOUT charging the
     * card (a zero-amount setup, e.g. a Stripe SetupIntent). This gates "0 avans"
     * installment plans: processors that can only tokenize via a real charge
     * (e.g. Netopia) must require a non-zero down payment instead.
     */
    public function supportsZeroAmountMandate(): bool;

    /**
     * Create an on-session payment that ALSO stores a reusable mandate/token.
     *
     * Used for:
     *   - installment down payment (amount > 0), and
     *   - BNPL card capture (a nominal amount, e.g. 1 RON).
     *
     * The customer completes SCA here; the resulting mandate is what we later
     * charge off-session. The mandate reference arrives either in the returned
     * array (synchronous processors) or in the webhook callback (async), so the
     * caller must also read `mandate_reference` from processCallback() output.
     *
     * @param array $data Same keys as createPayment() PLUS:
     *   - store_mandate: bool (always true here)
     *   - customer_reference: string|null (stable per-customer id to attach the mandate to)
     * @return array createPayment()-shaped:
     *   - payment_id: string
     *   - redirect_url: string
     *   - mandate_reference: string|null (may be null until the callback)
     *   - additional_data: array
     */
    public function createPaymentWithMandate(array $data): array;

    /**
     * Charge a previously stored mandate off-session (no customer present).
     *
     * Must be idempotent-friendly: callers pass a unique `idempotency_key`
     * (the installment_payment id) so retries don't double-charge.
     *
     * @param string $mandateReference The token/mandate saved from checkout.
     * @param array $data
     *   - amount: float (major units)
     *   - currency: string
     *   - description: string
     *   - order_id: string
     *   - idempotency_key: string (unique per installment attempt target)
     *   - metadata: array
     * @return array
     *   - status: string (success|failed|action_required|pending)
     *   - payment_id: string|null (processor transaction id)
     *   - amount: float
     *   - currency: string
     *   - action_url: string|null (set when status=action_required → 3DS/SCA link)
     *   - decline_code: string|null (processor decline reason; drives retry policy)
     *   - hard_decline: bool (true = do-not-retry, e.g. stolen/closed card)
     *   - error: string|null
     */
    public function chargeWithToken(string $mandateReference, array $data): array;
}
