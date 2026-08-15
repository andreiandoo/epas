<?php

namespace App\Http\Middleware;

use App\Models\TixelloWidgetToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentificare pentru widget-ul de Android (`tixello.widget`).
 *
 * Bearer token din `tixello_widget_tokens`. Endpoint-ul din spate întoarce
 * cifrele întregii platforme, deci middleware-ul ăsta e singura poartă —
 * nu există fallback pe sesiune sau pe cheie de marketplace.
 */
class AuthenticateTixelloWidget
{
    /* Cât ţinem în cache rezultatul lookup-ului. Widget-ul bate des; fără
       cache fiecare poll ar face un SELECT pe token. */
    private const CACHE_TTL_SECONDS = 300;

    /* `last_used_at` se scrie cel mult o dată pe minut per token — altfel
       fiecare poll ar fi un UPDATE inutil. */
    private const TOUCH_INTERVAL_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $plain = $this->extractToken($request);

        if ($plain === null || $plain === '') {
            return $this->unauthorized('Token lipsă.');
        }

        $hash = TixelloWidgetToken::hash($plain);

        /* Cache-uim ID-ul, nu modelul: revocarea trebuie să prindă efect
           în cel mult CACHE_TTL_SECONDS, iar un model serializat ar duce
           mai departe şi un `revoked_at` vechi. */
        $tokenId = Cache::remember(
            "tixello_widget_token:{$hash}",
            self::CACHE_TTL_SECONDS,
            fn () => TixelloWidgetToken::findValid($plain)?->id ?? 0
        );

        if (! $tokenId) {
            Log::warning('[TixelloWidget] token respins', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return $this->unauthorized('Token invalid sau revocat.');
        }

        $token = TixelloWidgetToken::find($tokenId);

        if (! $token || ! $token->isUsable()) {
            Cache::forget("tixello_widget_token:{$hash}");

            return $this->unauthorized('Token invalid sau revocat.');
        }

        $this->touch($token, $request);

        $request->attributes->set('tixello_widget_token', $token);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if ($header && str_starts_with($header, 'Bearer ')) {
            return trim(substr($header, 7));
        }

        return $request->header('X-Widget-Token');
    }

    private function touch(TixelloWidgetToken $token, Request $request): void
    {
        $lock = "tixello_widget_token_touch:{$token->id}";

        if (Cache::get($lock)) {
            return;
        }

        Cache::put($lock, 1, self::TOUCH_INTERVAL_SECONDS);

        /* Timestamps oprite: un simplu poll nu trebuie să mişte `updated_at`. */
        $token->timestamps = false;
        $token->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $request->ip(),
        ])->saveQuietly();
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 401);
    }
}
