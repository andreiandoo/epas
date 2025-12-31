<?php

namespace App\Models\Integrations\GoogleSheets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleSheetsSpreadsheet extends Model
{
    protected $fillable = [
        'connection_id',
        'spreadsheet_id',
        'name',
        'purpose',
        'web_view_link',
        'is_auto_sync',
        'sync_frequency',
        'last_synced_at',
        'sheet_config',
        'metadata',
    ];

    protected $casts = [
        'is_auto_sync' => 'boolean',
        'last_synced_at' => 'datetime',
        'sheet_config' => 'array',
        'metadata' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleSheetsConnection::class, 'connection_id');
    }

    public function syncJobs(): HasMany
    {
        return $this->hasMany(GoogleSheetsSyncJob::class, 'spreadsheet_id');
    }

    public function columnMappings(): HasMany
    {
        return $this->hasMany(GoogleSheetsColumnMapping::class, 'spreadsheet_id');
    }

    public function getColumnMappingsFor(string $dataType): array
    {
        return $this->columnMappings()
            ->where('data_type', $dataType)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }
}
