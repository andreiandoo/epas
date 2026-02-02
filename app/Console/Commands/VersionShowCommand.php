<?php

namespace App\Console\Commands;

use App\Services\VersionManager;
use Illuminate\Console\Command;

class VersionShowCommand extends Command
{
    protected $signature = 'version:show {--service= : Show version for a specific service}';

    protected $description = 'Display current versions of core app and services';

    public function handle(VersionManager $versionManager): int
    {
        $service = $this->option('service');

        if ($service) {
            $version = $versionManager->getServiceVersion($service);
            if ($version) {
                $this->info("{$service}: v{$version}");
            } else {
                $this->error("Service '{$service}' not found");
                return 1;
            }
        } else {
            $this->info($versionManager->getFormattedVersions());
        }

        return 0;
    }
}
