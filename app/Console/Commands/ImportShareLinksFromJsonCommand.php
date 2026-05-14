<?php

namespace App\Console\Commands;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceShareLink;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot backfill: read the live server's share-links.json (from the
 * old file-storage path) and import each entry into the new
 * marketplace_share_links table.
 *
 *   php artisan share-links:import-from-json
 *     [--file=/path/to/share-links.json]      override the default path
 *     [--marketplace=1]                       force marketplace_client_id
 *     [--dry-run]                             preview without inserting
 *
 * Default file path tries each of these in order:
 *   1. The --file option (if provided)
 *   2. AMBILET_PATH/data/share-links.json   (env-configured deploy root)
 *   3. /home/core-cuhlf/ambilet.ro/data/share-links.json   (current prod)
 *
 * Entries that already exist in the DB (matched by code) are skipped —
 * idempotent across re-runs.
 */
class ImportShareLinksFromJsonCommand extends Command
{
    protected $signature = 'share-links:import-from-json
        {--file= : explicit path to share-links.json}
        {--marketplace= : marketplace_client_id to assign}
        {--dry-run}';

    protected $description = 'Backfill marketplace_share_links from the legacy proxy.php JSON file.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $candidates = array_filter([
            $this->option('file'),
            env('AMBILET_DEPLOY_ROOT') ? rtrim(env('AMBILET_DEPLOY_ROOT'), '/') . '/data/share-links.json' : null,
            '/home/core-cuhlf/ambilet.ro/data/share-links.json',
            '/home/core-cuhlf/public_html/data/share-links.json',
        ]);

        $file = null;
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                $file = $candidate;
                break;
            }
        }

        if (!$file) {
            $this->error('Could not locate share-links.json. Try passing --file=/abs/path.');
            return self::FAILURE;
        }

        $this->info("Reading: {$file}");
        $raw = file_get_contents($file);
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            $this->error('share-links.json did not decode to a JSON object.');
            return self::FAILURE;
        }

        $defaultClientId = $this->option('marketplace')
            ? (int) $this->option('marketplace')
            : (int) MarketplaceClient::query()->orderBy('id')->value('id');

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($data as $code => $link) {
            if (!is_array($link) || !$code) {
                $skipped++;
                continue;
            }

            if (MarketplaceShareLink::where('code', $code)->exists()) {
                $skipped++;
                continue;
            }

            try {
                $eventIds = array_values(array_filter(array_map('intval', (array) ($link['event_ids'] ?? []))));
                if (empty($eventIds)) {
                    $errors++;
                    $this->warn("  [skip] code={$code} has no event_ids");
                    continue;
                }

                $row = [
                    'code' => $code,
                    'marketplace_client_id' => (int) ($link['marketplace_client_id'] ?? $defaultClientId),
                    'marketplace_organizer_id' => (int) ($link['organizer_id'] ?? $link['marketplace_organizer_id'] ?? 0),
                    'name' => substr(strip_tags((string) ($link['name'] ?? '')), 0, 100),
                    'event_ids' => $eventIds,
                    'is_active' => (bool) ($link['is_active'] ?? true),
                    'has_password' => !empty($link['password_hash']),
                    'password_hash' => $link['password_hash'] ?? null,
                    'show_participants' => (bool) ($link['show_participants'] ?? false),
                    'show_revenue' => array_key_exists('show_revenue', $link) ? (bool) $link['show_revenue'] : true,
                    'ticket_data' => is_array($link['ticket_data'] ?? null) ? $link['ticket_data'] : null,
                    'participants_data' => is_array($link['participants_data'] ?? null) ? $link['participants_data'] : null,
                    'ticket_data_updated_at' => !empty($link['ticket_data_updated_at'])
                        ? \Carbon\Carbon::parse($link['ticket_data_updated_at'])
                        : now(),
                    'access_count' => (int) ($link['access_count'] ?? 0),
                    'last_accessed_at' => !empty($link['last_accessed_at'])
                        ? \Carbon\Carbon::parse($link['last_accessed_at'])
                        : null,
                    'created_at' => !empty($link['created_at']) ? \Carbon\Carbon::parse($link['created_at']) : now(),
                    'updated_at' => now(),
                ];

                if ($row['marketplace_organizer_id'] <= 0) {
                    $errors++;
                    $this->warn("  [skip] code={$code} has no organizer_id");
                    continue;
                }

                if (!$dry) {
                    DB::table('marketplace_share_links')->insert($row);
                }
                $imported++;
                $this->line("  [ok] code={$code} organizer={$row['marketplace_organizer_id']} events=" . count($eventIds));
            } catch (\Throwable $e) {
                $errors++;
                $this->error("  [error] code={$code}: " . $e->getMessage());
            }
        }

        $tag = $dry ? '[DRY RUN] Would import' : 'Imported';
        $this->info("{$tag}: {$imported} | Skipped existing: {$skipped} | Errors: {$errors}");

        return self::SUCCESS;
    }
}
