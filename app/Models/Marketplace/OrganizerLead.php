<?php

namespace App\Models\Marketplace;

use App\Models\MarketplaceClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Prospective organizer signup / lead pipeline row.
 *
 * Created by /inregistrare-locatie's submit POST, optionally linked to
 * anonymous pre-submission funnel events via session_token. The list of
 * statuses below is the canonical pipeline — UI selects / filters /
 * Filament resources should consume STATUSES rather than hand-rolling
 * the list.
 */
class OrganizerLead extends Model
{
    use SoftDeletes;

    protected $table = 'marketplace_organizer_leads';

    public const STATUS_NEW              = 'new';
    public const STATUS_CONTACTED        = 'contacted';
    public const STATUS_IN_NEGOTIATION   = 'in_negotiation';
    public const STATUS_DEMO_SCHEDULED   = 'demo_scheduled';
    public const STATUS_ACCEPTED         = 'accepted';
    public const STATUS_REJECTED         = 'rejected';
    public const STATUS_GHOSTED          = 'ghosted';
    public const STATUS_ARCHIVED         = 'archived';

    public const STATUSES = [
        self::STATUS_NEW              => 'New',
        self::STATUS_CONTACTED        => 'Contacted',
        self::STATUS_IN_NEGOTIATION   => 'In negotiation',
        self::STATUS_DEMO_SCHEDULED   => 'Demo scheduled',
        self::STATUS_ACCEPTED         => 'Accepted',
        self::STATUS_REJECTED         => 'Rejected',
        self::STATUS_GHOSTED          => 'Ghosted',
        self::STATUS_ARCHIVED         => 'Archived',
    ];

    public const SOURCES = [
        'partner_signup' => 'Form (partner signup)',
        'manual'         => 'Added manually',
        'import'         => 'Imported',
        'referral'       => 'Referral',
        'ads'            => 'Paid ads',
        'organic'        => 'Organic search',
    ];

    protected $fillable = [
        'marketplace_client_id',
        'session_token',
        'contact_name',
        'email',
        'phone',
        'location_name',
        'city',
        'website',
        'category_slug',
        'category_name',
        'category_other',
        'volume_estimate',
        'notes',
        'status',
        'source',
        'source_detail',
        'assigned_to_user_id',
        'next_action_at',
        'prefill_tip',
        'prefill_loc',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'first_landing_at',
        'first_onboarding_at',
        'submitted_at',
        'contacted_at',
        'accepted_at',
        'rejected_at',
        'ghosted_at',
        'landing_views',
        'onboarding_views',
        'meta',
    ];

    protected $casts = [
        'meta'                => 'array',
        'next_action_at'      => 'datetime',
        'first_landing_at'    => 'datetime',
        'first_onboarding_at' => 'datetime',
        'submitted_at'        => 'datetime',
        'contacted_at'        => 'datetime',
        'accepted_at'         => 'datetime',
        'rejected_at'         => 'datetime',
        'ghosted_at'          => 'datetime',
        'landing_views'       => 'integer',
        'onboarding_views'    => 'integer',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrganizerLeadEvent::class, 'lead_id')->orderByDesc('created_at');
    }

    /**
     * Mark a transition. Writes both the column and the matching timeline
     * event so the audit log + dashboard sorting stay consistent.
     */
    public function transitionTo(string $status, ?string $summary = null, ?int $userId = null): void
    {
        if (!array_key_exists($status, self::STATUSES)) {
            throw new \InvalidArgumentException("Unknown lead status: {$status}");
        }
        $previous = $this->status;
        $update = ['status' => $status];

        $now = now();
        if ($status === self::STATUS_CONTACTED) {
            $update['contacted_at'] = $this->contacted_at ?? $now;
        }
        if ($status === self::STATUS_ACCEPTED) $update['accepted_at'] = $now;
        if ($status === self::STATUS_REJECTED) $update['rejected_at'] = $now;
        if ($status === self::STATUS_GHOSTED)  $update['ghosted_at']  = $now;
        $this->update($update);

        $this->events()->create([
            'marketplace_client_id' => $this->marketplace_client_id,
            'event_type'            => 'status_changed',
            'summary'               => $summary ?: "Status: {$previous} → {$status}",
            'payload'               => ['from' => $previous, 'to' => $status],
            'performed_by_user_id'  => $userId,
        ]);
    }

    /** Convenience: a single human-readable label for the active activity-type. */
    public function getActivityTypeLabelAttribute(): string
    {
        return $this->category_name
            ?: $this->category_other
            ?: $this->category_slug
            ?: '(necunoscut)';
    }
}
