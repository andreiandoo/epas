<?php

namespace App\Models\Integrations\Microsoft365;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MicrosoftOnedriveFile extends Model
{
    protected $fillable = [
        'connection_id', 'item_id', 'name', 'mime_type', 'size',
        'web_url', 'parent_reference', 'correlation_ref', 'uploaded_at',
    ];

    protected $casts = ['uploaded_at' => 'datetime'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Microsoft365Connection::class, 'connection_id');
    }
}
