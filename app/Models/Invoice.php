<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'tenant_id',
        'number',
        'type',
        'description',
        'issue_date',
        'period_start',
        'period_end',
        'due_date',
        'subtotal',
        'vat_rate',
        'vat_amount',
        'amount',
        'currency',
        'status',
        'stripe_payment_link_id',
        'stripe_payment_link_url',
        'stripe_checkout_session_id',
        'paid_at',
        'payment_method',
        'meta',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'meta' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // Scopes
    public function scopeOutstanding($q)
    {
        return $q->where('status', 'outstanding');
    }

    public function scopePaid($q)
    {
        return $q->where('status', 'paid');
    }

    public function scopeProforma($q)
    {
        return $q->where('type', 'proforma');
    }

    public function scopeFiscal($q)
    {
        return $q->where('type', 'fiscal');
    }

    /**
     * Check if this is a proforma invoice
     */
    public function isProforma(): bool
    {
        return $this->type === 'proforma';
    }

    /**
     * Check if this is a fiscal invoice
     */
    public function isFiscal(): bool
    {
        return $this->type === 'fiscal';
    }

    /**
     * Get the invoice type label in Romanian
     */
    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'proforma' => 'Factura Proforma',
            'fiscal' => 'Factura Fiscala',
            default => 'Factura',
        };
    }

    /**
     * Check if invoice has a payment link
     */
    public function hasPaymentLink(): bool
    {
        return !empty($this->stripe_payment_link_url);
    }

    /**
     * Mark invoice as paid
     */
    public function markAsPaid(string $paymentMethod = 'stripe', ?string $checkoutSessionId = null): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $paymentMethod,
            'stripe_checkout_session_id' => $checkoutSessionId ?? $this->stripe_checkout_session_id,
        ]);
    }

    /**
     * Generate description based on tenant contract and period
     */
    public static function generateDescription(Tenant $tenant, ?\Carbon\Carbon $periodStart = null, ?\Carbon\Carbon $periodEnd = null): string
    {
        $description = "Comision servicii digitale";

        // Add contract info
        if ($tenant->contract_number) {
            $description .= ", conform Contract nr. {$tenant->contract_number}";

            // Add contract validation date if available
            if ($tenant->contract_signed_at) {
                $description .= " din data de " . $tenant->contract_signed_at->format('d.m.Y');
            }
        }

        // Add billing period
        if ($periodStart && $periodEnd) {
            $description .= " - Perioada {$periodStart->format('d.m.Y')} - {$periodEnd->format('d.m.Y')}";
        }

        return $description;
    }
}
