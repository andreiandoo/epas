<?php

namespace App\Models\Integrations\Salesforce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesforceSyncLog extends Model
{
    protected $fillable = [
        'connection_id', 'object_type', 'operation', 'salesforce_id', 'local_id',
        'direction', 'status', 'payload', 'response', 'correlation_ref',
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SalesforceConnection::class, 'connection_id');
    }
}
