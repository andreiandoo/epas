<?php

namespace App\Services\Installments;

use App\Models\InstallmentAgreement;
use App\Models\InstallmentPayment;
use App\Models\InstallmentPlan;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates and manages flexible-payment agreements from a calculator quote.
 * Handles the checkout-time creation, down-payment activation, and status
 * transitions. Money is stored in bani (cents); no processor calls here.
 */
class InstallmentAgreementService
{
    /**
     * Create a pending agreement + full schedule from a validated quote.
     *
     * @param array $quote  output of InstallmentPlanCalculator::quote() (must be eligible)
     * @param array $ctx    order_id, marketplace_client_id, tenant_id, marketplace_customer_id,
     *                      customer_email, customer_name, customer_phone, event_id,
     *                      marketplace_event_id, event_start_date, provider
     */
    public function createFromQuote(InstallmentPlan $plan, array $quote, array $ctx): InstallmentAgreement
    {
        if (empty($quote['eligible'])) {
            throw new \InvalidArgumentException('Cannot create an agreement from an ineligible quote: ' . ($quote['reason'] ?? 'unknown'));
        }

        return DB::transaction(function () use ($plan, $quote, $ctx) {
            $agreement = InstallmentAgreement::create([
                'marketplace_client_id' => $ctx['marketplace_client_id'] ?? null,
                'tenant_id' => $ctx['tenant_id'] ?? null,
                'order_id' => $ctx['order_id'] ?? null,
                'installment_plan_id' => $plan->id,
                'marketplace_customer_id' => $ctx['marketplace_customer_id'] ?? null,
                'customer_email' => $ctx['customer_email'] ?? '',
                'customer_name' => $ctx['customer_name'] ?? null,
                'customer_phone' => $ctx['customer_phone'] ?? null,
                'event_id' => $ctx['event_id'] ?? null,
                'marketplace_event_id' => $ctx['marketplace_event_id'] ?? null,
                'event_start_date' => $ctx['event_start_date'] ?? null,
                'plan_type' => $plan->plan_type,
                'currency' => $quote['base_total_cents'] ? ($ctx['currency'] ?? $plan->currency) : $plan->currency,
                'base_total_cents' => $quote['base_total_cents'],
                'surcharge_cents' => $quote['surcharge_cents'],
                'customer_total_cents' => $quote['customer_total_cents'],
                'platform_fee_cents' => $quote['platform_fee_cents'],
                'platform_fee_percent' => $quote['platform_fee_percent'],
                'down_payment_cents' => $quote['down_payment_cents'],
                'financed_cents' => $quote['financed_cents'],
                'number_of_installments' => $quote['number_of_installments'],
                'status' => InstallmentAgreement::STATUS_PENDING,
                'ticket_issuance_policy' => $plan->ticket_issuance_policy ?: 'issue_invalid_until_paid',
                'provider' => $ctx['provider'] ?? null,
                'auto_debit_enabled' => true,
                'plan_snapshot' => $this->snapshot($plan, $quote),
            ]);

            foreach ($quote['schedule'] as $row) {
                InstallmentPayment::create([
                    'installment_agreement_id' => $agreement->id,
                    'sequence' => $row['sequence'],
                    'due_date' => Carbon::parse($row['due_date']),
                    'amount_cents' => $row['amount_cents'],
                    'principal_cents' => $row['amount_cents'],
                    'status' => $row['sequence'] === 0
                        ? InstallmentPayment::STATUS_DUE          // down payment charged at checkout
                        : InstallmentPayment::STATUS_SCHEDULED,
                ]);
            }

            $agreement->log('created', 'Agreement created from quote', [
                'plan_id' => $plan->id,
                'customer_total_cents' => $quote['customer_total_cents'],
            ]);

            // Reflect on the order (method + outstanding balance).
            $this->syncOrder($agreement);

            return $agreement->fresh('payments');
        });
    }

    /**
     * Called after the down payment (or BNPL capture) succeeds at checkout.
     * Stores the mandate, activates the agreement, marks the down payment paid,
     * and sets the next due date.
     */
    public function activate(InstallmentAgreement $agreement, array $result): InstallmentAgreement
    {
        return DB::transaction(function () use ($agreement, $result) {
            $down = $agreement->payments()->where('sequence', 0)->first();
            if ($down && $down->status !== InstallmentPayment::STATUS_PAID) {
                $down->update([
                    'status' => InstallmentPayment::STATUS_PAID,
                    'paid_at' => now(),
                    'paid_amount_cents' => $down->amount_cents,
                    'payment_reference' => $result['payment_id'] ?? null,
                ]);
                $agreement->increment('paid_installments_count');
            }

            $agreement->update([
                'status' => InstallmentAgreement::STATUS_ACTIVE,
                'mandate_reference' => $result['mandate_reference'] ?? $agreement->mandate_reference,
                'payment_method_id' => $result['payment_method_id'] ?? $agreement->payment_method_id,
            ]);

            $agreement->recomputeNextDue();
            $agreement->log('activated', 'Down payment settled, agreement active', [
                'mandate' => (bool) ($result['mandate_reference'] ?? false),
            ], $down?->id);

            $this->syncOrder($agreement);

            return $agreement->fresh('payments');
        });
    }

    /**
     * Mark the whole agreement completed when the final installment is paid.
     * Ticket becomes valid (handled by the caller / ticket service).
     */
    public function complete(InstallmentAgreement $agreement): void
    {
        $agreement->update(['status' => InstallmentAgreement::STATUS_COMPLETED, 'next_due_at' => null]);
        $agreement->log('completed', 'All payments settled — ticket valid');
        $this->syncOrder($agreement);
    }

    /**
     * Keep the order's flexible-payment display fields in sync.
     */
    public function syncOrder(InstallmentAgreement $agreement): void
    {
        if (! $agreement->order_id) {
            return;
        }
        $order = Order::find($agreement->order_id);
        if (! $order) {
            return;
        }

        $kind = $agreement->plan_type === InstallmentPlan::TYPE_BNPL ? 'bnpl' : 'installments';
        $outstanding = $agreement->outstandingCents();

        $order->forceFill([
            'installment_agreement_id' => $agreement->id,
            'payment_method_kind' => $kind,
            'outstanding_cents' => $outstanding,
            'payment_status' => $outstanding > 0 ? 'partially_paid' : ($order->payment_status ?? 'paid'),
        ])->save();
    }

    protected function snapshot(InstallmentPlan $plan, array $quote): array
    {
        return [
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->getTranslation('name'),
                'plan_type' => $plan->plan_type,
                'surcharge_percent' => $plan->surcharge_percent,
                'surcharge_fixed_cents' => $plan->surcharge_fixed_cents,
                'number_of_installments' => $plan->number_of_installments,
                'schedule_type' => $plan->schedule_type,
                'interval_unit' => $plan->interval_unit,
                'interval_count' => $plan->interval_count,
                'distribution' => $plan->distribution,
                'default_policy' => $plan->defaultPolicy(),
                'refund_policy' => $plan->refundPolicy(),
                'days_before_event_fully_paid' => $plan->daysBeforeEvent(),
                'terms_url' => $plan->terms_url,
            ],
            'quote' => collect($quote)->except('schedule')->all(),
        ];
    }
}
