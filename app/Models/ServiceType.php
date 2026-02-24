<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceType extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketplace_client_id',
        'code',
        'name',
        'description',
        'pricing',
        'is_active',
    ];

    protected $casts = [
        'pricing' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Default pricing configuration for a new marketplace
     */
    public static function getDefaultPricing(): array
    {
        return [
            'featuring' => [
                'home_hero'            => 120,
                'home_recommendations' => 80,
                'category'             => 60,
                'city'                 => 40,
            ],
            'email' => [
                'own_per_email' => 0.40,
                'marketplace_per_email' => 0.50,
                'minimum' => 100,
            ],
            'tracking' => [
                'per_platform_monthly' => 49,
                'discounts' => [
                    '1' => 0,
                    '3' => 0.10,
                    '6' => 0.15,
                    '12' => 0.25,
                ],
            ],
            'campaign' => [
                'basic' => 499,
                'standard' => 899,
                'premium' => 1499,
            ],
        ];
    }

    /**
     * Get or create default service types for a marketplace
     */
    public static function getOrCreateForMarketplace(int $marketplaceClientId): array
    {
        $types = [];
        $defaults = [
            'featuring' => [
                'name' => 'Promovare Eveniment',
                'description' => 'Promoveaza evenimentul pe paginile principale ale platformei',
            ],
            'email' => [
                'name' => 'Email Marketing',
                'description' => 'Trimite campanii email catre publicul tinta',
            ],
            'tracking' => [
                'name' => 'Ad Tracking',
                'description' => 'Integreaza pixeli de tracking pentru campanii publicitare',
            ],
            'campaign' => [
                'name' => 'Creare Campanii',
                'description' => 'Servicii profesionale de creare campanii publicitare',
            ],
        ];

        $pricing = self::getDefaultPricing();

        foreach ($defaults as $code => $data) {
            $types[$code] = self::firstOrCreate(
                [
                    'marketplace_client_id' => $marketplaceClientId,
                    'code' => $code,
                ],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'pricing' => $pricing[$code],
                    'is_active' => true,
                ]
            );
        }

        return $types;
    }

    // Relationships

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'service_type', 'code');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForMarketplace($query, int $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }
}
