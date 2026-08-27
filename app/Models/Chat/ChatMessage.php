<?php

namespace App\Models\Chat;

use App\Traits\SecureMarketplaceScoping;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A single message within a chat conversation. Part of the `live-chat`
 * microservice.
 *
 * Author is polymorphic (staff => MarketplaceAdmin, customer/organizer/artist
 * => their models). A NULL author with type='system' is a generated notice.
 * Internal notes (is_internal) are staff-only.
 */
class ChatMessage extends Model
{
    use SecureMarketplaceScoping;

    public const TYPE_TEXT = 'text';
    public const TYPE_SYSTEM = 'system';

    protected $fillable = [
        'marketplace_client_id',
        'chat_conversation_id',
        'author_type',
        'author_id',
        'author_label',
        'type',
        'body',
        'is_internal',
        'attachments',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'attachments' => 'array',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePublic(Builder $q): Builder
    {
        return $q->where('is_internal', false);
    }

    public function isFromStaff(): bool
    {
        return $this->author_type === 'staff';
    }

    public function isSystem(): bool
    {
        return $this->type === self::TYPE_SYSTEM;
    }
}
