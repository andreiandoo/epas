<?php

namespace App\Services\Blog;

use App\Models\Blog\BlogArticle;
use App\Models\Blog\BlogArticleRevision;
use App\Models\Blog\BlogArticleView;
use App\Models\Blog\BlogAuthor;
use App\Models\Blog\BlogCategory;
use App\Models\Blog\BlogComment;
use App\Models\Blog\BlogSeries;
use App\Models\Blog\BlogSubscription;
use App\Models\Blog\BlogTag;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BlogService
{
    // ==========================================
    // ARTICLE MANAGEMENT
    // ==========================================

    public function createArticle(int $tenantId, array $data): BlogArticle
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $article = BlogArticle::create([
                'tenant_id' => $tenantId,
                'author_id' => $data['author_id'],
                'category_id' => $data['category_id'] ?? null,
                'series_id' => $data['series_id'] ?? null,
                'title' => $data['title'],
                'slug' => $data['slug'] ?? Str::slug($data['title']),
                'excerpt' => $data['excerpt'] ?? null,
                'content' => $data['content'],
                'featured_image' => $data['featured_image'] ?? null,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'meta_keywords' => $data['meta_keywords'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'visibility' => $data['visibility'] ?? 'public',
                'is_featured' => $data['is_featured'] ?? false,
                'allow_comments' => $data['allow_comments'] ?? true,
                'published_at' => $data['published_at'] ?? null,
                'reading_time_minutes' => $this->calculateReadingTime($data['content']),
            ]);

            // Attach tags if provided
            if (!empty($data['tags'])) {
                $article->tags()->sync($data['tags']);
            }

            // Create initial revision
            $this->createRevision($article, $data['content'], 'Initial version');

            return $article->load(['author', 'category', 'tags']);
        });
    }

    public function updateArticle(BlogArticle $article, array $data): BlogArticle
    {
        return DB::transaction(function () use ($article, $data) {
            $contentChanged = isset($data['content']) && $data['content'] !== $article->content;

            $article->update(array_filter([
                'author_id' => $data['author_id'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'series_id' => $data['series_id'] ?? null,
                'title' => $data['title'] ?? null,
                'slug' => $data['slug'] ?? null,
                'excerpt' => $data['excerpt'] ?? null,
                'content' => $data['content'] ?? null,
                'featured_image' => $data['featured_image'] ?? null,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'meta_keywords' => $data['meta_keywords'] ?? null,
                'status' => $data['status'] ?? null,
                'visibility' => $data['visibility'] ?? null,
                'is_featured' => $data['is_featured'] ?? null,
                'allow_comments' => $data['allow_comments'] ?? null,
                'published_at' => $data['published_at'] ?? null,
                'reading_time_minutes' => isset($data['content'])
                    ? $this->calculateReadingTime($data['content'])
                    : null,
            ], fn($value) => $value !== null));

            // Update tags if provided
            if (isset($data['tags'])) {
                $article->tags()->sync($data['tags']);
            }

            // Create revision if content changed
            if ($contentChanged) {
                $this->createRevision(
                    $article,
                    $data['content'],
                    $data['revision_note'] ?? 'Content updated'
                );
            }

            return $article->fresh(['author', 'category', 'tags']);
        });
    }

    public function publishArticle(BlogArticle $article, ?\DateTime $publishAt = null): BlogArticle
    {
        $article->update([
            'status' => 'published',
            'published_at' => $publishAt ?? now(),
        ]);

        return $article;
    }

    public function unpublishArticle(BlogArticle $article): BlogArticle
    {
        $article->update([
            'status' => 'draft',
            'published_at' => null,
        ]);

        return $article;
    }

    public function archiveArticle(BlogArticle $article): BlogArticle
    {
        $article->update(['status' => 'archived']);
        return $article;
    }

    public function deleteArticle(BlogArticle $article): bool
    {
        return $article->delete();
    }

    public function getArticle(int $tenantId, int $articleId): ?BlogArticle
    {
        return BlogArticle::where('tenant_id', $tenantId)
            ->with(['author', 'category', 'tags', 'series'])
            ->find($articleId);
    }

    public function getArticleBySlug(int $tenantId, string $slug): ?BlogArticle
    {
        return BlogArticle::where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->with(['author', 'category', 'tags', 'series'])
            ->first();
    }

    public function listArticles(
        int $tenantId,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = BlogArticle::where('tenant_id', $tenantId)
            ->with(['author', 'category', 'tags']);

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['author_id'])) {
            $query->where('author_id', $filters['author_id']);
        }

        if (!empty($filters['series_id'])) {
            $query->where('series_id', $filters['series_id']);
        }

        if (!empty($filters['tag_id'])) {
            $query->whereHas('tags', fn($q) => $q->where('blog_tags.id', $filters['tag_id']));
        }

        if (!empty($filters['is_featured'])) {
            $query->where('is_featured', true);
        }

        if (!empty($filters['visibility'])) {
            $query->where('visibility', $filters['visibility']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('content', 'ilike', "%{$search}%")
                    ->orWhere('excerpt', 'ilike', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'published_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    public function getPublishedArticles(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->listArticles($tenantId, [
            'status' => 'published',
            'visibility' => 'public',
        ], $perPage);
    }

    public function getFeaturedArticles(int $tenantId, int $limit = 5): Collection
    {
        return BlogArticle::where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->where('is_featured', true)
            ->with(['author', 'category'])
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getRelatedArticles(BlogArticle $article, int $limit = 5): Collection
    {
        $tagIds = $article->tags->pluck('id');

        return BlogArticle::where('tenant_id', $article->tenant_id)
            ->where('id', '!=', $article->id)
            ->where('status', 'published')
            ->where(function ($query) use ($article, $tagIds) {
                $query->where('category_id', $article->category_id)
                    ->orWhereHas('tags', fn($q) => $q->whereIn('blog_tags.id', $tagIds));
            })
            ->with(['author', 'category'])
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }

    // ==========================================
    // REVISION MANAGEMENT
    // ==========================================

    protected function createRevision(
        BlogArticle $article,
        string $content,
        ?string $note = null
    ): BlogArticleRevision {
        $latestRevision = $article->revisions()->max('version') ?? 0;

        return BlogArticleRevision::create([
            'article_id' => $article->id,
            'content' => $content,
            'version' => $latestRevision + 1,
            'revision_note' => $note,
            'created_by' => auth()->id(),
        ]);
    }

    public function getRevisions(BlogArticle $article): Collection
    {
        return $article->revisions()
            ->orderBy('version', 'desc')
            ->get();
    }

    public function restoreRevision(BlogArticle $article, int $revisionId): BlogArticle
    {
        $revision = $article->revisions()->findOrFail($revisionId);

        $article->update(['content' => $revision->content]);
        $this->createRevision($article, $revision->content, "Restored from version {$revision->version}");

        return $article->fresh();
    }

    // ==========================================
    // VIEW TRACKING
    // ==========================================

    public function recordView(
        BlogArticle $article,
        ?int $userId = null,
        ?string $sessionId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $referrer = null
    ): void {
        // Check for recent view from same user/session to avoid duplicate counting
        $recentView = BlogArticleView::where('article_id', $article->id)
            ->where(function ($query) use ($userId, $sessionId, $ipAddress) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } elseif ($sessionId) {
                    $query->where('session_id', $sessionId);
                } else {
                    $query->where('ip_address', $ipAddress);
                }
            })
            ->where('viewed_at', '>=', now()->subMinutes(30))
            ->exists();

        if (!$recentView) {
            BlogArticleView::create([
                'article_id' => $article->id,
                'user_id' => $userId,
                'session_id' => $sessionId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'referrer' => $referrer,
                'viewed_at' => now(),
            ]);

            $article->increment('view_count');
        }
    }

    public function getViewStats(BlogArticle $article, ?string $period = '30d'): array
    {
        $startDate = match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            '1y' => now()->subYear(),
            default => now()->subDays(30),
        };

        $views = BlogArticleView::where('article_id', $article->id)
            ->where('viewed_at', '>=', $startDate)
            ->selectRaw('DATE(viewed_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'total_views' => $article->view_count,
            'period_views' => $views->sum('count'),
            'unique_visitors' => BlogArticleView::where('article_id', $article->id)
                ->where('viewed_at', '>=', $startDate)
                ->distinct('ip_address')
                ->count(),
            'daily_views' => $views->pluck('count', 'date')->toArray(),
        ];
    }

    // ==========================================
    // CATEGORY MANAGEMENT
    // ==========================================

    public function createCategory(int $tenantId, array $data): BlogCategory
    {
        return BlogCategory::create([
            'tenant_id' => $tenantId,
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    public function updateCategory(BlogCategory $category, array $data): BlogCategory
    {
        $category->update(array_filter($data, fn($value) => $value !== null));
        return $category->fresh();
    }

    public function deleteCategory(BlogCategory $category): bool
    {
        // Move articles to parent category or uncategorized
        BlogArticle::where('category_id', $category->id)
            ->update(['category_id' => $category->parent_id]);

        return $category->delete();
    }

    public function listCategories(int $tenantId, bool $activeOnly = false): Collection
    {
        $query = BlogCategory::where('tenant_id', $tenantId)
            ->withCount('articles')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    public function getCategoryTree(int $tenantId): Collection
    {
        $categories = $this->listCategories($tenantId, true);
        return $this->buildTree($categories);
    }

    protected function buildTree(Collection $categories, ?int $parentId = null): Collection
    {
        return $categories
            ->filter(fn($cat) => $cat->parent_id === $parentId)
            ->map(function ($category) use ($categories) {
                $category->children = $this->buildTree($categories, $category->id);
                return $category;
            });
    }

    // ==========================================
    // TAG MANAGEMENT
    // ==========================================

    public function createTag(int $tenantId, array $data): BlogTag
    {
        return BlogTag::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'description' => $data['description'] ?? null,
        ]);
    }

    public function findOrCreateTags(int $tenantId, array $tagNames): array
    {
        $tagIds = [];

        foreach ($tagNames as $name) {
            $tag = BlogTag::firstOrCreate(
                ['tenant_id' => $tenantId, 'slug' => Str::slug($name)],
                ['name' => $name]
            );
            $tagIds[] = $tag->id;
        }

        return $tagIds;
    }

    public function listTags(int $tenantId): Collection
    {
        return BlogTag::where('tenant_id', $tenantId)
            ->withCount('articles')
            ->orderBy('name')
            ->get();
    }

    public function getPopularTags(int $tenantId, int $limit = 20): Collection
    {
        return BlogTag::where('tenant_id', $tenantId)
            ->withCount('articles')
            ->orderByDesc('articles_count')
            ->limit($limit)
            ->get();
    }

    // ==========================================
    // AUTHOR MANAGEMENT
    // ==========================================

    public function createAuthor(int $tenantId, array $data): BlogAuthor
    {
        return BlogAuthor::create([
            'tenant_id' => $tenantId,
            'user_id' => $data['user_id'] ?? null,
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'email' => $data['email'] ?? null,
            'bio' => $data['bio'] ?? null,
            'avatar' => $data['avatar'] ?? null,
            'website' => $data['website'] ?? null,
            'social_links' => $data['social_links'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function updateAuthor(BlogAuthor $author, array $data): BlogAuthor
    {
        $author->update(array_filter($data, fn($value) => $value !== null));
        return $author->fresh();
    }

    public function listAuthors(int $tenantId, bool $activeOnly = false): Collection
    {
        $query = BlogAuthor::where('tenant_id', $tenantId)
            ->withCount('articles')
            ->orderBy('name');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    // ==========================================
    // SERIES MANAGEMENT
    // ==========================================

    public function createSeries(int $tenantId, array $data): BlogSeries
    {
        return BlogSeries::create([
            'tenant_id' => $tenantId,
            'title' => $data['title'],
            'slug' => $data['slug'] ?? Str::slug($data['title']),
            'description' => $data['description'] ?? null,
            'featured_image' => $data['featured_image'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    public function listSeries(int $tenantId, bool $activeOnly = false): Collection
    {
        $query = BlogSeries::where('tenant_id', $tenantId)
            ->withCount('articles')
            ->orderBy('sort_order')
            ->orderBy('title');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    public function getSeriesArticles(BlogSeries $series): Collection
    {
        return $series->articles()
            ->where('status', 'published')
            ->orderBy('series_order')
            ->get();
    }

    // ==========================================
    // COMMENT MANAGEMENT
    // ==========================================

    public function createComment(BlogArticle $article, array $data): BlogComment
    {
        return BlogComment::create([
            'article_id' => $article->id,
            'user_id' => $data['user_id'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'author_name' => $data['author_name'] ?? null,
            'author_email' => $data['author_email'] ?? null,
            'content' => $data['content'],
            'status' => $data['status'] ?? 'pending',
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
        ]);
    }

    public function approveComment(BlogComment $comment): BlogComment
    {
        $comment->update(['status' => 'approved']);
        return $comment;
    }

    public function rejectComment(BlogComment $comment): BlogComment
    {
        $comment->update(['status' => 'rejected']);
        return $comment;
    }

    public function markCommentAsSpam(BlogComment $comment): BlogComment
    {
        $comment->update(['status' => 'spam']);
        return $comment;
    }

    public function deleteComment(BlogComment $comment): bool
    {
        return $comment->delete();
    }

    public function getArticleComments(
        BlogArticle $article,
        bool $approvedOnly = true
    ): Collection {
        $query = $article->comments()
            ->whereNull('parent_id')
            ->with(['replies' => function ($q) use ($approvedOnly) {
                if ($approvedOnly) {
                    $q->where('status', 'approved');
                }
                $q->orderBy('created_at');
            }])
            ->orderBy('created_at', 'desc');

        if ($approvedOnly) {
            $query->where('status', 'approved');
        }

        return $query->get();
    }

    public function getPendingComments(int $tenantId): Collection
    {
        return BlogComment::whereHas('article', fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'pending')
            ->with('article')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // ==========================================
    // SUBSCRIPTION MANAGEMENT
    // ==========================================

    public function subscribe(int $tenantId, string $email, array $data = []): BlogSubscription
    {
        return BlogSubscription::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'email' => $email,
            ],
            [
                'name' => $data['name'] ?? null,
                'status' => 'active',
                'subscribed_at' => now(),
                'preferences' => $data['preferences'] ?? null,
            ]
        );
    }

    public function unsubscribe(int $tenantId, string $email): bool
    {
        return BlogSubscription::where('tenant_id', $tenantId)
            ->where('email', $email)
            ->update([
                'status' => 'unsubscribed',
                'unsubscribed_at' => now(),
            ]) > 0;
    }

    public function getActiveSubscribers(int $tenantId): Collection
    {
        return BlogSubscription::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->get();
    }

    // ==========================================
    // UTILITY METHODS
    // ==========================================

    protected function calculateReadingTime(string $content): int
    {
        $wordCount = str_word_count(strip_tags($content));
        $wordsPerMinute = 200;

        return max(1, (int) ceil($wordCount / $wordsPerMinute));
    }

    public function generateUniqueSlug(int $tenantId, string $title, ?int $excludeId = null): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        while (BlogArticle::where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists()
        ) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    public function getStats(int $tenantId): array
    {
        return [
            'total_articles' => BlogArticle::where('tenant_id', $tenantId)->count(),
            'published_articles' => BlogArticle::where('tenant_id', $tenantId)
                ->where('status', 'published')->count(),
            'draft_articles' => BlogArticle::where('tenant_id', $tenantId)
                ->where('status', 'draft')->count(),
            'total_views' => BlogArticle::where('tenant_id', $tenantId)->sum('view_count'),
            'total_comments' => BlogComment::whereHas('article',
                fn($q) => $q->where('tenant_id', $tenantId))->count(),
            'pending_comments' => BlogComment::whereHas('article',
                fn($q) => $q->where('tenant_id', $tenantId))
                ->where('status', 'pending')->count(),
            'total_subscribers' => BlogSubscription::where('tenant_id', $tenantId)
                ->where('status', 'active')->count(),
            'total_categories' => BlogCategory::where('tenant_id', $tenantId)->count(),
            'total_tags' => BlogTag::where('tenant_id', $tenantId)->count(),
            'total_authors' => BlogAuthor::where('tenant_id', $tenantId)->count(),
        ];
    }
}
