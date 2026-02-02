<?php

namespace App\Console\Commands;

use App\Services\VersionManager;
use Illuminate\Console\Command;

class VersionAutoCommand extends Command
{
    protected $signature = 'version:auto
                            {--type=patch : Version increment type (major, minor, patch)}
                            {--dry-run : Show what would be bumped without making changes}';

    protected $description = 'Automatically detect changed files and bump versions accordingly';

    public function handle(VersionManager $versionManager): int
    {
        $type = $this->option('type');
        $dryRun = $this->option('dry-run');

        // Get staged files from git
        $output = shell_exec('git diff --cached --name-only 2>/dev/null');

        if (empty($output)) {
            // If no staged files, check for files changed in last commit
            $output = shell_exec('git diff-tree --no-commit-id --name-only -r HEAD 2>/dev/null');
        }

        if (empty($output)) {
            $this->warn('No changed files detected');
            return 0;
        }

        $changedFiles = array_filter(explode("\n", trim($output)));

        if (empty($changedFiles)) {
            $this->warn('No changed files detected');
            return 0;
        }

        $this->info('Changed files detected:');
        foreach ($changedFiles as $file) {
            $this->line("  - {$file}");
        }
        $this->newLine();

        $affected = $versionManager->detectAffectedComponents($changedFiles);

        if (!$affected['core'] && empty($affected['services'])) {
            $this->info('No version bumps required for the changed files');
            return 0;
        }

        if ($dryRun) {
            $this->info('Would bump the following versions:');
            if ($affected['core']) {
                $current = $versionManager->getCoreVersion();
                $new = $versionManager->incrementVersion($current, $type);
                $this->line("  Core: v{$current} → v{$new}");
            }
            foreach (array_keys($affected['services']) as $service) {
                $current = $versionManager->getServiceVersion($service);
                $new = $versionManager->incrementVersion($current, $type);
                $this->line("  {$service}: v{$current} → v{$new}");
            }
            return 0;
        }

        $bumped = $versionManager->bumpVersionsFromChanges($changedFiles, $type);

        $this->info('Version bumps applied:');
        if (isset($bumped['core'])) {
            $this->line("  Core: v{$bumped['core']}");
        }
        if (isset($bumped['services'])) {
            foreach ($bumped['services'] as $service => $version) {
                $this->line("  {$service}: v{$version}");
            }
        }

        return 0;
    }
}
