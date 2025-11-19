<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
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
        'email',
        'password',
        'role',
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
     * Determine if the user can access the Filament admin panel.
     */
    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        \Illuminate\Support\Facades\Log::info('=== User->canAccessPanel() CALLED ===', [
            'user_id' => $this->id,
            'user_email' => $this->email,
            'user_role' => $this->role,
            'panel_id' => $panel->getId(),
            'panel_path' => $panel->getPath(),
            'is_authenticated' => auth()->check(),
            'auth_user_id' => auth()->id(),
        ]);

        // TEMPORARY: Allow all authenticated users to test
        $result = true;

        \Illuminate\Support\Facades\Log::info('=== User->canAccessPanel() RETURNING ===', [
            'result' => $result,
            'user_id' => $this->id,
        ]);

        return $result;

        // Original logic (will restore after testing):
        // return in_array($this->role, ['super-admin', 'admin', 'editor']);
    }
}
