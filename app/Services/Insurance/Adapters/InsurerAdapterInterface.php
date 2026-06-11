<?php

namespace App\Services\Insurance\Adapters;

/**
 * Insurance Provider Adapter Interface
 *
 * Implement this interface to integrate with different insurance providers
 */
interface InsurerAdapterInterface
{
    /**
     * Get a quote for insurance premium
     *
     * @param array $params {
     *   ticket_price: float,
     *   ticket_type: string,
     *   config: array,
     *   user: array,
     *   event: array
     * }
     * @return array {premium: float, currency: string, details: array}
     */
    public function quote(array $params): array;

    /**
     * Issue an insurance policy
     *
     * @param array $params {
     *   order_ref: string,
     *   ticket_ref: string|null,
     *   user: array,
     *   event: array,
     *   premium: float,
     *   params: array
     * }
     * @return array {policy_number: string, policy_doc_url: string|null, details: array}
     */
    public function issue(array $params): array;

    /**
     * Void/cancel a policy
     *
     * @param string $policyNumber
     * @return array {success: bool, message: string}
     */
    public function void(string $policyNumber): array;

    /**
     * Refund a policy
     *
     * @param string $policyNumber
     * @param float|null $amount Partial refund amount (null = full refund)
     * @return array {success: bool, refund_amount: float, message: string}
     */
    public function refund(string $policyNumber, ?float $amount = null): array;

    /**
     * Sync policy status with provider
     *
     * @param string $policyNumber
     * @return array {status: string, doc_url: string|null, details: array}
     */
    public function sync(string $policyNumber): array;
}
