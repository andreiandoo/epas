<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\MarketplacePayout;
use App\Services\EventAlertsService;
use App\Services\Marketplace\SalesBreakdownService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Deconturile rămase deschise pe evenimente decontate și plătite în sistemul
 * vechi (înainte de mutarea pe Tixello). Le trece direct în „plătit”, fără
 * fluxul normal: fără email/notificare, fără PDF, fără compensare din avans,
 * fără rânduri în ledger. Soldurile se recalculează la final.
 *
 * Implicit doar simulează și scrie raportul CSV; scrie în DB doar cu --apply.
 */
class SettleLegacyPayouts extends Command
{
    protected $signature = 'payouts:settle-legacy
        {--marketplace=1 : Marketplace client id}
        {--ended-before=2026-04-15 : Ultima zi de final a evenimentului (inclusiv, ora României)}
        {--event= : Doar acest eveniment (pentru test)}
        {--skip-event= : Evenimente de sărit, separate prin virgulă}
        {--out= : Folderul pentru CSV + backup (implicit ~/settle-legacy)}
        {--apply : Scrie în DB}
        {--revert= : Restaurează deconturile din fișierul de backup (.jsonl) dat}';

    protected $description = 'Marchează plătite deconturile deschise pe evenimentele încheiate înainte de mutarea pe Tixello (decontate în sistemul vechi).';

    private const OPEN_STATUSES = ['pending', 'approved', 'processing'];

    private const BACKUP_FIELDS = ['id', 'status', 'approved_at', 'completed_at', 'payment_reference', 'payment_notes', 'admin_notes'];

    private const REFERENCE = 'Decontat în sistemul anterior AmBilet';

