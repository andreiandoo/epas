<?php

namespace App\Console\Commands;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceTaxTemplate;
use App\Models\Tenant;
use App\Models\TenantTaxTemplate;
use App\Services\Tenant\TenantFiscalDocuments;
use Illuminate\Console\Command;

/**
 * Copiază la un tenant șabloanele celor trei documente fiscale (cerere de avizare, declarație de impozit
 * pe spectacole, PV de distrugere) dintr-un marketplace și îl leagă de direcțiile fiscale ale acestuia.
 *
 *   php artisan tenant:copy-fiscal-templates wukf --from=ambilet
 *
 * Copiile sunt ale tenantului și se pot edita din panoul lui. Re-rularea nu calcă un șablon deja copiat
 * (și eventual modificat) decât cu --force.
 */
class TenantCopyFiscalTemplates extends Command
{
    protected $signature = 'tenant:copy-fiscal-templates
        {tenant : ID-ul sau slug-ul tenantului}
        {--from=ambilet : Marketplace-ul sursă (ID, slug sau parte din nume)}
        {--force : Rescrie șabloanele deja copiate}';

    protected $description = 'Copiază șabloanele de documente fiscale dintr-un marketplace la un tenant';

    public function handle(): int
    {
        if (! TenantFiscalDocuments::available()) {
            $this->error('Tabelele pentru documentele fiscale ale tenanților lipsesc. Rulează întâi: php artisan migrate');

            return self::FAILURE;
        }

        $key = (string) $this->argument('tenant');
        $tenant = ctype_digit($key) ? Tenant::find((int) $key) : Tenant::where('slug', $key)->first();
        if (! $tenant) {
            $this->error("Tenantul „{$key}” nu a fost găsit.");

            return self::FAILURE;
        }

        $from = (string) $this->option('from');
        $client = ctype_digit($from)
            ? MarketplaceClient::find((int) $from)
            : (MarketplaceClient::where('slug', $from)->first()
                ?: MarketplaceClient::whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($from) . '%'])->orderBy('id')->first());
        if (! $client) {
            $this->error("Marketplace-ul sursă „{$from}” nu a fost găsit.");

            return self::FAILURE;
        }

        $this->info("Tenant #{$tenant->id} {$tenant->name}  ←  marketplace #{$client->id} {$client->name}");

        $copied = 0;
        foreach (TenantTaxTemplate::TYPES as $type => $label) {
            $source = MarketplaceTaxTemplate::where('marketplace_client_id', $client->id)
                ->where('type', $type)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderByDesc('id')
                ->first();
            if (! $source) {
                $this->warn("  {$label}: marketplace-ul sursă nu are un șablon activ de tip „{$type}” — sar peste.");
                continue;
            }

            $existing = TenantTaxTemplate::where('tenant_id', $tenant->id)->where('type', $type)->first();
            if ($existing && ! $this->option('force')) {
                $this->line("  {$label}: există deja (#{$existing->id}) — păstrat. Folosește --force ca să-l rescrii.");
                continue;
            }

            $data = [
                'name'                => $label,
                'html_content'        => $source->html_content,
                'html_content_page_2' => $source->html_content_page_2,
                'page_orientation'    => $source->page_orientation ?: 'portrait',
                'general_tax_ids'     => $source->general_tax_ids,
                'source_template_id'  => $source->id,
                'is_active'           => true,
            ];
            if ($existing) {
                $existing->update($data);
            } else {
                TenantTaxTemplate::create($data + ['tenant_id' => $tenant->id, 'type' => $type]);
            }
            $copied++;
            $this->line("  {$label}: copiat din șablonul #{$source->id} „{$source->name}”.");
        }

        // Direcțiile fiscale (cu cota de impozit) rămân ale marketplace-ului; tenantul le folosește pe ale lui.
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $settings['fiscal'] = array_merge(is_array($settings['fiscal'] ?? null) ? $settings['fiscal'] : [], [
            'source_marketplace_client_id' => $client->id,
        ]);
        $tenant->update(['settings' => $settings]);

        $this->newLine();
        $this->info("Gata: {$copied} șabloane copiate. Direcții fiscale: cele ale marketplace-ului #{$client->id}.");
        $this->line('  Șabloanele pot menționa marketplace-ul ca intermediar sau un împuternicit; verifică-le textul în panou:');
        $this->line('  ' . rtrim((string) config('app.url'), '/') . '/tenant/fiscal-templates');

        return self::SUCCESS;
    }
}
