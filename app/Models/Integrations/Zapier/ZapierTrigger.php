<?php

namespace App\Models\Integrations\Zapier;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZapierTrigger extends Model
{
    protected $fillable = [
        'connection_id', 'trigger_type', 'webhook_url', 'zap_id',
        'is_active', 'last_triggered_at', 'trigger_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ZapierConnection::class, 'connection_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ZapierTriggerLog::class, 'trigger_id');
    }
}
