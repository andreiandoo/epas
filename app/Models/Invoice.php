<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    protected $fillable = [
        'tenant_id',
        'marketplace_client_id',
        'marketplace_organizer_id',
        'marketplace_payout_id',
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

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(\App\Models\MarketplacePayout::class, 'marketplace_payout_id');
    }

    public function anafQueue(): HasOne
    {
        return $this->hasOne(AnafQueue::class, 'invoice_id');
    }

    /**
     * Get the client name (tenant or marketplace client)
     */
    public function getClientNameAttribute(): string
    {
        if ($this->tenant) {
            return $this->tenant->public_name ?? $this->tenant->name;
        }
        if ($this->marketplaceClient) {
            return $this->marketplaceClient->name;
        }
        return '-';
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

    // ─── Accounting provider (Oblio) status helpers ──────────────────────
    //
    // Provider state lives in meta.accounting.* — extended with:
    //   deleted_at         (proforma removed at provider)
    //   stornoed_at        (fiscal has a credit note at provider)
    //   status_check_at    (last time we asked the provider)
    //   status_check_source ('mount' | 'manual' | 'cron')
    //   history[]          (past external_refs after resend, oldest first)
    // The two "voided" states are kept distinct per operator request — a
    // proforma really disappears while a fiscal only becomes canceled.

    /**
     * Provider-side proforma removal (meta.accounting.deleted_at set).
     * True only for proformas — fiscals get isStornoedInOblio instead.
     */
    public function isDeletedInOblio(): bool
    {
        return !empty(($this->meta['accounting']['deleted_at'] ?? null));
    }

    /**
     * Provider-side fiscal storno (credit note issued, meta.accounting.stornoed_at set).
     */
    public function isStornoedInOblio(): bool
    {
        return !empty(($this->meta['accounting']['stornoed_at'] ?? null));
    }

    /**
     * Either voided state — proforma deleted OR fiscal stornoed.
     * Convenience for buttons that behave identically in both cases
     * (retrimit / regenerează).
     */
    public function isVoidedInOblio(): bool
    {
        return $this->isDeletedInOblio() || $this->isStornoedInOblio();
    }

    /**
     * Has an external_ref AND that ref is still live at the provider.
     * Buttons that mutate the invoice locally (regenerează, edit items)
     * should be hidden when this returns true — the operator must storno
     * or wait for a real removal in Oblio first.
     */
    public function hasLiveOblioInvoice(): bool
    {
        return !empty(($this->meta['accounting']['external_ref'] ?? null))
            && !$this->isVoidedInOblio();
    }

    /**
     * Timestamp we last asked Oblio about this invoice's state, or null
     * if we never checked.
     */
    public function oblioStatusCheckedAt(): ?\Carbon\Carbon
    {
        $ts = $this->meta['accounting']['status_check_at'] ?? null;
        return $ts ? \Carbon\Carbon::parse($ts) : null;
    }

    /**
     * Persist the outcome of a getInvoiceStatus() call onto meta.accounting.
     * Idempotent: if the state didn't change (still live / still voided at
     * the same moment) nothing writes, so no phantom updated_at bumps.
     *
     * $result shape: OblioAdapter::getInvoiceStatus() return contract.
     * $source: one of 'mount' | 'manual' | 'cron' — surfaces on the UI so
     * operators can tell whether a check was auto or forced.
     *
     * Returns the newly-applied state: 'live' | 'deleted' | 'stornoed' | 'unknown'.
     */
    public function applyOblioStatus(array $result, string $source): string
    {
        $meta = $this->meta ?? [];
        $meta['accounting'] = $meta['accounting'] ?? [];
        $now = now()->toIso8601String();
        $meta['accounting']['status_check_at'] = $now;
        $meta['accounting']['status_check_source'] = $source;

        // Transient — don't mutate deleted_at / stornoed_at, but do stamp the
        // check timestamp so operators can see we tried.
        if (($result['exists'] ?? null) === null) {
            $this->meta = $meta;
            $this->save();
            return 'unknown';
        }

        if ($result['exists'] === false) {
            $applied = 'deleted';
            if ($this->isFiscal()) {
                // Fiscal that comes back "not found" is unusual — treat it as
                // storno since Oblio doesn't let fiscals disappear. Keeps the
                // downstream flag (isStornoedInOblio) consistent with what an
                // operator would see in Oblio's UI.
                $applied = 'stornoed';
                if (empty($meta['accounting']['stornoed_at'])) {
                    $meta['accounting']['stornoed_at'] = $now;
                }
            } else {
                if (empty($meta['accounting']['deleted_at'])) {
                    $meta['accounting']['deleted_at'] = $now;
                }
            }
        } elseif (!empty($result['canceled'])) {
            $applied = 'stornoed';
            if (empty($meta['accounting']['stornoed_at'])) {
                $meta['accounting']['stornoed_at'] = $now;
            }
        } else {
            // Live at provider — clear stale void markers (shouldn't happen
            // in practice, but keeps the flag honest if the operator restored
            // the invoice in Oblio manually).
            $applied = 'live';
            unset($meta['accounting']['deleted_at'], $meta['accounting']['stornoed_at']);
        }

        $this->meta = $meta;

        // Rule 4 of the spec: an invoice can't be locally 'paid' when
        // the provider record is gone/storno — the org didn't actually
        // pay for a canceled document. Reset to outstanding + clear the
        // paid_at stamp so downstream (dashboards, exports) match reality.
        if (in_array($applied, ['deleted', 'stornoed'], true) && $this->status === 'paid') {
            $this->status = 'outstanding';
            $this->paid_at = null;
        }

        $this->save();
        return $applied;
    }

    /**
     * Rebuild items + subtotal + vat + amount from the underlying payout
     * snapshot. Delegates to PosInvoiceContentBuilder so the "Regenerează"
     * button on the invoice edit page and the initial "Generează factură"
     * button on the payout page emit IDENTICAL content — see the service
     * for the four commission-stream categories.
     *
     * Safety: refuses if the invoice is live at the provider — you must
     * storno / delete it there first (or wait for the mount-auto-check to
     * flip the state) so the printed Oblio document and the local one
     * can't disagree.
     *
     * Returns ['old' => [...], 'new' => [...]] with subtotal/vat/amount so
     * the caller can render a diff toast to the operator. Throws on
     * hard errors (no payout linked, no content to bill).
     */
    public function rebuildFromPayout(): array
    {
        if (!$this->marketplace_payout_id) {
            throw new \RuntimeException('Factura nu este legată de un decont — nu se poate regenera.');
        }
        if ($this->hasLiveOblioInvoice()) {
            throw new \RuntimeException('Factura este activă în Oblio — storno acolo întâi.');
        }
        $payout = $this->payout;
        if (!$payout) {
            throw new \RuntimeException('Decontul asociat nu mai există.');
        }

        $result = app(\App\Services\Invoicing\PosInvoiceContentBuilder::class)->build($payout);
        if (isset($result['error'])) {
            throw new \RuntimeException($result['error']);
        }

        $old = [
            'subtotal' => (float) $this->subtotal,
            'vat_amount' => (float) $this->vat_amount,
            'amount' => (float) $this->amount,
            'items_count' => count(($this->meta['items'] ?? [])),
        ];

        $meta = $this->meta ?? [];
        $meta['items'] = $result['items'];
        $meta['rebuilt_at'] = now()->toIso8601String();
        $this->meta = $meta;
        $this->subtotal = $result['subtotal'];
        $this->vat_rate = $result['vat_rate'];
        $this->vat_amount = $result['vat_amount'];
        $this->amount = $result['amount'];
        $this->save();

        return [
            'old' => $old,
            'new' => [
                'subtotal' => (float) $this->subtotal,
                'vat_amount' => (float) $this->vat_amount,
                'amount' => (float) $this->amount,
                'items_count' => count($result['items']),
            ],
        ];
    }

    /**
     * Push the current external_ref (+ pdf_url + sent_at) onto meta.accounting.history
     * before overwriting it with a resend. Called from EditOrganizerInvoice
     * when the operator re-emits after a storno/delete.
     */
    public function archiveCurrentOblioRef(?string $reason = null): void
    {
        $acc = $this->meta['accounting'] ?? [];
        if (empty($acc['external_ref'])) {
            return;
        }
        $meta = $this->meta ?? [];
        $meta['accounting']['history'] = $meta['accounting']['history'] ?? [];
        $meta['accounting']['history'][] = [
            'external_ref' => $acc['external_ref'] ?? null,
            'invoice_number' => $acc['invoice_number'] ?? null,
            'pdf_url' => $acc['pdf_url'] ?? null,
            'sent_at' => $acc['sent_at'] ?? null,
            'deleted_at' => $acc['deleted_at'] ?? null,
            'stornoed_at' => $acc['stornoed_at'] ?? null,
            'reason' => $reason,
            'archived_at' => now()->toIso8601String(),
        ];
        // Clear the void markers + external ref — the next createInvoice
        // call at the provider will populate them fresh.
        unset(
            $meta['accounting']['external_ref'],
            $meta['accounting']['invoice_number'],
            $meta['accounting']['pdf_url'],
            $meta['accounting']['sent_at'],
            $meta['accounting']['deleted_at'],
            $meta['accounting']['stornoed_at'],
            $meta['accounting']['status_check_at'],
            $meta['accounting']['status_check_source']
        );
        $this->meta = $meta;
        $this->save();
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

    /**
     * Generate description for marketplace client invoice
     */
    public static function generateMarketplaceDescription(MarketplaceClient $client, ?\Carbon\Carbon $periodStart = null, ?\Carbon\Carbon $periodEnd = null): string
    {
        $description = "Comision servicii marketplace - {$client->name}";

        if ($periodStart && $periodEnd) {
            $description .= " - Perioada {$periodStart->format('d.m.Y')} - {$periodEnd->format('d.m.Y')}";
        }

        return $description;
    }
}
