<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GroupBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'event_id', 'organizer_customer_id', 'group_name', 'group_type',
        'total_tickets', 'total_amount', 'discount_percentage', 'discount_amount',
        'status', 'payment_type', 'notes', 'meta', 'confirmed_at', 'deadline_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'meta' => 'array',
        'confirmed_at' => 'datetime',
        'deadline_at' => 'datetime',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    const PAYMENT_FULL = 'full';
    const PAYMENT_SPLIT = 'split';
    const PAYMENT_INVOICE = 'invoice';

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function organizer(): BelongsTo { return $this->belongsTo(Customer::class, 'organizer_customer_id'); }
    public function members(): HasMany { return $this->hasMany(GroupBookingMember::class); }

    public function scopeForTenant($q, $id) { return $q->where('tenant_id', $id); }
    public function scopeConfirmed($q) { return $q->where('status', self::STATUS_CONFIRMED); }

    public function getFinalAmount(): float
    {
        return $this->total_amount - $this->discount_amount;
    }

    public function getPaymentProgress(): float
    {
        $paid = $this->members()->sum('amount_paid');
        return $this->getFinalAmount() > 0 ? ($paid / $this->getFinalAmount()) * 100 : 0;
    }
}
