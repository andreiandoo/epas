<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PlatformCredential extends Model
{
    protected $fillable = [
        'name',
        'url',
        'username',
        'email',
        'password',
        'category',
        'is_active',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Category labels for display
     */
    public static function getCategoryLabels(): array
    {
        return [
            'social_content' => 'Social & Content Platforms',
            'saas_review' => 'SaaS/Software Review & Trust',
            'startup_directory' => 'Startup & Product Directories',
            'business_listing' => 'Business Listings & Maps',
            'developer_tech' => 'Developer & Tech Ecosystem',
            'integration_marketplace' => 'Integration & App Marketplaces',
            'community_forum' => 'Communities, Forums & Design',
        ];
    }

    /**
     * Get category label
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::getCategoryLabels()[$this->category] ?? $this->category;
    }

    /**
     * Encrypt password when setting
     */
    public function setPasswordAttribute($value)
    {
        if ($value) {
            $this->attributes['password'] = Crypt::encryptString($value);
        } else {
            $this->attributes['password'] = null;
        }
    }

    /**
     * Decrypt password when getting
     */
    public function getPasswordAttribute($value)
    {
        if ($value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return $value; // Return as-is if decryption fails
            }
        }
        return null;
    }

    /**
     * Scope for active credentials
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by category
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
