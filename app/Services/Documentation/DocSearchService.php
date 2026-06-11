<?php

namespace App\Services\Documentation;

use App\Models\Doc;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class DocSearchService
{
    public function search(string $query, bool $publicOnly = true, int $perPage = 15): LengthAwarePaginator
    {
        $builder = Doc::with('category')
            ->search($query)
            ->ordered();

        if ($publicOnly) {
            $builder->public();
        }

        return $builder->paginate($perPage);
    }

    public function autocomplete(string $query, int $limit = 10): Collection
    {
        if (strlen($query) < 2) {
            return collect();
        }

        return Doc::public()
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('excerpt', 'like', "%{$query}%");
            })
            ->select(['id', 'title', 'slug', 'excerpt', 'type', 'doc_category_id'])
            ->with('category:id,name,slug,icon')
            ->limit($limit)
            ->get()
            ->map(function ($doc) use ($query) {
                return [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'slug' => $doc->slug,
                    'excerpt' => $this->highlightMatch($doc->excerpt ?? '', $query),
                    'type' => $doc->type,
                    'type_label' => $doc->getTypeLabel(),
                    'category' => [
                        'name' => $doc->category->name,
                        'slug' => $doc->category->slug,
                        'icon' => $doc->category->icon,
                    ],
                    'url' => route('docs.show', $doc->slug),
                ];
            });
    }

    public function searchByTags(array $tags, bool $publicOnly = true): Collection
    {
        $builder = Doc::with('category');

        if ($publicOnly) {
            $builder->public();
        }

        foreach ($tags as $tag) {
            $builder->whereJsonContains('tags', $tag);
        }

        return $builder->ordered()->get();
    }

    public function getPopularSearches(int $limit = 10): Collection
    {
        // This could be enhanced with actual search analytics
        return Doc::public()
            ->select('title', 'slug')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    public function getSuggestions(string $query): Collection
    {
        // Get suggestions based on existing doc titles
        return Doc::public()
            ->where('title', 'like', "{$query}%")
            ->pluck('title')
            ->take(5);
    }

    protected function highlightMatch(string $text, string $query): string
    {
        if (empty($text) || empty($query)) {
            return $text;
        }

        $excerpt = strip_tags($text);

        // Find position of query in text
        $pos = stripos($excerpt, $query);
        if ($pos === false) {
            return \Str::limit($excerpt, 100);
        }

        // Extract context around the match
        $start = max(0, $pos - 40);
        $length = 100;
        $excerpt = substr($excerpt, $start, $length);

        if ($start > 0) {
            $excerpt = '...' . $excerpt;
        }

        return $excerpt . '...';
    }
}
