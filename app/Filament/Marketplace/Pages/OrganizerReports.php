<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Reports\ConfigurableReportPage;
use App\Filament\Marketplace\Resources\OrganizerResource;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceTaxRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrganizerReports extends ConfigurableReportPage
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Rapoarte organizatori';

    protected static ?string $title = 'Rapoarte organizatori';

    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'organizer-reports';

    protected ?array $registryNames = null;

    protected static function entity(): string
    {
        return 'organizers';
    }

    public function introText(): ?string
    {
        return 'Alegi coloanele din datele organizatorilor și salvezi raportul. Rapoartele sunt comune pentru toți adminii. '
            .'Numele organizatorului deschide fișa lui de editare într-un tab nou.';
    }

    protected function baseQuery(): Builder
    {
        return MarketplaceOrganizer::query()
            ->where('marketplace_organizers.marketplace_client_id', static::getMarketplaceClient()?->id);
    }

    protected function titleColumnKey(): string
    {
        return 'name';
    }

    protected function editUrl(Model $record): string
    {
        return OrganizerResource::getUrl('edit', ['record' => $record]);
    }

    protected function exportBaseName(): string
    {
        return 'organizatori';
    }

    protected function presets(): array
    {
        return [
            [
                'name' => 'Comisioane',
                'columns' => [
                    'commission_rate_effective',
                    'commission_mode_effective',
                    'fixed_commission_default',
                    'commission_use_floor',
                    'marketplace_manages_documents',
                    'document_filing_exceptions',
                ],
            ],
        ];
    }

    protected function registryName(int $id): string
    {
        if ($this->registryNames === null) {
            $this->registryNames = MarketplaceTaxRegistry::query()
                ->where('marketplace_client_id', static::getMarketplaceClient()?->id)
                ->get(['id', 'name', 'subname'])
                ->mapWithKeys(fn ($r) => [$r->id => trim($r->name.($r->subname ? ' · '.$r->subname : ''))])
                ->all();
        }

        return $this->registryNames[$id] ?? ('#'.$id);
    }

    protected function catalog(): array
    {
        $mp = static::getMarketplaceClient();
        $mpName = $mp?->public_name ?: ($mp?->name ?: 'Marketplace');
        $mpRate = (float) ($mp?->commission_rate ?? 0);
        $mpMode = $mp?->commission_mode ?: 'included';
        $modeOptions = ['included' => 'Inclus în preț', 'added_on_top' => 'Peste preț'];
        $hasFile = fn (string $col) => "({$col} IS NOT NULL AND {$col} <> '')";

        $text = fn (string $label, string $group, array $extra = []) => ['label' => $label, 'group' => $group, 'type' => 'text'] + $extra;
        $bool = fn (string $label, string $group) => ['label' => $label, 'group' => $group, 'type' => 'bool'];
        $fileCol = fn (string $col, string $label, string $group) => [
            'label' => $label, 'group' => $group, 'type' => 'bool',
            'expr' => $hasFile($col), 'select' => true,
        ];

        $g = [
            'cont' => 'Cont',
            'tip' => 'Tip și contract',
            'comision' => 'Comision',
            'doc' => 'Documente și depunere',
            'firma' => 'Firmă (PJ)',
            'pf' => 'Persoană fizică',
            'garant' => 'Garant',
            'banca' => 'Bancă și facturare',
            'emitent2' => 'Al doilea emitent',
            'setari' => 'Setări',
            'stats' => 'Statistici și sold',
        ];

        $columns = [
            // Cont
            'name' => $text('Organizator', $g['cont']),
            'email' => $text('Email', $g['cont']),
            'contact_name' => $text('Persoană de contact', $g['cont']),
            'phone' => $text('Telefon', $g['cont']),
            'slug' => $text('Slug', $g['cont']),
            'status' => [
                'label' => 'Status', 'group' => $g['cont'], 'type' => 'enum',
                'options' => ['active' => 'Activ', 'pending' => 'În așteptare', 'suspended' => 'Suspendat', 'inactive' => 'Inactiv'],
            ],
            'verified_at' => ['label' => 'Verificat la', 'group' => $g['cont'], 'type' => 'datetime'],
            'email_verified_at' => ['label' => 'Email confirmat la', 'group' => $g['cont'], 'type' => 'datetime'],
            'is_public' => $bool('Public', $g['cont']),
            'is_featured' => $bool('Recomandat', $g['cont']),
            'city' => $text('Oraș', $g['cont']),
            'state' => $text('Județ', $g['cont']),
            'website' => $text('Website', $g['cont']),
            'social_links' => [
                'label' => 'Rețele sociale', 'group' => $g['cont'], 'type' => 'list', 'filterable' => false, 'sortable' => false,
                'state' => fn ($r) => array_values(array_filter((array) ($r->social_links ?? []), fn ($v) => is_string($v) && $v !== '')),
            ],
            'description' => $text('Descriere', $g['cont'], ['limit' => 80]),
            'created_at' => ['label' => 'Creat la', 'group' => $g['cont'], 'type' => 'datetime'],
            'updated_at' => ['label' => 'Modificat la', 'group' => $g['cont'], 'type' => 'datetime'],

            // Tip și contract
            'person_type' => [
                'label' => 'Tip persoană', 'group' => $g['tip'], 'type' => 'enum',
                'options' => ['pj' => 'Persoană juridică', 'pf' => 'Persoană fizică'],
            ],
            'work_mode' => [
                'label' => 'Mod de lucru', 'group' => $g['tip'], 'type' => 'enum',
                'options' => ['exclusive' => 'Exclusiv', 'non_exclusive' => 'Neexclusiv'],
            ],
            'organizer_type' => [
                'label' => 'Tip organizator', 'group' => $g['tip'], 'type' => 'enum',
                'options' => [
                    'agency' => 'Agenție', 'promoter' => 'Promotor independent', 'venue' => 'Locație / sală',
                    'artist' => 'Artist / manager', 'ngo' => 'ONG / fundație', 'leisure' => 'Leisure', 'other' => 'Altul',
                ],
            ],
            'leisure_template_variant' => $text('Variantă template leisure', $g['tip']),
            'contract_state' => [
                'label' => 'Contract', 'group' => $g['tip'], 'type' => 'enum',
                'options' => ['semnat' => 'Semnat', 'nesemnat' => 'Nesemnat', 'exceptat' => 'Exceptat'],
                'expr' => "CASE WHEN marketplace_organizers.created_at < ? THEN 'exceptat' WHEN marketplace_organizers.contract_signed_at IS NOT NULL THEN 'semnat' ELSE 'nesemnat' END",
                'bindings' => [MarketplaceOrganizer::CONTRACT_SIGNATURE_REQUIRED_FROM.' 00:00:00'],
                'state' => fn ($r) => ! $r->isContractSignatureRequired() ? 'exceptat' : ($r->hasSignedContract() ? 'semnat' : 'nesemnat'),
            ],
            'contract_signed_at' => ['label' => 'Contract semnat la', 'group' => $g['tip'], 'type' => 'datetime'],
            'contract_signed_ip' => $text('IP semnare contract', $g['tip']),
            'contract_number_series' => $text('Nr./serie contract', $g['tip']),
            'contract_date' => ['label' => 'Data contract', 'group' => $g['tip'], 'type' => 'date'],
            'past_contract' => $text('Contract anterior', $g['tip'], ['limit' => 60]),
            'invoice_due_days' => ['label' => 'Termen plată factură (zile)', 'group' => $g['tip'], 'type' => 'number'],

            // Comision
            'commission_rate_effective' => [
                'label' => 'Comision (%)', 'group' => $g['comision'], 'type' => 'percent',
                'expr' => 'COALESCE(marketplace_organizers.commission_rate, ?)', 'bindings' => [$mpRate],
                'state' => fn ($r) => $r->commission_rate ?? $mpRate,
                'format' => fn ($state, $r) => $this->formatPercent($state).($r->commission_rate === null ? ' (implicit)' : ''),
            ],
            'commission_mode_effective' => [
                'label' => 'Mod comision', 'group' => $g['comision'], 'type' => 'enum', 'options' => $modeOptions,
                'expr' => 'COALESCE(marketplace_organizers.default_commission_mode, ?)', 'bindings' => [$mpMode],
                'state' => fn ($r) => $r->default_commission_mode ?: $mpMode,
                'format' => fn ($state, $r) => ($modeOptions[$state] ?? $state).($r->default_commission_mode ? '' : ' (implicit)'),
                'bulk' => [
                    'attr' => 'default_commission_mode',
                    'options' => $modeOptions + ['__null' => 'Implicit marketplace ('.($modeOptions[$mpMode] ?? $mpMode).')'],
                ],
            ],
            'fixed_commission_default' => ['label' => 'Valoare minimă comision (RON/bilet)', 'group' => $g['comision'], 'type' => 'money'],
            'commission_use_floor' => $bool('Valoare minimă activă', $g['comision']),
            'payment_fee_mode' => [
                'label' => 'Taxă procesare card', 'group' => $g['comision'], 'type' => 'enum',
                'options' => ['pass_to_customer' => 'Transferată clientului', 'absorbed_by_commission' => 'Inclusă în comision'],
                'bulk' => [
                    'attr' => 'payment_fee_mode',
                    'options' => ['pass_to_customer' => 'Transferată clientului', 'absorbed_by_commission' => 'Inclusă în comision', '__null' => 'Implicit marketplace'],
                ],
            ],
            'currency' => $text('Monedă', $g['comision']),

            // Documente și depunere
            'marketplace_manages_documents' => $bool($mpName.' depune documentele', $g['doc']),
            'document_filing_exceptions' => [
                'label' => 'Excepții de depunere', 'group' => $g['doc'], 'type' => 'list', 'filterable' => false, 'sortable' => false,
                'state' => fn ($r) => array_map(fn ($id) => $this->registryName($id), $r->filingExceptionRegistryIds()),
            ],
            'has_proxy_authorization' => $bool('Există împuternicire', $g['doc']),
            'proxy_authorization_file' => $fileCol('marketplace_organizers.proxy_authorization_file', 'Împuternicire încărcată', $g['doc']),
            'contract_document' => $fileCol('marketplace_organizers.contract_document', 'Contract încărcat', $g['doc']),
            'signature_image' => $fileCol('marketplace_organizers.signature_image', 'Semnătură', $g['doc']),
            'id_card_document' => $fileCol('marketplace_organizers.id_card_document', 'Copie CI încărcată', $g['doc']),
            'cui_document' => $fileCol('marketplace_organizers.cui_document', 'Certificat CUI încărcat', $g['doc']),

            // Firmă
            'company_name' => $text('Denumire firmă', $g['firma']),
            'company_tax_id' => $text('CUI', $g['firma']),
            'company_registration' => $text('Nr. Reg. Comerțului', $g['firma']),
            'vat_payer' => $bool('Plătitor TVA', $g['firma']),
            'company_address' => $text('Adresă firmă', $g['firma']),
            'company_city' => $text('Oraș firmă', $g['firma']),
            'company_county' => $text('Județ firmă', $g['firma']),
            'company_zip' => $text('Cod poștal firmă', $g['firma']),
            'representative_first_name' => $text('Reprezentant — prenume', $g['firma']),
            'representative_last_name' => $text('Reprezentant — nume', $g['firma']),

            // Persoană fizică
            'individual_full_name' => $text('Nume complet (PF)', $g['pf']),
            'individual_cnp' => $text('CNP (PF)', $g['pf']),
            'individual_id_series_number' => $text('Serie și nr. CI (PF)', $g['pf']),
            'individual_address' => $text('Adresă (PF)', $g['pf']),
            'individual_city' => $text('Oraș (PF)', $g['pf']),
            'individual_county' => $text('Județ (PF)', $g['pf']),
            'individual_country' => $text('Țară (PF)', $g['pf']),

            // Garant
            'guarantor_first_name' => $text('Garant — prenume', $g['garant']),
            'guarantor_last_name' => $text('Garant — nume', $g['garant']),
            'guarantor_cnp' => $text('Garant — CNP', $g['garant']),
            'guarantor_address' => $text('Garant — adresă', $g['garant']),
            'guarantor_city' => $text('Garant — oraș', $g['garant']),
            'guarantor_id_type' => [
                'label' => 'Garant — tip act', 'group' => $g['garant'], 'type' => 'enum',
                'options' => ['ci' => 'Carte de identitate', 'bi' => 'Buletin'],
            ],
            'guarantor_id_series' => $text('Garant — serie act', $g['garant']),
            'guarantor_id_number' => $text('Garant — nr. act', $g['garant']),
            'guarantor_id_issued_by' => $text('Garant — eliberat de', $g['garant']),
            'guarantor_id_issued_date' => ['label' => 'Garant — eliberat la', 'group' => $g['garant'], 'type' => 'date'],

            // Bancă și facturare
            'bank_name' => $text('Bancă', $g['banca']),
            'iban' => $text('IBAN', $g['banca']),
            'primary_invoice_series' => $text('Serie facturi', $g['banca']),
            'primary_last_invoice_number' => ['label' => 'Ultimul nr. factură', 'group' => $g['banca'], 'type' => 'number'],
            'primary_vat_payer' => $bool('Plătitor TVA (facturare)', $g['banca']),
            'primary_vat_rate' => ['label' => 'Cotă TVA (facturare)', 'group' => $g['banca'], 'type' => 'percent'],

            // Al doilea emitent
            'has_secondary_issuer' => $bool('Are al doilea emitent', $g['emitent2']),
            'secondary_company_name' => $text('Emitent 2 — denumire', $g['emitent2']),
            'secondary_company_tax_id' => $text('Emitent 2 — CUI', $g['emitent2']),
            'secondary_company_registration' => $text('Emitent 2 — Reg. Com.', $g['emitent2']),
            'secondary_company_address' => $text('Emitent 2 — adresă', $g['emitent2']),
            'secondary_company_city' => $text('Emitent 2 — oraș', $g['emitent2']),
            'secondary_company_county' => $text('Emitent 2 — județ', $g['emitent2']),
            'secondary_company_zip' => $text('Emitent 2 — cod poștal', $g['emitent2']),
            'secondary_bank_name' => $text('Emitent 2 — bancă', $g['emitent2']),
            'secondary_iban' => $text('Emitent 2 — IBAN', $g['emitent2']),
            'secondary_invoice_series' => $text('Emitent 2 — serie facturi', $g['emitent2']),
            'secondary_last_invoice_number' => ['label' => 'Emitent 2 — ultimul nr. factură', 'group' => $g['emitent2'], 'type' => 'number'],
            'secondary_vat_payer' => $bool('Emitent 2 — plătitor TVA', $g['emitent2']),
            'secondary_vat_rate' => ['label' => 'Emitent 2 — cotă TVA', 'group' => $g['emitent2'], 'type' => 'percent'],
            'secondary_contract_number_series' => $text('Emitent 2 — nr. contract', $g['emitent2']),
            'secondary_contract_date' => ['label' => 'Emitent 2 — data contract', 'group' => $g['emitent2'], 'type' => 'date'],

            // Setări
            'test_pos_enabled' => $bool('Test POS activ', $g['setari']),
            'allow_live_edits' => $bool('Editări live permise', $g['setari']),
            'gamification_enabled' => $bool('Gamification activ', $g['setari']),
            'invitations_enabled' => $bool('Invitații active', $g['setari']),

            // Statistici și sold
            'events_count' => [
                'label' => 'Nr. evenimente', 'group' => $g['stats'], 'type' => 'number',
                'expr' => '(SELECT COUNT(*) FROM events WHERE events.marketplace_organizer_id = marketplace_organizers.id)',
                'select' => true,
            ],
            'upcoming_events_count' => [
                'label' => 'Evenimente viitoare', 'group' => $g['stats'], 'type' => 'number',
                'expr' => '(SELECT COUNT(*) FROM events WHERE events.marketplace_organizer_id = marketplace_organizers.id AND events.event_date >= ?)',
                'bindings' => [now()->toDateString()],
                'select' => true,
            ],
            'total_tickets_sold' => ['label' => 'Bilete vândute (statistică)', 'group' => $g['stats'], 'type' => 'number'],
            'total_revenue' => ['label' => 'Venituri (statistică)', 'group' => $g['stats'], 'type' => 'money'],
            'available_balance' => ['label' => 'Sold disponibil', 'group' => $g['stats'], 'type' => 'money'],
            'pending_balance' => ['label' => 'În procesare', 'group' => $g['stats'], 'type' => 'money'],
            'total_paid_out' => ['label' => 'Total plătit', 'group' => $g['stats'], 'type' => 'money'],
        ];

        // Coloanele simple citesc direct câmpul cu același nume. Cele cu
        // `state` și fără expr (liste calculate) nu au ce sorta sau filtra.
        foreach ($columns as $key => &$def) {
            if (! isset($def['expr']) && ! isset($def['attr']) && ! isset($def['state'])) {
                $def['attr'] = $key;
            }
        }
        unset($def);

        return $columns;
    }

    protected function formatPercent($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').'%';
    }
}
