<?php

namespace App\Models\AdsCampaign;

use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceOrganizer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AdsServiceRequest extends Model
{
    use LogsActivity;

    protected $table = 'ads_service_requests';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'marketplace_client_id',
        'marketplace_organizer_id',
        'name',
        'brief',
        'target_platforms',
        'creative_types',
        'budget',
        'service_fee',
        'currency',
        'materials',
        'brand_guidelines',
        'audience_hints',
        'status',
        'review_notes',
        'payment_status',
        'payment_reference',
        'paid_at',
        'reviewed_by',
        'created_by',
        'reviewed_at',
    ];

    protected $casts = [
        'target_platforms' => 'array',
        'creative_types' => 'array',
        'materials' => 'array',
        'brand_guidelines' => 'array',
        'audience_hints' => 'array',
        'budget' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'paid_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function marketplaceOrganizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function campaign(): HasOne
    {
        return $this->hasOne(AdsCampaign::class, 'service_request_id');
    }

    // Status helpers
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function canCreateCampaign(): bool
    {
        return $this->isApproved() && $this->isPaid();
    }

    public function getTotalCostAttribute(): float
    {
        return (float) $this->budget + (float) $this->service_fee;
    }

    public function approve(User $reviewer, ?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);
    }

    public function reject(User $reviewer, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $reason,
        ]);
    }

    public function markPaid(string $reference): void
    {
        $this->update([
            'payment_status' => 'paid',
            'payment_reference' => $reference,
            'paid_at' => now(),
        ]);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'payment_status', 'review_notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Ads service request {$eventName}")
            ->useLogName('ads_campaigns');
    }
}
