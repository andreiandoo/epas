<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketplaceGiftCardDesign extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'marketplace_client_id',
        'name',
        'slug',
        'description',
        'occasion',
        'preview_image',
        'email_template_path',
        'pdf_template_path',
        'colors',
        'options',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'colors' => 'array',
        'options' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    // =========================================
    // Relationships
    // =========================================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function giftCards(): HasMany
    {
        return $this->hasMany(MarketplaceGiftCard::class, 'design_template', 'slug');
    }

    // =========================================
    // Scopes
    // =========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForMarketplace($query, int $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }

    public function scopeForOccasion($query, string $occasion)
    {
        return $query->where('occasion', $occasion);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // =========================================
    // Helpers
    // =========================================

    public function getOccasionLabelAttribute(): ?string
    {
        return MarketplaceGiftCard::OCCASIONS[$this->occasion] ?? $this->occasion;
    }

    public function getPrimaryColorAttribute(): ?string
    {
        return $this->colors['primary'] ?? '#4f46e5';
    }

    public function getSecondaryColorAttribute(): ?string
    {
        return $this->colors['secondary'] ?? '#818cf8';
    }

    public function getPreviewUrlAttribute(): ?string
    {
        if (!$this->preview_image) {
            return null;
        }
        return \Storage::url($this->preview_image);
    }

    /**
     * Set as default design for this marketplace
     */
    public function setAsDefault(): void
    {
        // Remove default from other designs
        static::where('marketplace_client_id', $this->marketplace_client_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }
}
