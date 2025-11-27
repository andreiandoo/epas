<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasFactory;
    use SoftDeletes;
    use Notifiable;
    use HasApiTokens;

    protected $fillable = [
        'tenant_id',
        'primary_tenant_id',
        'email',
        'password',
        'first_name',
        'last_name',
        'full_name',
        'phone',
        'meta',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'meta' => 'array',
        'email_verified_at' => 'datetime',
        'date_of_birth' => 'date',
        'password' => 'hashed',
    ];

    // Tenant de bază (unde a fost creat inițial customerul)
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // „Primary tenant” (entry point), FK: primary_tenant_id
    public function primaryTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'primary_tenant_id');
    }

    // Tenants multiple (customerul poate aparține mai multor tenants)
    // pivot table: customer_tenant (customer_id, tenant_id)
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'customer_tenant')->withTimestamps();
    }

    // Comenzi asociate (presupune că ai coloana customer_id în orders)
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // Helper pentru nume afișat
    public function getFullNameAttribute(): ?string
    {
        $first = trim((string) $this->first_name);
        $last  = trim((string) $this->last_name);

        if ($first === '' && $last === '') {
            return null;
        }

        return trim($first . ' ' . $last);
    }

    // Check if customer is active (not soft deleted)
    public function isActive(): bool
    {
        return !$this->trashed();
    }

    // Watchlist - events that customer is watching
    public function watchlist(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'customer_watchlist')
            ->withTimestamps();
    }
}
