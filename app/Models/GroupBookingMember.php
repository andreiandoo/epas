<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GroupBookingMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_booking_id', 'ticket_id', 'name', 'email', 'phone',
        'amount_due', 'amount_paid', 'payment_status', 'payment_link', 'paid_at',
    ];

    protected $casts = [
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';

    public function booking(): BelongsTo { return $this->belongsTo(GroupBooking::class, 'group_booking_id'); }
    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }

    public function isPaid(): bool { return $this->payment_status === self::STATUS_PAID; }
    public function getBalance(): float { return $this->amount_due - $this->amount_paid; }
}
