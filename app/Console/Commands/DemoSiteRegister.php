<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\TenantSiteDemo;
use App\Services\TenantSites\TemplateRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Înregistrează (sau actualizează) un demo live de template: demo-{slug}.tics.ro
 * pe datele unui tenant demo, listat în galeria demo.tics.ro. Același lucru ca
 * Tenants → Demo-uri site → Creează, din linia de comandă.
 *
 *   php artisan demo:site teatru 17 teatru --title="Teatru clasic"
 *
 * Idempotent: domeniul și intrarea se refolosesc; pune settings.demo_checkout pe tenant.
 */
class DemoSiteRegister extends Command
{
    protected $signature = 'demo:site
        {template : Template-ul (folderul din resources/tenant-sites/templates)}
        {tenant : ID-ul tenantului demo (datele)}
        {slug : Adresa: demo-{slug}.tics.ro}
        {--title= : Titlul din galerie (implicit: numele template-ului)}
        {--description= : Descrierea din galerie (implicit: descrierea template-ului)}
        {--sort=0 : Ordinea în galerie}
        {--hidden : Nu apare în galerie (doar link direct)}';

    protected $description = 'Demo live de template pe demo-{slug}.tics.ro, pe datele unui tenant demo';

    public function handle(TemplateRegistry $templates): int
    {
        $template = (string) $this->argument('template');
        $manifest = $templates->get($template);
        if (! $manifest) {
            $this->error("Template inexistent: {$template}. Disponibile: " . implode(', ', array_keys($templates->all())));
            return self::FAILURE;
        }

        $tenant = Tenant::find((int) $this->argument('tenant'));
        if (! $tenant) {
            $this->error('Tenant inexistent: #' . $this->argument('tenant'));
            return self::FAILURE;
        }

        $slug = strtolower(trim((string) $this->argument('slug')));
        if (! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $slug)) {
            $this->error('Slug invalid: doar litere mici, cifre și cratimă.');
            return self::FAILURE;
        }
        $base = (string) config('tenant-sites.demo_base_domain', 'tics.ro');
        $host = 'demo-' . $slug . '.' . $base;

        $domain = Domain::where('domain', $host)->first();
        if ($domain && (int) $domain->tenant_id !== (int) $tenant->id) {
            $this->error("{$host} aparține deja tenantului #{$domain->tenant_id}. Oprit, nu am modificat nimic.");
            return self::FAILURE;
        }
        if (! $domain) {
            $domain = Domain::create([
                'tenant_id' => $tenant->id,
                'domain' => $host,
                'is_primary' => false,
                'is_active' => true,
                'is_managed_subdomain' => true,
                'subdomain' => 'demo-' . $slug,
                'base_domain' => $base,
                'activated_at' => now(),
                'notes' => 'Demo de template (demo:site)',
            ]);
            $this->line("  domeniu creat: {$host}");
        } elseif (! $domain->is_active) {
            $domain->update(['is_active' => true]);
            $this->line("  domeniu reactivat: {$host}");
        } else {
            $this->line("  domeniu existent: {$host}");
        }

        $demo = TenantSiteDemo::updateOrCreate(
            ['domain_id' => $domain->id],
            [
                'tenant_id' => $tenant->id,
                'template' => $template,
                'title' => $this->option('title') ?: ($manifest['name'] ?? $template),
                'description' => $this->option('description') ?: ($manifest['description'] ?? null),
                'is_listed' => ! $this->option('hidden'),
                'sort' => (int) $this->option('sort'),
            ]
        );

        $settings = $tenant->settings ?? [];
        $settings['demo_checkout'] = true;
        $tenant->update(['settings' => $settings]);

        Cache::forget("domain_tenant_{$host}");

        $this->info("Demo #{$demo->id}: https://{$host}  (template {$template}, tenant #{$tenant->id})");
        $this->line('  Galerie: https://demo.' . $base);

        return self::SUCCESS;
    }
}
