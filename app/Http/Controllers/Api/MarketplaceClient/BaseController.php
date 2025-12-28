<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceClient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

abstract class BaseController extends Controller
{
    /**
     * Get marketplace client from request
     */
    protected function getClient(Request $request): ?MarketplaceClient
    {
        return $request->attributes->get('marketplace_client');
    }

    /**
     * Require authenticated marketplace client
     */
    protected function requireClient(Request $request): MarketplaceClient
    {
        $client = $this->getClient($request);

        if (!$client) {
            abort(401, 'Marketplace client authentication required');
        }

        if (!$client->isActive()) {
            abort(403, 'Marketplace client account is not active');
        }

        return $client;
    }

    /**
     * Standard success response
     */
    protected function success(mixed $data = null, string $message = null, int $code = 200): JsonResponse
    {
        $response = ['success' => true];

        if ($message) {
            $response['message'] = $message;
        }

        if (!is_null($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Standard error response
     */
    protected function error(string $message, int $code = 400, array $errors = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Paginated response
     */
    protected function paginated($paginator, array $meta = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => array_merge([
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ], $meta),
        ]);
    }
}
