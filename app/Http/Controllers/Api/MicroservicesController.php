<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Microservices\ActivateMicroserviceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MicroservicesController extends Controller
{
    /**
     * List all available microservices
     */
    public function index(): JsonResponse
    {
        $microservices = DB::table('microservices')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'microservices' => $microservices,
        ]);
    }

    /**
     * Get tenant's active microservices
     */
    public function tenantMicroservices(string $tenantId): JsonResponse
    {
        $activeMicroservices = DB::table('tenant_microservices')
            ->join('microservices', 'tenant_microservices.microservice_id', '=', 'microservices.id')
            ->where('tenant_microservices.tenant_id', $tenantId)
            ->select([
                'tenant_microservices.*',
                'microservices.name',
                'microservices.slug',
                'microservices.description',
            ])
            ->get();

        return response()->json([
            'success' => true,
            'microservices' => $activeMicroservices,
        ]);
    }

    /**
     * Activate a microservice for a tenant
     */
    public function activate(ActivateMicroserviceRequest $request): JsonResponse
    {
        $microservice = DB::table('microservices')
            ->where('slug', $request->microservice_slug)
            ->first();

        if (!$microservice) {
            return response()->json([
                'success' => false,
                'message' => 'Microservice not found',
            ], 404);
        }

        // Check if already activated
        $existing = DB::table('tenant_microservices')
            ->where('tenant_id', $request->tenant_id)
            ->where('microservice_id', $microservice->id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Microservice already activated',
            ], 409);
        }

        $id = DB::table('tenant_microservices')->insertGetId([
            'tenant_id' => $request->tenant_id,
            'microservice_id' => $microservice->id,
            'status' => $request->trial ? 'trial' : 'active',
            'activated_at' => now(),
            'expires_at' => $this->calculateExpiration($microservice, $request->trial),
            'settings' => $request->settings ? json_encode($request->settings) : null,
            'monthly_price' => $microservice->price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Microservice activated successfully',
            'activation_id' => $id,
        ]);
    }

    /**
     * Deactivate a microservice for a tenant
     */
    public function deactivate(Request $request, string $tenantId, string $microserviceSlug): JsonResponse
    {
        $microservice = DB::table('microservices')
            ->where('slug', $microserviceSlug)
            ->first();

        if (!$microservice) {
            return response()->json([
                'success' => false,
                'message' => 'Microservice not found',
            ], 404);
        }

        $updated = DB::table('tenant_microservices')
            ->where('tenant_id', $tenantId)
            ->where('microservice_id', $microservice->id)
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Microservice not activated for this tenant',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Microservice deactivated successfully',
        ]);
    }

    /**
     * Calculate expiration date based on billing cycle
     */
    protected function calculateExpiration($microservice, bool $trial)
    {
        if ($trial) {
            return now()->addDays(14); // 14-day trial
        }

        return match ($microservice->billing_cycle) {
            'monthly' => now()->addMonth(),
            'yearly' => now()->addYear(),
            'one_time' => null,
            default => now()->addMonth(),
        };
    }
}
