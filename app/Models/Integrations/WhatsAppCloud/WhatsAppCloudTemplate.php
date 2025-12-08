<?php

namespace App\Models\Integrations\WhatsAppCloud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppCloudTemplate extends Model
{
    protected $fillable = [
        'connection_id',
        'template_name',
        'template_id',
        'language',
        'category',
        'status',
        'components',
        'example',
        'rejection_reason',
        'submitted_at',
        'approved_at',
    ];

    protected $casts = [
        'components' => 'array',
        'example' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCloudConnection::class, 'connection_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
