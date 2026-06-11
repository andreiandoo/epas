<?php

namespace App\Services\Insurance;

use App\Models\InsuranceConfig;
use App\Models\InsurancePolicy;
use App\Models\InsuranceEvent;
use App\Services\Insurance\Adapters\InsurerAdapterInterface;
use App\Services\Insurance\Adapters\MockInsurerAdapter;
use Illuminate\Support\Facades\DB;

/**
 * Insurance Service
 *
 * Handles quote calculation, policy issuance, and management
 */
class InsuranceService
{
    protected array $adapters = [];

    public function __construct()
    {
        // Register default adapters
        $this->registerAdapter('mock', new MockInsurerAdapter());
    }

    public function registerAdapter(string $key, InsurerAdapterInterface $adapter): void
    {
        $this->adapters[$key] = $adapter;
    }

    protected function getAdapter(string $key): InsurerAdapterInterface
    {
        if (!isset($this->adapters[$key])) {
            throw new \Exception("Insurance adapter '{$key}' not registered");
        }

        return $this->adapters[$key];
    }

    /**
     * Get applicable config for a context (hierarchical: ticket_type > event > tenant)
     */
    public function getConfig(string $tenantId, array $context = []): ?InsuranceConfig
    {
        $configs = InsuranceConfig::where('tenant_id', $tenantId)
            ->where('enabled', true)
            ->orderBy('priority', 'asc')
            ->get();

        // Try ticket_type specific
        if (!empty($context['ticket_type'])) {
            $config = $configs->where('scope', 'ticket_type')
                ->where('scope_ref', $context['ticket_type'])
                ->first();
            if ($config && $config->isEligible($context)) {
                return $config;
            }
        }

        // Try event specific
        if (!empty($context['event_ref'])) {
            $config = $configs->where('scope', 'event')
                ->where('scope_ref', $context['event_ref'])
                ->first();
            if ($config && $config->isEligible($context)) {
                return $config;
            }
        }

        // Try tenant default
        $config = $configs->where('scope', 'tenant')
            ->whereNull('scope_ref')
            ->first();

        if ($config && $config->isEligible($context)) {
            return $config;
        }

        return null;
    }

    /**
     * Calculate insurance quote
     */
    public function quote(string $tenantId, array $params): array
    {
        $config = $this->getConfig($tenantId, $params);

        if (!$config) {
            return [
                'available' => false,
                'message' => 'Insurance not available for this ticket',
            ];
        }

        $ticketPrice = $params['ticket_price'] ?? 0;
        $premium = $config->calculatePremium($ticketPrice);

        // Calculate tax if applicable
        $taxPolicy = $config->tax_policy ?? [];
        $taxAmount = 0;

        if (!empty($taxPolicy['rate']) && empty($taxPolicy['inclusive'])) {
            $taxAmount = $premium * ($taxPolicy['rate'] / 100);
        }

        $total = $premium + $taxAmount;

        // Optionally query adapter for real-time quote
        $adapterQuote = null;
        if ($config->insurer_provider !== 'mock') {
            try {
                $adapter = $this->getAdapter($config->insurer_provider);
                $adapterQuote = $adapter->quote(array_merge($params, [
                    'premium' => $premium,
                    'config' => $config->toArray(),
                ]));
            } catch (\Exception $e) {
                // Fallback to config-based calculation
            }
        }

        return [
            'available' => true,
            'premium' => $premium,
            'tax' => $taxAmount,
            'total' => $total,
            'currency' => 'EUR',
            'config_id' => $config->id,
            'terms_url' => $config->getTermsUrl(),
            'description' => $config->getDescription(),
            'scope_level' => $config->scope_level,
            'adapter_quote' => $adapterQuote,
        ];
    }

