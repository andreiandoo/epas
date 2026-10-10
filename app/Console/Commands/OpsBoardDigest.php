<?php

namespace App\Console\Commands;

use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceClientMicroservice;
use App\Models\MarketplaceEmailLog;
use App\Services\Marketplace\OpsBoardService;
use App\Support\MarketplaceTz;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Emails what the operations board shows as open, so nothing waits on
 * someone remembering to look at it.
 *
 *   ops-board:digest               Monday summary for admins + super admins:
 *                                  what is overdue and what is due this week.
 *   ops-board:digest --escalation  Items overdue for at least `escalate_days`,
 *                                  to super admins only. Sends nothing when
 *                                  there are none.
 */
class OpsBoardDigest extends Command
{
    protected $signature = 'ops-board:digest
        {--escalation : Send the overdue escalation to super admins instead of the weekly summary}
        {--marketplace= : Only this marketplace client id}
        {--dry-run : Print what would be sent without sending}';

    protected $description = 'Email the operations board summary (weekly) or the overdue escalation';

    public function handle(OpsBoardService $board): int
    {
        $escalation = (bool) $this->option('escalation');

        $pivots = MarketplaceClientMicroservice::query()
            ->where('status', 'active')
            ->whereHas('microservice', fn ($q) => $q->where('slug', OpsBoardService::SLUG))
            ->when($this->option('marketplace'), fn ($q, $id) => $q->where('marketplace_client_id', (int) $id))
            ->with('marketplaceClient')
            ->get();

        foreach ($pivots as $pivot) {
            $marketplace = $pivot->marketplaceClient;
            if (! $marketplace || ! $pivot->isActive()) {
                continue;
            }

            $escalateDays = (int) ($pivot->getSetting('escalate_days') ?? OpsBoardService::DEFAULT_ESCALATE_DAYS);
            if ($escalation ? $escalateDays <= 0 : ! ($pivot->getSetting('digest_enabled') ?? true)) {
                continue;
            }

            $tz = MarketplaceTz::tz($marketplace);
            $today = Carbon::now($tz)->startOfDay();
            $from = $today->copy()->startOfWeek(Carbon::MONDAY);
            $to = $today->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay();

            $items = $board->openItems($board->build($marketplace, $from, $to, OpsBoardService::trackFrom($pivot, $tz)));
            if ($escalation) {
                $items = array_values(array_filter($items, fn ($i) => $i['overdue'] && $i['late'] >= $escalateDays));
            }
            if (empty($items)) {
                $this->line("[{$marketplace->id}] nothing to report");

                continue;
            }

            $recipients = MarketplaceAdmin::query()
                ->where('marketplace_client_id', $marketplace->id)
                ->where('status', 'active')
                ->whereIn('role', $escalation ? ['super_admin'] : ['super_admin', 'admin'])
                ->whereNotNull('email')
                ->pluck('email')
                // Core super admins get a "(System)" marketplace admin with a
                // placeholder address; only real mailboxes are written to.
                ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
                ->unique()
                ->values();

            $overdue = array_values(array_filter($items, fn ($i) => $i['overdue']));
            $upcoming = array_values(array_filter($items, fn ($i) => ! $i['overdue']));
            $subject = $escalation
                ? 'Tablă operațiuni: ' . count($items) . ' restanțe de cel puțin ' . $escalateDays . ' zile'
                : 'Tablă operațiuni: ' . count($overdue) . ' restante, ' . count($upcoming) . ' de făcut săptămâna aceasta';
            $html = $this->render($marketplace->public_name ?? $marketplace->name, $overdue, $escalation ? [] : $upcoming, $escalation);

            if ($this->option('dry-run')) {
                $this->info("[{$marketplace->id}] {$subject} -> " . $recipients->implode(', '));
                foreach ($items as $i) {
                    $this->line('  ' . ($i['overdue'] ? '!' : ' ') . " {$i['task']}: {$i['event']} ({$i['date']}) - {$i['label']}");
                }

                continue;
            }

            if ($recipients->isEmpty() || (! $marketplace->hasMailConfigured() && ! $marketplace->hasTransactionalMailConfigured())) {
                $this->warn("[{$marketplace->id}] no recipients or mail not configured");

                continue;
            }

            $fromAddress = $marketplace->getTransactionalEmailFromAddress();
            $fromName = $marketplace->getTransactionalEmailFromName();
            foreach ($recipients as $to) {
                $result = $marketplace->sendTransactionalEmail(
                    (new \Symfony\Component\Mime\Email())
                        ->from(new \Symfony\Component\Mime\Address($fromAddress, $fromName))
                        ->to($to)
                        ->subject($subject)
                        ->html($html)
                );

                MarketplaceEmailLog::create([
                    'marketplace_client_id' => $marketplace->id,
                    'template_slug' => $escalation ? 'ops_board_escalation' : 'ops_board_digest',
                    'from_email' => $fromAddress,
                    'from_name' => $fromName,
                    'to_email' => $to,
                    'subject' => $subject,
                    'body_html' => $html,
                    'status' => ($result['success'] ?? false) ? 'sent' : 'failed',
                    'sent_at' => ($result['success'] ?? false) ? now() : null,
                    'message_id' => $result['message_id'] ?? null,
                    'error_message' => ($result['success'] ?? false) ? null : ($result['error'] ?? null),
                    'metadata' => ['transport_used' => $result['transport_used'] ?? null],
                ]);
            }
            $this->info("[{$marketplace->id}] sent to {$recipients->count()}: {$subject}");
        }

        return self::SUCCESS;
    }

    protected function render(string $marketplaceName, array $overdue, array $upcoming, bool $escalation): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $list = function (string $heading, array $items, string $color) use ($base) {
            if (empty($items)) {
                return '';
            }
            $rows = '';
            foreach ($items as $i) {
                $rows .= '<tr>'
                    . '<td style="padding:6px 10px 6px 0;border-bottom:1px solid #eee;"><a href="' . e($base . $i['url']) . '" style="color:#111;font-weight:bold;text-decoration:none;">' . e($i['event']) . '</a>'
                    . '<br><span style="color:#888;font-size:12px;">' . e($i['date']) . '</span></td>'
                    . '<td style="padding:6px 10px;border-bottom:1px solid #eee;white-space:nowrap;">' . e($i['task']) . '</td>'
                    . '<td style="padding:6px 0;border-bottom:1px solid #eee;color:' . $color . ';">' . e($i['label']) . ($i['detail'] ? '<br><span style="font-size:12px;">' . e($i['detail']) . '</span>' : '') . '</td>'
                    . '</tr>';
            }

            return '<h3 style="font-size:15px;margin:20px 0 6px;">' . e($heading) . ' (' . count($items) . ')</h3>'
                . '<table style="border-collapse:collapse;width:100%;font-size:13px;">' . $rows . '</table>';
        };

        return '<div style="font-family:Arial,sans-serif;font-size:14px;color:#333;max-width:720px;">'
            . '<p>Bună ziua,</p>'
            . '<p>' . ($escalation
                ? 'Mai jos sunt task-urile care au depășit scadența și sunt încă nerezolvate.'
                : 'Mai jos e situația de pe tabla de operațiuni la început de săptămână.') . '</p>'
            . $list('Restante', $overdue, '#b91c1c')
            . $list('De făcut', $upcoming, '#92400e')
            . '<p style="margin-top:20px;"><a href="' . e($base . '/marketplace/ops-board') . '">Deschide tabla de operațiuni</a></p>'
            . '<p style="color:#888;font-size:12px;">' . e($marketplaceName) . ' · mesaj automat, se poate opri din Setările tablei.</p>'
            . '</div>';
    }
}
