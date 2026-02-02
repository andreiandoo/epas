<?php

namespace App\Models\Integrations\Zapier;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZapierAction extends Model
{
    protected $fillable = [
        'connection_id', 'action_type', 'payload', 'status',
        'result', 'correlation_ref', 'executed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'executed_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ZapierConnection::class, 'connection_id');
    }
}
