<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AutomationWorkflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'trigger_type',
        'trigger_conditions', 'is_active', 'enrolled_count', 'completed_count',
    ];

    protected $casts = [
        'trigger_conditions' => 'array',
        'is_active' => 'boolean',
    ];

    const TRIGGER_PURCHASE = 'purchase';
    const TRIGGER_SIGNUP = 'signup';
    const TRIGGER_EVENT_DAY = 'event_day';
    const TRIGGER_CUSTOM = 'custom';

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function steps(): HasMany { return $this->hasMany(AutomationStep::class, 'workflow_id')->orderBy('order'); }
    public function enrollments(): HasMany { return $this->hasMany(AutomationEnrollment::class, 'workflow_id'); }

    public function scopeForTenant($query, $tenantId) { return $query->where('tenant_id', $tenantId); }
    public function scopeActive($query) { return $query->where('is_active', true); }
}
