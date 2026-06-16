<?php

namespace App\Models;

use App\Traits\SecureMarketplaceScoping;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MarketplaceTodo extends Model
{
    use SecureMarketplaceScoping;
    use SoftDeletes;
    use LogsActivity;

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_AWAITING_RESPONSE = 'awaiting_response';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_AWAITING_RESPONSE,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_OPEN => 'Deschis',
        self::STATUS_IN_PROGRESS => 'În lucru',
        self::STATUS_AWAITING_RESPONSE => 'Așteaptă răspuns',
        self::STATUS_RESOLVED => 'Rezolvat',
        self::STATUS_CLOSED => 'Închis',
    ];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public const PRIORITY_LABELS = [
        'low' => 'Scăzută',
        'normal' => 'Normală',
        'high' => 'Ridicată',
        'urgent' => 'Urgentă',
    ];

    protected $fillable = [
        'marketplace_client_id',
        'todo_number',
        'created_by_marketplace_admin_id',
        'assigned_to_marketplace_admin_id',
        'marketplace_todo_category_id',
        'title',
        'description',
        'attachments',
        'status',
        'priority',
        'opened_at',
        'first_response_at',
        'last_activity_at',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'opened_at' => 'datetime',
        'first_response_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (MarketplaceTodo $todo) {
            if ($todo->todo_number) {
                return;
            }
            $year = ($todo->opened_at ?? $todo->created_at ?? now())->format('Y');
            $todo->todo_number = sprintf('TODO-%s-%06d', $year, $todo->id);
            $todo->saveQuietly();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status',
                'priority',
                'assigned_to_marketplace_admin_id',
                'marketplace_todo_category_id',
                'title',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('marketplace_todo');
    }

    // -------- Relations --------

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'created_by_marketplace_admin_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'assigned_to_marketplace_admin_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MarketplaceTodoCategory::class, 'marketplace_todo_category_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(MarketplaceTodoComment::class)->orderBy('created_at');
    }

    // -------- Scopes --------

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_AWAITING_RESPONSE,
        ]);
    }

    public function scopeAssignedTo(Builder $q, int $adminId): Builder
    {
        return $q->where('assigned_to_marketplace_admin_id', $adminId);
    }

    // -------- Helpers --------

    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED], true);
    }

    public function markActivity(?Carbon $at = null): void
    {
        $this->last_activity_at = $at ?: now();
    }
}
