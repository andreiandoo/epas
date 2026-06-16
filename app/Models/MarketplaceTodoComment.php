<?php

namespace App\Models;

use App\Traits\SecureMarketplaceScoping;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceTodoComment extends Model
{
    use SecureMarketplaceScoping;

    public const EVENT_STATUS_CHANGED = 'status_changed';
    public const EVENT_ASSIGNED = 'assigned';
    public const EVENT_PRIORITY_CHANGED = 'priority_changed';
    public const EVENT_RESOLVED = 'resolved';
    public const EVENT_CLOSED = 'closed';
    public const EVENT_REOPENED = 'reopened';

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_todo_id',
        'author_marketplace_admin_id',
        'body',
        'attachments',
        'event_type',
    ];

    protected $casts = [
        'attachments' => 'array',
    ];

    public function todo(): BelongsTo
    {
        return $this->belongsTo(MarketplaceTodo::class, 'marketplace_todo_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'author_marketplace_admin_id');
    }

    public function isSystemEvent(): bool
    {
        return !empty($this->event_type);
    }
}
