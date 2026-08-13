<?php

use App\Models\MarketplaceTaxTemplate;
use Illuminate\Database\Migrations\Migration;

/**
 * Decont PDF template: make the Preț / TVA / Valoare columns equal width.
 *
 * The header defines Preț=65px, TVA=35px, Valoare=55px. TVA was too narrow, so
 * "Total TVA" (e.g. "39.90 lei") wrapped onto two lines. Widen TVA and Valoare
 * to 65px (= Preț) so the three numeric columns are equal and nothing wraps.
 *
 * Idempotent + self-scoping: str_replace only fires on templates that still
 * carry the old widths on those exact header cells.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->apply([
            'width:35px; font-weight:bold;">TVA</th>' => 'width:65px; font-weight:bold;">TVA</th>',
            'width:55px; font-weight:bold;">Valoare</th>' => 'width:65px; font-weight:bold;">Valoare</th>',
        ]);
    }

    public function down(): void
    {
        $this->apply([
            'width:65px; font-weight:bold;">TVA</th>' => 'width:35px; font-weight:bold;">TVA</th>',
            'width:65px; font-weight:bold;">Valoare</th>' => 'width:55px; font-weight:bold;">Valoare</th>',
        ]);
    }

    private function apply(array $map): void
    {
        foreach (MarketplaceTaxTemplate::whereIn('type', ['decont_inclus', 'decont'])->get() as $t) {
            $h = (string) $t->html_content;
            if ($h === '') {
                continue;
            }
            $new = strtr($h, $map);
            if ($new !== $h) {
                $t->html_content = $new;
                $t->save();
            }
        }
    }
};
