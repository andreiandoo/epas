<?php

namespace App\Models\Integrations\Discord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordMessage extends Model
{
    protected $fillable = [
        'connection_id', 'channel_id', 'message_id', 'delivery_method',
        'content', 'embeds', 'status', 'correlation_ref', 'sent_at', 'error_details',
    ];

    protected $casts = [
        'embeds' => 'array',
        'error_details' => 'array',
        'sent_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(DiscordConnection::class, 'connection_id');
    }
}
