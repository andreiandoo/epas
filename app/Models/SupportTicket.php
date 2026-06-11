<?php

namespace App\Models;

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

class SupportTicket extends Model
{
    use SecureMarketplaceScoping;
    use SoftDeletes;
    use LogsActivity;

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_AWAITING_ORGANIZER = 'awaiting_organizer';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_AWAITING_ORGANIZER,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
    ];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $fillable = [
        'marketplace_client_id',
        'ticket_number',
        'opener_type',
        'opener_id',
        'support_department_id',
        'support_problem_type_id',
        'assigned_to_marketplace_admin_id',
        'subject',
        'status',
        'priority',
        'meta',
        'context',
        'opened_at',
        'first_response_at',
        'last_activity_at',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'context' => 'array',
        'opened_at' => 'datetime',
        'first_response_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Generate human-readable ticket_number after the row has an id.
        // Format: TKT-YYYY-000123. Uniqueness is enforced per
        // marketplace_client_id by the schema's composite unique index.
        static::created(function (SupportTicket $ticket) {
            if ($ticket->ticket_number) {
                return;
            }
            $year = ($ticket->opened_at ?? $ticket->created_at ?? now())->format('Y');
            $ticket->ticket_number = sprintf('TKT-%s-%06d', $year, $ticket->id);
            $ticket->saveQuietly();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status',
                'priority',
                'support_department_id',
                'support_problem_type_id',
                'assigned_to_marketplace_admin_id',
                'subject',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('support');
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

    public function problemType(): BelongsTo
    {
        return $this->belongsTo(SupportProblemType::class, 'support_problem_type_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'assigned_to_marketplace_admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('created_at');
    }

    public function publicMessages(): HasMany
    {
        return $this->messages()->where('is_internal_note', false);
    }

    // -------- Scopes --------

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_AWAITING_ORGANIZER,
        ]);
    }

    public function scopeForOpener(Builder $q, string $type, int $id): Builder
    {
        return $q->where('opener_type', $type)->where('opener_id', $id);
    }

    public function scopeAssignedTo(Builder $q, int $userId): Builder
    {
        return $q->where('assigned_to_marketplace_admin_id', $userId);
    }

    // -------- Helpers --------

    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED], true);
    }

    /**
     * Time-to-first-response in seconds, or null if no staff has replied yet.
     */
    public function timeToFirstResponseSeconds(): ?int
    {
        if (!$this->first_response_at || !$this->opened_at) {
            return null;
        }
        return (int) $this->first_response_at->diffInSeconds($this->opened_at, true);
    }

    /**
     * Time-to-resolution in seconds, or null if not resolved/closed yet.
     */
    public function timeToResolutionSeconds(): ?int
    {
        $end = $this->closed_at ?? $this->resolved_at;
        if (!$end || !$this->opened_at) {
            return null;
        }
        return (int) $end->diffInSeconds($this->opened_at, true);
    }

    public function markActivity(?Carbon $at = null): void
    {
        $this->last_activity_at = $at ?: now();
    }
}
