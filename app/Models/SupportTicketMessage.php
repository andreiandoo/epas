<?php

namespace App\Models;

use App\Traits\SecureMarketplaceScoping;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SupportTicketMessage extends Model
{
    use SecureMarketplaceScoping;

    protected $fillable = [
        'marketplace_client_id',
        'support_ticket_id',
        'author_type',
        'author_id',
        'body',
        'is_internal_note',
        'attachments',
    ];

    protected $casts = [
        'is_internal_note' => 'boolean',
        'attachments' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePublic(Builder $q): Builder
    {
        return $q->where('is_internal_note', false);
    }

    public function isFromStaff(): bool
    {
        return $this->author_type === 'staff';
    }

    public function isFromOpener(): bool
    {
        return in_array($this->author_type, ['organizer', 'customer'], true);
    }
}
