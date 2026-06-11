<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Waitlist extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'email',
        'name',
        'phone',
        'waitable_type',
        'waitable_id',
        'quantity',
        'status',
        'notified_at',
        'converted_at',
        'expires_at',
        'notification_token',
        'position',
        'meta',
    ];

    protected $casts = [
        'quantity'    => 'integer',
        'notified_at' => 'datetime',
        'converted_at' => 'datetime',
        'expires_at'  => 'datetime',
        'position'    => 'integer',
        'meta'        => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $entry) {
            if (empty($entry->notification_token)) {
                $entry->notification_token = Str::uuid()->toString();
            }

            if (empty($entry->position)) {
                $entry->position = static::where('waitable_type', $entry->waitable_type)
                    ->where('waitable_id', $entry->waitable_id)
                    ->where('status', 'waiting')
                    ->max('position') + 1;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function waitable(): MorphTo
    {
        return $this->morphTo();
    }

    public function notify(): void
    {
        $this->update([
            'status'      => 'notified',
            'notified_at' => now(),
            'expires_at'  => now()->addHours(48),
        ]);
    }

    public function markConverted(): void
    {
        $this->update([
            'status'       => 'converted',
            'converted_at' => now(),
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    public function scopeForItem($query, string $type, int $id)
    {
        return $query->where('waitable_type', $type)->where('waitable_id', $id);
    }

    /**
     * Notify next N people in the waitlist for a given item.
     */
    public static function notifyNext(string $waitableType, int $waitableId, int $count = 1): int
    {
        $entries = static::where('waitable_type', $waitableType)
            ->where('waitable_id', $waitableId)
            ->where('status', 'waiting')
            ->orderBy('position')
            ->limit($count)
            ->get();

        foreach ($entries as $entry) {
            $entry->notify();
        }

        return $entries->count();
    }
}
