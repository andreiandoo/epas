<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Token de sesiune pentru aplicația Tixello. Se pastreaza hash-ul, nu tokenul. */
class TixelloAccountToken extends Model
{
    protected $fillable = ['tixello_account_id', 'token', 'name', 'device_id', 'last_used_at', 'expires_at'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(TixelloAccount::class, 'tixello_account_id');
    }
}
