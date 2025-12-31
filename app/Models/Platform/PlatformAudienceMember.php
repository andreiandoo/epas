<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAudienceMember extends Model
{
    protected $fillable = [
        'platform_audience_id',
        'core_customer_id',
        'hashed_email',
        'hashed_phone',
        'hashed_first_name',
        'hashed_last_name',
        'is_matched',
        'added_at',
        'removed_at',
    ];

    protected $casts = [
        'is_matched' => 'boolean',
        'added_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function audience(): BelongsTo
    {
        return $this->belongsTo(PlatformAudience::class, 'platform_audience_id');
    }

    public function coreCustomer(): BelongsTo
    {
        return $this->belongsTo(CoreCustomer::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereNull('removed_at');
    }

    public function scopeMatched($query)
    {
        return $query->where('is_matched', true);
    }

    public function scopeUnmatched($query)
    {
        return $query->where('is_matched', false);
    }

    public function scopeForAudience($query, int $audienceId)
    {
        return $query->where('platform_audience_id', $audienceId);
    }

    public function scopeAddedAfter($query, $date)
    {
        return $query->where('added_at', '>=', $date);
    }

    // Status management
    public function markMatched(): void
    {
        $this->update(['is_matched' => true]);
    }

    public function markRemoved(): void
    {
        $this->update(['removed_at' => now()]);
    }

    // Helpers
    public function isActive(): bool
    {
        return is_null($this->removed_at);
    }

    public function getHashedData(): array
    {
        $data = [];

        if ($this->hashed_email) {
            $data['em'] = $this->hashed_email;
        }

        if ($this->hashed_phone) {
            $data['ph'] = $this->hashed_phone;
        }

        if ($this->hashed_first_name) {
            $data['fn'] = $this->hashed_first_name;
        }

        if ($this->hashed_last_name) {
            $data['ln'] = $this->hashed_last_name;
        }

        return $data;
    }

    // Static factory
    public static function createFromCustomer(
        PlatformAudience $audience,
        CoreCustomer $customer
    ): self {
        return static::create([
            'platform_audience_id' => $audience->id,
            'core_customer_id' => $customer->id,
            'hashed_email' => $customer->email_hash,
            'hashed_phone' => $customer->phone_hash,
            'hashed_first_name' => $customer->getHashedDataForAds()['fn'] ?? null,
            'hashed_last_name' => $customer->getHashedDataForAds()['ln'] ?? null,
            'is_matched' => false,
            'added_at' => now(),
        ]);
    }
}
