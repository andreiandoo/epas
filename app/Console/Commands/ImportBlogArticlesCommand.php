<?php

namespace App\Console\Commands;

use App\Models\Blog\BlogArticle;
use App\Models\Blog\BlogCategory;
use App\Models\MarketplaceClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Import guide articles for a marketplace blog from JSON batches
 * (articole-lot-XX.json) written outside the admin, with their images.
 *
 * Source folder (default database/data/blog-import/{marketplace slug}):
 *   articole-lot-01.json, articole-lot-02.json, ...   arrays of articles
 *   images/{file}.webp|jpg                            cover + inline images
 *
 *   php artisan blog:import-articles --marketplace=bileteonline
 *     [--path=DIR]          source folder
 *     [--only=1,2,7]        import only these article numbers ("nr")
 *     [--status=draft]      status for NEW articles: draft | published
 *     [--update]            also overwrite articles that already exist
 *     [--dry-run]           report what would happen, write nothing
 *
 * Safety: articles are matched on (marketplace, slug). An existing
 * article is skipped unless --update is passed, so edits made in the
 * admin are never overwritten by a re-run; --update keeps its status,
 * publish date and counters. Nothing is ever deleted.
 *
 * Images go on the public disk under blog-images/, the same place the
 * admin upload uses. On Ploi run it as the web user so the files are
 * writable by php-fpm:  sudo -u core-cuhlf php artisan blog:import-articles ...
 */
class ImportBlogArticlesCommand extends Command
{
    protected $signature = 'blog:import-articles
        {--marketplace= : marketplace client id, slug or domain}
        {--path= : source folder (default database/data/blog-import/{slug})}
        {--only= : comma-separated article numbers to import}
        {--status=draft : status for new articles (draft|published)}
        {--update : overwrite articles that already exist}
        {--dry-run : report only, write nothing}';

    protected $description = 'Import blog/guide articles for a marketplace from JSON batches';

    /** Category key used in the JSON batches => category name in the admin (matched case-insensitively). */
    private const CATEGORIES = [
        'city-guides' => 'City guides',
        'weekend-ideas' => 'Weekend ideas',
        'couples' => 'Couples & date night',
        'family-kids' => 'Family & kids',
        'adventure' => 'Adventure & Adrenaline',
        'culture-history' => 'Culture & history',
        'food-drink' => 'Food & drink',
        'nature-outdoor' => 'Nature & outdoor',
        'team-building' => 'Team building',
        'escape-rooms' => 'Escape rooms & games',
        'seasonal' => 'Seasonal & holidays',
        'wellness' => 'Wellness & relax',
        'guided-tours' => 'Guided tours',
        'nightlife' => 'Nightlife',
        'budget-deals' => 'Budget & deals',
        'rainy-day' => 'Rainy day / indoor',
    ];

    private const IMAGE_DIR = 'blog-images';

