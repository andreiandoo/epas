<?php

namespace App\Models\Cashless;

use App\Models\Customer;
use App\Models\FestivalEdition;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WristbandTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashlessDispute extends Model
{
    protected $fillable = [
        'tenant_id', 'festival_edition_id', 'cashless_account_id', 'customer_id',
        'wristband_transaction_id', 'cashless_sale_id', 'vendor_id',
        'dispute_type', 'status', 'amount_disputed_cents', 'amount_refunded_cents',
        'description', 'evidence', 'admin_notes', 'assigned_to', 'priority',
        'opened_at', 'resolved_at', 'resolution_reason', 'meta',
    ];

    protected $casts = [
        'amount_disputed_cents' => 'integer',
        'amount_refunded_cents' => 'integer',
        'evidence'              => 'array',
        'opened_at'             => 'datetime',
        'resolved_at'           => 'datetime',
        'meta'                  => 'array',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function edition(): BelongsTo { return $this->belongsTo(FestivalEdition::class, 'festival_edition_id'); }
    public function account(): BelongsTo { return $this->belongsTo(CashlessAccount::class, 'cashless_account_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function transaction(): BelongsTo { return $this->belongsTo(WristbandTransaction::class, 'wristband_transaction_id'); }
    public function sale(): BelongsTo { return $this->belongsTo(CashlessSale::class, 'cashless_sale_id'); }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }

    public function isOpen(): bool { return $this->status === 'open'; }
    public function isResolved(): bool { return str_starts_with($this->status, 'resolved_'); }

    public function scopeOpen($query) { return $query->where('status', 'open'); }
    public function scopeUnresolved($query) { return $query->whereNotIn('status', ['resolved_refund', 'resolved_partial_refund', 'resolved_no_action', 'rejected']); }
}
