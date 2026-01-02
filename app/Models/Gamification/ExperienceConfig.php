<?php

namespace App\Models\Gamification;

use App\Models\MarketplaceClient;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\Translatable;

class ExperienceConfig extends Model
{
    use HasFactory;
    use Translatable;

    public array $translatable = ['xp_name', 'level_name'];

    public const FORMULA_LINEAR = 'linear';
    public const FORMULA_EXPONENTIAL = 'exponential';
    public const FORMULA_CUSTOM = 'custom';

    protected $fillable = [
        'tenant_id',
        'marketplace_client_id',
        'xp_name',
        'level_name',
        'icon',
        'level_formula',
        'base_xp_per_level',
        'level_multiplier',
        'custom_levels',
        'level_groups',
        'level_rewards',
        'max_level',
        'is_active',
    ];

    protected $casts = [
        'xp_name' => 'array',
        'level_name' => 'array',
        'base_xp_per_level' => 'integer',
        'level_multiplier' => 'decimal:2',
        'custom_levels' => 'array',
        'level_groups' => 'array',
        'level_rewards' => 'array',
        'max_level' => 'integer',
        'is_active' => 'boolean',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function actions(): HasMany
    {
        if ($this->tenant_id) {
            return $this->hasMany(ExperienceAction::class, 'tenant_id', 'tenant_id');
        }
        return $this->hasMany(ExperienceAction::class, 'marketplace_client_id', 'marketplace_client_id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Calculate XP required for a specific level
     */
    public function getXpRequiredForLevel(int $level): int
    {
        if ($level <= 1) {
            return 0;
        }

        return match ($this->level_formula) {
            self::FORMULA_LINEAR => $this->base_xp_per_level * ($level - 1),
            self::FORMULA_EXPONENTIAL => (int) round($this->base_xp_per_level * pow($this->level_multiplier, $level - 2)),
            self::FORMULA_CUSTOM => $this->getCustomLevelXp($level),
            default => $this->base_xp_per_level * ($level - 1),
        };
    }

    /**
     * Get XP from custom levels array
     */
    protected function getCustomLevelXp(int $level): int
    {
        if (empty($this->custom_levels)) {
            return $this->base_xp_per_level * ($level - 1);
        }

        foreach ($this->custom_levels as $customLevel) {
            if (($customLevel['level'] ?? 0) === $level) {
                return $customLevel['xp_required'] ?? 0;
            }
        }

        // If level not found, use last defined or linear fallback
        $lastLevel = end($this->custom_levels);
        return ($lastLevel['xp_required'] ?? 0) + ($this->base_xp_per_level * ($level - count($this->custom_levels)));
    }

    /**
     * Calculate total XP needed to reach a level
     */
    public function getTotalXpForLevel(int $level): int
    {
        if ($level <= 1) {
            return 0;
        }

        $totalXp = 0;
        for ($i = 2; $i <= $level; $i++) {
            $totalXp += $this->getXpRequiredForLevel($i);
        }

        return $totalXp;
    }

    /**
     * Calculate level from total XP
     */
    public function getLevelFromXp(int $totalXp): int
    {
        $level = 1;
        $xpAccumulated = 0;

        while ($level < $this->max_level) {
            $xpForNextLevel = $this->getXpRequiredForLevel($level + 1);
            if ($xpAccumulated + $xpForNextLevel > $totalXp) {
                break;
            }
            $xpAccumulated += $xpForNextLevel;
            $level++;
        }

        return $level;
    }

    /**
     * Get level group for a given level
     */
    public function getLevelGroup(int $level): ?array
    {
        if (empty($this->level_groups)) {
            return null;
        }

        foreach ($this->level_groups as $group) {
            $minLevel = $group['min_level'] ?? 1;
            $maxLevel = $group['max_level'] ?? 999;

            if ($level >= $minLevel && $level <= $maxLevel) {
                return $group;
            }
        }

        return null;
    }

    /**
     * Get level rewards for a specific level
     */
    public function getLevelRewards(int $level): ?array
    {
        if (empty($this->level_rewards)) {
            return null;
        }

        foreach ($this->level_rewards as $reward) {
            if (($reward['level'] ?? 0) === $level) {
                return $reward;
            }
        }

        return null;
    }

    /**
     * Format XP name based on value
     */
    public function formatXpName(int $xp): string
    {
        $name = $this->getTranslation('xp_name', app()->getLocale()) ?? 'XP';
        return "{$xp} {$name}";
    }

    /**
     * Format level name
     */
    public function formatLevelName(int $level): string
    {
        $name = $this->getTranslation('level_name', app()->getLocale()) ?? 'Level';
        return "{$name} {$level}";
    }

    /**
     * Get or create config for a tenant
     */
    public static function getOrCreateForTenant(int $tenantId): self
    {
        return self::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'xp_name' => ['en' => 'Experience', 'ro' => 'Experiență'],
                'level_name' => ['en' => 'Level', 'ro' => 'Nivel'],
                'icon' => 'star',
                'level_formula' => self::FORMULA_EXPONENTIAL,
                'base_xp_per_level' => 100,
                'level_multiplier' => 1.5,
                'level_groups' => [
                    ['name' => 'Bronze', 'min_level' => 1, 'max_level' => 5, 'color' => '#CD7F32'],
                    ['name' => 'Silver', 'min_level' => 6, 'max_level' => 10, 'color' => '#C0C0C0'],
                    ['name' => 'Gold', 'min_level' => 11, 'max_level' => 15, 'color' => '#FFD700'],
                    ['name' => 'Platinum', 'min_level' => 16, 'max_level' => 20, 'color' => '#E5E4E2'],
                    ['name' => 'Diamond', 'min_level' => 21, 'max_level' => 99, 'color' => '#B9F2FF'],
                ],
                'max_level' => 100,
                'is_active' => true,
            ]
        );
    }

    /**
     * Get or create config for a marketplace client
     */
    public static function getOrCreateForMarketplace(int $marketplaceClientId): self
    {
        return self::firstOrCreate(
            ['marketplace_client_id' => $marketplaceClientId],
            [
                'xp_name' => ['en' => 'Experience', 'ro' => 'Experiență'],
                'level_name' => ['en' => 'Level', 'ro' => 'Nivel'],
                'icon' => 'star',
                'level_formula' => self::FORMULA_EXPONENTIAL,
                'base_xp_per_level' => 100,
                'level_multiplier' => 1.5,
                'level_groups' => [
                    ['name' => 'Bronze', 'min_level' => 1, 'max_level' => 5, 'color' => '#CD7F32'],
                    ['name' => 'Silver', 'min_level' => 6, 'max_level' => 10, 'color' => '#C0C0C0'],
                    ['name' => 'Gold', 'min_level' => 11, 'max_level' => 15, 'color' => '#FFD700'],
                    ['name' => 'Platinum', 'min_level' => 16, 'max_level' => 20, 'color' => '#E5E4E2'],
                    ['name' => 'Diamond', 'min_level' => 21, 'max_level' => 99, 'color' => '#B9F2FF'],
                ],
                'max_level' => 100,
                'is_active' => true,
            ]
        );
    }
}
