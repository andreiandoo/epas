<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestivalScheduleFavorite extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'festival_lineup_slot_id',
        'notify_before',
        'notify_minutes_before',
    ];

    protected $casts = [
        'notify_before'         => 'boolean',
        'notify_minutes_before' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lineupSlot(): BelongsTo
    {
        return $this->belongsTo(FestivalLineupSlot::class, 'festival_lineup_slot_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(ArtistSetNotification::class);
    }
}
