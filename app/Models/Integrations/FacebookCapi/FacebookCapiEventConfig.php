<?php

namespace App\Models\Integrations\FacebookCapi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookCapiEventConfig extends Model
{
    protected $table = 'facebook_capi_event_configs';

    protected $fillable = [
        'connection_id',
        'event_name',
        'is_enabled',
        'trigger_on',
        'custom_data_mapping',
        'user_data_mapping',
        'send_test_events',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'custom_data_mapping' => 'array',
        'user_data_mapping' => 'array',
        'send_test_events' => 'boolean',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(FacebookCapiConnection::class, 'connection_id');
    }

    public function isEnabled(): bool
    {
        return $this->is_enabled;
    }

    public function getCustomDataFields(): array
    {
        return $this->custom_data_mapping ?? [];
    }

    public function getUserDataFields(): array
    {
        return $this->user_data_mapping ?? [];
    }
}
