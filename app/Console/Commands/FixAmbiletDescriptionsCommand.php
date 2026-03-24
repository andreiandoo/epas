<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixAmbiletDescriptionsCommand extends Command
{
    protected $signature = 'fix:ambilet-descriptions
        {--marketplace=1 : marketplace_client_id}
        {--dry-run}
        {--field=description : Field to fix (description or ticket_terms)}';

    protected $description = 'Fix imported AmBilet descriptions: convert plain text with &nbsp; separators into proper HTML paragraphs';

    public function handle(): int
    {
        $clientId = (int) $this->option('marketplace');
        $dryRun   = $this->option('dry-run');
        $field    = $this->option('field');

        if (! in_array($field, ['description', 'ticket_terms'])) {
            $this->error("Invalid field: {$field}. Use 'description' or 'ticket_terms'.");
            return 1;
        }

        $events = DB::table('events')
            ->where('marketplace_client_id', $clientId)
            ->whereNotNull($field)
            ->where($field, '!=', '')
            ->select('id', $field)
            ->get();

        $this->info("Found {$events->count()} events with {$field}.");

        $updated = $skipped = 0;

        foreach ($events as $event) {
            $json = json_decode($event->$field, true);
            if (! is_array($json) || empty($json['ro'])) {
                $skipped++;
                continue;
            }

            $original = $json['ro'];
            $cleaned  = $this->cleanHtml($original);

            if ($cleaned === $original) {
                $skipped++;
                continue;
            }

            $json['ro'] = $cleaned;

            if (! $dryRun) {
                DB::table('events')->where('id', $event->id)->update([
                    $field       => json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
            }

            $updated++;

            if ($updated % 200 === 0) {
                $this->line("Progress: {$updated} updated...");
            }
        }

        $prefix = $dryRun ? '[DRY RUN] Would update' : 'Updated';
        $this->info("{$prefix}: {$updated} | Skipped (no change): {$skipped}");

        return 0;
    }

    /**
     * Clean HTML from ambilet format into proper paragraphs for RichEditor.
     */
    private function cleanHtml(string $text): string
    {
        // If already has multiple <p> tags, consider it properly formatted
        if (substr_count($text, '<p>') > 1 || substr_count($text, '<p ') > 1) {
            // Still clean separators within existing paragraphs
            $text = preg_replace('/={3,}/', '', $text);
            $text = preg_replace('/-{3,}/', '', $text);
            return $text;
        }

        // Unwrap single <p> wrapper if present (fix:ambilet-event-fields wraps everything in one <p>)
        if (preg_match('/^<p>(.*)<\/p>$/s', trim($text), $m)) {
            $text = $m[1];
        }

        // Remove separator lines (=== and ---)
        $text = preg_replace('/={3,}/', "\n\n", $text);
        $text = preg_replace('/-{3,}/', "\n\n", $text);

        // Normalize non-breaking spaces
        $text = str_replace(["\xc2\xa0", "\xa0"], ' ', $text);

        // Decode HTML entities
        $text = str_replace('&nbsp;', ' ', $text);
        $text = str_replace('&amp;', '&', $text);

        // Normalize multiple spaces to single
        $text = preg_replace('/  +/', ' ', $text);

        // Add paragraph breaks before emoji characters
        $text = preg_replace('/\s+([\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}])/u', "\n\n$1", $text);

        // Add paragraph breaks before common section patterns
        $text = preg_replace('/\s+(Distribuție|Durată|Locație|Organizator|Important|Recomand|Program|Acces|Lineup|Line-up|Bilete|Reguli|Restricții):/u', "\n\n$1:", $text);

        // Convert existing <br> tags to newlines
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);

        // Split on double newlines (or more)
        $paragraphs = preg_split('/\n{2,}/', $text);

        // Wrap each non-empty paragraph in <p> tags, preserving inline HTML
        $html = '';
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            // Convert single newlines within a paragraph to <br>
            $p = str_replace("\n", '<br>', $p);
            $html .= "<p>{$p}</p>";
        }

        return $html ?: $text;
    }
}
