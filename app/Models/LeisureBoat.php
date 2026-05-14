<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LeisureBoat extends Model
{
    protected $fillable = [
        'event_id',
        'ticket_type_id',
        'number',
        'label',
        'qr_code',
        'status',
    ];

    protected $casts = [
        'number' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (LeisureBoat $boat) {
            if (empty($boat->qr_code)) {
                $boat->qr_code = self::generateQrCode();
            }
        });
    }

    public static function generateQrCode(): string
    {
        do {
            $code = 'BOAT-' . strtoupper(Str::random(10));
        } while (self::where('qr_code', $code)->exists());
        return $code;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function activeRental(): HasMany
    {
        return $this->hasMany(BoatRental::class, 'boat_id')->where('status', 'active');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(BoatRental::class, 'boat_id');
    }
}
