<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O scanare individuală a unui QR de staff la check-in. Sursă pentru
 * raportul de pontaj/activitate.
 */
class LeisureStaffCheckin extends Model
{
    use HasFactory;

    protected $table = 'leisure_staff_checkins';

    protected $fillable = [
        'staff_member_id',
        'event_id',
        'scanned_by_user_id',
        'location',
        'ip_address',
        'user_agent',
        'checked_in_at',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
    ];

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(LeisureStaffMember::class, 'staff_member_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}
