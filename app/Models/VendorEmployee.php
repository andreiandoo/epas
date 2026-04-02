<?php

namespace App\Models;

use App\Enums\VendorUserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorEmployee extends Model
{
    protected $fillable = [
        'tenant_id',
        'vendor_id',
        'name',
        'full_name',
        'phone',
        'email',
        'password',
        'pin',
        'role',
        'status',
        'permissions',
        'avatar_url',
        'email_verified_at',
        'meta',
    ];

    protected $hidden = ['pin', 'password'];

    protected $casts = [
        'permissions'       => 'array',
        'meta'              => 'array',
        'email_verified_at' => 'datetime',
    ];

    // ── Relationships ──

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(VendorShift::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(VendorSaleItem::class);
    }

    // ── Helpers ──

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->role === 'admin') {
            return true;
        }

        return in_array($permission, $this->permissions ?? []);
    }

    public function activeShift(): ?VendorShift
    {
        return $this->shifts()->where('status', 'active')->latest('started_at')->first();
    }

    public function startShift(int $editionId, ?int $posDeviceId = null): VendorShift
    {
        // Close any existing active shift
        $this->shifts()->where('status', 'active')->update([
            'status'   => 'completed',
            'ended_at' => now(),
        ]);

        return $this->shifts()->create([
            'tenant_id'             => $this->tenant_id,
            'vendor_id'             => $this->vendor_id,
            'festival_edition_id'   => $editionId,
            'vendor_pos_device_id'  => $posDeviceId,
            'started_at'            => now(),
            'status'                => 'active',
        ]);
    }

    public function endShift(): ?VendorShift
    {
        $shift = $this->activeShift();
        if ($shift) {
            $shift->update([
                'status'   => 'completed',
                'ended_at' => now(),
            ]);
        }
        return $shift;
    }
}
