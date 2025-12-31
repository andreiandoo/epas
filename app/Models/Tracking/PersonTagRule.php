<?php

namespace App\Models\Tracking;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonTagRule extends Model
{
    protected $fillable = [
        'tenant_id',
        'tag_id',
        'name',
        'description',
        'conditions',
        'match_type',
        'is_active',
        'remove_when_unmet',
        'priority',
        'last_run_at',
        'last_run_count',
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_active' => 'boolean',
        'remove_when_unmet' => 'boolean',
        'priority' => 'integer',
        'last_run_at' => 'datetime',
        'last_run_count' => 'integer',
    ];

    /**
     * Available condition fields (same as audience builder).
     */
    public const CONDITION_FIELDS = [
        // Purchase behavior
        'total_orders' => ['label' => 'Total Orders', 'type' => 'number', 'operators' => ['=', '!=', '>', '>=', '<', '<=']],
        'total_spent' => ['label' => 'Total Spent', 'type' => 'number', 'operators' => ['=', '!=', '>', '>=', '<', '<=']],
        'average_order_value' => ['label' => 'Average Order Value', 'type' => 'number', 'operators' => ['=', '!=', '>', '>=', '<', '<=']],
        'days_since_last_purchase' => ['label' => 'Days Since Last Purchase', 'type' => 'number', 'operators' => ['=', '!=', '>', '>=', '<', '<=']],

        // Engagement
        'total_visits' => ['label' => 'Total Visits', 'type' => 'number', 'operators' => ['=', '!=', '>', '>=', '<', '<=']],
        'total_pageviews' => ['label' => 'Total Pageviews', 'type' => 'number', 'operators' => ['=', '!=', '>', '>=', '<', '<=']],
        'total_events_viewed' => ['label' => 'Events Viewed', 'type' => 'number', 'operators' => ['=', '!=', '>', '>=', '<', '<=']],
        'total_events_attended' => ['label' => 'Events Attended', 'type' => 'number', 'operators' => ['=', '!=', '>', '>=', '<', '<=']],

        // Email
        'email_open_rate' => ['label' => 'Email Open Rate', 'type' => 'percentage', 'operators' => ['=', '!=', '>', '>=', '<', '<=']],
        'email_click_rate' => ['label' => 'Email Click Rate', 'type' => 'percentage', 'operators' => ['=', '!=', '>', '>=', '<', '<=']],
        'email_subscribed' => ['label' => 'Email Subscribed', 'type' => 'boolean', 'operators' => ['=']],

        // Scores
        'health_score' => ['label' => 'Health Score', 'type' => 'number', 'operators' => ['=', '!=', '>', '>=', '<', '<=']],
        'engagement_score' => ['label' => 'Engagement Score', 'type' => 'number', 'operators' => ['=', '!=', '>', '>=', '<', '<=']],
        'rfm_score' => ['label' => 'RFM Score', 'type' => 'number', 'operators' => ['=', '!=', '>', '>=', '<', '<=']],

        // Segments
        'customer_segment' => ['label' => 'Customer Segment', 'type' => 'select', 'operators' => ['=', '!=', 'in', 'not_in']],
        'rfm_segment' => ['label' => 'RFM Segment', 'type' => 'select', 'operators' => ['=', '!=', 'in', 'not_in']],

        // Behavior flags
        'has_cart_abandoned' => ['label' => 'Has Abandoned Cart', 'type' => 'boolean', 'operators' => ['=']],

        // Device
        'primary_device' => ['label' => 'Primary Device', 'type' => 'select', 'operators' => ['=', '!=', 'in', 'not_in']],

        // Activity
        'days_since_last_seen' => ['label' => 'Days Since Last Seen', 'type' => 'number', 'operators' => ['=', '!=', '>', '>=', '<', '<=']],

        // Consent
        'marketing_consent' => ['label' => 'Marketing Consent', 'type' => 'boolean', 'operators' => ['=']],
    ];

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(PersonTag::class, 'tag_id');
    }

    // Scopes

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('priority')->orderBy('name');
    }

    // Helpers

    /**
     * Get schema for building conditions UI.
     */
    public static function getConditionSchema(): array
    {
        return self::CONDITION_FIELDS;
    }

    /**
     * Update run statistics.
     */
    public function recordRun(int $count): void
    {
        $this->update([
            'last_run_at' => now(),
            'last_run_count' => $count,
        ]);
    }
}
