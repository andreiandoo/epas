<?php

namespace App\Models\AdsCampaign;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdsOptimizationLog extends Model
{
    protected $table = 'ads_optimization_logs';

    protected $fillable = [
        'campaign_id',
        'platform_campaign_id',
        'action_type',
        'description',
        'before_state',
        'after_state',
        'trigger_metrics',
        'expected_improvement',
        'actual_improvement',
        'source',
        'performed_by',
    ];

    protected $casts = [
        'before_state' => 'array',
        'after_state' => 'array',
        'trigger_metrics' => 'array',
        'expected_improvement' => 'decimal:4',
        'actual_improvement' => 'decimal:4',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdsCampaign::class, 'campaign_id');
    }

    public function platformCampaign(): BelongsTo
    {
        return $this->belongsTo(AdsPlatformCampaign::class, 'platform_campaign_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function isAutomatic(): bool
    {
        return $this->source === 'auto';
    }

    public function isManual(): bool
    {
        return $this->source === 'manual';
    }

    public function getImprovementStatusAttribute(): string
    {
        if ($this->actual_improvement === null) return 'pending';
        if ($this->actual_improvement > 0) return 'positive';
        if ($this->actual_improvement < 0) return 'negative';
        return 'neutral';
    }

    public function scopeAutomatic($query)
    {
        return $query->where('source', 'auto');
    }

    public function scopeManual($query)
    {
        return $query->where('source', 'manual');
    }

    public function scopeForAction($query, string $actionType)
    {
        return $query->where('action_type', $actionType);
    }
}
