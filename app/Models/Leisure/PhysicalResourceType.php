<?php

namespace App\Models\Leisure;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Catalog of resource types a leisure tenant rents out (Kayak, MTB Bike,
 * Locker M, …). Distinct from the individual units (PhysicalResource rows),
 * which all reference a single type via physical_resource_type_id.
 *
 * `linked_ticket_type_ids` here is the *default* whitelist that gets copied
 * onto new resource instances when they are created from this type.
 */
class PhysicalResourceType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'image_url',
        'is_active',
        'linked_ticket_type_ids',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'linked_ticket_type_ids' => 'array',
        'meta' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(PhysicalResource::class);
    }

    public function availableCount(): int
    {
        return $this->resources()->where('status', PhysicalResource::STATUS_AVAILABLE)->count();
    }

    public function totalCount(): int
    {
        return $this->resources()->count();
    }

    public static function generateSlug(string $name, int $tenantId): string
    {
        $base = Str::slug($name) ?: 'res';
        $slug = $base;
        $i = 1;
        while (self::query()->where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
