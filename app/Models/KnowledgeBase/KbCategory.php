<?php

namespace App\Models\KnowledgeBase;

use App\Models\MarketplaceClient;
use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KbCategory extends Model
{
    use Translatable;

    protected $table = 'kb_categories';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'marketplace_client_id',
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'image_url',
        'sort_order',
        'is_visible',
        'article_count',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'is_visible' => 'boolean',
    ];

    /**
     * Get the marketplace client that owns this category
     */
    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    /**
     * Get all articles in this category
     */
    public function articles(): HasMany
    {
        return $this->hasMany(KbArticle::class, 'kb_category_id');
    }

    /**
     * Scope for visible categories
     */
    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    /**
     * Scope for a specific marketplace
     */
    public function scopeForMarketplace($query, $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }

    /**
     * Update the article count
     */
    public function updateArticleCount(): void
    {
        $this->update([
            'article_count' => $this->articles()->where('is_visible', true)->count(),
        ]);
    }

    /**
     * Get the Heroicon SVG path for this category
     */
    public function getIconSvgAttribute(): ?string
    {
        if (!$this->icon) {
            return null;
        }

        // Map common icon names to their SVG paths
        $iconMap = [
            'ticket' => '<path d="M2 9a3 3 0 0 1 3 3v1a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-1a3 3 0 0 1 0-6V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v1a3 3 0 0 1-3 3Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>',
            'card' => '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
            'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
            'refund' => '<polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>',
            'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'document' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
            'question' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
            'folder' => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
            'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        ];

        return $iconMap[$this->icon] ?? null;
    }
}
