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

            // Consumer-credit disclosure record (§16.6d): capture what the buyer
            // was shown and agreed to at checkout — the full cost breakdown and
            // schedule — so the accepted terms are auditable later. The immutable
            // plan_snapshot already stores the quote; this is the explicit,
            // timestamped consent event tied to the disclosed figures.
            $agreement->log('consent_recorded', 'Customer accepted the payment-plan terms', [
                'base_total_cents' => $quote['base_total_cents'],
                'surcharge_cents' => $quote['surcharge_cents'],
                'customer_total_cents' => $quote['customer_total_cents'],
                'number_of_installments' => $quote['number_of_installments'],
                'down_payment_cents' => $quote['down_payment_cents'],
                'terms_url' => $plan->terms_url,
                'schedule' => array_map(fn ($r) => [
                    'sequence' => $r['sequence'],
                    'due_date' => $r['due_date'],
                    'amount_cents' => $r['amount_cents'],
                ], $quote['schedule']),
                'disclosed_at' => now()->toIso8601String(),
                'consent_source' => $ctx['consent_source'] ?? 'checkout',
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
        $downFreshlyPaid = DB::transaction(function () use ($agreement, $result) {
            // Lock the down-payment row so two racing callbacks can't both settle
            // + credit it (webhook + return-url arriving together).
            $down = $agreement->payments()->where('sequence', 0)->lockForUpdate()->first();
            $fresh = false;
            if ($down && $down->status !== InstallmentPayment::STATUS_PAID) {
                $down->update([
                    'status' => InstallmentPayment::STATUS_PAID,
                    'paid_at' => now(),
                    'paid_amount_cents' => $down->amount_cents,
                    'payment_reference' => $result['payment_id'] ?? null,
                ]);
                $agreement->increment('paid_installments_count');
                $fresh = true;
            }

            $agreement->update([
                'status' => InstallmentAgreement::STATUS_ACTIVE,
                'mandate_reference' => $result['mandate_reference'] ?? $agreement->mandate_reference,
                'payment_method_id' => $result['payment_method_id'] ?? $agreement->payment_method_id,
            ]);

            $agreement->recomputeNextDue();
            if ($fresh) {
                $agreement->log('activated', 'Down payment settled, agreement active', [
                    'mandate' => (bool) ($result['mandate_reference'] ?? false),
                ], $down?->id);
            }

            $this->syncOrder($agreement);

            return $fresh ? ($down?->amount_cents > 0 ? $down : null) : null;
        });

        // Credit the organizer ONLY when this call actually settled the down
        // payment (outside the txn; idempotent against replayed/racing callbacks).
        if ($downFreshlyPaid) {
            app(InstallmentPayoutService::class)->creditInstallment($agreement, $downFreshlyPaid);
        }

        // Card-validity check (§16.2): if the mandate result carries the saved
        // card's expiry, ensure it outlives the final installment. When it does
        // not, flag the agreement and email the customer to update their card
        // before the auto-debit fails. Dormant no-op until a processor supplies
        // `card_exp_month`/`card_exp_year` in its mandate result.
        $this->checkCardExpiry($agreement->fresh(), $result);

        return $agreement->fresh('payments');
    }

    /**
     * Warn when the saved card expires before the plan finishes (§16.2).
     * No-op unless the processor supplied the card expiry in the mandate result.
     */
    protected function checkCardExpiry(InstallmentAgreement $agreement, array $result): void
    {
        $month = $result['card_exp_month'] ?? ($result['metadata']['card_exp_month'] ?? null);
        $year = $result['card_exp_year'] ?? ($result['metadata']['card_exp_year'] ?? null);
        if (! $month || ! $year) {
            return;
        }

        // Card is valid through the LAST day of its expiry month.
        $cardValidUntil = Carbon::create((int) $year, (int) $month, 1)->endOfMonth();
        $lastDue = $agreement->payments()->max('due_date');
        if (! $lastDue) {
            return;
        }
        $lastDue = Carbon::parse($lastDue);

        $agreement->update(['metadata' => array_merge($agreement->metadata ?? [], [
            'card_exp_month' => (int) $month,
            'card_exp_year' => (int) $year,
        ])]);

        if ($cardValidUntil->lessThan($lastDue)) {
            $agreement->update(['metadata' => array_merge($agreement->metadata ?? [], [
                'card_expiry_warning' => true,
            ])]);
            $agreement->log('card_expiry_warning', 'Saved card expires before the plan ends', [
                'card_valid_until' => $cardValidUntil->toDateString(),
                'last_installment_due' => $lastDue->toDateString(),
            ]);
            app(FlexiblePaymentMailer::class)->send($agreement, 'installment_action_required', [
                'reason' => 'card_expiring',
            ]);
        }
    }

    /**
     * Handle a successful down-payment / BNPL-capture callback for an order.
     * Activates the pending agreement, captures the mandate, and marks the
     * order's tickets reserved-invalid. Returns true if this was a flexible-
     * payment order (so the caller skips the normal full-paid flow).
     *
     * Idempotent: a replayed callback on an already-active agreement is a no-op.
     */
    public function handleDownPaymentCallback(Order $order, array $result): bool
    {
        if (! $order->installment_agreement_id) {
            return false;
        }
        $agreement = InstallmentAgreement::find($order->installment_agreement_id);
        if (! $agreement) {
            return false;
        }
        if ($agreement->status !== InstallmentAgreement::STATUS_PENDING) {
            return true; // already handled; still a flexible-payment order
        }

        $mandate = $result['mandate_reference']
            ?? ($result['metadata']['mandate_reference'] ?? null)
            ?? $agreement->mandate_reference;

        $this->activate($agreement, [
            'mandate_reference' => $mandate,
            'payment_id' => $result['transaction_id'] ?? $result['payment_id'] ?? null,
        ]);

        $ticketService = app(TicketStateService::class);
        $ticketService->markPendingForAgreement($agreement->fresh());
        // The down payment cleared → the customer owns the seat. Confirm the
        // held seats (held → sold) so the auto-release sweep can't free them
        // out from under an active plan (oversell). The ticket stays invalid
        // until the plan is fully paid.
        $ticketService->confirmSeatsForOrder($order);

        return true;
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
     * Reconcile an active agreement against its live event start date (§16.3).
     *
     * The organizer may move the event AFTER a plan started. If it moved EARLIER
     * we must pull the remaining installments in so the plan still finishes
     * before the (new) deadline — never charging a customer past it, and never
     * unfairly defaulting them for the organizer's change. If it moved later we
     * simply record the new date (more breathing room; no reschedule needed).
     *
     * Returns true if the schedule was changed. Safe/no-op when nothing drifted.
     */
    public function reconcileEventStartDate(InstallmentAgreement $agreement): bool
    {
        if (! $agreement->isActive() || ! $agreement->event_id) {
            return false;
        }
        $event = \App\Models\Event::find($agreement->event_id);
        $liveStart = $event?->start_date;
        if (! $liveStart) {
            return false;
        }
        $stored = $agreement->event_start_date;
        if ($stored && $liveStart->equalTo($stored)) {
            return false; // no drift
        }

        $daysBefore = (int) ($agreement->plan_snapshot['plan']['days_before_event_fully_paid'] ?? 1);
        $deadline = $liveStart->copy()->subDays($daysBefore);

        $agreement->update(['event_start_date' => $liveStart]);
        $agreement->log('event_rescheduled', 'Event start date changed; schedule reconciled', [
            'old_start_date' => $stored?->toIso8601String(),
            'new_start_date' => $liveStart->toIso8601String(),
            'new_deadline' => $deadline->toIso8601String(),
        ]);

        // Only outstanding (not-yet-paid) installments can move.
        $remaining = $agreement->payments()
            ->where('sequence', '>', 0)
            ->whereIn('status', [
                InstallmentPayment::STATUS_SCHEDULED, InstallmentPayment::STATUS_DUE,
                InstallmentPayment::STATUS_RETRYING, InstallmentPayment::STATUS_ACTION_REQUIRED,
                InstallmentPayment::STATUS_FAILED,
            ])
            ->orderBy('due_date')->get();

        if ($remaining->isEmpty()) {
            $agreement->recomputeNextDue();
            return true;
        }

        // Any installment now due after the deadline must be pulled in. If the
        // whole tail still fits, only the late ones move; if the deadline is
        // already upon us, collapse them all to "due now" (customer pays now /
        // early payoff, else dunning defaults it as before).
        $now = Carbon::now();
        $late = $remaining->filter(fn ($p) => $p->due_date->greaterThan($deadline));
        if ($late->isEmpty()) {
            $agreement->recomputeNextDue();
            return true;
        }

        $count = $remaining->count();
        if ($deadline->lessThanOrEqualTo($now)) {
            // No room left → everything is due immediately.
            foreach ($remaining as $p) {
                $p->update(['due_date' => $now, 'status' => InstallmentPayment::STATUS_DUE]);
            }
        } else {
            // Spread the remaining installments evenly across [now, deadline].
            $span = max(1, $now->diffInDays($deadline));
            $i = 1;
            foreach ($remaining as $p) {
                $offset = (int) floor($span * $i / $count);
                $p->update(['due_date' => $now->copy()->addDays(min($offset, $span))]);
                $i++;
            }
        }

        $agreement->recomputeNextDue();
        $this->syncOrder($agreement->fresh());

        // Tell the customer their plan was rescheduled with the new dates.
        app(FlexiblePaymentMailer::class)->send($agreement->fresh(), 'installment_event_rescheduled', [
            'new_event_date' => $liveStart->toDateString(),
            'new_deadline' => $deadline->toDateString(),
        ]);

        return true;
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

        $fields = [
            'installment_agreement_id' => $agreement->id,
            'payment_method_kind' => $kind,
            'outstanding_cents' => $outstanding,
        ];

        // Only once the agreement is ACTIVE (down payment actually cleared) do we
        // mark the order partially_paid and take it out of the expire-pending
        // sweep. A still-PENDING agreement (avans not paid) leaves the order
        // expirable, so an abandoned checkout releases its seats normally.
        if (in_array($agreement->status, [
            InstallmentAgreement::STATUS_ACTIVE,
            InstallmentAgreement::STATUS_COMPLETED,
        ], true)) {
            $fields['payment_status'] = $outstanding > 0 ? 'partially_paid' : 'paid';
            $fields['expires_at'] = null;
        }

        // When the plan completes (fully paid), move the order out of 'pending'
        // to 'completed'. Sale/payout was already recorded incrementally per
        // installment, and the normal full-paid recordSale path is bypassed for
        // installment orders — so this triggers only the observer's conversion +
        // notify hooks (no double payout).
        if ($agreement->status === InstallmentAgreement::STATUS_COMPLETED && $outstanding === 0) {
            $fields['status'] = 'completed';
            $fields['paid_at'] = $order->paid_at ?? now();
        }

        $order->forceFill($fields)->save();
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
