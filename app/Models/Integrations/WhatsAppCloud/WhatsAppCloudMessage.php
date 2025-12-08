<?php

namespace App\Models\Integrations\WhatsAppCloud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppCloudMessage extends Model
{
    protected $table = 'whatsapp_cloud_messages';

    protected $fillable = [
        'connection_id',
        'wamid',
        'direction',
        'recipient_phone',
        'message_type',
        'content',
        'template_name',
        'template_params',
        'media',
        'status',
        'status_history',
        'error_code',
        'error_message',
        'context_message_id',
        'correlation_type',
        'correlation_id',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'template_params' => 'array',
        'media' => 'array',
        'status_history' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCloudConnection::class, 'connection_id');
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    public function isDelivered(): bool
    {
        return in_array($this->status, ['delivered', 'read']);
    }

    public function addStatusHistory(string $status, ?string $timestamp = null): void
    {
        $history = $this->status_history ?? [];
        $history[] = [
            'status' => $status,
            'timestamp' => $timestamp ?? now()->toIso8601String(),
        ];
        $this->update(['status_history' => $history]);
    }
}
