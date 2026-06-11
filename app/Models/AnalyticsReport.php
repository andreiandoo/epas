<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnalyticsReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'user_id', 'name', 'type', 'config',
        'schedule', 'format', 'last_generated_at',
    ];

    protected $casts = [
        'config' => 'array',
        'schedule' => 'array',
        'last_generated_at' => 'datetime',
    ];

    const TYPE_SALES = 'sales';
    const TYPE_ATTENDANCE = 'attendance';
    const TYPE_FINANCIAL = 'financial';
    const TYPE_CUSTOM = 'custom';

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function scopeForTenant($query, $tenantId) { return $query->where('tenant_id', $tenantId); }
}
