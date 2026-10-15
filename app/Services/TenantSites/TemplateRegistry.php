<?php

namespace App\Services\TenantSites;

/**
 * Template-urile site-urilor de tenant: câte un folder în
 * resources/tenant-sites/templates, descris de template.json (paginile
 * personalizabile, secțiunile și câmpurile lor, tema implicită). Panoul din
 * core și site-ul public citesc același fișier.
 */
class TemplateRegistry
{
    /** @var array<string, array>|null */
    private ?array $templates = null;

    /** @return array<string, array> slug => manifest */
    public function all(): array
    {
        if ($this->templates !== null) {
            return $this->templates;
        }

        $this->templates = [];
        $base = config('tenant-sites.templates_path');
        foreach (glob($base . '/*/template.json') ?: [] as $file) {
            $manifest = json_decode((string) file_get_contents($file), true);
            if (! is_array($manifest)) {
                continue;
            }
            $this->templates[basename(dirname($file))] = $manifest;
        }
        ksort($this->templates);

        return $this->templates;
    }

    public function get(?string $slug): ?array
    {
        return $slug ? ($this->all()[$slug] ?? null) : null;
    }

    /** @return array<string, string> slug => nume, pentru select-uri */
    public function options(): array
    {
        return array_map(fn (array $m) => $m['name'] ?? '', $this->all());
    }

    /**
     * Definițiile câmpurilor unei secțiuni, cu cele de tip imagine găsite
     * recursiv (și în repeatere), ca să le putem transforma în URL-uri.
     */
    public function sectionFields(string $template, string $type): array
    {
        return $this->get($template)['sections'][$type]['fields'] ?? [];
    }
}
