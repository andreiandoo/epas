<?php

namespace App\Models\Sanius;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaniusTow extends Model
{
    protected $table = 'sanius_tows';

    protected $fillable = [
        'event_id', 'shift_id', 'sled_id', 'sled_code', 'rental_id', 'client_uuid', 'scanned_at_device',
        'scanned_at', 'received_at', 'status', 'anomaly_reason', 'is_test',
    ];

    protected $casts = [
        'scanned_at_device' => 'datetime',
        'scanned_at' => 'datetime',
        'received_at' => 'datetime',
        'is_test' => 'boolean',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(SaniusTowShift::class, 'shift_id');
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(SaniusRental::class, 'rental_id');
    }
}
