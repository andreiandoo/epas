<?php

namespace App\Support\Manual;

use App\Filament\Marketplace\Pages\UserManual\EventsManual;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Component\Yaml\Yaml;

/**
 * Contextual admin page manual.
 *
 * Every admin page with a manual has a folder of markdown chapters under
 * resources/manual/{dir}, ordered by file name. Each file starts with a YAML
 * front matter block:
 *
 *   id:          stable chapter anchor (epm-ch-{id})
 *   chapter:     group title in the table of contents
 *   title:       chapter title
 *   tab:         Filament tab key the chapter explains (opens on that tab)
 *   applies_to:  toate | standard | agrement
 *   covers:      form section / tab labels the chapter documents
 *   fields:      form field keys explained in the chapter
 *   updated:     last content review (Y-m-d)
 *
 * The rendered chapters feed both the in-page drawer ("Manual pagină") and the
 * full page in the Help hub. `covers` + `fields` are what
 * `php artisan manual:coverage` checks against the Filament form.
 */
class PageManual
{
    public const PAGES = [
        'event-edit' => [
            'dir' => 'marketplace/event-edit',
            'title' => 'Pagina evenimentului',
            'description' => 'Fiecare câmp, opțiune și buton din pagina de editare a unui eveniment, explicate pe înțeles, plus scenarii concrete de folosire.',
            'hub' => EventsManual::class,
            'form_sources' => [
                [
                    'path' => 'app/Filament/Marketplace/Resources/EventResource.php',
                    'from' => 'public static function form(',
                    'to' => 'public static function table(',
                ],
            ],
            // Filament tab keys => labels. The first one is the tab Filament opens by default.
            'tabs' => [
                'detalii' => 'Detalii',
                'vanzari' => 'Vânzări',
                'deconturi' => 'Deconturi',
                'continut' => 'Conținut',
                'venue-config' => 'Configurare Locație',
                'bilete' => 'Bilete',
                'harta' => 'Harta',
                'turneu' => 'Grupare',
                'observatii' => 'Observații',
                'documente' => 'Documente',
            ],
        ],
    ];

    public static function exists(string $page): bool
    {
        return isset(self::PAGES[$page]);
    }

    public static function config(string $page): array
    {
        if (! self::exists($page)) {
            throw new \InvalidArgumentException("Unknown manual page [{$page}].");
        }

        return self::PAGES[$page];
    }

    /**
     * @return array{page: string, title: string, description: string, tabs: array<string, string>, chapters: list<array<string, mixed>>}
     */
    public static function load(string $page): array
    {
        $config = self::config($page);
        $files = glob(resource_path('manual/' . $config['dir'] . '/*.md')) ?: [];
        sort($files, SORT_STRING);

        // Keyed on names + mtimes + sizes, so a deploy invalidates it on its own.
        $signature = md5(implode('|', array_map(
            fn (string $file) => basename($file) . ':' . filemtime($file) . ':' . filesize($file),
            $files
        )));

        return Cache::remember(
            "page-manual:{$page}:{$signature}",
            now()->addDay(),
            fn () => self::build($page, $config, $files)
        );
    }

    /**
     * Normalises a form section label or a `covers` entry for comparison
     * (drops leading emoji/punctuation, lowercases).
     */
    public static function groupKey(string $label): string
    {
        return Str::lower(trim(preg_replace('/^[^\p{L}\p{N}]+/u', '', $label) ?? $label));
    }

    private static function build(string $page, array $config, array $files): array
    {
        $converter = self::converter();
        $chapters = [];

        foreach ($files as $file) {
            [$meta, $body] = self::splitFrontMatter((string) file_get_contents($file));
            $name = pathinfo($file, PATHINFO_FILENAME);
            $id = (string) ($meta['id'] ?? Str::slug(preg_replace('/^[\d-]+/', '', $name) ?: $name));

            [$html, $sections] = self::wrapSections((string) $converter->convert($body), $id);

            $chapters[] = [
                'id' => $id,
                'chapter' => (string) ($meta['chapter'] ?? $meta['title'] ?? $name),
                'title' => (string) ($meta['title'] ?? $name),
                'tab' => isset($meta['tab']) ? (string) $meta['tab'] : null,
                'applies_to' => (string) ($meta['applies_to'] ?? 'toate'),
                'covers' => array_values(array_map('strval', (array) ($meta['covers'] ?? []))),
                'fields' => array_values(array_map('strval', (array) ($meta['fields'] ?? []))),
                'updated' => self::dateString($meta['updated'] ?? null),
                'sections' => $sections,
                'html' => $html,
            ];
        }

        return [
            'page' => $page,
            'title' => $config['title'],
            'description' => $config['description'],
            'tabs' => $config['tabs'],
            'chapters' => $chapters,
        ];
    }

    private static function converter(): MarkdownConverter
    {
        $environment = new Environment([
            // Chapters are written by us and versioned in the repo; raw HTML is
            // allowed for the <details> Q&A blocks.
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'attributes' => ['allow' => ['id', 'class']],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new AttributesExtension());

        return new MarkdownConverter($environment);
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private static function splitFrontMatter(string $raw): array
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $raw = str_replace("\r\n", "\n", $raw);

        if (preg_match('/\A---\n(.*?)\n---\n?(.*)\z/s', $raw, $matches)) {
            $meta = Yaml::parse($matches[1]);

            return [is_array($meta) ? $meta : [], $matches[2]];
        }

        return [[], $raw];
    }

    /**
     * Wraps every <h2> block in a <section> carrying the anchor id, so search
     * can hide/show whole sections and links can jump to them. Headings
     * written as `## Titlu {#ancora}` keep their explicit id.
     *
     * @return array{0: string, 1: list<array{id: string, title: string}>}
     */
    private static function wrapSections(string $html, string $chapterId): array
    {
        $html = str_replace(['<table>', '</table>'], ['<div class="epm-table"><table>', '</table></div>'], $html);
        $parts = preg_split('/(?=<h2[\s>])/', $html) ?: [$html];
        $sections = [];
        $usedIds = [];
        $out = '';

        foreach ($parts as $part) {
            if (! str_starts_with($part, '<h2')) {
                if (trim($part) !== '') {
                    $out .= '<div class="epm-sec epm-intro">' . $part . '</div>';
                }

                continue;
            }

            preg_match('/\A<h2([^>]*)>(.*?)<\/h2>/s', $part, $heading);
            $attributes = $heading[1] ?? '';
            $title = trim(html_entity_decode(strip_tags($heading[2] ?? ''), ENT_QUOTES | ENT_HTML5));

            if (preg_match('/\sid="([^"]+)"/', $attributes, $idMatch)) {
                $sectionId = $idMatch[1];
                $part = preg_replace('/\A<h2([^>]*?)\sid="[^"]+"/', '<h2$1', $part, 1) ?? $part;
            } else {
                $sectionId = $chapterId . '--' . (Str::slug($title) ?: 'sectiune');
            }

            $baseId = $sectionId;
            for ($i = 2; isset($usedIds[$sectionId]); $i++) {
                $sectionId = $baseId . '-' . $i;
            }
            $usedIds[$sectionId] = true;

            $sections[] = ['id' => $sectionId, 'title' => $title];
            $out .= '<section class="epm-sec" id="' . e($sectionId) . '">' . $part . '</section>';
        }

        return [$out, $sections];
    }

    private static function dateString(mixed $value): ?string
    {
        if (is_int($value)) {
            return date('Y-m-d', $value);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return filled($value) ? (string) $value : null;
    }
}
