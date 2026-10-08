<?php

namespace App\Services\Tenant;

use App\Models\Event;
use App\Models\MarketplaceTaxRegistry;
use App\Models\MarketplaceTaxTemplate;
use App\Models\Tenant;
use App\Models\TenantEventDocument;
use App\Models\TenantTaxTemplate;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Documentele fiscale ale evenimentelor de tenant: cerere de avizare, declarație de impozit pe
 * spectacole și PV de distrugere a biletelor. Fără decont și fără facturi de organizator.
 *
 * Refolosește motorul de variabile al șabloanelor de marketplace (MarketplaceTaxTemplate::
 * getVariablesForContext), cu două diferențe: organizatorul este tenantul însuși (datele firmei vin de
 * pe tenant), iar între el și primărie nu există un marketplace, deci variabilele de intermediar rămân goale.
 * TVA-ul din declarația de impozit urmează setarea tenantului (vat_payer + settings.fiscal.vat_rate) — calculul
 * e în MarketplaceTaxTemplate::getVariablesForContext, ramura pentru evenimente de tenant.
 *
 * Direcțiile fiscale (cu cota de impozit) sunt ținute de un marketplace; tenantul le folosește pe cele
 * ale marketplace-ului din care și-a copiat șabloanele (settings.fiscal.source_marketplace_client_id).
 */
class TenantFiscalDocuments
{
    /** Când poate fi generat fiecare document. */
    public const WHEN = [
        'cerere_avizare'      => 'published',   // după publicarea evenimentului
        'declaratie_impozite' => 'finished',    // după încheierea evenimentului
        'pv_distrugere'       => 'finished',
    ];

