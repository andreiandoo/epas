<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Jobs\GeneratePackageJob;
use App\Models\Domain;
use App\Models\TenantPackage;
use App\Services\PackageGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PackageController extends Controller
{
    public function __construct(
        protected PackageGeneratorService $packageService
    ) {}

    /**
     * Generate a new package for a domain
     */
    public function generate(Request $request, Domain $domain): JsonResponse
    {
        Gate::authorize('update', $domain);

        if (!$domain->isVerified()) {
            return response()->json([
                'success' => false,
                'message' => 'Domain must be verified before generating a package',
            ], 400);
        }

        $regenerate = $request->boolean('regenerate', false);

        // Dispatch job for async generation
        GeneratePackageJob::dispatch($domain, $regenerate);

        return response()->json([
            'success' => true,
            'message' => 'Package generation started',
        ]);
    }

    /**
     * Get package status for a domain
     */
    public function status(Domain $domain): JsonResponse
    {
        Gate::authorize('view', $domain);

        $package = $domain->latestPackage;

        if (!$package) {
            return response()->json([
                'success' => false,
                'message' => 'No package found for this domain',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $package->id,
                'version' => $package->version,
                'status' => $package->status,
                'file_size' => $package->getFileSizeFormatted(),
                'download_count' => $package->download_count,
                'generated_at' => $package->generated_at?->toIso8601String(),
                'is_ready' => $package->isReady(),
            ],
        ]);
    }

    /**
     * Download a package
     */
    public function download(TenantPackage $package, string $hash): BinaryFileResponse|JsonResponse
    {
        // Verify hash matches
        if ($package->package_hash !== $hash) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid package hash',
            ], 403);
        }

        if (!$package->isReady()) {
            return response()->json([
                'success' => false,
                'message' => 'Package is not ready for download',
            ], 400);
        }

        if (!$package->file_path || !Storage::exists($package->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Package file not found',
            ], 404);
        }

        $package->incrementDownloadCount();

        $filename = sprintf(
            'tixello-%s-v%s.min.js',
            str_replace('.', '-', $package->domain->domain),
            $package->version
        );

        return response()->download(
            Storage::path($package->file_path),
            $filename,
            [
                'Content-Type' => 'application/javascript',
            ]
        );
    }

    /**
     * Get installation code for a package
     */
    public function installCode(TenantPackage $package): JsonResponse
    {
        Gate::authorize('view', $package->domain);

        if (!$package->isReady()) {
            return response()->json([
                'success' => false,
                'message' => 'Package is not ready',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'install_code' => $package->getInstallationCode(),
                'script_url' => $package->getScriptUrl(),
                'integrity_hash' => $package->integrity_hash,
            ],
        ]);
    }

    /**
     * List all packages for a domain
     */
    public function list(Domain $domain): JsonResponse
    {
        Gate::authorize('view', $domain);

        $packages = $domain->packages()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (TenantPackage $p) => [
                'id' => $p->id,
                'version' => $p->version,
                'status' => $p->status,
                'file_size' => $p->getFileSizeFormatted(),
                'download_count' => $p->download_count,
                'generated_at' => $p->generated_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $packages,
        ]);
    }

    /**
     * Invalidate a package
     */
    public function invalidate(TenantPackage $package): JsonResponse
    {
        Gate::authorize('update', $package->domain);

        $package->invalidate();

        return response()->json([
            'success' => true,
            'message' => 'Package invalidated',
        ]);
    }
}
