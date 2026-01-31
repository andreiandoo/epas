<?php

namespace App\Models\Integrations\Airtable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AirtableBase extends Model
{
    protected $fillable = [
        'connection_id',
        'base_id',
        'name',
        'permission_level',
        'tables',
        'tables_synced_at',
        'metadata',
    ];

    protected $casts = [
        'tables' => 'array',
        'tables_synced_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AirtableConnection::class, 'connection_id');
    }

    public function tableSyncs(): HasMany
    {
        return $this->hasMany(AirtableTableSync::class, 'base_id');
    }

    public function canEdit(): bool
    {
        return in_array($this->permission_level, ['owner', 'editor']);
    }
}
