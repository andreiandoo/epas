<?php

namespace App\Console\Commands;

use App\Models\ChangelogEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Carbon\Carbon;

class UpdateChangelogCommand extends Command
{
    protected $signature = 'changelog:update
                            {--from= : Start commit hash (default: last processed or initial)}
                            {--to=HEAD : End commit hash}
                            {--full : Process all commits from beginning}
                            {--generate-md : Also generate CHANGELOG.md file}';

    protected $description = 'Parse git commits and update changelog entries in database';

    public function handle(): int
    {
        $this->info('🔄 Updating changelog from git commits...');

        $from = $this->option('from');
        $to = $this->option('to');
        $full = $this->option('full');
        $generateMd = $this->option('generate-md');

        // Determine starting point
        if ($full) {
            $from = $this->getInitialCommit();
            $this->info("Processing all commits from initial commit: {$from}");
        } elseif (!$from) {
            $lastEntry = ChangelogEntry::orderBy('committed_at', 'desc')->first();
            $from = $lastEntry ? $lastEntry->commit_hash : $this->getInitialCommit();
            $this->info("Processing commits since: {$from}");
        }

        // Get commits
        $commits = $this->getCommits($from, $to);
        $this->info("Found " . count($commits) . " commits to process");

        if (empty($commits)) {
            $this->info('No new commits to process.');
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($commits));
        $bar->start();

        $processed = 0;
        $skipped = 0;

        foreach ($commits as $commit) {
            // Skip if already exists
            if (ChangelogEntry::where('commit_hash', $commit['hash'])->exists()) {
                $skipped++;
                $bar->advance();
                continue;
            }

            // Parse commit message
            $parsed = ChangelogEntry::parseCommitMessage($commit['message']);

            // Detect module
            $module = ChangelogEntry::detectModule(
                $parsed['scope'],
                $commit['message'],
                $commit['files'] ?? []
            );

            // Create entry
            ChangelogEntry::create([
                'commit_hash' => $commit['hash'],
                'short_hash' => substr($commit['hash'], 0, 8),
                'type' => $parsed['type'],
                'scope' => $parsed['scope'],
                'module' => $module,
                'message' => $commit['message'],
                'description' => $parsed['description'],
                'author_name' => $commit['author_name'],
                'author_email' => $commit['author_email'],
                'committed_at' => Carbon::parse($commit['date']),
                'files_changed' => $commit['files'] ?? [],
                'additions' => $commit['additions'] ?? 0,
                'deletions' => $commit['deletions'] ?? 0,
                'is_breaking' => $parsed['is_breaking'],
                'is_visible' => ChangelogEntry::shouldBeVisible($commit['message']),
            ]);

            $processed++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("✅ Processed: {$processed} commits");
        if ($skipped > 0) {
            $this->info("⏭️  Skipped (already exists): {$skipped}");
        }

        // Generate markdown file if requested
        if ($generateMd) {
            $this->call('changelog:generate-md');
        }

        return Command::SUCCESS;
    }

    /**
     * Get initial commit hash
     */
    private function getInitialCommit(): string
    {
        $result = Process::run('git rev-list --max-parents=0 HEAD');
        return trim($result->output());
    }

    /**
     * Get commits between two refs
     */
    private function getCommits(string $from, string $to): array
    {
        // Get commit list with details
        $format = '%H|%an|%ae|%aI|%s';
        $range = "{$from}..{$to}";

        // If from equals to initial commit, include it
        if ($from === $this->getInitialCommit()) {
            $range = $to;
        }

        $result = Process::run("git log {$range} --pretty=format:\"{$format}\" --reverse");

        if (!$result->successful()) {
            $this->error('Failed to get git log: ' . $result->errorOutput());
            return [];
        }

        $lines = array_filter(explode("\n", $result->output()));
        $commits = [];

        foreach ($lines as $line) {
            $parts = explode('|', $line, 5);
            if (count($parts) < 5) continue;

            [$hash, $authorName, $authorEmail, $date, $message] = $parts;

            // Get file stats for this commit
            $stats = $this->getCommitStats($hash);

            $commits[] = [
                'hash' => $hash,
                'author_name' => $authorName,
                'author_email' => $authorEmail,
                'date' => $date,
                'message' => $message,
                'files' => $stats['files'],
                'additions' => $stats['additions'],
                'deletions' => $stats['deletions'],
            ];
        }

        return $commits;
    }

    /**
     * Get stats for a single commit
     */
    private function getCommitStats(string $hash): array
    {
        $result = Process::run("git diff-tree --no-commit-id --name-only -r {$hash}");
        $files = array_filter(explode("\n", $result->output()));

        $statResult = Process::run("git show --stat --format= {$hash} | tail -1");
        $statLine = trim($statResult->output());

        $additions = 0;
        $deletions = 0;

        if (preg_match('/(\d+)\s+insertion/', $statLine, $m)) {
            $additions = (int)$m[1];
        }
        if (preg_match('/(\d+)\s+deletion/', $statLine, $m)) {
            $deletions = (int)$m[1];
        }

        return [
            'files' => $files,
            'additions' => $additions,
            'deletions' => $deletions,
        ];
    }
}
