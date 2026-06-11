<?php

namespace App\Models\Integrations\FacebookCapi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacebookAdsAccount extends Model
{
    protected $table = 'facebook_ads_accounts';

    protected $fillable = [
        'marketplace_organizer_id',
        'marketplace_client_id',
        'tenant_id',
        'connection_id',
        'fb_account_id',
        'account_name',
        'currency',
        'account_status',
        'timezone_name',
        'metadata',
        'last_synced_at',
        'last_sync_status',
        'last_sync_error',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(FacebookCapiConnection::class, 'connection_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(FacebookAdsCampaign::class, 'ads_account_id');
    }

    public function insights(): HasMany
    {
        return $this->hasMany(FacebookAdsInsight::class, 'ads_account_id');
    }
}
