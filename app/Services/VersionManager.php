<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class VersionManager
{
    protected string $versionFile;
    protected array $versions;

    public function __construct()
    {
        $this->versionFile = base_path('version.json');
        $this->loadVersions();
    }

    protected function loadVersions(): void
    {
        if (File::exists($this->versionFile)) {
            $this->versions = json_decode(File::get($this->versionFile), true);
        } else {
            $this->versions = [
                'core' => '1.0.0',
                'services' => [],
                'lastUpdated' => now()->toIso8601String(),
            ];
        }
    }

    public function getVersions(): array
    {
        return $this->versions;
    }

    public function getCoreVersion(): string
    {
        return $this->versions['core'] ?? '1.0.0';
    }

    public function getServiceVersion(string $service): ?string
    {
        return $this->versions['services'][$service] ?? null;
    }

    public function getAllServiceVersions(): array
    {
        return $this->versions['services'] ?? [];
    }

    public function incrementVersion(string $version, string $type = 'patch'): string
    {
        $parts = explode('.', $version);

        if (count($parts) !== 3) {
            $parts = [1, 0, 0];
        }

        $major = (int) $parts[0];
        $minor = (int) $parts[1];
        $patch = (int) $parts[2];

        switch ($type) {
            case 'major':
                $major++;
                $minor = 0;
                $patch = 0;
                break;
            case 'minor':
                $minor++;
                $patch = 0;
                break;
            case 'patch':
            default:
                $patch++;
                break;
        }

        return "{$major}.{$minor}.{$patch}";
    }

    public function bumpCoreVersion(string $type = 'patch'): string
    {
        $currentVersion = $this->getCoreVersion();
        $newVersion = $this->incrementVersion($currentVersion, $type);
        $this->versions['core'] = $newVersion;
        $this->save();

        return $newVersion;
    }

    public function bumpServiceVersion(string $service, string $type = 'patch'): string
    {
        $currentVersion = $this->getServiceVersion($service) ?? '1.0.0';
        $newVersion = $this->incrementVersion($currentVersion, $type);
        $this->versions['services'][$service] = $newVersion;
        $this->save();

        return $newVersion;
    }

    public function detectAffectedComponents(array $changedFiles): array
    {
        $affected = [
            'core' => false,
            'services' => [],
        ];

        foreach ($changedFiles as $file) {
            // Check if file is in app/Services/{ServiceName}/
            if (preg_match('#^app/Services/([^/]+)/#', $file, $matches)) {
                $serviceName = $matches[1];
                if (isset($this->versions['services'][$serviceName])) {
                    $affected['services'][$serviceName] = true;
                }
            }
            // Check if file is a direct service file like app/Services/ServiceName.php
            elseif (preg_match('#^app/Services/([^/]+)\.php$#', $file, $matches)) {
                $serviceName = str_replace('Service', '', $matches[1]);
                // Map service files to their directory counterparts
                $serviceMapping = [
                    'AffiliateTracking' => 'Tracking',
                    'Location' => 'core',
                    'Notification' => 'core',
                    'PackageGenerator' => 'core',
                    'Exchange' => 'core',
                    'Spotify' => 'core',
                    'YouTube' => 'core',
                    'FeatureFlag' => 'FeatureFlags',
                    'DomainVerification' => 'core',
                ];

                if (isset($serviceMapping[$serviceName])) {
                    $mapped = $serviceMapping[$serviceName];
                    if ($mapped === 'core') {
                        $affected['core'] = true;
                    } else {
                        $affected['services'][$mapped] = true;
                    }
                } else {
                    $affected['core'] = true;
                }
            }
            // Any other app/ file affects core
            elseif (preg_match('#^app/#', $file)) {
                $affected['core'] = true;
            }
            // Config, database, resources, routes affect core
            elseif (preg_match('#^(config|database|resources|routes)/#', $file)) {
                $affected['core'] = true;
            }
        }

        return $affected;
    }

    public function bumpVersionsFromChanges(array $changedFiles, string $type = 'patch'): array
    {
        $affected = $this->detectAffectedComponents($changedFiles);
        $bumped = [];

        if ($affected['core']) {
            $bumped['core'] = $this->bumpCoreVersion($type);
        }

        foreach (array_keys($affected['services']) as $service) {
            $bumped['services'][$service] = $this->bumpServiceVersion($service, $type);
        }

        return $bumped;
    }

    protected function save(): void
    {
        $this->versions['lastUpdated'] = now()->toIso8601String();

        File::put(
            $this->versionFile,
            json_encode($this->versions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    public function getFormattedVersions(): string
    {
        $output = "Core Application: v{$this->getCoreVersion()}\n\n";
        $output .= "Services:\n";

        foreach ($this->getAllServiceVersions() as $service => $version) {
            $output .= "  - {$service}: v{$version}\n";
        }

        $output .= "\nLast Updated: {$this->versions['lastUpdated']}";

        return $output;
    }
}
