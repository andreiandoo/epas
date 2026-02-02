<?php

namespace App\Console\Commands;

use App\Services\VersionManager;
use Illuminate\Console\Command;

class VersionBumpCommand extends Command
{
    protected $signature = 'version:bump
                            {component : The component to bump (core or service name)}
                            {--type=patch : Version increment type (major, minor, patch)}';

    protected $description = 'Manually bump version for core app or a specific service';

    public function handle(VersionManager $versionManager): int
    {
        $component = $this->argument('component');
        $type = $this->option('type');

        if (!in_array($type, ['major', 'minor', 'patch'])) {
            $this->error("Invalid type. Use: major, minor, or patch");
            return 1;
        }

        if ($component === 'core') {
            $newVersion = $versionManager->bumpCoreVersion($type);
            $this->info("Core version bumped to v{$newVersion}");
        } else {
            $currentVersion = $versionManager->getServiceVersion($component);
            if (!$currentVersion) {
                $this->error("Service '{$component}' not found");
                return 1;
            }

            $newVersion = $versionManager->bumpServiceVersion($component, $type);
            $this->info("{$component} version bumped to v{$newVersion}");
        }

        return 0;
    }
}