    public function handle(): int
    {
        if ($backup = $this->option('revert')) {
            return $this->revert($backup);
        }

        $apply = (bool) $this->option('apply');
        $marketplaceId = (int) $this->option('marketplace');
        $cutoff = Carbon::parse($this->option('ended-before') . ' 23:59:59', 'Europe/Bucharest');
        $skip = array_filter(array_map('intval', explode(',', (string) $this->option('skip-event'))));

        $outDir = $this->option('out') ?: ((getenv('HOME') ?: storage_path('app')) . '/settle-legacy');
        if (! is_dir($outDir) && ! @mkdir($outDir, 0775, true)) {
            $this->error("Nu pot crea folderul {$outDir}");

            return self::FAILURE;
        }
        $stamp = now()->format('Ymd-His');

        $payouts = MarketplacePayout::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereNotNull('event_id')
            ->when($this->option('event'), fn ($q, $id) => $q->where('event_id', (int) $id))
            ->with('event')
            ->orderBy('id')
            ->get();

        $byEvent = [];
        $noDate = [];
        foreach ($payouts as $p) {
            if (! $p->event || in_array((int) $p->event_id, $skip, true)) {
                continue;
            }
            $end = $p->event->getEffectiveEndDatetime();
            if (! $end) {
                $noDate[$p->event_id] = true;

                continue;
            }
            if ($end->lte($cutoff)) {
                $byEvent[$p->event_id][] = $p;
            }
        }

        $this->info(($apply ? 'APLICARE' : 'SIMULARE') . ': ' . count($byEvent) . ' evenimente, '
            . collect($byEvent)->flatten()->count() . ' deconturi. Evenimente fără dată de final (sărite): ' . count($noDate));

        if ($apply && ! $this->confirm('Scriu în baza de date?', false)) {
            return self::FAILURE;
        }

        $service = app(SalesBreakdownService::class);
        $csv = fopen("{$outDir}/settle-legacy-{$stamp}" . ($apply ? '' : '-dry') . '.csv', 'w');
        fwrite($csv, "\xEF\xBB\xBF");
        fputcsv($csv, ['organizer_id', 'event_id', 'eveniment', 'final_eveniment', 'deconturi', 'statusuri', 'suma_decontata',
            'deconturi_platite_existente', 'mai_multe_deschise', 'sold_inainte', 'sold_dupa', 'net_dupa', 'platit_dupa']);

        $backupFile = "{$outDir}/settle-legacy-{$stamp}-backup.jsonl";
        $organizers = [];
        $bar = $this->output->createProgressBar(count($byEvent));

        foreach ($byEvent as $eventId => $rows) {
            $event = $rows[0]->event;
            $before = $this->eventBalance($event, $service);

            DB::beginTransaction();
            try {
                $backup = [];
                foreach ($rows as $p) {
                    $backup[] = array_intersect_key($p->getRawOriginal(), array_flip(self::BACKUP_FIELDS));
                    $this->settle($p);
                }
                $after = $this->eventBalance($event, $service);
                if ($apply) {
                    DB::commit();
                    foreach ($backup as $b) {
                        file_put_contents($backupFile, json_encode($b, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
                    }
                } else {
                    DB::rollBack();
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->newLine();
                $this->error("Eveniment {$eventId}: {$e->getMessage()}");

                continue;
            }

            $title = is_array($event->title) ? ($event->title['ro'] ?? collect($event->title)->first()) : $event->title;
            fputcsv($csv, [
                $event->marketplace_organizer_id,
                $eventId,
                $title,
                $event->getEffectiveEndDatetime()?->format('Y-m-d'),
                collect($rows)->pluck('id')->implode(' '),
                collect($rows)->pluck('status')->implode(' '),
                $this->num(collect($rows)->sum('amount')),
                MarketplacePayout::where('event_id', $eventId)->where('status', 'completed')
                    ->whereNotIn('id', collect($rows)->pluck('id'))->count(),
                count($rows) > 1 ? 'da' : '',
                $this->num($before['balance']),
                $this->num($after['balance']),
                $this->num($after['net']),
                $this->num($after['paid']),
            ]);

            $organizers[$event->marketplace_organizer_id] = true;
            if ($apply) {
                EventAlertsService::flushCache((int) $eventId);
            }
            $bar->advance();
        }
        $bar->finish();
        fclose($csv);
        $this->newLine();

        if (! $apply) {
            $this->info("Raport: {$outDir}/settle-legacy-{$stamp}-dry.csv");

            return self::SUCCESS;
        }

        foreach (array_keys($organizers) as $orgId) {
            Cache::forget('organizer_balance_page_events:v3:' . $orgId);
        }

        $this->info("Raport: {$outDir}/settle-legacy-{$stamp}.csv");
        $this->info("Backup: {$backupFile}");
        $this->info('Recalculez soldurile (balances:reconcile --fix)...');
        $this->call('balances:reconcile', ['--fix' => true]);

        return self::SUCCESS;
    }

    /**
     * Update direct pe query: fără evenimente de model, deci fără email,
     * PDF de decont și compensare din avans. Data plății = data ciornei.
     */
    private function settle(MarketplacePayout $p): void
    {
        $note = trim(($p->admin_notes ? $p->admin_notes . "\n" : '')
            . 'Marcat plătit ' . now()->format('d.m.Y') . ': decontat și plătit în sistemul anterior, înainte de mutarea pe Tixello.');

        MarketplacePayout::whereKey($p->id)->update([
            'status' => 'completed',
            'approved_at' => $p->getRawOriginal('approved_at') ?? $p->getRawOriginal('created_at'),
            'completed_at' => $p->getRawOriginal('created_at'),
            'payment_reference' => $p->payment_reference ?: self::REFERENCE,
            'admin_notes' => $note,
        ]);
    }

    /** Sold pe eveniment, ca pe pagina de sold: net − plătit − în procesare. */
    private function eventBalance(Event $event, SalesBreakdownService $service): array
    {
        $net = (float) (MarketplacePayout::balanceBreakdown($event, $service)['total_net'] ?? 0);
        $paid = (float) MarketplacePayout::where('event_id', $event->id)->where('status', 'completed')->sum('amount');
        $pending = (float) MarketplacePayout::where('event_id', $event->id)->whereIn('status', ['approved', 'processing'])->sum('amount');

        return ['net' => $net, 'paid' => $paid, 'balance' => round($net - $paid - $pending, 2)];
    }

    private function revert(string $file): int
    {
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $rows = $lines ? array_values(array_filter(array_map(fn ($l) => json_decode($l, true), $lines))) : [];
        if (! $rows) {
            $this->error("Backup ilizibil: {$file}");

            return self::FAILURE;
        }
        if (! $this->confirm('Restaurez ' . count($rows) . ' deconturi la starea anterioară?', false)) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $r) {
                MarketplacePayout::whereKey($r['id'])->where('status', 'completed')->update([
                    'status' => $r['status'],
                    'approved_at' => $r['approved_at'],
                    'completed_at' => $r['completed_at'],
                    'payment_reference' => $r['payment_reference'],
                    'payment_notes' => $r['payment_notes'],
                    'admin_notes' => $r['admin_notes'],
                ]);
            }
        });

        $this->info('Restaurat. Recalculez soldurile...');
        $this->call('balances:reconcile', ['--fix' => true]);

        return self::SUCCESS;
    }

    private function num(float $v): string
    {
        return number_format($v, 2, '.', '');
    }
}
