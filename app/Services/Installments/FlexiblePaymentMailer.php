<?php

namespace App\Services\Installments;

use App\Models\InstallmentAgreement;
use App\Models\InstallmentPayment;
use App\Models\MarketplaceClient;
use App\Services\MarketplaceEmailService;
use Illuminate\Support\Facades\Log;

/**
 * Sends flexible-payment emails using the marketplace's editable templates
 * (MarketplaceEmailTemplate) via MarketplaceEmailService::sendTemplatedEmail().
 * Falls back silently (logged) when there is no marketplace client or template.
 */
class FlexiblePaymentMailer
{
    public function send(InstallmentAgreement $agreement, string $slug, array $extra = []): bool
    {
        $client = $agreement->marketplace_client_id
            ? MarketplaceClient::find($agreement->marketplace_client_id)
            : null;

        if (! $client) {
            // Tenant-scoped agreements use TenantMailService (wired separately).
            Log::info("FlexiblePaymentMailer: no marketplace client for agreement {$agreement->id}, slug {$slug}");
            return false;
        }

        $variables = array_merge($this->baseVariables($agreement), $extra);

        return (new MarketplaceEmailService($client))->sendTemplatedEmail(
            $slug,
            $agreement->customer_email,
            $agreement->customer_name,
            $variables,
            $agreement->marketplace_customer_id
        );
    }

    public function baseVariables(InstallmentAgreement $agreement): array
    {
        $currency = $agreement->currency ?: 'RON';
        $next = $agreement->payments()
            ->whereIn('status', ['scheduled', 'due', 'retrying', 'action_required'])
            ->orderBy('due_date')->first();

        return [
            'customer_name' => $agreement->customer_name ?: '',
            'plan_name' => $agreement->plan_snapshot['plan']['name'] ?? '',
            'event_name' => (string) ($agreement->metadata['event_name'] ?? ''),
            'event_date' => optional($agreement->event_start_date)->format('d.m.Y'),
            'customer_total' => $this->money($agreement->customer_total_cents, $currency),
            'direct_price' => $this->money($agreement->base_total_cents, $currency),
            'down_payment' => $this->money($agreement->down_payment_cents, $currency),
            'remaining_balance' => $this->money($agreement->outstandingCents(), $currency),
            'installments_count' => (string) $agreement->number_of_installments,
            'next_due_date' => $next ? $next->due_date->format('d.m.Y') : '-',
            'next_due_amount' => $next ? $this->money($next->amount_cents, $currency) : '-',
            'schedule_table' => $this->scheduleTable($agreement, $currency),
            'portal_link' => $this->portalLink($agreement),
        ];
    }

    public function paymentVariables(InstallmentPayment $payment, string $currency): array
    {
        return [
            'installment_amount' => $this->money($payment->amount_cents, $currency),
            'installment_sequence' => (string) $payment->sequence,
            'due_date' => $payment->due_date->format('d.m.Y'),
            'pay_link' => $payment->pay_link_token ? url("/pay/{$payment->pay_link_token}") : '',
        ];
    }

    protected function scheduleTable(InstallmentAgreement $agreement, string $currency): string
    {
        $rows = $agreement->payments()->orderBy('sequence')->get()->map(function ($p) use ($currency) {
            $label = $p->sequence === 0 ? 'Avans' : "Rata {$p->sequence}";
            return sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
                $label,
                $p->due_date->format('d.m.Y'),
                $this->money($p->amount_cents, $currency)
            );
        })->implode('');

        return '<table><thead><tr><th>Plată</th><th>Scadență</th><th>Sumă</th></tr></thead><tbody>'
            . $rows . '</tbody></table>';
    }

    protected function money(int $cents, string $currency): string
    {
        return number_format($cents / 100, 2, ',', '.') . ' ' . $currency;
    }

    protected function portalLink(InstallmentAgreement $agreement): string
    {
        return url("/installments/agreements/{$agreement->id}");
    }
}
