<?php

namespace App\Models\Integrations\Microsoft365;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MicrosoftOutlookMessage extends Model
{
    protected $fillable = [
        'connection_id', 'message_id', 'conversation_id', 'to_email', 'subject',
        'body', 'body_type', 'attachments', 'status', 'correlation_ref',
        'sent_at', 'error_details',
    ];

    protected $casts = [
        'attachments' => 'array',
        'error_details' => 'array',
        'sent_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Microsoft365Connection::class, 'connection_id');
    }
}
