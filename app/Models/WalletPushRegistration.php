<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WalletPushRegistration extends Model
{
    use HasFactory;

    protected $table = 'wallet_push_registrations';

    protected $fillable = [
        'pass_id',
        'device_library_id',
        'push_token',
    ];

    // Relationships
    public function pass(): BelongsTo
    {
        return $this->belongsTo(WalletPass::class, 'pass_id');
    }
}