    /**
     * Issue insurance policy (idempotent per order_ref + ticket_ref)
     */
    public function issue(string $tenantId, string $orderRef, array $params): InsurancePolicy
    {
        $ticketRef = $params['ticket_ref'] ?? null;

        // Check for existing policy (idempotency)
        $existing = InsurancePolicy::where('tenant_id', $tenantId)
            ->where('order_ref', $orderRef)
            ->where('ticket_ref', $ticketRef)
            ->first();

        if ($existing) {
            return $existing;
        }

        $config = $this->getConfig($tenantId, $params);

        if (!$config) {
            throw new \Exception('Insurance not available');
        }

        $premium = $params['premium'] ?? $config->calculatePremium($params['ticket_price'] ?? 0);

        DB::beginTransaction();

        try {
            // Create policy record
            $policy = InsurancePolicy::create([
                'tenant_id' => $tenantId,
                'order_ref' => $orderRef,
                'ticket_ref' => $ticketRef,
                'insurer' => $config->insurer_provider,
                'premium_amount' => $premium,
                'currency' => $params['currency'] ?? 'EUR',
                'tax_amount' => $params['tax_amount'] ?? 0,
                'status' => 'pending',
                'metadata' => $params['metadata'] ?? [],
            ]);

            // Issue policy via adapter
            try {
                $adapter = $this->getAdapter($config->insurer_provider);

                $result = $adapter->issue([
                    'order_ref' => $orderRef,
                    'ticket_ref' => $ticketRef,
                    'user' => $params['user'] ?? [],
                    'event' => $params['event'] ?? [],
                    'premium' => $premium,
                    'params' => $params,
                ]);

                $policy->markAsIssued($result['policy_number'], $result['policy_doc_url'] ?? null);
                $policy->update(['provider_payload' => $result]);

                InsuranceEvent::logIssue($policy, $result);

            } catch (\Exception $e) {
                $policy->markAsError($e->getMessage());
                InsuranceEvent::logError($policy, 'ISSUE_ERROR', $e->getMessage());
                throw $e;
            }

            DB::commit();

            return $policy;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Void a policy
     */
    public function void(InsurancePolicy $policy, ?string $reason = null): bool
    {
        if (!$policy->canBeVoided()) {
            throw new \Exception('Policy cannot be voided in current status: ' . $policy->status);
        }

        try {
            $adapter = $this->getAdapter($policy->insurer);
            $result = $adapter->void($policy->policy_number);

            if ($result['success']) {
                $policy->markAsVoided();
                InsuranceEvent::logVoid($policy, $reason);
                return true;
            }

            return false;

        } catch (\Exception $e) {
            InsuranceEvent::logError($policy, 'VOID_ERROR', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Refund a policy
     */
    public function refund(InsurancePolicy $policy, ?float $amount = null): bool
    {
        if (!$policy->canBeRefunded()) {
            throw new \Exception('Policy cannot be refunded in current status: ' . $policy->status);
        }

        try {
            $adapter = $this->getAdapter($policy->insurer);
            $result = $adapter->refund($policy->policy_number, $amount);

            if ($result['success']) {
                $refundAmount = $result['refund_amount'] ?? $amount ?? $policy->premium_amount;
                $policy->markAsRefunded($refundAmount);
                InsuranceEvent::logRefund($policy, $refundAmount);
                return true;
            }

            return false;

        } catch (\Exception $e) {
            InsuranceEvent::logError($policy, 'REFUND_ERROR', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync policy status with provider
     */
    public function sync(InsurancePolicy $policy): array
    {
        try {
            $adapter = $this->getAdapter($policy->insurer);
            $result = $adapter->sync($policy->policy_number);

            // Update local status if different
            if ($result['status'] !== $policy->status) {
                $policy->update(['status' => $result['status']]);
            }

            return $result;

        } catch (\Exception $e) {
            InsuranceEvent::logError($policy, 'SYNC_ERROR', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get statistics for tenant
     */
    public function getStats(string $tenantId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = InsurancePolicy::where('tenant_id', $tenantId);

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }

        $total = $query->count();
        $issued = (clone $query)->where('status', 'issued')->count();
        $voided = (clone $query)->where('status', 'voided')->count();
        $refunded = (clone $query)->where('status', 'refunded')->count();
        $errors = (clone $query)->where('status', 'error')->count();

        $premiumGMV = (clone $query)->whereIn('status', ['issued', 'refunded'])->sum('premium_amount');
        $refundedAmount = (clone $query)->where('status', 'refunded')->sum('refund_amount');

        $attachRate = 0;
        // TODO: Calculate attach rate (policies / total orders) - requires order data

        return [
            'total_policies' => $total,
            'issued' => $issued,
            'voided' => $voided,
            'refunded' => $refunded,
            'errors' => $errors,
            'premium_gmv' => $premiumGMV,
            'refunded_amount' => $refundedAmount,
            'net_premium' => $premiumGMV - $refundedAmount,
            'attach_rate' => $attachRate,
            'void_rate' => $total > 0 ? round(($voided / $total) * 100, 2) : 0,
            'error_rate' => $total > 0 ? round(($errors / $total) * 100, 2) : 0,
        ];
    }
}
