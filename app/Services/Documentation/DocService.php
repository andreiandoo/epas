<?php

namespace App\Services\Documentation;

use App\Models\Doc;
use App\Models\DocCategory;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class DocService
{
    public function getPublicDocs(int $perPage = 15): LengthAwarePaginator
    {
        return Doc::public()
            ->with('category')
            ->ordered()
            ->paginate($perPage);
    }

    public function getDocsByCategory(string $categorySlug, int $perPage = 15): LengthAwarePaginator
    {
        return Doc::public()
            ->whereHas('category', fn($q) => $q->where('slug', $categorySlug))
            ->with('category')
            ->ordered()
            ->paginate($perPage);
    }

    public function getDocsByType(string $type, int $perPage = 15): LengthAwarePaginator
    {
        return Doc::public()
            ->ofType($type)
            ->with('category')
            ->ordered()
            ->paginate($perPage);
    }

    public function getDocBySlug(string $slug): ?Doc
    {
        return Doc::public()
            ->where('slug', $slug)
            ->with(['category', 'children', 'parent'])
            ->first();
    }

    public function getFeaturedDocs(int $limit = 6): Collection
    {
        return Doc::featured()
            ->with('category')
            ->limit($limit)
            ->get();
    }

    public function getPublicCategories(): Collection
    {
        return DocCategory::public()
            ->withCount(['docs' => fn($q) => $q->public()])
            ->ordered()
            ->get();
    }

    public function getTableOfContents(): Collection
    {
        return DocCategory::public()
            ->with(['docs' => fn($q) => $q->public()->ordered()])
            ->ordered()
            ->get();
    }

    public function getRelatedDocs(Doc $doc, int $limit = 4): Collection
    {
        return Doc::public()
            ->where('id', '!=', $doc->id)
            ->where(function ($query) use ($doc) {
                $query->where('doc_category_id', $doc->doc_category_id)
                    ->orWhere('type', $doc->type);
            })
            ->limit($limit)
            ->get();
    }

    public function incrementVersion(Doc $doc): Doc
    {
        $parts = explode('.', $doc->version);
        $parts[count($parts) - 1] = (int) end($parts) + 1;
        $doc->version = implode('.', $parts);
        return $doc;
    }
}
