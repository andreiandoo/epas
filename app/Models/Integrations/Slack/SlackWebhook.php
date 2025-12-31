<?php

namespace App\Models\Integrations\Slack;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlackWebhook extends Model
{
    protected $fillable = [
        'connection_id',
        'event_type',
        'endpoint_url',
        'secret',
        'is_active',
        'last_triggered_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    protected $hidden = ['secret'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SlackConnection::class, 'connection_id');
    }
}
