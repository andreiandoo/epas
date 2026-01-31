<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomerSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'conditions',
        'is_dynamic', 'member_count', 'last_calculated_at',
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_dynamic' => 'boolean',
        'last_calculated_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_segment_members', 'segment_id', 'customer_id')
            ->withPivot('added_at')
            ->withTimestamps();
    }

    public function campaigns() { return $this->hasMany(EmailCampaign::class, 'segment_id'); }

    public function scopeForTenant($query, $tenantId) { return $query->where('tenant_id', $tenantId); }
    public function scopeDynamic($query) { return $query->where('is_dynamic', true); }
}
