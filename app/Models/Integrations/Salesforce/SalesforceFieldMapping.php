<?php

namespace App\Models\Integrations\Salesforce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesforceFieldMapping extends Model
{
    protected $fillable = [
        'connection_id', 'object_type', 'local_field', 'salesforce_field',
        'direction', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SalesforceConnection::class, 'connection_id');
    }
}
