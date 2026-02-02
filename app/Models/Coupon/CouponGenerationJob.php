<?php

namespace App\Models\Coupon;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponGenerationJob extends Model
{
    use HasUuids;

    protected $table = 'coupon_generation_jobs';

    protected $fillable = [
        'tenant_id',
        'campaign_id',
        'quantity',
        'pattern',
        'prefix',
        'suffix',
        'template_data',
        'status',
        'codes_generated',
        'download_url',
        'expires_at',
    ];

    protected $casts = [
        'template_data' => 'array',
        'expires_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CouponCampaign::class, 'campaign_id');
    }

    public function start(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function complete(int $codesGenerated, string $downloadUrl): void
    {
        $this->update([
            'status' => 'completed',
            'codes_generated' => $codesGenerated,
            'download_url' => $downloadUrl,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function fail(): void
    {
        $this->update(['status' => 'failed']);
    }

    public function incrementGenerated(): void
    {
        $this->increment('codes_generated');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
