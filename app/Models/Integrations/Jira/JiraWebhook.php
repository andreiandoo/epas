<?php

namespace App\Models\Integrations\Jira;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JiraWebhook extends Model
{
    protected $fillable = [
        'connection_id', 'webhook_id', 'event_type', 'endpoint_url',
        'jql_filter', 'is_active', 'last_triggered_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(JiraConnection::class, 'connection_id');
    }
}
