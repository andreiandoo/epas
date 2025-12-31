<?php

namespace App\Models\Integrations\Slack;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlackMessage extends Model
{
    protected $fillable = [
        'connection_id',
        'channel_id',
        'message_ts',
        'direction',
        'content',
        'blocks',
        'attachments',
        'status',
        'correlation_ref',
        'sent_at',
        'error_details',
    ];

    protected $casts = [
        'blocks' => 'array',
        'attachments' => 'array',
        'error_details' => 'array',
        'sent_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SlackConnection::class, 'connection_id');
    }
}
