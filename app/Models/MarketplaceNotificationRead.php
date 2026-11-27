<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceNotificationRead extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'marketplace_notification_id',
        'marketplace_admin_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(MarketplaceNotification::class, 'marketplace_notification_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'marketplace_admin_id');
    }
}
