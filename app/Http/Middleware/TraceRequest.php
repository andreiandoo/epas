<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TraceRequest
{
    public function handle(Request $request, Closure $next)
    {
        // Only trace admin routes
        if (!str_starts_with($request->path(), 'admin')) {
            return $next($request);
        }

        Log::info('=== REQUEST START ===', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'path' => $request->path(),
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'user_role' => auth()->user()?->role,
            'is_authenticated' => auth()->check(),
        ]);

        try {
            $response = $next($request);

            Log::info('=== REQUEST COMPLETE ===', [
                'status' => $response->getStatusCode(),
                'path' => $request->path(),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::error('=== REQUEST EXCEPTION ===', [
                'path' => $request->path(),
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e;
        }
    }
}
