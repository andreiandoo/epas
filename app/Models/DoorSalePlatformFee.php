<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DoorSalePlatformFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'door_sale_id', 'transaction_amount',
        'fee_percentage', 'fee_amount', 'settled', 'settled_at',
    ];

    protected $casts = [
        'transaction_amount' => 'decimal:2',
        'fee_percentage' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'settled' => 'boolean',
        'settled_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function doorSale(): BelongsTo { return $this->belongsTo(DoorSale::class); }

    public function scopeUnsettled($query) { return $query->where('settled', false); }
    public function scopeForTenant($query, $tenantId) { return $query->where('tenant_id', $tenantId); }
}
