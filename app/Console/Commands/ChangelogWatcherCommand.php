<?php

namespace App\Console\Commands;

use App\Models\ChangelogEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Carbon\Carbon;

class ChangelogWatcherCommand extends Command
{
    protected $signature = 'changelog:watch
                            {--interval=30 : Check interval in seconds}
                            {--branch=core-main : Branch to watch}';

    protected $description = 'Watch for new commits and update changelog in real-time';

    private string $lastKnownCommit = '';

    public function handle(): int
    {
        $interval = (int) $this->option('interval');
        $branch = $this->option('branch');

        $this->info("👁️  Watching branch '{$branch}' for new commits (every {$interval}s)");
        $this->info("   Press Ctrl+C to stop");
        $this->newLine();

        // Get initial commit
        $this->lastKnownCommit = $this->getLatestCommit($branch);
        $this->info("📍 Starting from commit: " . substr($this->lastKnownCommit, 0, 8));

        while (true) {
            $this->checkForNewCommits($branch);
            sleep($interval);
        }

        return Command::SUCCESS;
    }

    private function checkForNewCommits(string $branch): void
    {
        // Fetch latest from remote
        Process::run("git fetch origin {$branch} 2>/dev/null");

        $latestCommit = $this->getLatestCommit("origin/{$branch}");

        if ($latestCommit !== $this->lastKnownCommit) {
            $this->info("🆕 New commits detected!");

            // Get new commits
            $newCommits = $this->getCommitsBetween($this->lastKnownCommit, $latestCommit);

            if (!empty($newCommits)) {
                $this->processNewCommits($newCommits);
            }

            $this->lastKnownCommit = $latestCommit;
        } else {
            $this->output->write("\r⏳ " . now()->format('H:i:s') . " - No new commits");
        }
    }

    private function getLatestCommit(string $ref): string
    {
        $result = Process::run("git rev-parse {$ref}");
        return trim($result->output());
    }

    private function getCommitsBetween(string $from, string $to): array
    {
        $format = '%H|%an|%ae|%aI|%s';
        $result = Process::run("git log {$from}..{$to} --pretty=format:\"{$format}\" --reverse");

        if (!$result->successful()) {
            return [];
        }

        $lines = array_filter(explode("\n", $result->output()));
        $commits = [];

        foreach ($lines as $line) {
            $parts = explode('|', $line, 5);
            if (count($parts) >= 5) {
                $commits[] = [
                    'hash' => $parts[0],
                    'author_name' => $parts[1],
                    'author_email' => $parts[2],
                    'date' => $parts[3],
                    'message' => $parts[4],
                ];
            }
        }

        return $commits;
    }

    private function processNewCommits(array $commits): void
    {
        foreach ($commits as $commit) {
            // Skip if exists
            if (ChangelogEntry::where('commit_hash', $commit['hash'])->exists()) {
                continue;
            }

            $parsed = ChangelogEntry::parseCommitMessage($commit['message']);
            $module = ChangelogEntry::detectModule($parsed['scope'], $commit['message']);

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
                'is_breaking' => $parsed['is_breaking'],
                'is_visible' => ChangelogEntry::shouldBeVisible($commit['message']),
            ]);

            $typeEmoji = match($parsed['type']) {
                'feat' => '✨',
                'fix' => '🐛',
                'refactor' => '♻️',
                'docs' => '📚',
                'style' => '💄',
                'test' => '🧪',
                'perf' => '⚡',
                default => '📝',
            };

            $moduleLabel = ChangelogEntry::MODULE_MAPPINGS[$module] ?? ucfirst($module);

            $this->newLine();
            $this->info("{$typeEmoji} [{$moduleLabel}] {$parsed['description']}");
            $this->line("   <fg=gray>" . substr($commit['hash'], 0, 8) . " by {$commit['author_name']}</>");
        }

        $this->newLine();
        $this->info("✅ Processed " . count($commits) . " new commit(s)");
    }
}
