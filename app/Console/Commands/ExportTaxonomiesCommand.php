<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ExportTaxonomiesCommand extends Command
{
    protected $signature = 'export:taxonomies {type} {--file=}';
    protected $description = 'Export taxonomies to CSV file. Types: event-types, event-genres, event-tags, artist-types, artist-genres';

    private $modelMap = [
        'event-types' => \App\Models\EventType::class,
        'event-genres' => \App\Models\EventGenre::class,
        'event-tags' => \App\Models\EventTag::class,
        'artist-types' => \App\Models\ArtistType::class,
        'artist-genres' => \App\Models\ArtistGenre::class,
    ];

    public function handle()
    {
        $type = $this->argument('type');
        $file = $this->option('file') ?: storage_path("app/exports/{$type}-" . date('Y-m-d') . ".csv");

        if (!isset($this->modelMap[$type])) {
            $this->error("Invalid type: {$type}. Must be one of: " . implode(', ', array_keys($this->modelMap)));
            return 1;
        }

        $modelClass = $this->modelMap[$type];

        // Ensure export directory exists
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->info("Exporting {$type} to {$file}...");

        $handle = fopen($file, 'w');

        // Write header
        fputcsv($handle, ['name', 'slug', 'description', 'parent_slug']);

        // Get all records
        $records = $modelClass::with('parent')->get();

        foreach ($records as $record) {
            fputcsv($handle, [
                $record->name,
                $record->slug,
                $record->description ?? '',
                $record->parent?->slug ?? '',
            ]);
        }

        fclose($handle);

        $this->info("Export complete! File: {$file}");
        $this->info("Total records: {$records->count()}");

        return 0;
    }
}
