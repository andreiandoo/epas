<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Vendor extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'email',
        'password',
        'phone',
        'company_name',
        'cui',
        'contact_person',
        'logo_url',
        'status',
        'api_token',
        'meta',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'meta'     => 'array',
    ];

    // ── Relationships ──

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function editions(): HasMany
    {
        return $this->hasMany(VendorEdition::class);
    }

    public function posDevices(): HasMany
    {
        return $this->hasMany(VendorPosDevice::class);
    }

    public function productCategories(): HasMany
    {
        return $this->hasMany(VendorProductCategory::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(VendorProduct::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(VendorSaleItem::class);
    }

    public function wristbandTransactions(): HasMany
    {
        return $this->hasMany(WristbandTransaction::class);
    }

    // ── Helpers ──

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function generateApiToken(): string
    {
        $token = Str::random(64);
        $this->update(['api_token' => hash('sha256', $token)]);
        return $token;
    }

    public function editionPivot(int $editionId): ?VendorEdition
    {
        return $this->editions()->where('festival_edition_id', $editionId)->first();
    }

    public function commissionRateForEdition(int $editionId): float
    {
        return $this->editionPivot($editionId)?->commission_rate ?? 0;
    }

    public function totalSalesCentsForEdition(int $editionId): int
    {
        return $this->saleItems()
            ->where('festival_edition_id', $editionId)
            ->sum('total_cents');
    }

    public function totalCommissionCentsForEdition(int $editionId): int
    {
        return $this->saleItems()
            ->where('festival_edition_id', $editionId)
            ->sum('commission_cents');
    }
}
