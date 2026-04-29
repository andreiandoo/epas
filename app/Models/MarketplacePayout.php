<?php

namespace App\Models;

use App\Notifications\MarketplaceAdminPayoutRequestNotification;
use App\Notifications\MarketplacePayoutNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MarketplacePayout extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_organizer_id',
        'event_id',
        'reference',
        'amount',
        'currency',
        'period_start',
        'period_end',
        'gross_amount',
        'commission_amount',
        'fees_amount',
        'adjustments_amount',
        'adjustments_note',
        'status',
        'source',
        'payout_method',
        'approved_by',
        'approved_at',
        'processed_by',
        'processed_at',
        'completed_at',
        'rejection_reason',
        'rejected_by',
        'rejected_at',
        'payment_reference',
        'payment_method',
        'payment_notes',
        'admin_notes',
        'organizer_notes',
        'ticket_breakdown',
        'commission_mode',
        'invoice_recipient_type',
    ];

    public function isCommissionAddedOnTop(): bool
    {
        return $this->commission_mode === 'added_on_top';
    }

    protected $casts = [
        'amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'fees_amount' => 'decimal:2',
        'adjustments_amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'payout_method' => 'array',
        'ticket_breakdown' => 'array',
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payout) {
            if (empty($payout->reference)) {
                $payout->reference = 'PAY-' . strtoupper(Str::random(8));
            }
        });

        static::created(function ($payout) {
            // Append payout ID to reference (e.g., PAY-QGTQJTNF-1)
            if (!str_ends_with($payout->reference, '-' . $payout->id)) {
                $payout->updateQuietly(['reference' => $payout->reference . '-' . $payout->id]);
            }
        });
    }

    // =========================================
    // Relationships
    // =========================================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'approved_by');
    }

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'processed_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'rejected_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MarketplaceTransaction::class);
    }

    public function decontDocument(): HasOne
    {
        return $this->hasOne(OrganizerDocument::class, 'marketplace_payout_id')
            ->where('document_type', 'decont');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        // Regular invoice (commission billed for online ticket sales) — excludes POS-commission invoices
        return $this->hasOne(\App\Models\Invoice::class, 'marketplace_payout_id')
            ->where(function ($q) {
                $q->whereNull('meta->is_pos_commission')
                    ->orWhere('meta->is_pos_commission', false);
            });
    }

    public function posInvoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        // Separate invoice that charges the organizer for commissions on POS/app sales,
        // since that money never flowed through the marketplace.
        return $this->hasOne(\App\Models\Invoice::class, 'marketplace_payout_id')
            ->where('meta->is_pos_commission', true);
    }

    /**
     * Split gross / commission / discount / extras / net for this payout
     * across online vs POS rows of the breakdown. POS rows are those whose
     * ticket_type sells exclusively via pos_app orders (see getPosTicketTypeIds()).
     *
     * Per-line math mirrors the "Detalii bilete" blade:
     *   gross = price*qty (+ commission for added_on_top → what the customer paid)
     *   commission = commission_per_ticket * qty
     *   discount = per-row when present in snapshot, else allocated from order-level
     *              discounts via getDiscountsPerTicketType()
     *   extras = per-row only (insurance, cultural-card surcharge); legacy
     *            snapshots don't track this
     *   net = gross - commission - discount - extras
     *
     * @return array{
     *   online: array{gross: float, commission: float, discount: float, extras: float, net: float},
     *   pos: array{gross: float, commission: float, discount: float, extras: float, net: float}
     * }
     */
    public function getBreakdownTotals(): array
    {
        $breakdown = $this->ticket_breakdown ?? [];
        $posSet = array_flip($this->getPosTicketTypeIds());
        $result = [
            'online' => ['gross' => 0.0, 'commission' => 0.0, 'discount' => 0.0, 'extras' => 0.0, 'net' => 0.0],
            'pos'    => ['gross' => 0.0, 'commission' => 0.0, 'discount' => 0.0,'extras' => 0.0, 'net' => 0.0],
        ];

        // Legacy snapshots don't carry per-row discount; fall back to the
        // record-level allocation by ticket type.
        $hasPerRowDiscount = !empty($breakdown) && array_key_exists('discount', $breakdown[0] ?? []);
        $legacyDiscountsByType = (!empty($breakdown) && !$hasPerRowDiscount)
            ? $this->getDiscountsPerTicketType()
            : [];

        foreach ($breakdown as $item) {
            $ttId = $item['ticket_type_id'] ?? null;
            $bucket = ($ttId && isset($posSet[$ttId])) ? 'pos' : 'online';

            $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
            $qty = (int) ($item['quantity'] ?? $item['tickets'] ?? $item['qty'] ?? 0);
            $commPer = (float) ($item['commission_per_ticket'] ?? 0);
            $itemMode = $item['commission_mode'] ?? null;
            $isOnTop = in_array($itemMode, ['added_on_top', 'on_top'], true);

            $commission = $commPer * $qty;
            $gross = $price * $qty + ($isOnTop ? $commission : 0);

            $discount = $hasPerRowDiscount
                ? (float) ($item['discount'] ?? 0)
                : (float) ($legacyDiscountsByType[$ttId] ?? 0);
            $extras = (float) ($item['extras'] ?? 0);

            // Prefer the snapshot's stored net when present (post-discount/extras);
            // else compute uniformly. The blade uses the same precedence so
            // "Net final" in the table matches what we add here.
            $net = isset($item['net'])
                ? (float) $item['net']
                : ($gross - $commission - $discount - $extras);

            $result[$bucket]['gross'] += $gross;
            $result[$bucket]['commission'] += $commission;
            $result[$bucket]['discount'] += $discount;
            $result[$bucket]['extras'] += $extras;
            $result[$bucket]['net'] += $net;
        }

        foreach ($result as $k => $v) {
            $result[$k]['gross'] = round($v['gross'], 2);
            $result[$k]['commission'] = round($v['commission'], 2);
            $result[$k]['discount'] = round($v['discount'], 2);
            $result[$k]['extras'] = round($v['extras'], 2);
            $result[$k]['net'] = round($v['net'], 2);
        }

        return $result;
    }

    /**
     * Commission value attributable to online (non-POS) tickets in this payout.
     * Derived from stored commission_amount minus POS commission; this is what
     * the regular Factură should bill, since POS commission is invoiced separately.
     */
    public function getCommissionExclPos(): float
    {
        $stored = (float) ($this->commission_amount ?? 0);
        return round(max(0, $stored - $this->getPosCommissionTotal()), 2);
    }

    /**
     * Total commission value attributable to POS/app-only tickets in this payout.
     * Used to generate a separate invoice billed to the organizer.
     */
    public function getPosCommissionTotal(): float
    {
        $posTypeIds = $this->getPosTicketTypeIds();
        if (empty($posTypeIds)) {
            return 0.0;
        }
        $posSet = array_flip($posTypeIds);

        $total = 0.0;
        foreach ($this->ticket_breakdown ?? [] as $item) {
            $ttId = $item['ticket_type_id'] ?? null;
            if (!$ttId || !isset($posSet[$ttId])) {
                continue;
            }
            $qty = (int) ($item['quantity'] ?? $item['tickets'] ?? $item['qty'] ?? 0);
            $commPer = (float) ($item['commission_per_ticket'] ?? 0);
            $total += $qty * $commPer;
        }
        return round($total, 2);
    }

    // =========================================
    // Status Checks
    // =========================================

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canBeApproved(): bool
    {
        return $this->isPending();
    }

    public function canBeProcessed(): bool
    {
        return $this->isApproved();
    }

    public function canBeCompleted(): bool
    {
        // Can complete from either approved or processing status
        return $this->isApproved() || $this->isProcessing();
    }

    public function canBeCancelled(): bool
    {
        return $this->isPending();
    }

    public function canBeRejected(): bool
    {
        return $this->isPending() || $this->isApproved();
    }

    // =========================================
    // Actions
    // =========================================

    /**
     * Approve the payout request
     */
    public function approve(int $userId): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        $this->notifyOrganizer('approved');
    }

    /**
     * Mark as processing
     */
    public function markAsProcessing(int $userId): void
    {
        $this->update([
            'status' => 'processing',
            'processed_by' => $userId,
            'processed_at' => now(),
        ]);

        $this->notifyOrganizer('processing');
    }

    /**
     * Complete the payout
     */
    public function complete(string $paymentReference, ?string $paymentNotes = null): void
    {
        $this->update([
            'status' => 'completed',
            'payment_reference' => $paymentReference,
            'payment_notes' => $paymentNotes,
            'completed_at' => now(),
        ]);

        // Update organizer balances
        $this->organizer->recordPayoutCompleted($this->amount);

        // Build description with payment reference
        $description = "Plată {$this->reference} finalizată";
        if ($paymentReference) {
            $description .= " (Ref: {$paymentReference})";
        }

        // Record transaction
        MarketplaceTransaction::create([
            'marketplace_client_id' => $this->marketplace_client_id,
            'marketplace_organizer_id' => $this->marketplace_organizer_id,
            'type' => 'payout',
            'amount' => -$this->amount,
            'currency' => $this->currency,
            'balance_after' => $this->organizer->available_balance,
            'marketplace_payout_id' => $this->id,
            'description' => $description,
            'metadata' => [
                'payment_reference' => $paymentReference,
                'payment_notes' => $paymentNotes,
            ],
        ]);

        $this->notifyOrganizer('completed');
    }

    /**
     * Reject the payout request
     */
    public function reject(int $userId, string $reason): void
    {
        $wasApproved = $this->isApproved();

        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'rejected_by' => $userId,
            'rejected_at' => now(),
        ]);

        // Return balance to available
        $this->organizer->returnPendingBalance($this->amount);

        $this->notifyOrganizer('rejected');
    }

    /**
     * Cancel the payout request (by organizer)
     */
    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
        ]);
    }

    // =========================================
    // Helpers
    // =========================================

    /**
     * Return the list of ticket_type_ids in this payout's breakdown whose sales
     * come exclusively from POS/app orders (source=pos_app). These rows must
     * be shown in the table for transparency but excluded from totals, since
     * POS money doesn't flow through the marketplace.
     *
     * @return array<int>  ticket_type_ids
     */
    public function getPosTicketTypeIds(): array
    {
        $typeIds = collect($this->ticket_breakdown ?? [])
            ->pluck('ticket_type_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($typeIds) || !$this->event_id) {
            return [];
        }

        // For each ticket type, check: are there any non-pos_app tickets?
        // If not, and there are pos_app tickets, it's a POS-only type.
        $posTypeIds = [];
        foreach ($typeIds as $typeId) {
            $hasNonPos = Ticket::where('ticket_type_id', $typeId)
                ->whereHas('order', function ($q) {
                    $q->where(function ($q2) {
                        $q2->where('event_id', $this->event_id)
                            ->orWhere('marketplace_event_id', $this->event_id);
                    })
                    ->whereIn('status', ['paid', 'confirmed', 'completed'])
                    ->where('source', '!=', 'pos_app');
                })
                ->exists();

            if ($hasNonPos) {
                continue;
            }

            $hasPos = Ticket::where('ticket_type_id', $typeId)
                ->whereHas('order', function ($q) {
                    $q->where(function ($q2) {
                        $q2->where('event_id', $this->event_id)
                            ->orWhere('marketplace_event_id', $this->event_id);
                    })
                    ->whereIn('status', ['paid', 'confirmed', 'completed'])
                    ->where('source', 'pos_app');
                })
                ->exists();

            if ($hasPos) {
                $posTypeIds[] = $typeId;
            }
        }

        return $posTypeIds;
    }

    /**
     * Compute discount amount attributable to each ticket type in this payout.
     * For each paid order in the payout's event + period that carries a discount,
     * the discount is distributed across ticket types proportionally to each
     * type's value contribution in that order.
     *
     * @return array<int, float>  [ticket_type_id => discount_amount]
     */
    public function getDiscountsPerTicketType(): array
    {
        if (!$this->event_id) {
            return [];
        }

        $query = Order::where('event_id', $this->event_id)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where('discount_amount', '>', 0);

        if ($this->period_start) {
            $query->where('created_at', '>=', $this->period_start->copy()->startOfDay());
        }
        if ($this->period_end) {
            $query->where('created_at', '<=', $this->period_end->copy()->endOfDay());
        }

        $orders = $query->with(['tickets:id,order_id,ticket_type_id,price'])->get();

        $discountsByType = [];
        foreach ($orders as $order) {
            $tickets = $order->tickets;
            $totalsByType = [];
            foreach ($tickets as $ticket) {
                if (!$ticket->ticket_type_id) {
                    continue;
                }
                $totalsByType[$ticket->ticket_type_id] = ($totalsByType[$ticket->ticket_type_id] ?? 0) + (float) $ticket->price;
            }

            $orderValue = array_sum($totalsByType);
            if ($orderValue <= 0) {
                continue;
            }

            foreach ($totalsByType as $typeId => $typeTotal) {
                $proportion = $typeTotal / $orderValue;
                $share = (float) $order->discount_amount * $proportion;
                $discountsByType[$typeId] = ($discountsByType[$typeId] ?? 0) + $share;
            }
        }

        return array_map(fn ($v) => round($v, 2), $discountsByType);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending Review',
            'approved' => 'Approved',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'approved' => 'info',
            'processing' => 'primary',
            'completed' => 'success',
            'rejected' => 'danger',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Send notification to organizer
     */
    public function notifyOrganizer(string $action): void
    {
        if (!$this->organizer) {
            return;
        }

        // Send email notification
        $this->organizer->notify(new MarketplacePayoutNotification($this, $action));

        // Create database notification in MarketplaceNotification table
        $this->createOrganizerNotification($action);
    }

    /**
     * Create notification record in MarketplaceNotification table
     */
    protected function createOrganizerNotification(string $action): void
    {
        $amount = number_format($this->amount, 2) . ' ' . $this->currency;

        $typeMap = [
            'submitted' => MarketplaceNotification::TYPE_PAYOUT_REQUEST,
            'approved' => MarketplaceNotification::TYPE_PAYOUT_APPROVED,
            'processing' => MarketplaceNotification::TYPE_PAYOUT_PROCESSING,
            'completed' => MarketplaceNotification::TYPE_PAYOUT_COMPLETED,
            'rejected' => MarketplaceNotification::TYPE_PAYOUT_REJECTED,
        ];

        $titleMap = [
            'submitted' => 'Cerere de plată înregistrată',
            'approved' => 'Cerere de plată aprobată',
            'processing' => 'Plată în procesare',
            'completed' => 'Plată finalizată',
            'rejected' => 'Cerere de plată respinsă',
        ];

        $messageMap = [
            'submitted' => "Cererea de plată {$this->reference} în valoare de {$amount} a fost înregistrată.",
            'approved' => "Cererea de plată {$this->reference} în valoare de {$amount} a fost aprobată.",
            'processing' => "Plata {$this->reference} în valoare de {$amount} este în curs de procesare.",
            'completed' => "Plata {$this->reference} în valoare de {$amount} a fost finalizată.",
            'rejected' => "Cererea de plată {$this->reference} în valoare de {$amount} a fost respinsă.",
        ];

        $data = [
            'payout_id' => $this->id,
            'reference' => $this->reference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'action' => $action,
        ];

        if ($action === 'rejected' && $this->rejection_reason) {
            $data['rejection_reason'] = $this->rejection_reason;
        }

        MarketplaceNotification::create([
            'marketplace_client_id' => $this->marketplace_client_id,
            'marketplace_organizer_id' => $this->marketplace_organizer_id,
            'type' => $typeMap[$action] ?? MarketplaceNotification::TYPE_PAYOUT_REQUEST,
            'title' => $titleMap[$action] ?? 'Actualizare plată',
            'message' => $messageMap[$action] ?? "Actualizare pentru plata {$this->reference}.",
            'data' => $data,
            'actionable_type' => self::class,
            'actionable_id' => $this->id,
            'action_url' => "/organizator/sold",
        ]);
    }

    /**
     * Send notification to marketplace admins about new payout request
     */
    public function notifyAdmins(): void
    {
        // Get all active admins for this marketplace client
        $admins = MarketplaceAdmin::where('marketplace_client_id', $this->marketplace_client_id)
            ->where('status', 'active')
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new MarketplaceAdminPayoutRequestNotification($this));
        }
    }
}
