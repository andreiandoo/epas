<?php

namespace App\Models\KnowledgeBase;

use App\Models\MarketplaceClient;
use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KbArticle extends Model
{
    use Translatable;

    protected $table = 'kb_articles';

    public array $translatable = ['title', 'content', 'question', 'meta_title', 'meta_description'];

    protected $fillable = [
        'marketplace_client_id',
        'kb_category_id',
        'type',
        'title',
        'slug',
        'content',
        'question',
        'icon',
        'sort_order',
        'is_visible',
        'is_featured',
        'is_popular',
        'view_count',
        'helpful_count',
        'not_helpful_count',
        'meta_title',
        'meta_description',
        'tags',
    ];

    protected $casts = [
        'title' => 'array',
        'content' => 'array',
        'question' => 'array',
        'meta_title' => 'array',
        'meta_description' => 'array',
        'tags' => 'array',
        'is_visible' => 'boolean',
        'is_featured' => 'boolean',
        'is_popular' => 'boolean',
    ];

    /**
     * Get the marketplace client that owns this article
     */
    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    /**
     * Get the category this article belongs to
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'kb_category_id');
    }

    /**
     * Scope for visible articles
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
     * Scope for articles (not FAQs)
     */
    public function scopeArticles($query)
    {
        return $query->where('type', 'article');
    }

    /**
     * Scope for FAQs
     */
    public function scopeFaqs($query)
    {
        return $query->where('type', 'faq');
    }

    /**
     * Scope for featured items
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for popular items
     */
    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }

    /**
     * Check if this is an FAQ type
     */
    public function isFaq(): bool
    {
        return $this->type === 'faq';
    }

    /**
     * Check if this is an article type
     */
    public function isArticle(): bool
    {
        return $this->type === 'article';
    }

    /**
     * Increment view count
     */
    public function incrementViews(): void
    {
        $this->increment('view_count');
    }

    /**
     * Mark as helpful
     */
    public function markHelpful(): void
    {
        $this->increment('helpful_count');
    }

    /**
     * Mark as not helpful
     */
    public function markNotHelpful(): void
    {
        $this->increment('not_helpful_count');
    }

    /**
     * Get the helpfulness score (percentage)
     */
    public function getHelpfulnessScoreAttribute(): ?float
    {
        $total = $this->helpful_count + $this->not_helpful_count;
        if ($total === 0) {
            return null;
        }
        return round(($this->helpful_count / $total) * 100, 1);
    }

    /**
     * Boot method to update category article count
     */
    protected static function booted(): void
    {
        static::created(function (KbArticle $article) {
            $article->category?->updateArticleCount();
        });

        static::updated(function (KbArticle $article) {
            if ($article->isDirty('is_visible') || $article->isDirty('kb_category_id')) {
                $article->category?->updateArticleCount();
                if ($article->isDirty('kb_category_id')) {
                    $originalCategoryId = $article->getOriginal('kb_category_id');
                    if ($originalCategoryId) {
                        KbCategory::find($originalCategoryId)?->updateArticleCount();
                    }
                }
            }
        });

        static::deleted(function (KbArticle $article) {
            $article->category?->updateArticleCount();
        });
    }
}
