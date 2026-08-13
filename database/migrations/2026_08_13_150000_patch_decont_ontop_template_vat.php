<?php

use App\Models\MarketplaceTaxTemplate;
use Illuminate\Database\Migrations\Migration;

/**
 * Same decont-PDF tweaks as the decont_inclus template, applied to the
 * decont_ontop variant (id 3):
 *   - row 1a "TVA" column shows the rate ({{payout_vat_rate}}) not a duplicate
 *     value;
 *   - "Total fără TVA" cell uses {{payout_amount_without_vat}};
 *   - Preț / TVA / Valoare header columns all 70px (equal), so "Total TVA"
 *     no longer wraps.
 *
 * Idempotent + self-scoping: replacements only fire on templates that still
 * carry the old markers.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (MarketplaceTaxTemplate::where('type', 'decont_ontop')->get() as $t) {
            $h = (string) $t->html_content;
            if ($h === '') {
                continue;
            }
            $orig = $h;

            // VAT rate in the row 1a "TVA" column (unique: only that cell pairs
            // #555 with the variable).
            $h = str_replace('color:#555;">{{payout_vat_amount}}', 'color:#555;">{{payout_vat_rate}}', $h);

            // "Total fără TVA" cell.
            $h = str_replace('{{payout_fees_amount}} lei</td>', '{{payout_amount_without_vat}} lei</td>', $h);

            // Equalize the three numeric header columns at 70px (any current width).
            $h = preg_replace('/width:\d+px;( font-weight:bold;">Pre)/', 'width:70px;$1', $h);
            $h = preg_replace('/width:\d+px;( font-weight:bold;">TVA)/', 'width:70px;$1', $h);
            $h = preg_replace('/width:\d+px;( font-weight:bold;">Valoare)/', 'width:70px;$1', $h);

            if ($h !== $orig) {
                $t->html_content = $h;
                $t->save();
            }
        }
    }

    public function down(): void
    {
        // Content patch — not reverted (widths/labels are cosmetic; the VAT
        // variable swaps are backward-compatible with the code).
    }
};
