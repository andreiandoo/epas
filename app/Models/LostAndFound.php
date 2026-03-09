<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LostAndFound extends Model
{
    protected $table = 'lost_and_found';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'customer_id',
        'type',
        'category',
        'item_description',
        'detailed_description',
        'color',
        'brand',
        'location_found_or_lost',
        'date_lost_or_found',
        'reporter_name',
        'reporter_email',
        'reporter_phone',
        'storage_location',
        'status',
        'matched_id',
        'claimed_at',
        'claimed_by_name',
        'claimed_by_id_number',
        'photo_url',
        'staff_notes',
        'meta',
    ];

    protected $casts = [
        'date_lost_or_found' => 'datetime',
        'claimed_at'         => 'datetime',
        'meta'               => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function matchedEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'matched_id');
    }

    public function markClaimed(string $name, ?string $idNumber = null): void
    {
        $this->update([
            'status'              => 'claimed',
            'claimed_at'          => now(),
            'claimed_by_name'     => $name,
            'claimed_by_id_number' => $idNumber,
        ]);
    }

    public function matchWith(self $other): void
    {
        $this->update(['matched_id' => $other->id, 'status' => 'matched']);
        $other->update(['matched_id' => $this->id, 'status' => 'matched']);
    }

    public function isLost(): bool
    {
        return $this->type === 'lost';
    }

    public function isFound(): bool
    {
        return $this->type === 'found';
    }

    public static function categoryLabels(): array
    {
        return [
            'phone'       => 'Phone',
            'wallet'      => 'Wallet / Purse',
            'keys'        => 'Keys',
            'clothing'    => 'Clothing',
            'bag'         => 'Bag / Backpack',
            'jewelry'     => 'Jewelry',
            'documents'   => 'Documents / ID',
            'electronics' => 'Electronics',
            'medication'  => 'Medication',
            'other'       => 'Other',
        ];
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeLost($query)
    {
        return $query->where('type', 'lost');
    }

    public function scopeFound($query)
    {
        return $query->where('type', 'found');
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
