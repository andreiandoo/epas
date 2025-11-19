<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Standardized API Response Wrapper
 *
 * Provides consistent API response format with:
 * - Success/error indicators
 * - Metadata
 * - Pagination support
 * - Error details
 * - Rate limit information
 * - Request tracking
 */
class ApiResponse
{
    /**
     * Create a successful response
     *
     * @param mixed $data Response data
     * @param string|null $message Optional success message
     * @param array $meta Additional metadata
     * @param int $statusCode HTTP status code
     * @return JsonResponse
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        array $meta = [],
        int $statusCode = 200
    ): JsonResponse {
        $response = [
            'success' => true,
            'data' => $data,
        ];

        if ($message) {
            $response['message'] = $message;
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        // Add request ID for tracking
        $response['request_id'] = request()->id();

        // Add timestamp
        $response['timestamp'] = now()->toIso8601String();

        return response()->json($response, $statusCode);
    }

    /**
     * Create an error response
     *
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param array $errors Detailed error information
     * @param string|null $errorCode Application-specific error code
     * @return JsonResponse
     */
    public static function error(
        string $message,
        int $statusCode = 400,
        array $errors = [],
        ?string $errorCode = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $errorCode ?? self::getDefaultErrorCode($statusCode),
            ],
        ];

        if (!empty($errors)) {
            $response['error']['details'] = $errors;
        }

        // Add request ID for debugging
        $response['request_id'] = request()->id();

        // Add timestamp
        $response['timestamp'] = now()->toIso8601String();

        // Add documentation link for errors
        $response['docs'] = url('/docs#errors');

        return response()->json($response, $statusCode);
    }

    /**
     * Create a paginated response
     *
     * @param LengthAwarePaginator $paginator Laravel paginator
     * @param string|null $message Optional message
     * @param array $meta Additional metadata
     * @return JsonResponse
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        ?string $message = null,
        array $meta = []
    ): JsonResponse {
        $pagination = [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'has_more' => $paginator->hasMorePages(),
        ];

        // Add links
        $links = [
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
        ];

        if ($paginator->currentPage() > 1) {
            $links['prev'] = $paginator->previousPageUrl();
        }

        if ($paginator->hasMorePages()) {
            $links['next'] = $paginator->nextPageUrl();
        }

        $pagination['links'] = $links;

        return self::success(
            $paginator->items(),
            $message,
            array_merge($meta, ['pagination' => $pagination])
        );
    }

    /**
     * Create a collection response with metadata
     *
     * @param Collection|array $items Collection of items
     * @param string|null $message Optional message
     * @param array $meta Additional metadata
     * @return JsonResponse
     */
    public static function collection(
        Collection|array $items,
        ?string $message = null,
        array $meta = []
    ): JsonResponse {
        $items = $items instanceof Collection ? $items->all() : $items;

        return self::success(
            $items,
            $message,
            array_merge($meta, ['count' => count($items)])
        );
    }

    /**
     * Create a 404 not found response
     *
     * @param string $resource Resource name
     * @return JsonResponse
     */
    public static function notFound(string $resource = 'Resource'): JsonResponse
    {
        return self::error(
            "{$resource} not found",
            404,
            errorCode: 'RESOURCE_NOT_FOUND'
        );
    }

    /**
     * Create a validation error response
     *
     * @param array $errors Validation errors
     * @param string $message Main error message
     * @return JsonResponse
     */
    public static function validationError(
        array $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        return self::error(
            $message,
            422,
            $errors,
            'VALIDATION_ERROR'
        );
    }

    /**
     * Create an unauthorized response
     *
     * @param string $message Optional custom message
     * @return JsonResponse
     */
    public static function unauthorized(
        string $message = 'Unauthorized access'
    ): JsonResponse {
        return self::error(
            $message,
            401,
            errorCode: 'UNAUTHORIZED'
        );
    }

    /**
     * Create a forbidden response
     *
     * @param string $message Optional custom message
     * @return JsonResponse
     */
    public static function forbidden(
        string $message = 'Access forbidden'
    ): JsonResponse {
        return self::error(
            $message,
            403,
            errorCode: 'FORBIDDEN'
        );
    }

    /**
     * Create a rate limit exceeded response
     *
     * @param int $retryAfter Seconds until retry allowed
     * @return JsonResponse
     */
    public static function rateLimitExceeded(int $retryAfter = 3600): JsonResponse
    {
        return self::error(
            'Rate limit exceeded',
            429,
            ['retry_after' => $retryAfter],
            'RATE_LIMIT_EXCEEDED'
        )->header('Retry-After', $retryAfter);
    }

    /**
     * Create a server error response
     *
     * @param string $message Error message
     * @param \Throwable|null $exception Optional exception for logging
     * @return JsonResponse
     */
    public static function serverError(
        string $message = 'Internal server error',
        ?\Throwable $exception = null
    ): JsonResponse {
        if ($exception && config('app.debug')) {
            return self::error(
                $message,
                500,
                [
                    'exception' => get_class($exception),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => collect($exception->getTrace())->take(5)->toArray(),
                ],
                'INTERNAL_ERROR'
            );
        }

        return self::error(
            $message,
            500,
            errorCode: 'INTERNAL_ERROR'
        );
    }

    /**
     * Get default error code based on status code
     *
     * @param int $statusCode
     * @return string
     */
    protected static function getDefaultErrorCode(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            422 => 'VALIDATION_ERROR',
            429 => 'RATE_LIMIT_EXCEEDED',
            500 => 'INTERNAL_ERROR',
            503 => 'SERVICE_UNAVAILABLE',
            default => 'ERROR',
        };
    }

    /**
     * Add rate limit headers to response
     *
     * @param JsonResponse $response
     * @param int $limit Total allowed requests
     * @param int $remaining Remaining requests
     * @param int $resetIn Seconds until reset
     * @return JsonResponse
     */
    public static function withRateLimitHeaders(
        JsonResponse $response,
        int $limit,
        int $remaining,
        int $resetIn
    ): JsonResponse {
        return $response
            ->header('X-RateLimit-Limit', $limit)
            ->header('X-RateLimit-Remaining', $remaining)
            ->header('X-RateLimit-Reset', now()->addSeconds($resetIn)->timestamp);
    }

    /**
     * Add cache headers to response
     *
     * @param JsonResponse $response
     * @param int $maxAge Cache max age in seconds
     * @param bool $public Whether cache is public
     * @return JsonResponse
     */
    public static function withCacheHeaders(
        JsonResponse $response,
        int $maxAge,
        bool $public = false
    ): JsonResponse {
        $cacheControl = $public ? 'public' : 'private';
        $cacheControl .= ", max-age={$maxAge}";

        return $response
            ->header('Cache-Control', $cacheControl)
            ->header('ETag', md5($response->getContent()));
    }
}
