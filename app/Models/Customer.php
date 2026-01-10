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
        'city',
        'country',
        'date_of_birth',
        'age',
        'meta',
        'points_balance',
        'points_earned',
        'points_spent',
        'referral_code',
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
        'points_balance' => 'integer',
        'points_earned' => 'integer',
        'points_spent' => 'integer',
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

    // Points transactions
    public function pointsTransactions(): HasMany
    {
        return $this->hasMany(PointsTransaction::class);
    }

    // Add points to customer
    public function addPoints(int $points, string $type = 'earned', ?string $description = null, ?int $orderId = null): PointsTransaction
    {
        $this->increment('points_balance', $points);
        $this->increment('points_earned', $points);

        return $this->pointsTransactions()->create([
            'points' => $points,
            'type' => $type,
            'description' => $description,
            'order_id' => $orderId,
            'balance_after' => $this->points_balance,
        ]);
    }

    // Spend points from customer
    public function spendPoints(int $points, string $type = 'spent', ?string $description = null, ?int $orderId = null): PointsTransaction
    {
        if ($points > $this->points_balance) {
            throw new \Exception('Insufficient points balance');
        }

        $this->decrement('points_balance', $points);
        $this->increment('points_spent', $points);

        return $this->pointsTransactions()->create([
            'points' => -$points,
            'type' => $type,
            'description' => $description,
            'order_id' => $orderId,
            'balance_after' => $this->points_balance,
        ]);
    }

    // Generate a unique referral code
    public function generateReferralCode(): string
    {
        if (!$this->referral_code) {
            $this->referral_code = strtoupper(substr(md5($this->id . $this->email . time()), 0, 8));
            $this->save();
        }
        return $this->referral_code;
    }

    // Get customer's referrals
    public function referrals(): HasMany
    {
        return $this->hasMany(Customer::class, 'referred_by');
    }

    // Get who referred this customer
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referred_by');
    }

    /**
     * Check if customer's profile is complete
     * A profile is considered complete when all core personal info fields are filled
     */
    public function isProfileComplete(): bool
    {
        return !empty($this->first_name)
            && !empty($this->last_name)
            && !empty($this->phone)
            && !empty($this->city)
            && !empty($this->country)
            && !empty($this->date_of_birth);
    }

    /**
     * Get profile completion percentage (0-100)
     */
    public function getProfileCompletionPercentage(): int
    {
        $fields = [
            $this->first_name,
            $this->last_name,
            $this->phone,
            $this->city,
            $this->country,
            $this->date_of_birth,
        ];

        $filled = collect($fields)->filter(fn ($value) => !empty($value))->count();

        return (int) round(($filled / count($fields)) * 100);
    }

    /**
     * Get list of missing profile fields
     */
    public function getMissingProfileFields(): array
    {
        $missing = [];

        if (empty($this->first_name)) $missing[] = 'first_name';
        if (empty($this->last_name)) $missing[] = 'last_name';
        if (empty($this->phone)) $missing[] = 'phone';
        if (empty($this->city)) $missing[] = 'city';
        if (empty($this->country)) $missing[] = 'country';
        if (empty($this->date_of_birth)) $missing[] = 'date_of_birth';

        return $missing;
    }
}
