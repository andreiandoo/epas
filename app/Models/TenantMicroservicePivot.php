<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TenantMicroservicePivot extends Pivot
{
    protected $table = 'tenant_microservice';

    protected $casts = [
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'configuration' => 'array',
    ];
}
