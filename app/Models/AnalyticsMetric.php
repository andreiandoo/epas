<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnalyticsMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'event_id', 'metric_type', 'date', 'hour', 'data',
    ];

    protected $casts = [
        'date' => 'date',
        'data' => 'array',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }

    public function scopeForTenant($query, $tenantId) { return $query->where('tenant_id', $tenantId); }
    public function scopeForEvent($query, $eventId) { return $query->where('event_id', $eventId); }
    public function scopeOfType($query, $type) { return $query->where('metric_type', $type); }
}
