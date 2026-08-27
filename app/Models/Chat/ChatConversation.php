<?php

namespace App\Models\Chat;

use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceClient;
use App\Models\SupportDepartment;
use App\Models\SupportTicket;
use App\Traits\SecureMarketplaceScoping;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A live-chat conversation thread. Part of the `live-chat` microservice.
 *
 * The opener is polymorphic (customer/organizer/artist) or NULL for a guest,
 * in which case guest_name/guest_email carry the contact. Marketplace-scoped
 * via SecureMarketplaceScoping so the module stays isolated per marketplace.
 */
class ChatConversation extends Model
{
    use SecureMarketplaceScoping;
    use SoftDeletes;
    use LogsActivity;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_OFFLINE_MESSAGE = 'offline_message';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_ACTIVE,
        self::STATUS_OFFLINE_MESSAGE,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
    ];

    public const VISITOR_CUSTOMER = 'customer';
    public const VISITOR_ORGANIZER = 'organizer';
    public const VISITOR_ARTIST = 'artist';
    public const VISITOR_GUEST = 'guest';

    protected $fillable = [
        'marketplace_client_id',
        'reference',
        'support_department_id',
        'event_id',
        'visitor_type',
        'opener_type',
        'opener_id',
        'guest_name',
        'guest_email',
        'assigned_to_marketplace_admin_id',
        'subject',
        'status',
        'context',
        'rating',
        'rating_comment',
        'support_ticket_id',
        'queued_at',
        'assigned_at',
        'first_response_at',
        'last_activity_at',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'context' => 'array',
        'rating' => 'integer',
        'queued_at' => 'datetime',
        'assigned_at' => 'datetime',
        'first_response_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Human-readable reference (CHAT-YYYY-000123) after the row has an id.
        static::created(function (ChatConversation $conversation) {
            if ($conversation->reference) {
                return;
            }
            $year = ($conversation->created_at ?? now())->format('Y');
            $conversation->reference = sprintf('CHAT-%s-%06d', $year, $conversation->id);
            $conversation->saveQuietly();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status',
                'support_department_id',
                'assigned_to_marketplace_admin_id',
                'rating',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('chat');
    }

    // -------- Relations --------

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function opener(): MorphTo
    {
        return $this->morphTo();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(SupportDepartment::class, 'support_department_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'assigned_to_marketplace_admin_id');
    }

    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at');
    }

    public function publicMessages(): HasMany
    {
        return $this->messages()->where('is_internal', false);
    }

    // -------- Scopes --------

    public function scopeQueued(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_QUEUED);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_QUEUED, self::STATUS_ACTIVE]);
    }

    public function scopeAssignedTo(Builder $q, int $adminId): Builder
    {
        return $q->where('assigned_to_marketplace_admin_id', $adminId);
    }

    // -------- Helpers --------

    public function isGuest(): bool
    {
        return $this->visitor_type === self::VISITOR_GUEST;
    }

    public function isOrganizer(): bool
    {
        return $this->visitor_type === self::VISITOR_ORGANIZER;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED], true);
    }

    /**
     * Best display name for the opener (guest name, account name, or fallback).
     */
    public function openerName(): string
    {
        if ($this->guest_name) {
            return $this->guest_name;
        }
        $opener = $this->relationLoaded('opener') ? $this->opener : $this->opener()->first();
        return $opener?->name ?? ($this->guest_email ?: 'Vizitator');
    }

    public function timeToFirstResponseSeconds(): ?int
    {
        if (!$this->first_response_at || !$this->queued_at) {
            return null;
        }
        return (int) $this->first_response_at->diffInSeconds($this->queued_at, true);
    }

    public function markActivity(?Carbon $at = null): void
    {
        $this->last_activity_at = $at ?: now();
    }
}
