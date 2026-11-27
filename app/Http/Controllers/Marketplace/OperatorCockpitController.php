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
