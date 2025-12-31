<?php

namespace App\Models\Integrations\Microsoft365;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MicrosoftTeamsMessage extends Model
{
    protected $fillable = [
        'connection_id', 'team_id', 'channel_id', 'message_id', 'content',
        'content_type', 'status', 'correlation_ref', 'sent_at', 'error_details',
    ];

    protected $casts = [
        'error_details' => 'array',
        'sent_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Microsoft365Connection::class, 'connection_id');
    }
}
