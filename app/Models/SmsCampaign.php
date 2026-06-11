<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsCampaign extends Model
{
    protected $fillable = [
        'marketplace_client_id',
        'name',
        'status',
        'message_text',
        'marketplace_organizer_id',
        'event_id',
        'filters',
        'total_audience',
        'audience_with_phone',
        'sms_per_recipient',
        'total_sms_needed',
        'sms_sent',
        'sms_delivered',
        'sms_failed',
        'total_cost',
        'scheduled_at',
        'sent_at',
        'created_by',
    ];

    protected $casts = [
        'filters' => 'array',
        'total_cost' => 'decimal:2',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function marketplaceOrganizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function calculateSmsCount(string $text): int
    {
        $length = mb_strlen($text);
        if ($length <= 160) return 1;
        // For concatenated SMS, each part is 153 chars (7 chars used for UDH header)
        return (int) ceil($length / 153);
    }
}
