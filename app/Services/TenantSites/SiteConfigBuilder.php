<?php

namespace App\Services\TenantSites;

use App\Models\Tenant;
use App\Models\TenantSite;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Construiește personalizarea unui site de tenant: ciorna inițială (din
 * valorile implicite ale template-ului) și configurația servită site-ului
 * public (GET /api/tenant-site/config).
 */
class SiteConfigBuilder
{
    public function __construct(private TemplateRegistry $templates)
    {
    }

    /** Ciorna de pornire pentru un template: tot conținutul implicit al manifestului. */
    public function defaultDraft(Tenant $tenant, string $template): array
    {
        $m = $this->templates->get($template) ?? [];
        $name = $tenant->public_name ?: $tenant->name;

        $pages = [];
        foreach ($m['pages'] ?? [] as $key => $page) {
            $pages[$key] = [
                'title' => $page['title'] ?? $page['label'] ?? '',
                'sections' => $this->toBuilderItems($page['sections'] ?? [], $m['sections'] ?? []),
            ];
        }

        return [
            'branding' => [
                'name' => $name,
                'short_name' => $this->initials($name),
                'tagline' => '',
                'logo' => null,
                'favicon' => null,
                'description' => '',
            ],
            'theme' => [
                'colors' => $m['theme']['colors'] ?? [],
                'fonts' => $m['theme']['fonts'] ?? [],
            ],
            'nav' => $m['nav'] ?? [],
            'header_cta' => $m['header_cta'] ?? ['label' => '', 'url' => ''],
            'footer' => [
                'text' => '',
                'email' => $tenant->contact_email ?? '',
                'phone' => $tenant->contact_phone ?? '',
                'facebook' => '',
                'instagram' => '',
                'tiktok' => '',
                'youtube' => '',
            ],
            'pages' => $pages,
            'languages' => ['ro'],
            // Câmpurile proprii ale template-ului (manifest "settings"), cu valorile implicite
            'settings' => collect($m['settings'] ?? [])->mapWithKeys(fn ($f) => [$f['name'] => $f['default'] ?? null])->all(),
        ];
    }

