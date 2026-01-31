<?php

namespace App\Models\Integrations\GoogleWorkspace;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleGmailMessage extends Model
{
    protected $fillable = [
        'connection_id', 'message_id', 'thread_id', 'to_email', 'subject',
        'body', 'is_html', 'attachments', 'status', 'correlation_ref',
        'sent_at', 'error_details',
    ];

    protected $casts = [
        'is_html' => 'boolean',
        'attachments' => 'array',
        'error_details' => 'array',
        'sent_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleWorkspaceConnection::class, 'connection_id');
    }
}
