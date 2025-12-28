<?php

namespace App\Models\Integrations\HubSpot;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubSpotPropertyMapping extends Model
{
    protected $table = 'hubspot_property_mappings';

    protected $fillable = [
        'connection_id', 'object_type', 'local_property', 'hubspot_property',
        'direction', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(HubSpotConnection::class, 'connection_id');
    }
}
