<?php

namespace App\Models\Shop;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class ShopShippingZone extends Model
{
    use HasUuids;

    protected $table = 'shop_shipping_zones';

    protected $fillable = [
        'tenant_id',
        'name',
        'countries',
        'regions',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'name' => 'array',
        'countries' => 'array',
        'regions' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function methods(): HasMany
    {
        return $this->hasMany(ShopShippingMethod::class, 'zone_id')->orderBy('sort_order');
    }

    public function activeMethods(): HasMany
    {
        return $this->methods()->where('is_active', true);
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // Matching

    public function matchesAddress(array $address): bool
    {
        $countryCode = strtoupper($address['country'] ?? $address['country_code'] ?? '');
        $regionCode = $address['region'] ?? $address['state'] ?? $address['province'] ?? null;

        // Check if country matches
        if (!in_array($countryCode, $this->countries ?? [])) {
            return false;
        }

        // If regions are specified, check region
        if (!empty($this->regions) && $regionCode) {
            return in_array($regionCode, $this->regions);
        }

        return true;
    }

    public static function findForAddress(int $tenantId, array $address): ?self
    {
        // First try to find a specific zone for the address
        $zones = static::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_default', false)
            ->get();

        foreach ($zones as $zone) {
            if ($zone->matchesAddress($address)) {
                return $zone;
            }
        }

        // Fall back to default zone
        return static::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
    }
}
