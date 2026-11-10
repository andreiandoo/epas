<?php

namespace App\Console\Commands;

use App\Models\MarketplaceTaxTemplate;
use Illuminate\Console\Command;

/**
 * Puts {{document_registration_number}} / {{document_registration_date}} in
 * place of the dotted "Nr. … din …" lines of the cerere, impozit and PV
 * templates, so a document can be completed once the city hall registers it.
 *
 * Shows the change by default; --apply saves it. A rule is applied only when
 * its pattern is found exactly once, and templates that already have the
 * variables are left alone.
 */
class TaxTemplatesRegistrationFields extends Command
{
    protected $signature = 'tax-templates:registration-fields
        {--apply : Salvează modificările (implicit doar le afișează)}
        {--id=* : Doar aceste template-uri}';

    protected $description = 'Pune câmpurile de nr./dată înregistrare în template-urile cerere vizare, impozit și PV distrugere';

    /** Dotted blank as typed in the templates. */
    private const DOTS = '(?:…|&hellip;|&#8230;)+';

    public function handle(): int
    {
        $rules = [
            'cerere_avizare' => [
                ['/Nr\.\s*inreg\s*' . self::DOTS . '(\s*<br\s*\/?>\s*)Din\s*' . self::DOTS . '/u', 'Nr. inreg {{document_registration_number}}$1Din {{document_registration_date}}'],
            ],
            'declaratie_impozite' => [
                ['/Nr\.\s*' . self::DOTS . '\s*\/\s*' . self::DOTS . '/u', 'Nr. {{document_registration_number}} / {{document_registration_date}}'],
            ],
            'pv_distrugere' => [
                ['/(<td[^>]*>\s*Nr\.\s*)' . self::DOTS . '(\s*<\/td>)/u', '$1{{document_registration_number}}$2'],
                ['/(>\s*din\s*<\/td>\s*<td[^>]*>\s*)' . self::DOTS . '(\s*<\/td>)/u', '$1{{document_registration_date}}$2'],
            ],
        ];

        $query = MarketplaceTaxTemplate::query()->whereIn('type', array_keys($rules));
        if ($ids = array_filter((array) $this->option('id'))) {
            $query->whereIn('id', $ids);
        }

        $changed = 0;
        foreach ($query->orderBy('id')->get() as $template) {
            $label = "#{$template->id} {$template->type} {$template->name} (client {$template->marketplace_client_id})";
            $html = (string) $template->html_content;

            if (str_contains($html, '{{document_registration_number}}') || str_contains($html, '{{document_registration_date}}')) {
                $this->line("{$label}: are deja câmpurile, sărit.");

                continue;
            }

            $new = $html;
            $ok = true;
            foreach ($rules[$template->type] as [$pattern, $replacement]) {
                $count = preg_match_all($pattern, $new);
                if ($count !== 1) {
                    $this->warn("{$label}: tiparul {$pattern} apare de {$count} ori, template-ul nu se modifică.");
                    $ok = false;

                    break;
                }
                preg_match($pattern, $new, $match, PREG_OFFSET_CAPTURE);
                $before = $this->around($new, $match[0][1], strlen($match[0][0]));
                $new = preg_replace($pattern, $replacement, $new, 1);
                $after = $this->around($new, $match[0][1], strlen($match[0][0]) + 40);
                $this->line("{$label}");
                $this->line('  înainte: ' . $before);
                $this->line('  după:    ' . $after);
            }

            if (! $ok || $new === $html) {
                continue;
            }

            $changed++;
            if ($this->option('apply')) {
                $template->html_content = $new;
                $template->save();
                $this->info("{$label}: salvat.");
            }
        }

        $this->newLine();
        $this->info($this->option('apply')
            ? "Template-uri modificate: {$changed}."
            : "Template-uri de modificat: {$changed}. Rulează cu --apply ca să salvezi.");

        return self::SUCCESS;
    }

    private function around(string $html, int $offset, int $length): string
    {
        $start = max(0, $offset - 60);

        return preg_replace('/\s+/', ' ', mb_strcut($html, $start, $length + 120));
    }
}
