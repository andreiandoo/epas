<?php

namespace Database\Seeders;

use App\Models\EventType;
use App\Models\Tax\GeneralTax;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Romanian Taxes Seeder - December 2025
 *
 * Based on Romanian tax legislation for event tickets:
 * - TVA (VAT) - Art. 291, 292 Cod Fiscal
 * - Timbre Culturale - Legea 35/1994, Legea 139/1995
 * - UCMR-ADA - ORDA 203/2011, ORDA 46/2020
 *
 * Note: Impozit pe Spectacole (local tax) should be configured per locality
 * in the Local Taxes module as rates are set by local HCL decisions.
 */
class RomanianTaxesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // First, ensure all needed event types exist
            $this->seedEventTypes();

            // Then seed the taxes
            $this->seedGeneralTaxes();
        });
    }

    /**
     * Add missing event types needed for Romanian tax categories
     */
    private function seedEventTypes(): void
    {
        $parentTypes = [
            ['name' => ['en' => 'Music & Entertainment', 'ro' => 'Muzică & divertisment'], 'slug' => 'muzica-divertisment'],
            ['name' => ['en' => 'Arts & Culture', 'ro' => 'Arte & cultură'], 'slug' => 'arte-cultura'],
            ['name' => ['en' => 'Sports & Outdoor', 'ro' => 'Sport & outdoor'], 'slug' => 'sport-outdoor'],
            ['name' => ['en' => 'Tourism & Attractions', 'ro' => 'Turism & atracții'], 'slug' => 'turism-atractii'],
        ];

        $parentIds = [];
        foreach ($parentTypes as $type) {
            $existing = EventType::where('slug', $type['slug'])->first();
            if ($existing) {
                $parentIds[$type['slug']] = $existing->id;
            } else {
                $new = EventType::create([
                    'name' => $type['name'],
                    'slug' => $type['slug'],
                ]);
                $parentIds[$type['slug']] = $new->id;
            }
        }

        // Child types needed for Romanian tax system
        $childTypes = [
            // Music & Entertainment
            ['name' => ['en' => 'Concert', 'ro' => 'Concert'], 'slug' => 'concert', 'parent' => 'muzica-divertisment'],
            ['name' => ['en' => 'Festival', 'ro' => 'Festival'], 'slug' => 'festival', 'parent' => 'muzica-divertisment'],
            ['name' => ['en' => 'DJ Set / Club Night', 'ro' => 'DJ Set / Club night'], 'slug' => 'dj-set-club-night', 'parent' => 'muzica-divertisment'],
            ['name' => ['en' => 'Philharmonic Concert', 'ro' => 'Concert filarmonic'], 'slug' => 'concert-filarmonic', 'parent' => 'muzica-divertisment'],

            // Arts & Culture
            ['name' => ['en' => 'Theatre', 'ro' => 'Teatru'], 'slug' => 'teatru', 'parent' => 'arte-cultura'],
            ['name' => ['en' => 'Opera', 'ro' => 'Operă'], 'slug' => 'opera', 'parent' => 'arte-cultura'],
            ['name' => ['en' => 'Ballet', 'ro' => 'Balet'], 'slug' => 'balet', 'parent' => 'arte-cultura'],
            ['name' => ['en' => 'Operetta', 'ro' => 'Operetă'], 'slug' => 'opereta', 'parent' => 'arte-cultura'],
            ['name' => ['en' => 'Cinema', 'ro' => 'Cinema'], 'slug' => 'cinema', 'parent' => 'arte-cultura'],
            ['name' => ['en' => 'Circus', 'ro' => 'Circ'], 'slug' => 'circ', 'parent' => 'arte-cultura'],
            ['name' => ['en' => 'Stand-up Comedy', 'ro' => 'Stand-up comedy'], 'slug' => 'stand-up-comedy', 'parent' => 'arte-cultura'],
            ['name' => ['en' => 'Exhibition', 'ro' => 'Expoziție'], 'slug' => 'expozitie', 'parent' => 'arte-cultura'],

            // Sports
            ['name' => ['en' => 'Sports Competition', 'ro' => 'Competiție sportivă'], 'slug' => 'competitie-sportiva', 'parent' => 'sport-outdoor'],

            // Tourism & Attractions
            ['name' => ['en' => 'Museum / Castle / Monument', 'ro' => 'Muzeu / Castel / Monument'], 'slug' => 'muzeu-castel-monument', 'parent' => 'turism-atractii'],
            ['name' => ['en' => 'Botanical / Zoological Garden', 'ro' => 'Grădină botanică / zoologică'], 'slug' => 'gradina-botanica-zoo', 'parent' => 'turism-atractii'],
            ['name' => ['en' => 'Fair / Carnival / Amusement Park', 'ro' => 'Bâlci / Carnaval / Parc distracții'], 'slug' => 'balci-carnaval-parc', 'parent' => 'turism-atractii'],
        ];

        foreach ($childTypes as $type) {
            $parentId = $parentIds[$type['parent']] ?? null;
            EventType::firstOrCreate(
                ['slug' => $type['slug']],
                [
                    'name' => $type['name'],
                    'parent_id' => $parentId,
                ]
            );
        }
    }

    /**
     * Seed Romanian general taxes
     */
    private function seedGeneralTaxes(): void
    {
        // Get event type IDs for mapping
        $eventTypes = EventType::pluck('id', 'slug')->toArray();

        $taxes = [
            // ============================================
            // TVA (VAT) - Art. 291, 292 Cod Fiscal
            // ============================================
            [
                'name' => 'TVA 21% - Concert comercial',
                'value' => 21.00,
                'value_type' => 'percent',
                'event_type_slug' => 'concert',
                'is_added_to_price' => false,
                'applied_to_base' => 'ticket_price',
                'legal_basis' => 'Art. 291 alin. 3 Cod Fiscal',
                'beneficiary' => 'ANAF',
                'payment_term_type' => 'day_of_month',
                'payment_term_day' => 25,
                'payment_term' => '25 a lunii următoare',
                'explanation' => '<p>TVA standard pentru concerte comerciale. Din august 2025 cota a crescut de la 9% la 21%.</p>',
                'valid_from' => '2025-08-01',
            ],
            [
                'name' => 'TVA 21% - Festival',
                'value' => 21.00,
                'value_type' => 'percent',
                'event_type_slug' => 'festival',
                'is_added_to_price' => false,
                'applied_to_base' => 'ticket_price',
                'legal_basis' => 'Art. 291 alin. 3 Cod Fiscal',
                'beneficiary' => 'ANAF',
                'payment_term_type' => 'day_of_month',
                'payment_term_day' => 25,
                'payment_term' => '25 a lunii următoare',
                'explanation' => '<p>TVA standard pentru festivaluri. Din august 2025.</p>',
                'valid_from' => '2025-08-01',
            ],
            [
                'name' => 'TVA 21% - Teatru privat',
                'value' => 21.00,
                'value_type' => 'percent',
                'event_type_slug' => 'teatru',
                'is_added_to_price' => false,
                'applied_to_base' => 'ticket_price',
                'legal_basis' => 'Art. 291 alin. 3 Cod Fiscal',
                'beneficiary' => 'ANAF',
                'payment_term_type' => 'day_of_month',
                'payment_term_day' => 25,
                'payment_term' => '25 a lunii următoare',
                'explanation' => '<p>TVA standard pentru teatru privat/comercial. Din august 2025. Instituțiile publice sunt scutite (Art. 292).</p>',
                'valid_from' => '2025-08-01',
            ],
            [
                'name' => 'TVA 21% - Cinema',
                'value' => 21.00,
                'value_type' => 'percent',
                'event_type_slug' => 'cinema',
                'is_added_to_price' => false,
                'applied_to_base' => 'ticket_price',
                'legal_basis' => 'Art. 291 alin. 3 Cod Fiscal',
                'beneficiary' => 'ANAF',
                'payment_term_type' => 'day_of_month',
                'payment_term_day' => 25,
                'payment_term' => '25 a lunii următoare',
                'explanation' => '<p>TVA standard pentru cinema. Din august 2025 cota a crescut de la 5% la 21%.</p>',
                'valid_from' => '2025-08-01',
            ],
            [
                'name' => 'TVA 21% - Competiție sportivă',
                'value' => 21.00,
                'value_type' => 'percent',
                'event_type_slug' => 'competitie-sportiva',
                'is_added_to_price' => false,
                'applied_to_base' => 'ticket_price',
                'legal_basis' => 'Art. 291 alin. 3 Cod Fiscal',
                'beneficiary' => 'ANAF',
                'payment_term_type' => 'day_of_month',
                'payment_term_day' => 25,
                'payment_term' => '25 a lunii următoare',
                'explanation' => '<p>TVA standard pentru competiții sportive comerciale.</p>',
                'valid_from' => '2025-08-01',
            ],
            [
                'name' => 'TVA 21% - Bâlci / Parc distracții',
                'value' => 21.00,
                'value_type' => 'percent',
                'event_type_slug' => 'balci-carnaval-parc',
                'is_added_to_price' => false,
                'applied_to_base' => 'ticket_price',
                'legal_basis' => 'Art. 291 alin. 3 Cod Fiscal',
                'beneficiary' => 'ANAF',
                'payment_term_type' => 'day_of_month',
                'payment_term_day' => 25,
                'payment_term' => '25 a lunii următoare',
                'explanation' => '<p>TVA standard pentru bâlciuri, târguri, parcuri de distracții.</p>',
                'valid_from' => '2025-08-01',
            ],
            [
                'name' => 'TVA 11% - Muzeu / Monument',
                'value' => 11.00,
                'value_type' => 'percent',
                'event_type_slug' => 'muzeu-castel-monument',
                'is_added_to_price' => false,
                'applied_to_base' => 'ticket_price',
                'legal_basis' => 'Art. 291 alin. 2 lit. l Cod Fiscal',
                'beneficiary' => 'ANAF',
                'payment_term_type' => 'day_of_month',
                'payment_term_day' => 25,
                'payment_term' => '25 a lunii următoare',
                'explanation' => '<p>Cotă redusă TVA pentru muzee, castele, monumente. Menținută la 11%.</p>',
            ],
            [
                'name' => 'TVA 11% - Grădină botanică/zoologică',
                'value' => 11.00,
                'value_type' => 'percent',
                'event_type_slug' => 'gradina-botanica-zoo',
                'is_added_to_price' => false,
                'applied_to_base' => 'ticket_price',
                'legal_basis' => 'Art. 291 alin. 2 lit. l Cod Fiscal',
                'beneficiary' => 'ANAF',
                'payment_term_type' => 'day_of_month',
                'payment_term_day' => 25,
                'payment_term' => '25 a lunii următoare',
                'explanation' => '<p>Cotă redusă TVA pentru grădini botanice și zoologice.</p>',
            ],

            // ============================================
            // TIMBRE CULTURALE - Legea 35/1994, Legea 139/1995
            // Se adaugă la prețul biletului
            // ============================================
            [
                'name' => 'Timbru Muzical 5%',
                'value' => 5.00,
                'value_type' => 'percent',
                'event_type_slug' => 'concert',
                'is_added_to_price' => true,
                'applied_to_base' => 'ticket_price',
                'legal_basis' => 'Legea 35/1994',
                'beneficiary' => 'UCMR',
                'payment_term_type' => 'at_sale',
                'payment_term' => 'La vânzarea biletului (inclus în preț)',
                'explanation' => '<p>Timbru muzical pentru concerte, festivaluri și evenimente cu muzică. Se adaugă la prețul biletului și se virează către UCMR.</p>',
            ],
            [
                'name' => 'Timbru Muzical 5% - Festival',
                'value' => 5.00,
                'value_type' => 'percent',
                'event_type_slug' => 'festival',
                'is_added_to_price' => true,
                'applied_to_base' => 'ticket_price',
                'legal_basis' => 'Legea 35/1994',
                'beneficiary' => 'UCMR',
                'payment_term_type' => 'at_sale',
                'payment_term' => 'La vânzarea biletului (inclus în preț)',
                'explanation' => '<p>Timbru muzical pentru festivaluri.</p>',
            ],
            [
                'name' => 'Timbru Muzical 5% - DJ/Club',
                'value' => 5.00,
                'value_type' => 'percent',
                'event_type_slug' => 'dj-set-club-night',
                'is_added_to_price' => true,
                'applied_to_base' => 'ticket_price',
                'legal_basis' => 'Legea 35/1994',
                'beneficiary' => 'UCMR',
                'payment_term_type' => 'at_sale',
                'payment_term' => 'La vânzarea biletului (inclus în preț)',
                'explanation' => '<p>Timbru muzical pentru evenimente DJ/club.</p>',
            ],
            [
                'name' => 'Timbru Teatral 5%',
                'value' => 5.00,
                'value_type' => 'percent',
                'event_type_slug' => 'teatru',
                'is_added_to_price' => true,
                'applied_to_base' => 'ticket_price',
                'legal_basis' => 'Legea 35/1994',
                'beneficiary' => 'UNITER',
                'payment_term_type' => 'at_sale',
                'payment_term' => 'La vânzarea biletului (inclus în preț)',
                'explanation' => '<p>Timbru teatral pentru spectacole de teatru. Se virează către UNITER.</p>',
            ],
            [
                'name' => 'Timbru Teatral 5% - Operă',
                'value' => 5.00,
                'value_type' => 'percent',
                'event_type_slug' => 'opera',
                'is_added_to_price' => true,
                'applied_to_base' => 'ticket_price',
                'legal_basis' => 'Legea 35/1994',
                'beneficiary' => 'UNITER',
                'payment_term_type' => 'at_sale',
                'payment_term' => 'La vânzarea biletului (inclus în preț)',
                'explanation' => '<p>Timbru teatral pentru operă.</p>',
            ],
            [
                'name' => 'Timbru Teatral 5% - Balet',
                'value' => 5.00,
                'value_type' => 'percent',
                'event_type_slug' => 'balet',
                'is_added_to_price' => true,
                'applied_to_base' => 'ticket_price',
                'legal_basis' => 'Legea 35/1994',
                'beneficiary' => 'UNITER',
                'payment_term_type' => 'at_sale',
                'payment_term' => 'La vânzarea biletului (inclus în preț)',
                'explanation' => '<p>Timbru teatral pentru balet.</p>',
            ],
            [
                'name' => 'Timbru Crucea Roșie 1%',
                'value' => 1.00,
                'value_type' => 'percent',
                'event_type_slug' => null, // Applies to ALL events
                'is_added_to_price' => true,
                'applied_to_base' => 'ticket_price',
                'legal_basis' => 'Legea 139/1995',
                'beneficiary' => 'Crucea Roșie Română',
                'payment_term_type' => 'at_sale',
                'payment_term' => 'La vânzarea biletului (inclus în preț)',
                'explanation' => '<p>Timbru Crucea Roșie - se aplică la TOATE evenimentele cu bilete. Se adaugă la prețul biletului.</p>',
            ],

            // ============================================
            // UCMR-ADA - Drepturi de autor
            // ORDA 203/2011 (concerte), ORDA 46/2020 (festivaluri)
            // ============================================
            [
                'name' => 'UCMR-ADA - Concerte',
                'value' => 7.00, // Default tier
                'value_type' => 'percent',
                'event_type_slug' => 'concert',
                'is_added_to_price' => false,
                'applied_to_base' => 'gross_excl_vat',
                'has_tiered_rates' => true,
                'tiered_rates' => [
                    ['min' => 0, 'max' => 500000, 'rate' => 7],
                    ['min' => 500001, 'max' => 2000000, 'rate' => 6.5],
                    ['min' => 2000001, 'max' => 5000000, 'rate' => 6],
                    ['min' => 5000001, 'max' => null, 'rate' => 5.5],
                ],
                'legal_basis' => 'ORDA 203/2011',
                'beneficiary' => 'UCMR-ADA',
                'payment_term_type' => 'days_after_event',
                'payment_term_days_after' => 45,
                'payment_term' => '45 zile de la eveniment',
                'explanation' => '<p>Drepturi de autor pentru concerte. Rata variază în funcție de veniturile evenimentului:</p>
<ul>
<li>≤ 500.000 RON: 7%</li>
<li>500.001 - 2.000.000 RON: 6.5%</li>
<li>2.000.001 - 5.000.000 RON: 6%</li>
<li>&gt; 5.000.000 RON: 5.5%</li>
</ul>
<p>Se aplică la venituri brute din bilete, exclusiv TVA.</p>',
                'after_event_instructions' => '<p><strong>După eveniment:</strong></p>
<ol>
<li>Calculați veniturile totale din bilete (exclusiv TVA)</li>
<li>Determinați rata aplicabilă în funcție de plafon</li>
<li>Completați declarația UCMR-ADA</li>
<li>Efectuați plata în termen de 45 zile</li>
</ol>',
            ],
            [
                'name' => 'UCMR-ADA - Festivaluri',
                'value' => 4.00, // Default tier
                'value_type' => 'percent',
                'event_type_slug' => 'festival',
                'is_added_to_price' => false,
                'applied_to_base' => 'gross_excl_vat',
                'has_tiered_rates' => true,
                'tiered_rates' => [
                    ['min' => 0, 'max' => 1000000, 'rate' => 4],
                    ['min' => 1000001, 'max' => 10000000, 'rate' => 3.5],
                    ['min' => 10000001, 'max' => 20000000, 'rate' => 3],
                    ['min' => 20000001, 'max' => null, 'rate' => 2.5],
                ],
                'legal_basis' => 'ORDA 46/2020',
                'beneficiary' => 'UCMR-ADA',
                'payment_term_type' => 'days_after_event',
                'payment_term_days_after' => 45,
                'payment_term' => '45 zile de la eveniment',
                'explanation' => '<p>Drepturi de autor pentru festivaluri. Rata variază:</p>
<ul>
<li>≤ 1.000.000 RON: 4%</li>
<li>1.000.001 - 10.000.000 RON: 3.5%</li>
<li>10.000.001 - 20.000.000 RON: 3%</li>
<li>&gt; 20.000.000 RON: 2.5%</li>
</ul>',
            ],
            [
                'name' => 'UCMR-ADA - Operă',
                'value' => 8.00,
                'value_type' => 'percent',
                'event_type_slug' => 'opera',
                'is_added_to_price' => false,
                'applied_to_base' => 'gross_excl_vat',
                'legal_basis' => 'Metodologie ORDA',
                'beneficiary' => 'UCMR-ADA',
                'payment_term_type' => 'days_after_event',
                'payment_term_days_after' => 45,
                'payment_term' => '45 zile de la eveniment',
                'explanation' => '<p>Drepturi de autor pentru operă - 8% din venituri brute exclusiv TVA.</p>',
            ],
            [
                'name' => 'UCMR-ADA - Operetă',
                'value' => 7.00,
                'value_type' => 'percent',
                'event_type_slug' => 'opereta',
                'is_added_to_price' => false,
                'applied_to_base' => 'gross_excl_vat',
                'legal_basis' => 'Metodologie ORDA',
                'beneficiary' => 'UCMR-ADA',
                'payment_term_type' => 'days_after_event',
                'payment_term_days_after' => 45,
                'payment_term' => '45 zile de la eveniment',
                'explanation' => '<p>Drepturi de autor pentru operetă - 7% din venituri brute exclusiv TVA.</p>',
            ],
            [
                'name' => 'UCMR-ADA - Balet',
                'value' => 6.00,
                'value_type' => 'percent',
                'event_type_slug' => 'balet',
                'is_added_to_price' => false,
                'applied_to_base' => 'gross_excl_vat',
                'legal_basis' => 'Metodologie ORDA',
                'beneficiary' => 'UCMR-ADA',
                'payment_term_type' => 'days_after_event',
                'payment_term_days_after' => 45,
                'payment_term' => '45 zile de la eveniment',
                'explanation' => '<p>Drepturi de autor pentru balet - 6% din venituri brute exclusiv TVA.</p>',
            ],
        ];

        foreach ($taxes as $taxData) {
            $eventTypeSlug = $taxData['event_type_slug'] ?? null;
            unset($taxData['event_type_slug']);

            $eventTypeId = null;
            if ($eventTypeSlug && isset($eventTypes[$eventTypeSlug])) {
                $eventTypeId = $eventTypes[$eventTypeSlug];
            }

            // Note: tiered_rates is automatically JSON encoded by Laravel's 'array' cast
            // Do NOT manually json_encode it here

            GeneralTax::updateOrCreate(
                [
                    'name' => $taxData['name'],
                    'event_type_id' => $eventTypeId,
                ],
                array_merge($taxData, [
                    'event_type_id' => $eventTypeId,
                    'is_active' => true,
                    'priority' => $this->getPriority($taxData['name']),
                ])
            );
        }
    }

    /**
     * Get priority based on tax type (higher = applied first)
     */
    private function getPriority(string $name): int
    {
        if (str_contains($name, 'TVA')) {
            return 100; // VAT calculated first
        }
        if (str_contains($name, 'Timbru')) {
            return 50; // Stamps added to price
        }
        if (str_contains($name, 'UCMR-ADA')) {
            return 30; // Copyright fees
        }
        return 10;
    }
}
