<?php

use App\Models\MarketplaceTaxTemplate;
use Illuminate\Database\Migrations\Migration;

/**
 * Decont PDF template tweak (chosen: edit the shared template).
 *
 *  - Row 1a "TVA" column: show the VAT RATE ({{payout_vat_rate}}) instead of a
 *    duplicate of the VAT value. (For ordinary deconturi this just replaces the
 *    duplicated value with the rate — amounts are unchanged.)
 *  - "Total fără TVA" cell: use {{payout_amount_without_vat}} instead of
 *    {{payout_fees_amount}}. For non-leisure that variable resolves to the same
 *    fees value it showed before, so nothing changes there; for leisure society
 *    deconturi it now shows gross − TVA (the taxable base).
 *
 * Idempotent + self-scoping: str/preg only fire on templates that still contain
 * the old markers, so only decont templates with this exact layout are touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (MarketplaceTaxTemplate::whereIn('type', ['decont_inclus', 'decont'])->get() as $t) {
            $h = (string) $t->html_content;
            if ($h === '') {
                continue;
            }
            $orig = $h;

            // Swap 1 — the FIRST center/#555 cell holding the VAT amount is the
            // row 1a "TVA" column; show the rate there instead.
            $h = preg_replace(
                '/(text-align:center;\s*color:#555;">)\{\{payout_vat_amount\}\}/',
                '${1}{{payout_vat_rate}}',
                $h,
                1
            );

            // Swap 2 — the "Total fără TVA" cell (fees + " lei</td>").
            $h = str_replace(
                '{{payout_fees_amount}} lei</td>',
                '{{payout_amount_without_vat}} lei</td>',
                $h
            );

            if ($h !== $orig) {
                $t->html_content = $h;
                $t->save();
            }
        }
    }

    public function down(): void
    {
        foreach (MarketplaceTaxTemplate::whereIn('type', ['decont_inclus', 'decont'])->get() as $t) {
            $h = (string) $t->html_content;
            if ($h === '') {
                continue;
            }
            $orig = $h;

            $h = preg_replace(
                '/(text-align:center;\s*color:#555;">)\{\{payout_vat_rate\}\}/',
                '${1}{{payout_vat_amount}}',
                $h,
                1
            );
            $h = str_replace(
                '{{payout_amount_without_vat}} lei</td>',
                '{{payout_fees_amount}} lei</td>',
                $h
            );

            if ($h !== $orig) {
                $t->html_content = $h;
                $t->save();
            }
        }
    }
};
