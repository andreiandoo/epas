<?php

namespace App\Models\Integrations\Zapier;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZapierTriggerLog extends Model
{
    protected $fillable = [
        'trigger_id', 'trigger_type', 'payload', 'status', 'http_status',
        'response', 'correlation_ref', 'triggered_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'triggered_at' => 'datetime',
    ];

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(ZapierTrigger::class, 'trigger_id');
    }
}
