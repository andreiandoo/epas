<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TixelloWidgetToken;
use App\Services\Widget\TixelloWidgetStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API-ul widget-ului de Android (vezi `tixello-widget-android/`).
 *
 * Un singur endpoint util: `summary`. Telefonul îl cheamă la fiecare poll şi
 * primeşte tot ce-i trebuie — cifrele, ultimele comisioane şi cele care sunt
 * noi faţă de ce ştia el. Un singur request per poll ţine bateria.
 */
class TixelloWidgetController extends Controller
{
    public function __construct(private readonly TixelloWidgetStatsService $stats)
    {
    }

    /**
     * Verificare de token pentru ecranul de configurare din aplicaţie.
     */
    public function ping(Request $request): JsonResponse
    {
        $token = $request->attributes->get('tixello_widget_token');

        return response()->json([
            'success' => true,
            'server_time' => now()->toIso8601String(),
            'token_name' => $token instanceof TixelloWidgetToken ? $token->name : null,
            'poll_interval_seconds' => (int) config('tixello-widget.poll_interval_seconds', 60),
        ]);
    }

    /**
     * Cifrele + ultimele comisioane.
     *
     * `since_commission_id` e cursorul telefonului: tot ce e mai nou decât el
     * iese separat în `new_commissions` şi declanşează alerta. La prima
     * pornire aplicaţia îl omite, ca să nu sune pentru tot istoricul.
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'since_commission_id' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:' . (int) config('tixello-widget.commissions_max_limit', 50)],
        ]);

        $payload = $this->stats->payload(
            isset($validated['since_commission_id']) ? (int) $validated['since_commission_id'] : null,
            isset($validated['limit']) ? (int) $validated['limit'] : null,
        );

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }
}
