<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GroupPricingTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'event_id', 'name', 'min_tickets', 'max_tickets',
        'discount_percentage', 'is_default', 'enabled',
    ];

    protected $casts = [
        'discount_percentage' => 'decimal:2',
        'is_default' => 'boolean',
        'enabled' => 'boolean',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }

    public function scopeForTenant($query, $tenantId) { return $query->where('tenant_id', $tenantId); }
    public function scopeEnabled($query) { return $query->where('enabled', true); }
}