    public function handle(): int
    {
        $marketplace = $this->resolveMarketplace((string) $this->option('marketplace'));
        if (! $marketplace) {
            $this->error('Marketplace not found. Pass --marketplace=<id|slug|domain>.');

            return self::FAILURE;
        }

        $status = (string) $this->option('status');
        if (! in_array($status, ['draft', 'published'], true)) {
            $this->error('--status must be draft or published.');

            return self::FAILURE;
        }

        $path = $this->option('path') ?: database_path('data/blog-import/' . $marketplace->slug);
        if (! File::isDirectory($path) && File::isDirectory(base_path($path))) {
            $path = base_path($path);
        }
        $path = rtrim($path, '/\\');
        $files = File::isDirectory($path) ? File::glob($path . '/articole-lot-*.json') : [];
        sort($files);
        if (! $files) {
            $this->error("No articole-lot-*.json in {$path}");

            return self::FAILURE;
        }

        $lang = $marketplace->language ?? $marketplace->locale ?? 'en';
        $dryRun = (bool) $this->option('dry-run');
        $update = (bool) $this->option('update');
        $only = array_filter(array_map('intval', explode(',', (string) $this->option('only'))));
        $categories = $this->categoryIds($marketplace);

        $articles = [];
        foreach ($files as $file) {
            $batch = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', File::get($file)), true);
            if (! is_array($batch)) {
                $this->error(basename($file) . ': invalid JSON.');

                return self::FAILURE;
            }
            foreach ($batch as $row) {
                if (is_array($row) && (! $only || in_array((int) ($row['nr'] ?? 0), $only, true))) {
                    $articles[] = $row;
                }
            }
        }

        // Every article must have a known category and a valid slug before anything is written.
        $problems = [];
        foreach ($articles as $row) {
            $label = '#' . ($row['nr'] ?? '?') . ' ' . ($row['slug'] ?? '');
            if (! preg_match('/^[a-z0-9][a-z0-9-]*$/', (string) ($row['slug'] ?? ''))) {
                $problems[] = "{$label}: invalid slug";
            }
            if (trim((string) ($row['title'] ?? '')) === '' || trim((string) ($row['content_html'] ?? '')) === '') {
                $problems[] = "{$label}: missing title or content";
            }
            if (! isset($categories[$row['category'] ?? ''])) {
                $problems[] = "{$label}: category \"" . ($row['category'] ?? '') . '" has no match in the admin (expected "'
                    . (self::CATEGORIES[$row['category'] ?? ''] ?? '?') . '")';
            }
        }
        if ($problems) {
            foreach ($problems as $problem) {
                $this->error($problem);
            }
            $this->error('Nothing was imported.');

            return self::FAILURE;
        }

        $this->info(sprintf('%s: %d articles from %d batches, language "%s"%s', $marketplace->name, count($articles), count($files), $lang, $dryRun ? ' (dry run)' : ''));

        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $now = now();
        foreach ($articles as $index => $row) {
            $slug = $row['slug'];
            $existing = BlogArticle::withTrashed()
                ->where('marketplace_client_id', $marketplace->id)
                ->where('slug', $slug)
                ->first();

            if ($existing && ! $update) {
                $counts['skipped']++;
                $this->line("  skip    #{$row['nr']} {$slug} (already exists)");

                continue;
            }

            $cover = $this->storeImage($path, (string) ($row['featured_image']['file'] ?? ''), $dryRun);
            $content = $this->prepareContent((string) $row['content_html'], $path, $dryRun);
            $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('/\[(?:activities|partner)\b[^\]]*\]/i', '', $content)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $words = $text === '' ? 0 : count(preg_split('/\s+/u', $text));

            $data = [
                'marketplace_client_id' => $marketplace->id,
                'slug' => $slug,
                'title' => [$lang => trim((string) $row['title'])],
                'subtitle' => [$lang => trim((string) ($row['subtitle'] ?? ''))],
                'excerpt' => [$lang => trim((string) ($row['excerpt'] ?? ''))],
                'content' => [$lang => $content],
                'category_id' => $categories[$row['category']],
                'featured_image_url' => $cover,
                'featured_image_alt' => mb_substr(trim((string) ($row['featured_image']['alt'] ?? '')), 0, 255) ?: null,
                'focus_keyword' => trim((string) ($row['focus_keyword'] ?? '')) ?: null,
                'secondary_keywords' => implode(', ', array_filter(array_map('trim', (array) ($row['secondary_keywords'] ?? [])))) ?: null,
                'longtail_phrases' => implode(', ', array_filter(array_map('trim', (array) ($row['longtail_phrases'] ?? [])))) ?: null,
                'meta_title' => [$lang => mb_substr(trim((string) ($row['meta_title'] ?? $row['title'])), 0, 60)],
                'meta_description' => [$lang => mb_substr(trim((string) ($row['meta_description'] ?? $row['excerpt'] ?? '')), 0, 160)],
                'og_title' => [$lang => mb_substr(trim((string) ($row['og_title'] ?? $row['title'])), 0, 60)],
                'og_description' => [$lang => mb_substr(trim((string) ($row['og_description'] ?? $row['excerpt'] ?? '')), 0, 200)],
                'og_image_url' => $cover,
                'twitter_card' => 'summary_large_image',
                'schema_markup' => ['type' => 'BlogPosting', 'publisher_name' => $marketplace->name],
                'faqs' => $this->faqs($row['faqs'] ?? []),
                'language' => $lang,
                'word_count' => $words,
                'reading_time_minutes' => max(1, (int) ceil($words / 200)),
            ];

            if ($existing) {
                $counts['updated']++;
                $this->line("  update  #{$row['nr']} {$slug}");
                if (! $dryRun) {
                    $existing->update($data);
                }

                continue;
            }

            $counts['created']++;
            $this->line("  create  #{$row['nr']} {$slug} [{$status}]" . ($cover ? '' : ' (no cover image)'));
            if (! $dryRun) {
                BlogArticle::create($data + [
                    'status' => $status,
                    // A minute apart, first article newest, so the list keeps the batch order.
                    'published_at' => $status === 'published' ? $now->copy()->subMinutes($index) : null,
                    'visibility' => 'public',
                    'is_featured' => false,
                    'no_index' => false,
                ]);
            }
        }

        $this->info("Created {$counts['created']}, updated {$counts['updated']}, skipped {$counts['skipped']}.");

        return self::SUCCESS;
    }

