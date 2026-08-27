<?php

namespace App\Models\Chat;

use App\Traits\SecureMarketplaceScoping;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An anti-abuse blocklist entry (IP or email). A non-expired match denies the
 * ability to open new conversations.
 */
class ChatBlocklistEntry extends Model
{
    use SecureMarketplaceScoping;

    protected $table = 'chat_blocklist';

    public const TYPE_IP = 'ip';
    public const TYPE_EMAIL = 'email';

    protected $fillable = [
        'marketplace_client_id',
        'type',
        'value',
        'reason',
        'created_by_marketplace_admin_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Active (non-expired) entries only.
     */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where(function (Builder $sub) {
            $sub->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function scopeMatching(Builder $q, string $type, string $value): Builder
    {
        return $q->where('type', $type)->where('value', $value);
    }
}
