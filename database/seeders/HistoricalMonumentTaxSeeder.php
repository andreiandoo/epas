<?php

namespace Database\Seeders;

use App\Models\Tax\GeneralTax;
use Illuminate\Database\Seeder;

/**
 * Historical Monument Tax Seeder
 *
 * Creates the "Taxa de Monument Istoric" which applies to events
 * held at venues that have the historical monument tax flag.
 *
 * This tax is 2% and applies when:
 * - The event's venue has `has_historical_monument_tax = true`
 */
class HistoricalMonumentTaxSeeder extends Seeder
{
    public function run(): void
    {
        GeneralTax::updateOrCreate(
            [
                'name' => 'Taxa de Monument Istoric',
            ],
            [
                'value' => 2.00,
                'value_type' => 'percent',
                'is_active' => true,
                'is_added_to_price' => true,
                'applied_to_base' => 'ticket_price',
                'priority' => 40, // Between stamps and UCMR-ADA
                'visible_on_checkout' => true,
                'visible_on_ticket' => true,
                'legal_basis' => 'Legea nr. 422/2001 privind protejarea monumentelor istorice',
                'beneficiary' => 'Administrația locală',
                'payment_term_type' => 'day_of_month',
                'payment_term_day' => 25,
                'payment_term' => '25 a lunii următoare',
                'explanation' => '<p>Taxa de 2% pentru evenimentele desfășurate în locații clasificate ca monumente istorice. Se aplică automat când venue-ul are activată opțiunea "Monument Istoric".</p>',
                'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>',
            ]
        );
    }
}
