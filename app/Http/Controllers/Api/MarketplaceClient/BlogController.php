<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Http\Controllers\Controller;
use App\Models\Blog\BlogArticle;
use App\Models\Blog\BlogCategory;
use App\Models\MarketplaceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    private function getClient(Request $request): ?MarketplaceClient
    {
        return $request->attributes->get('marketplace_client');
    }

    private function resolveLanguage(MarketplaceClient $client): string
    {
        return $client->language ?? $client->locale ?? 'en';
    }

    private function translate(mixed $value, string $lang): string
    {
        if (is_array($value)) {
            return $value[$lang] ?? $value['en'] ?? array_values($value)[0] ?? '';
        }
        return $value ?? '';
    }

    /**
     * GET /api/marketplace-client/blog-articles
     */
    public function articles(Request $request): JsonResponse
    {
        $client = $this->getClient($request);
        if (!$client) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $lang     = $this->resolveLanguage($client);
        $perPage  = max(1, min(50, (int) $request->query('per_page', 12)));
        $page     = max(1, (int) $request->query('page', 1));
        $status   = $request->query('status', 'published');
        $category = $request->query('category');

        $query = BlogArticle::where('marketplace_client_id', $client->id)
            ->with(['category']);

        if ($status === 'published') {
            $query->where('status', 'published')
                  ->where(function ($q) {
                      $q->whereNull('published_at')->orWhere('published_at', '<=', now());
                  });
        } elseif ($status) {
            $query->where('status', $status);
        }

        if ($category) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $category));
        }

        $total      = $query->count();
        $lastPage   = max(1, (int) ceil($total / $perPage));
        $articles   = $query->orderBy('published_at', 'desc')
                            ->orderBy('created_at', 'desc')
                            ->skip(($page - 1) * $perPage)
                            ->take($perPage)
                            ->with(['event'])
                            ->get();

        $data = $articles->map(fn ($a) => $this->formatArticle($a, $lang))->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'last_page'    => $lastPage,
                'per_page'     => $perPage,
                'total'        => $total,
            ],
        ]);
    }

    /**
     * GET /api/marketplace-client/blog-articles/{slug}
     */
    public function article(Request $request, string $slug): JsonResponse
    {
        $client = $this->getClient($request);
        if (!$client) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $lang    = $this->resolveLanguage($client);
        $article = BlogArticle::where('marketplace_client_id', $client->id)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with(['category', 'event'])
            ->first();

        if (!$article) {
            return response()->json(['success' => false, 'message' => 'Article not found'], 404);
        }

        $article->incrementViewCount();

        return response()->json([
            'data' => $this->formatArticle($article, $lang, true),
        ]);
    }

    /**
     * GET /api/marketplace-client/blog-categories
     */
    public function categories(Request $request): JsonResponse
    {
        $client = $this->getClient($request);
        if (!$client) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $lang = $this->resolveLanguage($client);

        $categories = BlogCategory::where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($c) => [
                'id'   => $c->id,
                'slug' => $c->slug,
                'name' => $this->translate($c->name, $lang),
                'icon' => $c->icon ?? '',
            ])
            ->values();

        return response()->json(['data' => $categories]);
    }

    private function formatArticle(BlogArticle $article, string $lang, bool $withContent = false): array
    {
        $catName  = null;
        $catSlug  = null;
        if ($article->category) {
            $catName = $this->translate($article->category->name, $lang);
            $catSlug = $article->category->slug;
        }

        $eventSlug  = null;
        $eventTitle = null;
        if ($article->event) {
            $eventSlug  = $article->event->slug;
            $eventTitle = $this->translate($article->event->title, $lang);
        }

        $data = [
            'slug'        => $article->slug,
            'title'       => $this->translate($article->title, $lang),
            'excerpt'     => $this->translate($article->excerpt, $lang),
            'image_url'   => $article->featured_image_url,
            'read_time'   => $article->reading_time_minutes ?? 5,
            'category'    => [
                'name' => $catName ?? '',
                'slug' => $catSlug ?? '',
            ],
            'author'      => [
                'name'   => 'Redacția TICS',
                'avatar' => '',
            ],
            'published_at' => $article->published_at?->toISOString(),
            'created_at'   => $article->created_at?->toISOString(),
            'is_featured'  => (bool) $article->is_featured,
            'view_count'   => (int) ($article->view_count ?? 0),
            'event'        => $eventSlug ? [
                'slug'  => $eventSlug,
                'title' => $eventTitle ?? '',
            ] : null,
        ];

        if ($withContent) {
            $data['content'] = $this->translate($article->content, $lang);
        }

        return $data;
    }
}
