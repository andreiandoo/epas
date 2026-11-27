<?php

namespace App\Models\TeamChat;

use App\Models\MarketplaceAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamThreadMember extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'team_thread_id',
        'marketplace_admin_id',
        'last_read_message_id',
        'muted_until',
        'joined_at',
    ];

    protected $casts = [
        'muted_until' => 'datetime',
        'joined_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(TeamThread::class, 'team_thread_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'marketplace_admin_id');
    }
}
