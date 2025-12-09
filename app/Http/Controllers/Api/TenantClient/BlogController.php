<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Blog\BlogArticle;
use App\Models\Blog\BlogCategory;
use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    /**
     * Resolve tenant from request (hostname preferred, ID fallback)
     */
    private function resolveTenant(Request $request): ?Tenant
    {
        $hostname = $request->query('hostname');
        $tenantId = $request->query('tenant');

        if ($hostname) {
            $domain = Domain::where('domain', $hostname)
                ->where('is_active', true)
                ->first();

            if (!$domain) {
                return null;
            }

            return $domain->tenant;
        }

        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        return null;
    }

    /**
     * Check if tenant has blog microservice active
     */
    private function hasBlogMicroservice(Tenant $tenant): bool
    {
        return $tenant->microservices()
            ->where('slug', 'blog')
            ->wherePivot('is_active', true)
            ->exists();
    }

    /**
     * List published blog articles for the tenant
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        if (!$this->hasBlogMicroservice($tenant)) {
            return response()->json([
                'success' => false,
                'message' => 'Blog is not enabled for this tenant',
            ], 403);
        }

        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';
        $category = $request->query('category');
        $page = $request->query('page', 1);
        $perPage = $request->query('per_page', 12);
        $featured = $request->boolean('featured');

        $query = BlogArticle::where('tenant_id', $tenant->id)
            ->published()
            ->with(['category', 'author']);

        // Category filter
        if ($category) {
            $query->whereHas('category', function ($q) use ($category) {
                $q->where('slug', $category);
            });
        }

        // Featured filter
        if ($featured) {
            $query->featured();
        }

        $total = $query->count();

        $articles = $query->orderBy('published_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $formattedArticles = $articles->map(function ($article) use ($tenantLanguage) {
            return $this->formatArticle($article, $tenantLanguage);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'articles' => $formattedArticles,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => (int) $page,
                    'last_page' => (int) ceil($total / $perPage),
                ],
            ],
        ]);
    }

    /**
     * Get a single blog article by slug
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        if (!$this->hasBlogMicroservice($tenant)) {
            return response()->json([
                'success' => false,
                'message' => 'Blog is not enabled for this tenant',
            ], 403);
        }

        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        $article = BlogArticle::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->published()
            ->with(['category', 'author', 'tags'])
            ->first();

        if (!$article) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found',
            ], 404);
        }

        // Increment view count
        $article->incrementViewCount();

        // Get related articles (same category, excluding current)
        $relatedArticles = BlogArticle::where('tenant_id', $tenant->id)
            ->where('id', '!=', $article->id)
            ->where('category_id', $article->category_id)
            ->published()
            ->orderBy('published_at', 'desc')
            ->take(3)
            ->get()
            ->map(function ($related) use ($tenantLanguage) {
                return $this->formatArticle($related, $tenantLanguage, false);
            });

        return response()->json([
            'success' => true,
            'data' => [
                'article' => $this->formatArticle($article, $tenantLanguage, true),
                'related' => $relatedArticles,
            ],
        ]);
    }

    /**
     * List blog categories for the tenant
     */
    public function categories(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        if (!$this->hasBlogMicroservice($tenant)) {
            return response()->json([
                'success' => false,
                'message' => 'Blog is not enabled for this tenant',
            ], 403);
        }

        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        $categories = BlogCategory::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->withCount(['articles' => function ($q) {
                $q->published();
            }])
            ->orderBy('sort_order')
            ->get()
            ->map(function ($category) use ($tenantLanguage) {
                $name = is_array($category->name)
                    ? ($category->name[$tenantLanguage] ?? $category->name['en'] ?? array_values($category->name)[0] ?? '')
                    : $category->name;

                $description = is_array($category->description)
                    ? ($category->description[$tenantLanguage] ?? $category->description['en'] ?? '')
                    : ($category->description ?? '');

                return [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'name' => $name,
                    'description' => $description,
                    'articles_count' => $category->articles_count,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
            ],
        ]);
    }

    /**
     * Format article for API response
     */
    private function formatArticle(BlogArticle $article, string $language, bool $includeContent = false): array
    {
        $getTranslation = function ($field) use ($article, $language) {
            $value = $article->{$field};
            if (is_array($value)) {
                return $value[$language] ?? $value['en'] ?? array_values($value)[0] ?? '';
            }
            return $value ?? '';
        };

        $categoryName = null;
        if ($article->category) {
            $catName = $article->category->name;
            $categoryName = is_array($catName)
                ? ($catName[$language] ?? $catName['en'] ?? array_values($catName)[0] ?? '')
                : $catName;
        }

        $authorName = $article->author?->name ?? null;

        $data = [
            'id' => $article->id,
            'slug' => $article->slug,
            'title' => $getTranslation('title'),
            'subtitle' => $getTranslation('subtitle'),
            'excerpt' => $getTranslation('excerpt'),
            'featured_image' => $article->featured_image_url,
            'featured_image_alt' => $article->featured_image_alt,
            'category' => $categoryName,
            'category_slug' => $article->category?->slug,
            'author' => $authorName,
            'published_at' => $article->published_at?->toISOString(),
            'reading_time' => $article->reading_time_minutes,
            'view_count' => $article->view_count ?? 0,
            'is_featured' => $article->is_featured,
        ];

        if ($includeContent) {
            $data['content'] = $getTranslation('content');
            $data['content_html'] = $getTranslation('content_html');
            $data['meta_title'] = $getTranslation('meta_title');
            $data['meta_description'] = $getTranslation('meta_description');
            $data['og_title'] = $getTranslation('og_title');
            $data['og_description'] = $getTranslation('og_description');
            $data['og_image'] = $article->og_image_url;
            $data['tags'] = $article->tags->pluck('name')->toArray();
        }

        return $data;
    }
}
