<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One credential in the marketplace "Seif". Only the admins attached through
 * accessAdmins() can see it; being a super admin is not enough on its own.
 */
class MarketplaceVaultEntry extends Model
{
    protected $table = 'marketplace_vault_entries';

    protected $fillable = [
        'marketplace_client_id',
        'name',
        'url',
        'username',
        'password',
        'requires_2fa',
        'phone',
        'email',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'url' => 'encrypted',
        'username' => 'encrypted',
        'password' => 'encrypted',
        'phone' => 'encrypted',
        'email' => 'encrypted',
        'requires_2fa' => 'boolean',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function accessAdmins(): BelongsToMany
    {
        return $this->belongsToMany(
            MarketplaceAdmin::class,
            'marketplace_vault_access',
            'vault_entry_id',
            'marketplace_admin_id'
        )->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'updated_by');
    }

    /**
     * Entries of the admin's marketplace that the admin was given access to.
     */
    public function scopeAccessibleBy(Builder $query, MarketplaceAdmin $admin): Builder
    {
        return $query
            ->where('marketplace_client_id', $admin->marketplace_client_id)
            ->whereHas('accessAdmins', fn (Builder $q) => $q->whereKey($admin->getKey()));
    }

    public function isAccessibleBy(?MarketplaceAdmin $admin): bool
    {
        if (! $admin || (int) $this->marketplace_client_id !== (int) $admin->marketplace_client_id) {
            return false;
        }

        if ($this->relationLoaded('accessAdmins')) {
            return $this->accessAdmins->contains($admin->getKey());
        }

        return $this->accessAdmins()->whereKey($admin->getKey())->exists();
    }
}
