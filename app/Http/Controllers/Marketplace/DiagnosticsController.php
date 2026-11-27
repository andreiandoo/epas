<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceAdmin;
use App\Models\SystemError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Diagnostic doar pentru super-admin, doar citire:
 * - textele netraduse adunate de colector (storage/logs/i18n-missing-{locale}.log);
 * - ultimele erori înregistrate pentru o adresă din panou.
 */
class DiagnosticsController extends Controller
{
    public function i18nMissing(Request $request, string $locale): JsonResponse
    {
        $this->authorizeSuperAdmin();

        if (! in_array($locale, (array) config('marketplace_locales.enabled', []), true)) {
            abort(404);
        }

        $path = storage_path('logs/i18n-missing-' . $locale . '.log');
        $lines = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        $keys = array_values(array_unique(array_filter(array_map(fn ($l) => json_decode($l, true), $lines), 'is_string')));

        $offset = max(0, (int) $request->query('offset', 0));
        $limit = min(1000, max(1, (int) $request->query('limit', 500)));

        return response()->json([
            'locale' => $locale,
            'total' => count($keys),
            'offset' => $offset,
            'keys' => array_slice($keys, $offset, $limit),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function errors(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $q = trim((string) $request->query('q', ''));
        if ($q === '' || strlen($q) > 200) {
            abort(422);
        }

        $rows = SystemError::query()
            ->where('request_url', 'like', '%' . $q . '%')
            ->orderByDesc('id')
            ->limit(min(10, max(1, (int) $request->query('limit', 3))))
            ->get(['id', 'created_at', 'exception_class', 'message', 'exception_file', 'exception_line', 'stack_trace', 'request_url']);

        return response()->json($rows->map(fn ($e) => [
            'id' => $e->id,
            'at' => optional($e->created_at)->toDateTimeString(),
            'url' => strtok((string) $e->request_url, '?'),
            'class' => $e->exception_class,
            'message' => mb_strimwidth((string) $e->message, 0, 600, '…'),
            'file' => $e->exception_file,
            'line' => $e->exception_line,
            // doar cadrele din cod propriu (fără vendor), primele 12
            'trace' => collect(preg_split('/\R/', (string) $e->stack_trace))
                ->filter(fn ($l) => str_contains($l, '/app/') || str_contains($l, '/resources/views/'))
                ->take(12)
                ->values(),
        ]), 200, [], JSON_UNESCAPED_UNICODE);
    }

    private function authorizeSuperAdmin(): void
    {
        $admin = Auth::guard('marketplace_admin')->user();
        if (! $admin instanceof MarketplaceAdmin || ! ($admin->isSuperAdmin() || session('marketplace_is_super_admin'))) {
            abort(403);
        }
    }
}