    /**
     * Completează o ciornă cu paginile pe care template-ul le are și ciorna nu
     * (după o schimbare de template, sau când template-ul primește o pagină nouă).
     */
    public function fillMissing(array $draft, Tenant $tenant, string $template): array
    {
        $defaults = $this->defaultDraft($tenant, $template);
        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $draft)) {
                $draft[$key] = $value;
            }
        }
        foreach ($defaults['pages'] as $key => $page) {
            if (! isset($draft['pages'][$key])) {
                $draft['pages'][$key] = $page;
            }
        }
        $draft['settings'] = array_merge($defaults['settings'], array_filter((array) ($draft['settings'] ?? []), fn ($v) => $v !== null && $v !== ''));
        foreach ($defaults['theme'] as $group => $values) {
            $draft['theme'][$group] = array_merge($values, array_filter($draft['theme'][$group] ?? []));
        }

        return $draft;
    }

    /** Configurația publică: ce primește site-ul (publicat sau, cu token, ciorna). */
    public function payload(TenantSite $site, string $host, bool $preview): array
    {
        $template = $preview ? $site->template : ($site->published_template ?: $site->template);
        $data = $preview ? ($site->draft ?? []) : ($site->published ?? []);
        $tenant = $site->tenant;
        $data = $this->fillMissing($data, $tenant, $template);
        $m = $this->templates->get($template) ?? [];

        $pages = [];
        foreach ($data['pages'] as $key => $page) {
            if (! isset($m['pages'][$key])) {
                continue;
            }
            $sections = [];
            foreach (array_values($page['sections'] ?? []) as $item) {
                $type = $item['type'] ?? null;
                if (! $type || ! isset($m['sections'][$type])) {
                    continue;
                }
                $sectionData = $item['data'] ?? [];
                if (($sectionData['enabled'] ?? true) === false) {
                    continue;
                }
                unset($sectionData['enabled']);
                $sections[] = [
                    'type' => $type,
                    'data' => $this->resolveImages($sectionData, $m['sections'][$type]['fields'] ?? []),
                ];
            }
            $pages[$key] = [
                'path' => $m['pages'][$key]['path'] ?? '/' . $key,
                'title' => $page['title'] ?? '',
                'description' => trim((string) ($page['description'] ?? '')),
                'sections' => $sections,
            ];
        }

        $branding = $data['branding'];
        $branding['logo'] = $this->url($branding['logo'] ?? null);
        $branding['favicon'] = $this->url($branding['favicon'] ?? null);

        return [
            'tenant_id' => $tenant->id,
            'host' => $host,
            'template' => $template,
            'preview' => $preview,
            'version' => ($preview ? $site->updated_at : $site->published_at)?->timestamp ?? 0,
            'branding' => $branding,
            'theme' => $data['theme'],
            'nav' => array_values(array_filter($data['nav'] ?? [], fn ($i) => ! empty($i['label']) && ! empty($i['url']))),
            'header_cta' => $data['header_cta'] ?? null,
            'footer' => $data['footer'] ?? [],
            'settings' => $this->resolveImages((array) ($data['settings'] ?? []), $m['settings'] ?? []),
            'pages' => $pages,
            // Limbile site-ului: română mereu prima; engleza doar dacă e bifată
            'languages' => array_values(array_unique(array_merge(['ro'], array_intersect((array) ($data['languages'] ?? ['ro']), ['ro', 'en'])))),
        ];
    }

    /**
     * Builder-ul Filament ține fiecare bloc ca {type, data}; adăugăm comutatorul
     * „vizibil”. Imaginile implicite din manifest sunt link-uri, nu fișiere din
     * storage, așa că trec în câmpul-pereche `{nume}_url`.
     */
    private function toBuilderItems(array $sections, array $defs): array
    {
        $items = [];
        foreach ($sections as $section) {
            $fields = $defs[$section['type']]['fields'] ?? [];
            $items[(string) Str::uuid()] = [
                'type' => $section['type'],
                'data' => ['enabled' => true] + $this->moveImageLinks($section['data'] ?? [], $fields),
            ];
        }

        return $items;
    }

    private function moveImageLinks(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            $name = $field['name'] ?? null;
            if (! $name || ! array_key_exists($name, $data)) {
                continue;
            }
            if (($field['type'] ?? '') === 'image' && is_string($data[$name]) && preg_match('#^https?://#', $data[$name])) {
                $data[$name . '_url'] = $data[$name];
                $data[$name] = null;
            } elseif (($field['type'] ?? '') === 'repeater' && is_array($data[$name])) {
                $data[$name] = array_map(
                    fn ($row) => is_array($row) ? $this->moveImageLinks($row, $field['fields'] ?? []) : $row,
                    $data[$name]
                );
            }
        }

        return $data;
    }

    /** Transformă recursiv câmpurile de tip imagine (căi din storage) în URL-uri absolute. */
    private function resolveImages(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            $name = $field['name'] ?? null;
            if (! $name || (! array_key_exists($name, $data) && ! array_key_exists($name . '_url', $data))) {
                continue;
            }
            $data[$name] ??= null;
            if (($field['type'] ?? '') === 'image') {
                $upload = is_array($data[$name]) ? (array_values($data[$name])[0] ?? null) : $data[$name];
                $data[$name] = $this->url($upload) ?? $this->url($data[$name . '_url'] ?? null);
                unset($data[$name . '_url']);
            } elseif (($field['type'] ?? '') === 'repeater' && is_array($data[$name])) {
                $data[$name] = array_map(
                    fn ($row) => is_array($row) ? $this->resolveImages($row, $field['fields'] ?? []) : $row,
                    array_values($data[$name])
                );
            }
        }

        return $data;
    }

    private function url(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }
        if (preg_match('#^https?://#', $path)) {
            return $path;
        }

        return Storage::disk(config('tenant-sites.disk'))->url($path);
    }

    private function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];
        $letters = array_map(fn ($w) => mb_substr($w, 0, 1), array_slice(array_filter($words), 0, 2));

        return mb_strtoupper(implode('', $letters)) ?: 'T';
    }
}
