<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FestivalReview extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'customer_id',
        'reviewable_type',
        'reviewable_id',
        'overall_rating',
        'sound_rating',
        'organization_rating',
        'value_rating',
        'food_rating',
        'safety_rating',
        'comment',
        'status',
        'is_anonymous',
        'moderation_note',
        'approved_at',
        'meta',
    ];

    protected $casts = [
        'overall_rating'      => 'integer',
        'sound_rating'        => 'integer',
        'organization_rating' => 'integer',
        'value_rating'        => 'integer',
        'food_rating'         => 'integer',
        'safety_rating'       => 'integer',
        'is_anonymous'        => 'boolean',
        'approved_at'         => 'datetime',
        'meta'                => 'array',
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

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function approve(): void
    {
        $this->update([
            'status'      => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function reject(?string $reason = null): void
    {
        $this->update([
            'status'          => 'rejected',
            'moderation_note' => $reason,
        ]);
    }

    public function getAverageRatingAttribute(): float
    {
        $ratings = array_filter([
            $this->overall_rating,
            $this->sound_rating,
            $this->organization_rating,
            $this->value_rating,
            $this->food_rating,
            $this->safety_rating,
        ]);

        return count($ratings) > 0 ? round(array_sum($ratings) / count($ratings), 1) : 0;
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeHighRated($query, int $minRating = 4)
    {
        return $query->where('overall_rating', '>=', $minRating);
    }
}
