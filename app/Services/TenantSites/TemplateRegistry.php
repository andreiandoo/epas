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

    /** @return array<string, string> slug => nume, toate template-urile (inclusiv draft și private) */
    public function options(): array
    {
        return array_map(fn (array $m) => $m['name'] ?? '', $this->all());
    }

    /**
     * Poate folosi tenantul template-ul? Publicele (`visibility: public`, implicit)
     * da; cele private („custom” pentru un client, exclusive cât le folosește) doar
     * tenanții din `tenants`. Cele în `status: draft` nu se aleg încă.
     */
    public function availableTo(string $slug, ?int $tenantId): bool
    {
        $m = $this->get($slug);
        if (! $m || ($m['status'] ?? 'ready') === 'draft') {
            return false;
        }
        if (($m['visibility'] ?? 'public') === 'private') {
            return $tenantId !== null && in_array($tenantId, array_map('intval', (array) ($m['tenants'] ?? [])), true);
        }

        return true;
    }

    /**
     * Opțiunile din Tenants → Site, grupate pe categorii. Template-ul folosit
     * acum de tenant rămâne în listă chiar dacă între timp a devenit indisponibil.
     *
     * @return array<string, array<string, string>>
     */
    public function groupedOptionsFor(?int $tenantId, ?string $current = null): array
    {
        $groups = [];
        foreach ($this->all() as $slug => $m) {
            if ($slug !== $current && ! $this->availableTo($slug, $tenantId)) {
                continue;
            }
            $label = ($m['name'] ?? $slug)
                . (($m['visibility'] ?? 'public') === 'private' ? ' (custom)' : '')
                . (($m['status'] ?? 'ready') === 'beta' ? ' (beta)' : '');
            $groups[$m['category'] ?? 'Altele'][$slug] = $label;
        }
        ksort($groups);

        return $groups;
    }

    /** Template-urile care pot primi un demo public (nu cele custom). */
    public function demoable(): array
    {
        return array_filter($this->all(), fn (array $m) => ($m['visibility'] ?? 'public') === 'public');
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
