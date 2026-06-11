<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentSplit extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'door_sale_id', 'stripe_payment_intent_id', 'stripe_transfer_id',
        'total_amount', 'tenant_amount', 'platform_fee', 'stripe_fee',
        'currency', 'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'tenant_amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'stripe_fee' => 'decimal:2',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function doorSale(): BelongsTo { return $this->belongsTo(DoorSale::class); }

    public function scopeForTenant($query, $tenantId) { return $query->where('tenant_id', $tenantId); }
    public function scopeCompleted($query) { return $query->where('status', self::STATUS_COMPLETED); }

    public function getNetPlatformFee(): float
    {
        return $this->platform_fee - $this->stripe_fee;
    }
}
