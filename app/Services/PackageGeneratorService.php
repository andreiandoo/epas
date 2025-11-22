<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\TenantPackage;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PackageGeneratorService
{
    protected string $buildPath;
    protected string $outputPath;

    public function __construct()
    {
        $this->buildPath = base_path('resources/tenant-client');
        $this->outputPath = storage_path('app/packages');
    }

    public function generate(Domain $domain): TenantPackage
    {
        $tenant = $domain->tenant;

        // Create package record
        $package = TenantPackage::create([
            'tenant_id' => $tenant->id,
            'domain_id' => $domain->id,
            'version' => $this->getNextVersion($domain),
            'package_hash' => Str::random(32),
            'integrity_hash' => '', // Will be set after build
            'status' => TenantPackage::STATUS_GENERATING,
            'config_snapshot' => $this->buildConfigSnapshot($tenant, $domain),
            'enabled_modules' => $this->getEnabledModules($tenant),
            'theme_config' => $this->getThemeConfig($tenant),
        ]);

        try {
            // Generate the package
            $result = $this->buildPackage($package);

            // Update package with results
            $package->update([
                'status' => TenantPackage::STATUS_READY,
                'file_path' => $result['file_path'],
                'file_size' => $result['file_size'],
                'integrity_hash' => $result['integrity_hash'],
                'generated_at' => now(),
            ]);

            Log::info('Package generated successfully', [
                'package_id' => $package->id,
                'domain' => $domain->domain,
                'size' => $package->getFileSizeFormatted(),
            ]);

            return $package;
        } catch (\Exception $e) {
            $package->update([
                'status' => TenantPackage::STATUS_EXPIRED,
            ]);

            Log::error('Package generation failed', [
                'package_id' => $package->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function buildPackage(TenantPackage $package): array
    {
        // Ensure output directory exists
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }

        $packageDir = $this->outputPath . '/' . $package->package_hash;
        mkdir($packageDir, 0755, true);

        // Write tenant configuration for build
        $configPath = $packageDir . '/tenant-config.json';
        file_put_contents($configPath, json_encode([
            'tenantId' => $package->tenant_id,
            'domainId' => $package->domain_id,
            'domain' => $package->domain->domain,
            'apiEndpoint' => config('app.url') . '/api/tenant-client',
            'modules' => $package->enabled_modules,
            'theme' => $package->theme_config,
            'version' => $package->version,
            'packageHash' => $package->package_hash,
        ], JSON_PRETTY_PRINT));

        // Run the build process
        $outputFile = $packageDir . '/tixello-loader.min.js';

        $result = Process::timeout(300)->run([
            'node',
            base_path('scripts/build-tenant-package.js'),
            '--config', $configPath,
            '--output', $outputFile,
            '--obfuscate', config('tenant-package.obfuscation.enabled', true) ? 'true' : 'false',
        ]);

        if (!$result->successful()) {
            throw new \RuntimeException('Build failed: ' . $result->errorOutput());
        }

        if (!file_exists($outputFile)) {
            throw new \RuntimeException('Build output file not found');
        }

        // Calculate integrity hash (SRI)
        $fileContent = file_get_contents($outputFile);
        $integrityHash = 'sha384-' . base64_encode(hash('sha384', $fileContent, true));

        // Move to storage
        $storagePath = 'packages/' . $package->package_hash . '/tixello-loader.min.js';
        Storage::put($storagePath, $fileContent);

        // Cleanup build directory
        $this->cleanupBuildDirectory($packageDir);

        return [
            'file_path' => $storagePath,
            'file_size' => strlen($fileContent),
            'integrity_hash' => $integrityHash,
        ];
    }

    protected function getNextVersion(Domain $domain): string
    {
        $latestPackage = $domain->packages()->latest()->first();

        if (!$latestPackage) {
            return '1.0.0';
        }

        $parts = explode('.', $latestPackage->version);
        $parts[2] = (int)$parts[2] + 1;

        return implode('.', $parts);
    }

    protected function buildConfigSnapshot(Tenant $tenant, Domain $domain): array
    {
        return [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'domain' => [
                'id' => $domain->id,
                'domain' => $domain->domain,
            ],
            'api_key' => $this->generatePackageApiKey($tenant, $domain),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    protected function generatePackageApiKey(Tenant $tenant, Domain $domain): string
    {
        // Generate a secure API key for the package
        $payload = [
            'tenant_id' => $tenant->id,
            'domain_id' => $domain->id,
            'created_at' => now()->timestamp,
        ];

        return encrypt($payload);
    }

    protected function getEnabledModules(Tenant $tenant): array
    {
        $modules = ['core', 'events', 'auth', 'cart', 'checkout'];

        // Add modules based on active microservices
        $microservices = $tenant->microservices()->active()->with('microservice')->get();

        foreach ($microservices as $tm) {
            $slug = $tm->microservice->slug ?? '';

            $moduleMap = [
                'seating' => 'seating',
                'affiliates' => 'affiliates',
                'insurance' => 'insurance',
                'whatsapp' => 'whatsapp',
                'promo-codes' => 'promo_codes',
                'invitations' => 'invitations',
                'tracking' => 'tracking',
            ];

            if (isset($moduleMap[$slug])) {
                $modules[] = $moduleMap[$slug];
            }
        }

        return array_unique($modules);
    }

    protected function getThemeConfig(Tenant $tenant): array
    {
        $settings = $tenant->settings ?? [];

        return [
            'primaryColor' => $settings['theme']['primary_color'] ?? '#3B82F6',
            'secondaryColor' => $settings['theme']['secondary_color'] ?? '#1E40AF',
            'logo' => $settings['branding']['logo_url'] ?? null,
            'favicon' => $settings['branding']['favicon_url'] ?? null,
            'fontFamily' => $settings['theme']['font_family'] ?? 'Inter',
        ];
    }

    protected function cleanupBuildDirectory(string $path): void
    {
        if (is_dir($path)) {
            $files = array_diff(scandir($path), ['.', '..']);
            foreach ($files as $file) {
                $filePath = $path . '/' . $file;
                if (is_dir($filePath)) {
                    $this->cleanupBuildDirectory($filePath);
                } else {
                    unlink($filePath);
                }
            }
            rmdir($path);
        }
    }

    public function invalidateAllPackages(Tenant $tenant): int
    {
        return $tenant->packages()
            ->where('status', TenantPackage::STATUS_READY)
            ->update(['status' => TenantPackage::STATUS_INVALIDATED]);
    }

    public function regenerateForDomain(Domain $domain): TenantPackage
    {
        // Invalidate existing packages for this domain
        $domain->packages()
            ->where('status', TenantPackage::STATUS_READY)
            ->update(['status' => TenantPackage::STATUS_INVALIDATED]);

        return $this->generate($domain);
    }
}
