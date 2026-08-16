<?php

namespace App\Console\Commands;

use App\Services\Widget\TixelloWidgetStatsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Arată în terminal exact cifrele pe care le-ar primi telefonul.
 *
 * Rostul ei e momentul deploy-ului: rulezi comanda pe server şi vezi imediat
 * dacă agregatele sunt corecte şi cât durează interogările, fără să instalezi
 * nimic pe telefon şi fără să emiţi un token.
 *
 *   php artisan tixello:widget-preview
 *   php artisan tixello:widget-preview --json --fresh
 *   php artisan tixello:widget-preview --since=12345
 */
class TixelloWidgetPreviewCommand extends Command
{
    protected $signature = 'tixello:widget-preview
        {--json : Afişează payload-ul brut, aşa cum îl primeşte telefonul}
        {--fresh : Ignoră cache-ul, ca să vezi timpii reali ai interogărilor}
        {--since= : Simulează cursorul unui telefon (ce ar fi considerat „nou")}
        {--limit=5 : Câte comisioane recente}';

    protected $description = 'Cifrele widget-ului Tixello, aşa cum le vede telefonul';

    public function handle(TixelloWidgetStatsService $stats): int
    {
        if ($this->option('fresh')) {
            Cache::forget('tixello_widget:stats:all-time');
            Cache::forget('tixello_widget:stats:today');
        }

        $startedAt = microtime(true);

        $payload = $stats->payload(
            $this->option('since') !== null ? (int) $this->option('since') : null,
            (int) $this->option('limit'),
        );

        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $currency = $payload['currency'];
        $s = $payload['stats'];

        $this->newLine();
        $this->info('Cifrele Tixello — toţi tenanţii şi toate marketplace-urile');
        $this->line("Fus orar pentru „azi”: {$payload['timezone']}");
        $this->newLine();

        $this->table(
            ['', 'Total', 'Azi'],
            [
                ['Vânzări', $this->money($s['sales']['total'], $currency), $this->money($s['sales']['today'], $currency)],
                ['Comenzi', number_format($s['sales']['total_orders']), number_format($s['sales']['today_orders'])],
                ['Bilete', number_format($s['tickets']['total']), number_format($s['tickets']['today'])],
                ['Clienţi', number_format($s['customers']['total']), number_format($s['customers']['today'])],
                ['Venituri Tixello', $this->money($s['revenue']['total'], $currency), $this->money($s['revenue']['today'], $currency)],
                ['  ├ comision bilete', $this->money($s['revenue']['tickets_total'], $currency), $this->money($s['revenue']['tickets_today'], $currency)],
                ['  ├ servicii (50%)', $this->money($s['revenue']['services_total'], $currency), $this->money($s['revenue']['services_today'], $currency)],
                ['  └ taxe one-time', $this->money($s['revenue']['one_time_total'], $currency), $this->money($s['revenue']['one_time_today'], $currency)],
            ]
        );

        /* Abonamentele lunare sunt o rată, nu o sumă acumulată — de aceea stau
           în afara tabelului, nu adunate în „Total". */
        $this->line('Abonamente active: ' . $this->money($s['revenue']['recurring_monthly'], $currency) . ' / lună');

        $this->newLine();
        $this->info('Ultimele comisioane');

        if ($payload['commissions'] === []) {
            $this->line('  (niciunul)');
        } else {
            $this->table(
                ['ID', 'Sumă', 'Eveniment', 'Sursă', 'Când'],
                array_map(fn (array $c) => [
                    $c['id'],
                    $this->money($c['amount_converted'] ?? $c['amount'], $c['amount_converted'] !== null ? $c['currency'] : $c['amount_currency']),
                    mb_strimwidth((string) $c['event'], 0, 40, '…'),
                    $c['source'] ?? '—',
                    $c['at'],
                ], $payload['commissions'])
            );
        }

        if ($this->option('since') !== null) {
            $count = count($payload['new_commissions']);
            $this->newLine();
            $this->line("Ar declanşa alertă: {$count} comision(e) peste cursorul " . $this->option('since'));
        }

        $this->newLine();
        $this->line("Cursor: {$payload['cursor']['last_commission_id']} · construit în {$elapsedMs} ms"
            . ($this->option('fresh') ? ' (fără cache)' : ' (cu cache)'));

        /* Peste 2 secunde pe un poll la 60 s înseamnă că serverul petrece 3%
           din timp doar desenând widget-ul — semn că trebuie indexuri sau un
           TTL mai mare. */
        if ($this->option('fresh') && $elapsedMs > 2000) {
            $this->newLine();
            $this->warn("Interogările durează {$elapsedMs} ms fără cache.");
            $this->line('Vezi migraţia opţională de indexuri din docs/tixello-widget-android.md,');
            $this->line('sau creşte TIXELLO_WIDGET_CACHE_TTL_ALL_TIME.');
        }

        return self::SUCCESS;
    }

    private function money(float|int|null $value, string $currency): string
    {
        return number_format((float) $value, 2, ',', '.') . ' ' . $currency;
    }
}
