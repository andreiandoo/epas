<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmailCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'segment_id', 'name', 'subject', 'content',
        'from_name', 'from_email', 'status', 'scheduled_at', 'sent_at',
        'total_recipients', 'sent_count', 'opened_count', 'clicked_count',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_SENDING = 'sending';
    const STATUS_SENT = 'sent';
    const STATUS_PAUSED = 'paused';

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function segment(): BelongsTo { return $this->belongsTo(CustomerSegment::class); }
    public function recipients(): HasMany { return $this->hasMany(CampaignRecipient::class, 'campaign_id'); }

    public function scopeForTenant($query, $tenantId) { return $query->where('tenant_id', $tenantId); }

    public function getOpenRate(): float
    {
        return $this->sent_count > 0 ? ($this->opened_count / $this->sent_count) * 100 : 0;
    }

    public function getClickRate(): float
    {
        return $this->opened_count > 0 ? ($this->clicked_count / $this->opened_count) * 100 : 0;
    }
}
