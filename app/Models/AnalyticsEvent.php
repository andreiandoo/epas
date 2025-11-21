<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnalyticsEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'event_id', 'event_type', 'properties',
        'session_id', 'user_agent', 'ip_address', 'occurred_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'occurred_at' => 'datetime',
    ];

    const TYPE_PAGE_VIEW = 'page_view';
    const TYPE_PURCHASE = 'purchase';
    const TYPE_CHECK_IN = 'check_in';
    const TYPE_CART_ADD = 'cart_add';

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }

    public function scopeForTenant($query, $tenantId) { return $query->where('tenant_id', $tenantId); }
    public function scopeOfType($query, $type) { return $query->where('event_type', $type); }
}
