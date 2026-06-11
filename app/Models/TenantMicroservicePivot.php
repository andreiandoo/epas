<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TenantMicroservicePivot extends Pivot
{
    protected $table = 'tenant_microservices';

    protected $casts = [
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'settings' => 'array',
    ];
}
