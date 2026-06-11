<?php

namespace App\Models\Integrations\Airtable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AirtableRecordMapping extends Model
{
    protected $fillable = [
        'table_sync_id',
        'local_type',
        'local_id',
        'airtable_record_id',
        'last_synced_at',
        'sync_hash',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public function tableSync(): BelongsTo
    {
        return $this->belongsTo(AirtableTableSync::class, 'table_sync_id');
    }

    public function needsSync(string $currentHash): bool
    {
        return $this->sync_hash !== $currentHash;
    }

    public function updateSyncHash(string $hash): void
    {
        $this->update([
            'sync_hash' => $hash,
            'last_synced_at' => now(),
        ]);
    }
}
