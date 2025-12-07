<?php

namespace App\Models\Integrations\Airtable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AirtableTableSync extends Model
{
    protected $fillable = [
        'base_id',
        'table_id',
        'table_name',
        'sync_direction',
        'local_data_type',
        'field_mappings',
        'sync_filters',
        'is_auto_sync',
        'sync_frequency',
        'last_synced_at',
        'metadata',
    ];

    protected $casts = [
        'field_mappings' => 'array',
        'sync_filters' => 'array',
        'is_auto_sync' => 'boolean',
        'last_synced_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function base(): BelongsTo
    {
        return $this->belongsTo(AirtableBase::class, 'base_id');
    }

    public function syncJobs(): HasMany
    {
        return $this->hasMany(AirtableSyncJob::class, 'table_sync_id');
    }

    public function recordMappings(): HasMany
    {
        return $this->hasMany(AirtableRecordMapping::class, 'table_sync_id');
    }

    public function isBidirectional(): bool
    {
        return $this->sync_direction === 'bidirectional';
    }
}
