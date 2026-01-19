<?php

namespace App\Models\Integrations\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramSubscriber extends Model
{
    protected $fillable = [
        'connection_id',
        'user_id',
        'username',
        'first_name',
        'last_name',
        'language_code',
        'is_bot',
        'is_blocked',
        'subscribed_at',
        'blocked_at',
        'last_interaction_at',
        'metadata',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'is_bot' => 'boolean',
        'is_blocked' => 'boolean',
        'subscribed_at' => 'datetime',
        'blocked_at' => 'datetime',
        'last_interaction_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TelegramBotConnection::class, 'connection_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function block(): void
    {
        $this->update([
            'is_blocked' => true,
            'blocked_at' => now(),
        ]);
    }

    public function unblock(): void
    {
        $this->update([
            'is_blocked' => false,
            'blocked_at' => null,
        ]);
    }
}
