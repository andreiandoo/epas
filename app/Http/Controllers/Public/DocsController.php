<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Documentation\DocService;
use App\Services\Documentation\DocSearchService;
use Illuminate\Http\Request;

class DocsController extends Controller
{
    public function __construct(
        protected DocService $docService,
        protected DocSearchService $searchService
    ) {}

    public function index(Request $request)
    {
        $featured = $this->docService->getFeaturedDocs();
        $categories = $this->docService->getPublicCategories();
        $tableOfContents = $this->docService->getTableOfContents();

        return view('docs.index', compact('featured', 'categories', 'tableOfContents'));
    }

    public function show(string $slug)
    {
        $doc = $this->docService->getDocBySlug($slug);

        if (!$doc) {
            abort(404);
        }

        $related = $this->docService->getRelatedDocs($doc);
        $tableOfContents = $this->docService->getTableOfContents();

        return view('docs.show', compact('doc', 'related', 'tableOfContents'));
    }

    public function category(string $categorySlug, Request $request)
    {
        $docs = $this->docService->getDocsByCategory($categorySlug);
        $categories = $this->docService->getPublicCategories();
        $currentCategory = $categories->firstWhere('slug', $categorySlug);

        if (!$currentCategory) {
            abort(404);
        }

        return view('docs.category', compact('docs', 'categories', 'currentCategory'));
    }

    public function search(Request $request)
    {
        $query = $request->get('q', '');
        $results = $query ? $this->searchService->search($query) : collect();
        $categories = $this->docService->getPublicCategories();

        return view('docs.search', compact('results', 'query', 'categories'));
    }
}
