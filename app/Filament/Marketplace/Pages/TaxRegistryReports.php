<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Reports\ConfigurableReportPage;
use App\Filament\Marketplace\Resources\TaxRegistryResource;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceTaxRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TaxRegistryReports extends ConfigurableReportPage
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Rapoarte registre taxe';

    protected static ?string $title = 'Rapoarte registre taxe';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'tax-registry-reports';

    /** registry id => nr. organizatori care au registrul la excepții */
    protected ?array $exceptionCounts = null;

    protected static function entity(): string
    {
        return 'tax_registries';
    }

    public function introText(): ?string
    {
        return 'Alegi coloanele din datele registrelor de taxe și salvezi raportul. Rapoartele sunt comune pentru toți adminii. '
            .'Numele registrului deschide pagina lui de editare într-un tab nou.';
    }

    protected function baseQuery(): Builder
    {
        return MarketplaceTaxRegistry::query()
            ->where('marketplace_tax_registries.marketplace_client_id', static::getMarketplaceClient()?->id);
    }

    protected function titleColumnKey(): string
    {
        return 'name';
    }

    protected function editUrl(Model $record): string
    {
        return TaxRegistryResource::getUrl('edit', ['record' => $record]);
    }

    protected function exportBaseName(): string
    {
        return 'registre-taxe';
    }

    protected function presets(): array
    {
        return [
            [
                'name' => 'Registre',
                'columns' => ['county', 'city', 'tax_rate', 'submission_method', 'submission_email', 'events_count', 'upcoming_events_count', 'is_active'],
            ],
        ];
    }

    protected function exceptionCount(int $registryId): int
    {
        if ($this->exceptionCounts === null) {
            $this->exceptionCounts = [];
            MarketplaceOrganizer::query()
                ->where('marketplace_client_id', static::getMarketplaceClient()?->id)
                ->where('marketplace_manages_documents', true)
                ->whereNotNull('document_filing_exceptions')
                ->get(['id', 'document_filing_exceptions'])
                ->each(function (MarketplaceOrganizer $o) {
                    foreach (array_unique($o->filingExceptionRegistryIds()) as $id) {
                        $this->exceptionCounts[$id] = ($this->exceptionCounts[$id] ?? 0) + 1;
                    }
                });
        }

        return $this->exceptionCounts[$registryId] ?? 0;
    }

    protected function catalog(): array
    {
        $loc = 'Locație';
        $contact = 'Contact și plată';
        $dep = 'Depunerea documentelor';
        $stats = 'Statistici';
        $text = fn (string $label, string $group, array $extra = []) => ['label' => $label, 'group' => $group, 'type' => 'text'] + $extra;

        $columns = [
            'name' => $text('Registru', $loc),
            'subname' => $text('Subdenumire', $loc),
            'country' => $text('Țară', $loc),
            'county' => $text('Județ', $loc),
            'city' => $text('Oraș', $loc),
            'commune' => $text('Comună', $loc),
            'address' => $text('Adresă', $loc),
            'directions' => $text('Indicații', $loc, ['limit' => 60]),
            'siruta_code' => $text('Cod SIRUTA', $loc),

            'phone' => $text('Telefon', $contact),
            'email' => $text('Email', $contact),
            'email2' => $text('Email 2', $contact),
            'website_url' => $text('Website', $contact),
            'cif' => $text('CIF', $contact),
            'iban' => $text('IBAN', $contact),
            'tax_rate' => ['label' => 'Cotă impozit', 'group' => $contact, 'type' => 'percent'],
            'coat_of_arms' => [
                'label' => 'Are stemă', 'group' => $contact, 'type' => 'bool',
                'expr' => "(marketplace_tax_registries.coat_of_arms IS NOT NULL AND marketplace_tax_registries.coat_of_arms <> '')",
                'select' => true,
            ],
            'is_active' => ['label' => 'Activ', 'group' => $contact, 'type' => 'bool'],

            'submission_method' => [
                'label' => 'Metodă de depunere', 'group' => $dep, 'type' => 'enum',
                'options' => [
                    MarketplaceTaxRegistry::SUBMISSION_EMAIL => 'Pe email',
                    MarketplaceTaxRegistry::SUBMISSION_THIRD_PARTY => 'Prin soluție terță',
                ],
                'bulk' => [
                    'attr' => 'submission_method',
                    'options' => [
                        MarketplaceTaxRegistry::SUBMISSION_EMAIL => 'Pe email',
                        MarketplaceTaxRegistry::SUBMISSION_THIRD_PARTY => 'Prin soluție terță',
                        '__null' => 'Nesetat',
                    ],
                ],
                'format' => fn ($state) => match ($state) {
                    MarketplaceTaxRegistry::SUBMISSION_EMAIL => 'Pe email',
                    MarketplaceTaxRegistry::SUBMISSION_THIRD_PARTY => 'Prin soluție terță',
                    null, '' => 'Nesetat',
                    default => (string) $state,
                },
            ],
            'submission_email' => $text('Email depunere', $dep),
            'third_party_name' => $text('Soluție terță', $dep),
            'third_party_url' => $text('Link soluție terță', $dep),
            'third_party_procedure' => $text('Procedură soluție terță', $dep, ['limit' => 60]),

            'events_count' => [
                'label' => 'Nr. evenimente', 'group' => $stats, 'type' => 'number',
                'expr' => '(SELECT COUNT(*) FROM events WHERE events.marketplace_tax_registry_id = marketplace_tax_registries.id)',
                'select' => true,
            ],
            'upcoming_events_count' => [
                'label' => 'Evenimente viitoare', 'group' => $stats, 'type' => 'number',
                'expr' => '(SELECT COUNT(*) FROM events WHERE events.marketplace_tax_registry_id = marketplace_tax_registries.id AND events.event_date >= ?)',
                'bindings' => [now()->toDateString()],
                'select' => true,
            ],
            'organizers_count' => [
                'label' => 'Nr. organizatori cu evenimente aici', 'group' => $stats, 'type' => 'number',
                'expr' => '(SELECT COUNT(DISTINCT events.marketplace_organizer_id) FROM events WHERE events.marketplace_tax_registry_id = marketplace_tax_registries.id)',
                'select' => true,
            ],
            'exception_organizers' => [
                'label' => 'Organizatori cu excepție de depunere', 'group' => $stats, 'type' => 'number',
                'filterable' => false, 'sortable' => false,
                'state' => fn ($r) => $this->exceptionCount((int) $r->id),
            ],
            'created_at' => ['label' => 'Creat la', 'group' => $stats, 'type' => 'datetime'],
            'updated_at' => ['label' => 'Modificat la', 'group' => $stats, 'type' => 'datetime'],
        ];

        foreach ($columns as $key => &$def) {
            if (! isset($def['expr']) && ! isset($def['attr']) && ! isset($def['state'])) {
                $def['attr'] = $key;
            }
        }
        unset($def);

        return $columns;
    }
}
