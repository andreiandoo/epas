<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\KnowledgeBase\KbArticle;
use App\Models\KnowledgeBase\KbCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class KnowledgeBaseController extends BaseController
{
    /**
     * Get all KB categories for the marketplace client
     */
    public function categories(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $language = $client->language ?? 'ro';

        $categories = KbCategory::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($category) use ($language) {
                return [
                    'id' => $category->id,
                    'name' => $category->getTranslation('name', $language),
                    'slug' => $category->slug,
                    'description' => $category->getTranslation('description', $language),
                    'icon' => $category->icon,
                    'color' => $category->color,
                    'image_url' => $category->image_url,
                    'article_count' => $category->article_count,
                    'sort_order' => $category->sort_order ?? 0,
                ];
            });

        return $this->success([
            'categories' => $categories,
        ]);
    }

    /**
     * Get a single category by slug with its articles
     */
    public function category(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);
        $language = $client->language ?? 'ro';

        $category = KbCategory::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->where('slug', $slug)
            ->first();

        if (!$category) {
            return $this->error('Category not found', 404);
        }

        $articles = KbArticle::query()
            ->where('marketplace_client_id', $client->id)
            ->where('kb_category_id', $category->id)
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($article) use ($language) {
                return $this->formatArticle($article, $language);
            });

        return $this->success([
            'category' => [
                'id' => $category->id,
                'name' => $category->getTranslation('name', $language),
                'slug' => $category->slug,
                'description' => $category->getTranslation('description', $language),
                'icon' => $category->icon,
                'color' => $category->color,
                'image_url' => $category->image_url,
                'article_count' => $category->article_count,
            ],
            'articles' => $articles,
        ]);
    }

    /**
     * Get all articles
     */
    public function articles(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $language = $client->language ?? 'ro';

        $query = KbArticle::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('created_at', 'desc');

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('kb_category_id', $request->input('category_id'));
        }

        $articles = $query->get()->map(function ($article) use ($language) {
            return $this->formatArticle($article, $language, true);
        });

        return $this->success([
            'articles' => $articles,
        ]);
    }

    /**
     * Get a single article by slug
     */
    public function article(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);
        $language = $client->language ?? 'ro';

        $article = KbArticle::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->where('slug', $slug)
            ->with('category')
            ->first();

        if (!$article) {
            return $this->error('Article not found', 404);
        }

        // Increment view count
        $article->incrementViews();

        return $this->success([
            'article' => $this->formatArticle($article, $language, true, true),
        ]);
    }

    /**
     * Get featured articles
     */
    public function featured(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $language = $client->language ?? 'ro';

        $articles = KbArticle::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->where('is_featured', true)
            ->with('category')
            ->orderBy('sort_order')
            ->limit($request->input('limit', 5))
            ->get()
            ->map(function ($article) use ($language) {
                return $this->formatArticle($article, $language, true);
            });

        return $this->success([
            'articles' => $articles,
        ]);
    }

    /**
     * Get popular articles
     */
    public function popular(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $language = $client->language ?? 'ro';

        $articles = KbArticle::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->where('is_popular', true)
            ->with('category')
            ->orderByDesc('view_count')
            ->limit($request->input('limit', 10))
            ->get()
            ->map(function ($article) use ($language) {
                return $this->formatArticle($article, $language, true);
            });

        return $this->success([
            'articles' => $articles,
        ]);
    }

    /**
     * Get all FAQs
     */
    public function faqs(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $language = $client->language ?? 'ro';

        $query = KbArticle::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->where('type', 'faq')
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('created_at', 'desc');

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('kb_category_id', $request->input('category_id'));
        }

        $faqs = $query->get()->map(function ($article) use ($language) {
            return $this->formatArticle($article, $language, true);
        });

        return $this->success([
            'faqs' => $faqs,
        ]);
    }

    /**
     * Search articles
     */
    public function search(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $language = $client->language ?? 'ro';
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return $this->error('Search query must be at least 2 characters', 400);
        }

        $articles = KbArticle::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->where(function ($q) use ($query, $language) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(title, '$.{$language}')) LIKE ?", ["%{$query}%"])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(content, '$.{$language}')) LIKE ?", ["%{$query}%"])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(question, '$.{$language}')) LIKE ?", ["%{$query}%"]);
            })
            ->with('category')
            ->orderByDesc('view_count')
            ->limit(20)
            ->get()
            ->map(function ($article) use ($language) {
                return $this->formatArticle($article, $language, true);
            });

        return $this->success([
            'query' => $query,
            'results' => $articles,
            'total' => count($articles),
        ]);
    }

    /**
     * Vote on article helpfulness
     */
    public function vote(Request $request, int $id): JsonResponse
    {
        $client = $this->requireClient($request);

        $article = KbArticle::query()
            ->where('marketplace_client_id', $client->id)
            ->where('id', $id)
            ->first();

        if (!$article) {
            return $this->error('Article not found', 404);
        }

        $helpful = $request->boolean('helpful');

        if ($helpful) {
            $article->markHelpful();
        } else {
            $article->markNotHelpful();
        }

        return $this->success([
            'message' => 'Vote recorded',
            'helpful_count' => $article->helpful_count,
            'not_helpful_count' => $article->not_helpful_count,
            'helpfulness_score' => $article->helpfulness_score,
        ]);
    }

    /**
     * Format article for API response
     */
    protected function formatArticle(KbArticle $article, string $language, bool $includeCategory = false, bool $includeContent = false): array
    {
        $data = [
            'id' => $article->id,
            'type' => $article->type,
            'slug' => $article->slug,
            'icon' => $article->icon,
            'is_featured' => $article->is_featured,
            'is_popular' => $article->is_popular,
            'view_count' => $article->view_count,
            'helpfulness_score' => $article->helpfulness_score,
            'tags' => $article->tags,
            'updated_at' => $article->updated_at?->toIso8601String(),
        ];

        if ($article->type === 'faq') {
            $data['question'] = $article->getTranslation('question', $language);
            if ($includeContent) {
                $data['answer'] = $article->getTranslation('content', $language);
            }
        } else {
            $data['title'] = $article->getTranslation('title', $language);
            if ($includeContent) {
                $data['content'] = $article->getTranslation('content', $language);
                $data['meta_title'] = $article->getTranslation('meta_title', $language);
                $data['meta_description'] = $article->getTranslation('meta_description', $language);
            }
        }

        if ($includeCategory && $article->category) {
            $data['category'] = [
                'id' => $article->category->id,
                'name' => $article->category->getTranslation('name', $language),
                'slug' => $article->category->slug,
                'icon' => $article->category->icon,
                'color' => $article->category->color,
            ];
        }

        return $data;
    }
}
