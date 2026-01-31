<?php

namespace App\Console\Commands;

use App\Models\ChangelogEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class GenerateChangelogMarkdownCommand extends Command
{
    protected $signature = 'changelog:generate-md
                            {--output=CHANGELOG.md : Output file path}
                            {--days=30 : Number of days to include (0 for all)}';

    protected $description = 'Generate CHANGELOG.md file from database entries';

    public function handle(): int
    {
        $outputPath = base_path($this->option('output'));
        $days = (int) $this->option('days');

        $this->info('📝 Generating CHANGELOG.md...');

        $query = ChangelogEntry::visible()->orderBy('committed_at', 'desc');

        if ($days > 0) {
            $query->where('committed_at', '>=', now()->subDays($days));
        }

        $entries = $query->get();

        if ($entries->isEmpty()) {
            $this->warn('No changelog entries found.');
            return Command::SUCCESS;
        }

        // Group by date, then by module
        $grouped = $entries->groupBy(function ($entry) {
            return $entry->committed_at->format('Y-m-d');
        });

        $markdown = $this->generateMarkdown($grouped);

        File::put($outputPath, $markdown);

        $this->info("✅ Generated: {$outputPath}");
        $this->info("   Entries: " . $entries->count());

        return Command::SUCCESS;
    }

    private function generateMarkdown($grouped): string
    {
        $md = "# CHANGELOG\n\n";
        $md .= "Acest document urmărește toate modificările din proiect.\n\n";
        $md .= "---\n\n";

        foreach ($grouped as $date => $entries) {
            $dateFormatted = Carbon::parse($date)->format('d F Y');
            $md .= "## [{$dateFormatted}]\n\n";

            // Group by module within date
            $byModule = $entries->groupBy('module');

            foreach ($byModule as $module => $moduleEntries) {
                $moduleLabel = ChangelogEntry::MODULE_MAPPINGS[$module] ?? ucfirst($module);
                $md .= "### {$moduleLabel}\n\n";

                // Group by type within module
                $byType = $moduleEntries->groupBy('type');

                foreach ($byType as $type => $typeEntries) {
                    $typeLabel = ChangelogEntry::TYPE_LABELS[$type] ?? ucfirst($type);
                    $md .= "**{$typeLabel}:**\n";

                    foreach ($typeEntries as $entry) {
                        $breaking = $entry->is_breaking ? ' ⚠️ BREAKING' : '';
                        $md .= "- {$entry->description}{$breaking} (`{$entry->short_hash}`)\n";
                    }
                    $md .= "\n";
                }
            }

            $md .= "---\n\n";
        }

        // Add summary section
        $md .= $this->generateSummary();

        $md .= "\n---\n\n";
        $md .= "*Generat automat la: " . now()->format('Y-m-d H:i:s') . "*\n";

        return $md;
    }

    private function generateSummary(): string
    {
        $md = "## Statistici\n\n";

        // By module
        $byModule = ChangelogEntry::visible()
            ->selectRaw('module, count(*) as count')
            ->groupBy('module')
            ->orderByDesc('count')
            ->get();

        $md .= "### Per Modul\n\n";
        $md .= "| Modul | Commit-uri |\n";
        $md .= "|-------|------------|\n";

        foreach ($byModule as $row) {
            $label = ChangelogEntry::MODULE_MAPPINGS[$row->module] ?? ucfirst($row->module);
            $md .= "| {$label} | {$row->count} |\n";
        }

        $md .= "\n";

        // By type
        $byType = ChangelogEntry::visible()
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->orderByDesc('count')
            ->get();

        $md .= "### Per Tip\n\n";
        $md .= "| Tip | Commit-uri |\n";
        $md .= "|-----|------------|\n";

        foreach ($byType as $row) {
            $label = ChangelogEntry::TYPE_LABELS[$row->type] ?? ucfirst($row->type);
            $md .= "| {$label} | {$row->count} |\n";
        }

        return $md;
    }
}
