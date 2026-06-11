<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Database\Seeders\Demo\FestivalDemoSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemoDataController extends Controller
{
    /**
     * Available demo datasets keyed by tenant type compatibility.
     */
    protected array $datasets = [
        'festival' => FestivalDemoSeeder::class,
    ];

    /**
     * Seed demo data for a tenant.
     */
    public function seed(Request $request, int $tenantId): JsonResponse
    {
        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found.'], 404);
        }

        if ($tenant->is_demo_shadow) {
            return response()->json(['success' => false, 'message' => 'Cannot seed demo data on a shadow tenant.'], 422);
        }

        if ($tenant->demo_shadow_id) {
            return response()->json(['success' => false, 'message' => 'Demo data already exists. Remove it first.'], 422);
        }

        $dataset = $request->input('dataset', 'festival');
        $seederClass = $this->datasets[$dataset] ?? null;

        if (! $seederClass) {
            return response()->json(['success' => false, 'message' => "Unknown dataset: {$dataset}"], 422);
        }

        try {
            $seeder = new $seederClass($tenantId);
            $seeder->run();

            $tenant->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Demo data populated successfully!',
                'shadow_tenant_id' => $tenant->demo_shadow_id,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove demo data for a tenant.
     */
    public function cleanup(int $tenantId): JsonResponse
    {
        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found.'], 404);
        }

        if (! $tenant->demo_shadow_id) {
            return response()->json(['success' => false, 'message' => 'No demo data to remove.'], 422);
        }

        $dataset = $tenant->demo_dataset ?? 'festival';
        $seederClass = $this->datasets[$dataset] ?? FestivalDemoSeeder::class;

        try {
            $seeder = new $seederClass($tenantId);
            $seeder->cleanup();

            return response()->json([
                'success' => true,
                'message' => 'Demo data removed successfully!',
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
