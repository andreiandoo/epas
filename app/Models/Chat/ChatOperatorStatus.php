<?php

namespace App\Models\Chat;

use App\Models\MarketplaceAdmin;
use App\Traits\SecureMarketplaceScoping;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Durable "last known" presence & capacity mirror for a chat operator.
 * Live presence is authoritative in Redis; this is the DB fallback.
 */
class ChatOperatorStatus extends Model
{
    use SecureMarketplaceScoping;

    public const PRESENCE_ONLINE = 'online';
    public const PRESENCE_AWAY = 'away';
    public const PRESENCE_OFFLINE = 'offline';

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_admin_id',
        'presence',
        'active_chats_count',
        'max_concurrent_chats',
        'last_seen_at',
    ];

    protected $casts = [
        'active_chats_count' => 'integer',
        'max_concurrent_chats' => 'integer',
        'last_seen_at' => 'datetime',
    ];

    public function operator(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'marketplace_admin_id');
    }

    public function scopeOnline(Builder $q): Builder
    {
        return $q->where('presence', self::PRESENCE_ONLINE);
    }

    /**
     * Effective capacity: the operator override, else the config default.
     */
    public function capacity(): int
    {
        return (int) ($this->max_concurrent_chats
            ?? config('chat.operator.default_max_concurrent_chats', 4));
    }

    public function hasFreeSlot(): bool
    {
        return $this->presence === self::PRESENCE_ONLINE
            && $this->active_chats_count < $this->capacity();
    }
}