    private function resolveMarketplace(string $value): ?MarketplaceClient
    {
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return MarketplaceClient::find((int) $value);
        }

        return MarketplaceClient::where('slug', $value)->orWhere('domain', $value)->first()
            ?? MarketplaceClient::where('domain', 'like', '%' . $value . '%')->first();
    }

    /** @return array<string,int> category key => blog_categories.id */
    private function categoryIds(MarketplaceClient $marketplace): array
    {
        $normalize = fn ($name) => preg_replace('/[^a-z0-9]+/', '', mb_strtolower((string) $name));

        $byName = [];
        foreach (BlogCategory::where('marketplace_client_id', $marketplace->id)->get() as $category) {
            foreach ((array) $category->name as $name) {
                $byName[$normalize($name)] = $category->id;
            }
        }

        $ids = [];
        foreach (self::CATEGORIES as $key => $name) {
            if (isset($byName[$normalize($name)])) {
                $ids[$key] = $byName[$normalize($name)];
            }
        }

        return $ids;
    }

    /** Copy one image to the public disk; returns its path there, or null when the source file is missing. */
    private function storeImage(string $path, string $file, bool $dryRun): ?string
    {
        $name = pathinfo(basename($file), PATHINFO_FILENAME);
        if ($name === '') {
            return null;
        }

        foreach (['webp', 'jpg', 'jpeg', 'png'] as $extension) {
            $source = "{$path}/images/{$name}.{$extension}";
            if (File::exists($source)) {
                $target = self::IMAGE_DIR . "/{$name}.{$extension}";
                if (! $dryRun) {
                    Storage::disk('public')->put($target, File::get($source));
                }

                return $target;
            }
        }

        return null;
    }

    /**
     * The body as the admin editor would store it: inline images point at the public disk (an image whose
     * file is missing is dropped with its paragraph) and text left outside a block is wrapped in a paragraph.
     */
    private function prepareContent(string $html, string $path, bool $dryRun): string
    {
        $html = preg_replace_callback('#<p>\s*<img\b[^>]*\bsrc="IMG:([^"]+)"[^>]*>\s*</p>#i', function ($m) use ($path, $dryRun) {
            $stored = $this->storeImage($path, $m[1], $dryRun);
            if (! $stored) {
                return '';
            }
            preg_match('/\balt="([^"]*)"/i', $m[0], $alt);

            return '<p><img src="' . e(Storage::disk('public')->url($stored)) . '" alt="' . ($alt[1] ?? '') . '" loading="lazy"></p>';
        }, $html);

        // "…</p> Stray sentence.</p>" (a paragraph whose opening tag was lost) becomes a paragraph of its own.
        $html = preg_replace('#</p>\s*([^<\s][^\n]*?)</p>#u', "</p>\n<p>$1</p>", $html);

        return trim(preg_replace("/\n{2,}/", "\n", $html));
    }

    /** @return array<int,array{q:string,a:string}> */
    private function faqs($faqs): array
    {
        $clean = [];
        foreach ((array) $faqs as $faq) {
            $question = trim((string) ($faq['q'] ?? ''));
            $answer = trim(strip_tags((string) ($faq['a'] ?? '')));
            if ($question !== '' && $answer !== '') {
                $clean[] = ['q' => mb_substr($question, 0, 255), 'a' => $answer];
            }
        }

        return $clean;
    }
}
