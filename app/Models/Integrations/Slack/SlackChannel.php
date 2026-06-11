<?php

namespace App\Models\Integrations\Slack;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlackChannel extends Model
{
    protected $fillable = [
        'connection_id',
        'channel_id',
        'name',
        'type',
        'is_private',
        'is_default',
        'metadata',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'is_default' => 'boolean',
        'metadata' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SlackConnection::class, 'connection_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SlackMessage::class, 'channel_id', 'channel_id');
    }
}
