<?php

namespace App\Models\Leisure;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Operator / staff member assigned to a tenant. Auth is via the linked User
 * (email + password). When the operator logs into /operator panel we resolve
 * their TenantTeamMember row by user_id and gate every action by the row's
 * role + leisure_role + permissions array.
 */
class TenantTeamMember extends Model
{
    use HasFactory, SoftDeletes;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_STAFF = 'staff';

    // Granular operational roles (subset of marketplace's leisure_role enum).
    public const LEISURE_ROLES = [
        'check_in' => 'Scanare bilete la intrare',
        'rental_operator' => 'Operator rentals',
        'pos_cashier' => 'Casier POS',
        'pos_manager' => 'Manager POS',
        'inventory_manager' => 'Manager inventar fizic',
        'admin' => 'Administrator complet',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'role',
        'leisure_role',
        'permissions',
        'status',
        'invite_token',
        'invited_at',
        'accepted_at',
        'expires_at',
        'shift_data',
        'notes',
    ];

    protected $casts = [
        'permissions' => 'array',
        'shift_data' => 'array',
        'invited_at' => 'datetime',
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generateInviteToken(): string
    {
        return Str::random(48);
    }

    public function hasPermission(string $perm): bool
    {
        // Admins bypass the explicit permissions list.
        if ($this->role === self::ROLE_ADMIN || $this->leisure_role === 'admin') {
            return true;
        }
        $perms = $this->permissions;
        if (! is_array($perms)) {
            return false;
        }
        return in_array($perm, $perms, true) || in_array('*', $perms, true);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeActive($q)
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }
}
