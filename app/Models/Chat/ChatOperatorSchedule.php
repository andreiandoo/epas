<?php

namespace App\Models\Chat;

use App\Models\MarketplaceAdmin;
use App\Traits\SecureMarketplaceScoping;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One weekly working-hours interval for a chat operator.
 * day_of_week uses Carbon convention: 0=Sunday .. 6=Saturday.
 */
class ChatOperatorSchedule extends Model
{
    use SecureMarketplaceScoping;

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_admin_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_active' => 'boolean',
    ];

    public function operator(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'marketplace_admin_id');
    }
}
