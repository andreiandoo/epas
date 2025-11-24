<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'position',
        'avatar',
        'role',
        'tenant_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    public function isSuperAdmin(): bool { return $this->role === 'super-admin'; }
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isEditor(): bool { return $this->role === 'editor'; }
    public function isTenant(): bool { return $this->role === 'tenant'; }

    /**
     * Determine if the user can access a Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'tenant') {
            return $this->role === 'tenant';
        }

        // Admin panel
        return in_array($this->role, ['super-admin', 'admin', 'editor']);
    }

    /**
     * Get the tenant this user owns (if tenant role).
     */
    public function ownedTenant(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Tenant::class, 'owner_id');
    }

    /**
     * Get the tenant this user belongs to (for editors).
     */
    public function belongsToTenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the tenant for this user (works for both tenant owners and editors).
     */
    public function getTenantAttribute(): ?Tenant
    {
        // First check if user belongs to a tenant (editor)
        if ($this->tenant_id) {
            return $this->belongsToTenant;
        }
        // Otherwise check if user owns a tenant (tenant role)
        return $this->ownedTenant;
    }
}
