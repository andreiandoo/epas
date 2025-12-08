<?php

namespace App\Models\Integrations\Square;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SquareLocation extends Model
{
    protected $fillable = [
        'connection_id',
        'location_id',
        'name',
        'status',
        'type',
        'address',
        'timezone',
        'currency',
        'is_primary',
        'capabilities',
        'metadata',
    ];

    protected $casts = [
        'address' => 'array',
        'is_primary' => 'boolean',
        'capabilities' => 'array',
        'metadata' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SquareConnection::class, 'connection_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function isPhysical(): bool
    {
        return $this->type === 'PHYSICAL';
    }
}
