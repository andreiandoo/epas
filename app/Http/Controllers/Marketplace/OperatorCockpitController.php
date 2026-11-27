<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceAdmin;
use App\Services\Marketplace\OperatorNotificationJournal;
use App\Services\OperatorCockpit\AttentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OperatorCockpitController extends Controller
{
    public function poll(): JsonResponse
    {
        $service = $this->service();
        if (! $service) {
            return response()->json(['ok' => true, 'enabled' => false]);
        }

        $items = $service->snapshot();

        return response()->json([
            'ok' => true,
            'enabled' => true,
            'super' => $service->isSuper(),
            'items' => $items,
            'counts' => array_map('count', $items),
            'journal' => $this->journal(),
            'prefs' => $this->prefs(Auth::guard('marketplace_admin')->user()),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function claim(Request $request): JsonResponse
    {
        $service = $this->service();
        if (! $service) {
            return response()->json(['ok' => false], 403);
        }

        try {
            $result = $service->claim((string) $request->input('key'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json($result, $result['ok'] ? 200 : 409);
    }

    public function snooze(Request $request): JsonResponse
    {
        $service = $this->service();
        if (! $service) {
            return response()->json(['ok' => false], 403);
        }

        try {
            $service->snooze((string) $request->input('key'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Sunete per categorie și „Nu deranja”. Salvate pe operator, ca să-l urmeze pe orice calculator.
     */
    public function savePrefs(Request $request): JsonResponse
    {
        $admin = Auth::guard('marketplace_admin')->user();
        if (! $this->service() || ! $admin instanceof MarketplaceAdmin) {
            return response()->json(['ok' => false], 403);
        }

        $current = $this->prefs($admin);
        $sounds = (array) $request->input('sounds', []);
        foreach (array_keys($current['sounds']) as $key) {
            if (array_key_exists($key, $sounds)) {
                $current['sounds'][$key] = (bool) $sounds[$key];
            }
        }

        if ($request->has('dnd_minutes')) {
            $minutes = (int) $request->input('dnd_minutes');
            $current['dnd_until'] = match (true) {
                $minutes === -1 => now()->addDay()->startOfDay()->addHours(8)->toIso8601String(), // până mâine la 8
                $minutes > 0 => now()->addMinutes(min($minutes, 1440))->toIso8601String(),
                default => null,
            };
        }

        $settings = (array) ($admin->settings ?? []);
        $settings['cockpit_prefs'] = $current;
        $admin->forceFill(['settings' => $settings])->saveQuietly();

        return response()->json(['ok' => true, 'prefs' => $this->prefs($admin->fresh())]);
    }

    private function prefs($admin): array
    {
        $saved = (array) data_get($admin?->settings, 'cockpit_prefs', []);
        $sounds = array_merge(
            ['action' => true, 'chat' => true, 'dm' => true, 'group' => true],
            array_map('boolval', (array) ($saved['sounds'] ?? []))
        );
        $dnd = $saved['dnd_until'] ?? null;
        if ($dnd) {
            try {
                if (\Carbon\Carbon::parse($dnd)->isPast()) {
                    $dnd = null;
                }
            } catch (\Throwable) {
                $dnd = null;
            }
        }

        return ['sounds' => array_intersect_key($sounds, array_flip(['action', 'chat', 'dm', 'group'])), 'dnd_until' => $dnd];
    }

    /**
     * „Sunt aici”: orice fereastră deschisă din panou trimite asta la un minut.
     * Scriem cel mult o dată la 45 de secunde per operator.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $admin = Auth::guard('marketplace_admin')->user();
        if (! $admin instanceof MarketplaceAdmin || ! \Illuminate\Support\Facades\Schema::hasColumn('marketplace_admins', 'last_seen_at')) {
            return response()->json(['ok' => false]);
        }

        // La închiderea unei ferestre: îl dăm „plecat” peste ~15 secunde. Dacă mai are un tab
        // deschis, heartbeat-ul acelui tab îl readuce online.
        if ($request->boolean('leaving')) {
            \Illuminate\Support\Facades\DB::table('marketplace_admins')
                ->where('id', $admin->id)
                ->update(['last_seen_at' => now()->subSeconds(60)]);

            return response()->json(['ok' => true]);
        }

        \Illuminate\Support\Facades\DB::table('marketplace_admins')
            ->where('id', $admin->id)
            ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subSeconds(20)))
            ->update(['last_seen_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function journalRead(Request $request): JsonResponse
    {
        if (! $this->service()) {
            return response()->json(['ok' => false], 403);
        }

        app(OperatorNotificationJournal::class)->markRead((int) $request->input('id'));

        return response()->json(['ok' => true]);
    }

    private function journal(): array
    {
        try {
            $journal = app(OperatorNotificationJournal::class);

            return ['items' => $journal->forBar(), 'unread' => $journal->unreadCount()];
        } catch (\Throwable $e) {
            report($e);
            return ['items' => [], 'unread' => 0];
        }
    }

    private function service(): ?AttentionService
    {
        $admin = Auth::guard('marketplace_admin')->user();
        if (! $admin instanceof MarketplaceAdmin || ! AttentionService::isEnabledFor($admin->marketplaceClient)) {
            return null;
        }

        return app(AttentionService::class)->forAdmin($admin);
    }
}
