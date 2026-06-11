<?php

namespace App\Models\Integrations\GoogleSheets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleSheetsColumnMapping extends Model
{
    protected $fillable = [
        'spreadsheet_id',
        'data_type',
        'local_field',
        'sheet_column',
        'column_header',
        'data_format',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function spreadsheet(): BelongsTo
    {
        return $this->belongsTo(GoogleSheetsSpreadsheet::class, 'spreadsheet_id');
    }
}
