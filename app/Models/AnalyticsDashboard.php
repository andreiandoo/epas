<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnalyticsDashboard extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'user_id', 'name', 'description',
        'is_default', 'is_shared', 'layout', 'filters',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_shared' => 'boolean',
        'layout' => 'array',
        'filters' => 'array',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function widgets(): HasMany { return $this->hasMany(AnalyticsWidget::class, 'dashboard_id'); }

    public function scopeForTenant($query, $tenantId) { return $query->where('tenant_id', $tenantId); }
    public function scopeShared($query) { return $query->where('is_shared', true); }
}
