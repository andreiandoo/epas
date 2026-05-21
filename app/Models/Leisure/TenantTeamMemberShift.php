<?php

namespace App\Models\Leisure;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantTeamMemberShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'tenant_team_member_id',
        'shift_date',
        'start_time',
        'end_time',
        'position',
        'location',
        'notes',
    ];

    protected $casts = [
        'shift_date' => 'date',
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(TenantTeamMember::class, 'tenant_team_member_id');
    }

    public function getDurationMinutesAttribute(): int
    {
        if (! $this->start_time || ! $this->end_time) return 0;
        return (int) $this->start_time->diffInMinutes($this->end_time);
    }
}