    public static function available(): bool
    {
        try {
            return Schema::hasTable('tenant_tax_templates') && Schema::hasTable('tenant_event_documents');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Marketplace-ul ale cărui direcții fiscale le folosește tenantul. */
    public static function sourceClientId(?Tenant $tenant): ?int
    {
        $id = $tenant && is_array($tenant->settings) ? ($tenant->settings['fiscal']['source_marketplace_client_id'] ?? null) : null;

        return $id ? (int) $id : null;
    }

    /** @return Collection<string, TenantTaxTemplate> șabloanele active ale tenantului, pe tip */
    public static function templates(Tenant $tenant): Collection
    {
        return TenantTaxTemplate::where('tenant_id', $tenant->id)->where('is_active', true)->get()->keyBy('type');
    }

    /** Direcția fiscală a evenimentului: cea aleasă pe eveniment, altfel cea potrivită după locație. */
    public static function registryFor(Event $event, ?Tenant $tenant): ?MarketplaceTaxRegistry
    {
        if ($event->marketplace_tax_registry_id) {
            $registry = MarketplaceTaxRegistry::find($event->marketplace_tax_registry_id);
            if ($registry) {
                return $registry;
            }
        }
        $clientId = self::sourceClientId($tenant);

        return ($clientId && $event->venue) ? MarketplaceTaxRegistry::matchForVenue($event->venue, $clientId) : null;
    }

    /** Evenimentul îndeplinește condiția de generare a documentului? */
    public static function canGenerate(Event $event, string $type): bool
    {
        return match (self::WHEN[$type] ?? null) {
            'published' => (bool) $event->is_published,
            'finished'  => self::isFinished($event),
            default     => false,
        };
    }

    public static function isFinished(Event $event): bool
    {
        try {
            if (method_exists($event, 'isPast')) {
                return (bool) $event->isPast();
            }
            $end = $event->end_date ?: $event->start_date;

            return $end ? $end->endOfDay()->isPast() : false;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Generează PDF-ul și îl înregistrează. Aruncă excepție cu mesaj pentru utilizator dacă nu se poate. */
    public static function generate(Event $event, string $type, ?User $by = null): TenantEventDocument
    {
        $tenant = Tenant::find($event->tenant_id);
        if (! $tenant) {
            throw new \RuntimeException('Evenimentul nu aparține unui tenant.');
        }
        $own = self::templates($tenant)->get($type);
        if (! $own || trim(strip_tags((string) $own->html_content)) === '') {
            throw new \RuntimeException('Nu există un șablon activ pentru acest document.');
        }
        if (! self::canGenerate($event, $type)) {
            throw new \RuntimeException(self::WHEN[$type] === 'published'
                ? 'Documentul se poate genera după publicarea evenimentului.'
                : 'Documentul se poate genera după încheierea evenimentului.');
        }

        $event->loadMissing(['venue', 'ticketTypes', 'eventTypes']);
        $registry = self::registryFor($event, $tenant);

        // Șablonul tenantului, îmbrăcat într-un șablon de marketplace nesalvat, ca să treacă prin același motor de variabile
        $template = new MarketplaceTaxTemplate();
        $template->forceFill([
            'name'                => $own->name,
            'type'                => $own->type,
            'html_content'        => (string) $own->html_content,
            'html_content_page_2' => $own->html_content_page_2,
            'page_orientation'    => $own->page_orientation ?: 'portrait',
            'general_tax_ids'     => $own->general_tax_ids,
            'is_active'           => true,
        ]);

        $variables = MarketplaceTaxTemplate::getVariablesForContext(
            $registry,
            null,
            null,
            $event,
            null,
            incrementContractNumber: false,
            template: $template,
        );
        $variables = array_merge($variables, self::tenantVariables($tenant));

        $html = $template->processTemplate($variables);
        if (MarketplaceTaxTemplate::hasMeaningfulContent($template->html_content_page_2)) {
            $page2 = (string) $template->html_content_page_2;
            foreach ($variables as $key => $value) {
                if (is_array($value)) {
                    continue;
                }
                $page2 = preg_replace('/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/', (string) ($value ?? ''), $page2);
            }
            $html .= '<div style="page-break-before: always;"></div>' . $page2;
        }
        // Variabilele fără valoare pentru un tenant (intermediar, împuternicit, garant) nu trebuie să rămână ca text în PDF
        $html = preg_replace('/\{\{\s*(marketplace|proxy|guarantor|organizer|individual|contract)_[a-z0-9_]+\s*\}\}/i', '', $html);

        // Entitățile numerice → UTF-8 (DomPDF pierde diacriticele altfel; la fel ca în EventGeneratedDocument)
        $html = preg_replace_callback('/&#(x[0-9a-fA-F]+|[0-9]+);/', function ($m) {
            $code = $m[1];
            $cp = ($code[0] === 'x' || $code[0] === 'X') ? hexdec(substr($code, 1)) : (int) $code;

            return ($cp <= 0 || $cp > 0x10FFFF) ? $m[0] : mb_chr($cp, 'UTF-8');
        }, $html);

        $pdf = Pdf::loadHTML($html)->setPaper('a4', $template->page_orientation === 'landscape' ? 'landscape' : 'portrait');

        $eventName = is_array($event->title) ? ($event->title['ro'] ?? $event->title['en'] ?? 'eveniment') : ((string) $event->title ?: 'eveniment');
        $filename = sprintf('%s_%s_%s.pdf', Str::slug($eventName), Str::slug($own->name), now()->format('Y-m-d_His'));
        $directory = 'documents/events/' . $event->id;
        $path = $directory . '/' . $filename;

        Storage::disk('public')->makeDirectory($directory);
        $binary = $pdf->output();
        Storage::disk('public')->put($path, $binary);

        return TenantEventDocument::create([
            'tenant_id'              => $tenant->id,
            'event_id'               => $event->id,
            'tenant_tax_template_id' => $own->id,
            'type'                   => $type,
            'filename'               => $filename,
            'file_path'              => $path,
            'file_size'              => strlen($binary),
            'generated_by_id'        => $by?->id,
            'generated_by_name'      => $by?->name,
            'meta'                   => ['tax_registry_id' => $registry?->id, 'tax_registry' => $registry?->name],
        ]);
    }

    public static function delete(TenantEventDocument $document): void
    {
        try {
            Storage::disk('public')->delete($document->file_path);
        } catch (\Throwable) {
            // fișierul poate lipsi deja
        }
        $document->delete();
    }

    /** Datele organizatorului, luate de pe tenant (el este organizatorul propriilor evenimente). */
    private static function tenantVariables(Tenant $tenant): array
    {
        $company = $tenant->company_name ?: ($tenant->public_name ?: $tenant->name);
        $person = trim(($tenant->contact_first_name ?? '') . ' ' . ($tenant->contact_last_name ?? ''));
        $isVat = (bool) $tenant->vat_payer;

        return [
            'organizer_name'                      => $tenant->public_name ?: $tenant->name,
            'organizer_company_name'              => $company,
            'organizer_tax_id'                    => (string) $tenant->cui,
            'organizer_tax_id_label'              => 'C.I.F.',
            'organizer_registration_number'       => (string) $tenant->reg_com,
            'organizer_registration_number_label' => 'Reg. Com. Nr.',
            'organizer_address'                   => (string) $tenant->address,
            'organizer_city'                      => (string) $tenant->city,
            'organizer_county'                    => (string) $tenant->state,
            'organizer_email'                     => (string) $tenant->contact_email,
            'organizer_phone'                     => (string) $tenant->contact_phone,
            'organizer_bank_name'                 => (string) $tenant->bank_name,
            'organizer_iban'                      => (string) $tenant->bank_account,
            'organizer_vat_status'                => $isVat ? 'plătitor de TVA' : 'neplătitor de TVA',
            'organizer_signed_name'               => $person,
            // Reprezentantul care semnează: persoana de contact a tenantului
            'proxy_full_name'                     => $person,
            'proxy_role'                          => (string) $tenant->contact_position,
        ];
    }
}
