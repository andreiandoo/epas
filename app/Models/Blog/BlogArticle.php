<?php

namespace App\Models\Blog;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Translatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogArticle extends Model
{
    use HasUuids, SoftDeletes, Translatable;

    protected $table = 'blog_articles';

    public array $translatable = [
        'title', 'subtitle', 'content', 'content_html', 'excerpt',
        'meta_title', 'meta_description', 'og_title', 'og_description'
    ];

    protected $fillable = [
        'tenant_id',
        'slug',
        'title',
        'subtitle',
        'content',
        'content_html',
        'content_json',
        'excerpt',
        'featured_image_url',
        'featured_image_alt',
        'featured_image_caption',
        'gallery',
        'category_id',
        'series_id',
        'series_order',
        'author_id',
        'co_author_ids',
        'status',
        'visibility',
        'password',
        'published_at',
        'scheduled_at',
        'meta_title',
        'meta_description',
        'canonical_url',
        'og_title',
        'og_description',
        'og_image_url',
        'twitter_card',
        'schema_markup',
        'no_index',
        'allow_comments',
        'is_featured',
        'is_pinned',
        'reading_time_minutes',
        'word_count',
        'view_count',
        'like_count',
        'share_count',
        'comment_count',
        'language',
        'translations',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'title' => 'array',
        'subtitle' => 'array',
        'content' => 'array',
        'content_html' => 'array',
        'content_json' => 'array',
        'excerpt' => 'array',
        'gallery' => 'array',
        'co_author_ids' => 'array',
        'meta_title' => 'array',
        'meta_description' => 'array',
        'og_title' => 'array',
        'og_description' => 'array',
        'schema_markup' => 'array',
        'translations' => 'array',
        'no_index' => 'boolean',
        'allow_comments' => 'boolean',
        'is_featured' => 'boolean',
        'is_pinned' => 'boolean',
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(BlogSeries::class, 'series_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(BlogAuthor::class, 'author_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_article_tag', 'article_id', 'tag_id')
            ->withTimestamps();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(BlogArticleRevision::class, 'article_id')->orderBy('revision_number', 'desc');
    }

    public function views(): HasMany
    {
        return $this->hasMany(BlogArticleView::class, 'article_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BlogComment::class, 'article_id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' &&
               ($this->published_at === null || $this->published_at->isPast());
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled' &&
               $this->scheduled_at !== null &&
               $this->scheduled_at->isFuture();
    }

    public function publish(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now(),
            'scheduled_at' => null,
        ]);
    }

    public function unpublish(): void
    {
        $this->update([
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    public function schedule(\DateTime $at): void
    {
        $this->update([
            'status' => 'scheduled',
            'scheduled_at' => $at,
        ]);
    }

    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    public function createRevision(?int $userId = null): BlogArticleRevision
    {
        $lastRevision = $this->revisions()->max('revision_number') ?? 0;

        return BlogArticleRevision::create([
            'article_id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'revision_number' => $lastRevision + 1,
            'title' => $this->title,
            'content' => $this->content,
            'content_json' => $this->content_json,
            'changed_by' => $userId,
        ]);
    }

    public function calculateReadingTime(): int
    {
        $content = is_array($this->content)
            ? ($this->content['en'] ?? reset($this->content) ?? '')
            : ($this->content ?? '');

        $wordCount = str_word_count(strip_tags($content));
        $this->word_count = $wordCount;

        // Average reading speed: 200 words per minute
        $readingTime = max(1, (int) ceil($wordCount / 200));
        $this->reading_time_minutes = $readingTime;

        return $readingTime;
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('published_at')
                  ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByAuthor($query, $authorId)
    {
        return $query->where('author_id', $authorId);
    }

    public function scopeInSeries($query, $seriesId)
    {
        return $query->where('series_id', $seriesId)->orderBy('series_order');
    }

    public function scopeScheduledToPublish($query)
    {
        return $query->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }
}
