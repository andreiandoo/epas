<?php

namespace App\Models\AdsCampaign;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdsCampaignReport extends Model
{
    protected $table = 'ads_campaign_reports';

    protected $fillable = [
        'campaign_id',
        'report_type',
        'title',
        'period_start',
        'period_end',
        'summary',
        'platform_breakdown',
        'daily_data',
        'creative_performance',
        'audience_insights',
        'recommendations',
        'ab_test_results',
        'sent_to_organizer',
        'sent_at',
        'pdf_path',
        'generated_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'summary' => 'array',
        'platform_breakdown' => 'array',
        'daily_data' => 'array',
        'creative_performance' => 'array',
        'audience_insights' => 'array',
        'recommendations' => 'array',
        'ab_test_results' => 'array',
        'sent_to_organizer' => 'boolean',
        'sent_at' => 'datetime',
    ];

    const TYPE_DAILY = 'daily';
    const TYPE_WEEKLY = 'weekly';
    const TYPE_MONTHLY = 'monthly';
    const TYPE_FINAL = 'final';
    const TYPE_AB_TEST = 'ab_test';
    const TYPE_CUSTOM = 'custom';

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdsCampaign::class, 'campaign_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function isSent(): bool
    {
        return $this->sent_to_organizer;
    }

    public function markSent(): void
    {
        $this->update([
            'sent_to_organizer' => true,
            'sent_at' => now(),
        ]);
    }

    /**
     * Get formatted ROI percentage
     */
    public function getRoiAttribute(): float
    {
        $summary = $this->summary ?? [];
        $spend = $summary['spend'] ?? 0;
        $revenue = $summary['revenue'] ?? 0;

        if ($spend <= 0) return 0;
        return round((($revenue - $spend) / $spend) * 100, 2);
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('report_type', $type);
    }

    public function scopeUnsent($query)
    {
        return $query->where('sent_to_organizer', false);
    }
}
