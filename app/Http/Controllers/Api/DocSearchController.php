<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Documentation\DocSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocSearchController extends Controller
{
    public function __construct(
        protected DocSearchService $searchService
    ) {}

    public function autocomplete(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = min((int) $request->get('limit', 10), 20);

        $results = $this->searchService->autocomplete($query, $limit);

        return response()->json([
            'success' => true,
            'data' => $results,
            'query' => $query,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $perPage = min((int) $request->get('per_page', 15), 50);

        $results = $this->searchService->search($query, true, $perPage);

        return response()->json([
            'success' => true,
            'data' => $results->items(),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
            'query' => $query,
        ]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $suggestions = $this->searchService->getSuggestions($query);

        return response()->json([
            'success' => true,
            'data' => $suggestions,
        ]);
    }
}
