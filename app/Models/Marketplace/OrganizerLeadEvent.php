<?php

namespace App\Models\Marketplace;

use App\Models\MarketplaceClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single event in a lead's activity timeline.
 *
 * `lead_id` is nullable on purpose — anonymous page-view events recorded
 * BEFORE the prospect submits the signup form carry only session_token.
 * LeadsController promotes those rows by filling in lead_id once the
 * matching session_token surfaces in a submission.
 */
class OrganizerLeadEvent extends Model
{
    protected $table = 'marketplace_organizer_lead_events';

    public const TYPE_PAGE_VIEW_LANDING    = 'page_view_landing';
    public const TYPE_PAGE_VIEW_ONBOARDING = 'page_view_onboarding';
    public const TYPE_CTA_CLICK            = 'cta_click';
    public const TYPE_FORM_SUBMITTED       = 'form_submitted';
    public const TYPE_STATUS_CHANGED       = 'status_changed';
    public const TYPE_NOTE                 = 'note';
    public const TYPE_EMAIL_SENT           = 'email_sent';
    public const TYPE_CALL                 = 'call';
    public const TYPE_DEMO_SCHEDULED       = 'demo_scheduled';
    public const TYPE_ASSIGNED             = 'assigned';

    public const TYPES = [
        self::TYPE_PAGE_VIEW_LANDING    => 'Landing visit',
        self::TYPE_PAGE_VIEW_ONBOARDING => 'Onboarding visit',
        self::TYPE_CTA_CLICK            => 'CTA click',
        self::TYPE_FORM_SUBMITTED       => 'Form submitted',
        self::TYPE_STATUS_CHANGED       => 'Status changed',
        self::TYPE_NOTE                 => 'Note',
        self::TYPE_EMAIL_SENT           => 'Email sent',
        self::TYPE_CALL                 => 'Phone call',
        self::TYPE_DEMO_SCHEDULED       => 'Demo scheduled',
        self::TYPE_ASSIGNED             => 'Assigned',
    ];

    protected $fillable = [
        'lead_id',
        'marketplace_client_id',
        'session_token',
        'event_type',
        'summary',
        'payload',
        'performed_by_user_id',
        'ip_address',
        'user_agent',
        'page_url',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(OrganizerLead::class, 'lead_id');
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->event_type] ?? $this->event_type;
    }
}
