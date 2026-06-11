<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

class CleanDescriptionBrTagsCommand extends Command
{
    protected $signature = 'events:clean-br-tags {--dry-run : Show what would be changed without saving}';
    protected $description = 'Clean up excessive <br>&nbsp;<br> patterns in event descriptions';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $pattern = '#(<br\s*/?>[\s\n]*){1,}\s*(&nbsp;[\s\n]*)+\s*(<br\s*/?>[\s\n]*){1,}#i';
        $replacement = '<br><br>';

        $updated = 0;
        $total = Event::count();

        $this->info("Scanning {$total} events" . ($dryRun ? ' (dry run)' : '') . '...');

        Event::query()->whereNotNull('description')->chunkById(200, function ($events) use ($pattern, $replacement, $dryRun, &$updated) {
            foreach ($events as $event) {
                $description = $event->getRawOriginal('description');
                if (!$description) continue;

                $data = json_decode($description, true);
                if (!is_array($data)) {
                    // Plain string (non-JSON)
                    $cleaned = preg_replace($pattern, $replacement, $description);
                    if ($cleaned !== $description) {
                        $updated++;
                        $this->line("  [{$event->id}] " . ($event->getTranslation('title', 'ro') ?: $event->getTranslation('title', 'en') ?: "Event #{$event->id}"));
                        if (!$dryRun) {
                            $event->forceFill(['description' => $cleaned])->saveQuietly();
                        }
                    }
                    continue;
                }

                $changed = false;
                foreach ($data as $locale => $text) {
                    if (!is_string($text)) continue;
                    $cleaned = preg_replace($pattern, $replacement, $text);
                    if ($cleaned !== $text) {
                        $data[$locale] = $cleaned;
                        $changed = true;
                    }
                }

                if ($changed) {
                    $updated++;
                    $this->line("  [{$event->id}] " . ($data['ro'] ?? $data['en'] ?? "Event #{$event->id}") . ' (trimmed to 50 chars)');
                    if (!$dryRun) {
                        $event->forceFill(['description' => $data])->saveQuietly();
                    }
                }
            }
        });

        $action = $dryRun ? 'would be cleaned' : 'cleaned';
        $this->info("Done. {$updated} events {$action}.");

        return self::SUCCESS;
    }
}
