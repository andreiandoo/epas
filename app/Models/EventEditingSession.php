<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventEditingSession extends Model
{
    protected $fillable = [
        'event_id',
        'admin_id',
        'admin_name',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];
}
