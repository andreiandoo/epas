<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    protected $fillable = [
        'marketplace_client_id',
        'marketplace_customer_id',
        'session_id',
        'status',
        'page_url',
        'metadata',
        'escalated_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'escalated_at' => 'datetime',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function marketplaceCustomer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeEscalated($query)
    {
        return $query->where('status', 'escalated');
    }

    public function markResolved(): void
    {
        $this->update(['status' => 'resolved']);
    }

    public function markEscalated(): void
    {
        $this->update([
            'status' => 'escalated',
            'escalated_at' => now(),
        ]);
    }

    public function getMessageCount(): int
    {
        return $this->messages()->count();
    }
}
