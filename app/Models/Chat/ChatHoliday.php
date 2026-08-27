<?php

namespace App\Models\Chat;

use App\Traits\SecureMarketplaceScoping;
use Illuminate\Database\Eloquent\Model;

/**
 * A marketplace-wide day off. On these dates the widget is fully offline
 * (leave-a-message flow) regardless of operator schedules.
 */
class ChatHoliday extends Model
{
    use SecureMarketplaceScoping;

    protected $fillable = [
        'marketplace_client_id',
        'date',
        'label',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
