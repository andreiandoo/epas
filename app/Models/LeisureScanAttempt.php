<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeisureScanAttempt extends Model
{
    use HasFactory;

    protected $table = 'leisure_scan_attempts';

    protected $fillable = [
        'event_id',
        'marketplace_organizer_id',
        'attempted_code',
        'result',
        'reason',
        'ip_address',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];
}
