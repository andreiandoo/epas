<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demo live al unui template (demo-{slug}.tics.ro): host-ul are un Domain al
 * tenantului demo (autentificarea și coșul găsesc tenantul după host), iar aici
 * stă template-ul servit pe acel host. Vezi TenantSiteController.
 */
class TenantSiteDemo extends Model
{
    protected $fillable = [
        'domain_id',
        'tenant_id',
        'template',
        'title',
        'description',
        'data',
        'is_listed',
        'sort',
    ];

    protected $casts = [
        'data' => 'array',
        'is_listed' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Domeniul demo-{slug} există doar pentru demo; pleacă odată cu el.
        static::deleted(function (TenantSiteDemo $demo) {
            Domain::whereKey($demo->domain_id)->delete();
        });
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
