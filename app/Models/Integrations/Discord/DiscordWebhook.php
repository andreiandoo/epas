<?php

namespace App\Models\Integrations\Discord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordWebhook extends Model
{
    protected $fillable = [
        'connection_id', 'webhook_id', 'webhook_token', 'name',
        'channel_id', 'channel_name', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];
    protected $hidden = ['webhook_token'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(DiscordConnection::class, 'connection_id');
    }

    public function getWebhookUrl(): string
    {
        return "https://discord.com/api/webhooks/{$this->webhook_id}/{$this->webhook_token}";
    }
}
