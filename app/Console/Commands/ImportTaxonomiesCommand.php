<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportTaxonomiesCommand extends Command
{
    protected $signature = 'import:taxonomies {file} {type}';
    protected $description = 'Import taxonomies from CSV file. Types: event-types, event-genres, event-tags, artist-types, artist-genres';

    private $modelMap = [
        'event-types' => \App\Models\EventType::class,
        'event-genres' => \App\Models\EventGenre::class,
        'event-tags' => \App\Models\EventTag::class,
        'artist-types' => \App\Models\ArtistType::class,
        'artist-genres' => \App\Models\ArtistGenre::class,
    ];

    public function handle()
    {
        $file = $this->argument('file');
        $type = $this->argument('type');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        if (!isset($this->modelMap[$type])) {
            $this->error("Invalid type: {$type}. Must be one of: " . implode(', ', array_keys($this->modelMap)));
            return 1;
        }

        $modelClass = $this->modelMap[$type];

        $this->info("Importing {$type} from {$file}...");

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        if ($header === false || !in_array('name', $header)) {
            $this->error("Invalid CSV format. Must have at least a 'name' column.");
            return 1;
        }

        $imported = 0;
        $skipped = 0;
        $parentMap = []; // Map slug => id for parent references

        // First pass: Import all items without parent relationships
        rewind($handle);
        fgetcsv($handle); // Skip header

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);

            // Skip if name is empty
            if (empty($data['name'])) {
                continue;
            }

            $slug = $data['slug'] ?? Str::slug($data['name']);

            // Check if already exists
            if ($modelClass::where('slug', $slug)->exists()) {
                $this->warn("Skipping duplicate: {$data['name']}");
                $skipped++;
                continue;
            }

            $record = $modelClass::create([
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'parent_id' => null, // Set in second pass
            ]);

            $parentMap[$slug] = $record->id;
            $imported++;
        }

        // Second pass: Update parent relationships
        rewind($handle);
        fgetcsv($handle); // Skip header

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);

            if (empty($data['name']) || empty($data['parent_slug'])) {
                continue;
            }

            $slug = $data['slug'] ?? Str::slug($data['name']);
            $parentSlug = $data['parent_slug'];

            if (!isset($parentMap[$parentSlug])) {
                $this->warn("Parent not found for '{$data['name']}': {$parentSlug}");
                continue;
            }

            $modelClass::where('slug', $slug)
                ->update(['parent_id' => $parentMap[$parentSlug]]);
        }

        fclose($handle);

        $this->info("Import complete!");
        $this->info("Imported: {$imported}");
        $this->info("Skipped: {$skipped}");

        return 0;
    }
}
