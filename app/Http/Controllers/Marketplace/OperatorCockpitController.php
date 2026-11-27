<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceAdmin;
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

    private function service(): ?AttentionService
    {
        $admin = Auth::guard('marketplace_admin')->user();
        if (! $admin instanceof MarketplaceAdmin || ! AttentionService::isEnabledFor($admin->marketplaceClient)) {
            return null;
        }

        return app(AttentionService::class)->forAdmin($admin);
    }
}
