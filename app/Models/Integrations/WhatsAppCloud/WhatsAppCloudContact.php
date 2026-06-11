<?php

namespace App\Models\Integrations\WhatsAppCloud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppCloudContact extends Model
{
    protected $table = 'whatsapp_cloud_contacts';

    protected $fillable = [
        'connection_id',
        'wa_id',
        'phone_number',
        'profile_name',
        'is_opted_in',
        'opted_in_at',
        'opted_out_at',
        'last_message_at',
        'conversation_expires_at',
        'metadata',
    ];

    protected $casts = [
        'is_opted_in' => 'boolean',
        'opted_in_at' => 'datetime',
        'opted_out_at' => 'datetime',
        'last_message_at' => 'datetime',
        'conversation_expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCloudConnection::class, 'connection_id');
    }

    public function hasActiveConversation(): bool
    {
        return $this->conversation_expires_at && $this->conversation_expires_at->isFuture();
    }

    public function optIn(): void
    {
        $this->update([
            'is_opted_in' => true,
            'opted_in_at' => now(),
            'opted_out_at' => null,
        ]);
    }

    public function optOut(): void
    {
        $this->update([
            'is_opted_in' => false,
            'opted_out_at' => now(),
        ]);
    }
}
