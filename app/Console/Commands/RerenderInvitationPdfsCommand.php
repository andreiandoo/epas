<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\MarketplaceClient\Organizer\InvitationsController;
use App\Models\Event;
use App\Models\Invite;
use App\Models\InviteBatch;
use App\Models\TicketTemplate;
use App\Services\Invitations\InvitationTemplateData;
use App\Services\TicketCustomizer\TicketPreviewGenerator;
use App\Services\TicketCustomizer\TicketVariableService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Re-renders invitation PDFs that were generated before the PDF size fixes
 * (font subsetting + template image optimisation), replacing each file in
 * place with a smaller one. A 1.7–2 MB invitation typically drops to a few
 * hundred KB.
 *
 * Built to be unable to damage anything:
 *   - writes NOTHING to the database (no status, urls, rendered_at, tickets);
 *   - only replaces a PDF that already exists, at the same path;
 *   - keeps the old file unless the new one is a valid PDF, rendered with
 *     every QR code / barcode / image fetched, and at least 10% smaller;
 *   - never falls back to another design: a template that fails to render
 *     leaves the old file untouched;
 *   - the QR encodes the invite's stored qr_data, not a URL rebuilt from
 *     APP_URL, so the code printed is byte-for-byte the one already sent.
 *
 * Only custom-template batches are handled (the hardcoded Blade design
 * carries no images, so there is little to gain). The data dictionary comes
 * from the same builder the batch was created with: the organizer
 * controller's buildTemplateData for organizer batches, InvitationTemplateData
 * for marketplace-admin batches.
 *
 * Dry-run by default: lists what would be processed without rendering.
 *
 *   php artisan invitations:rerender-pdfs
 *   php artisan invitations:rerender-pdfs --batch=958 --force
 *   php artisan invitations:rerender-pdfs --force
 */
class RerenderInvitationPdfsCommand extends Command
{
    protected $signature = 'invitations:rerender-pdfs
        {--batch=* : Only these batch ids}
        {--before=2026-10-14 : Only PDFs last written before this date (already-optimised files are newer)}
        {--allow-default-template : Also process admin batches with no template of their own (rendered with the marketplace default template as it is NOW)}
        {--sleep-ms=150 : Pause between invitations (each one fetches its QR from an external service)}
        {--limit= : Stop after this many batches}
        {--force : Actually re-render and replace files (without it the command only reports)}';

    protected $description = 'Re-render old invitation PDFs in place so they take less disk space (dry-run unless --force). Never touches the database.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $before = Carbon::parse($this->option('before'))->getTimestamp();
        $only = array_map('intval', (array) $this->option('batch'));
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $sleepMicros = max(0, (int) $this->option('sleep-ms')) * 1000;
        $disk = Storage::disk('local');

        $totals = ['batches' => 0, 'replaced' => 0, 'kept' => 0, 'before' => 0, 'after' => 0];
        $skippedBatches = [];
        $rows = [];

        $dirs = collect($disk->directories('invitations'))
            ->map(fn ($dir) => basename($dir))
            ->filter(fn ($id) => ctype_digit($id) && (!$only || in_array((int) $id, $only, true)))
            ->sortBy(fn ($id) => (int) $id)
            ->values();

        foreach ($dirs as $batchId) {
            if ($limit !== null && $totals['batches'] >= $limit) {
                break;
            }

            $batch = InviteBatch::find((int) $batchId);
            [$plan, $reason] = $batch ? $this->planFor($batch) : [null, 'no batch row'];
            if (!$plan) {
                $skippedBatches[$reason] = ($skippedBatches[$reason] ?? 0) + 1;
                continue;
            }

            // Old PDFs of this batch that are still on disk.
            $candidates = [];
            foreach ($batch->invites()->get() as $invite) {
                $path = "invitations/{$batch->id}/{$invite->invite_code}.pdf";
                if ($invite->invite_code && $disk->exists($path) && $disk->lastModified($path) < $before) {
                    $candidates[] = [$invite, $path, $disk->size($path)];
                }
            }
            if (!$candidates) {
                $skippedBatches['no old PDFs (already optimised or none on disk)'] = ($skippedBatches['no old PDFs (already optimised or none on disk)'] ?? 0) + 1;
                continue;
            }

            $totals['batches']++;
            $batchBefore = array_sum(array_column($candidates, 2));
            $batchAfter = 0;
            $replaced = 0;
            $kept = [];

            if ($force) {
                foreach ($candidates as [$invite, $path, $oldSize]) {
                    $result = $this->rerender($invite, $batch, $plan, $path, $oldSize);
                    if ($result['replaced']) {
                        $replaced++;
                        $batchAfter += $result['size'];
                    } else {
                        $batchAfter += $oldSize;
                        $kept[$result['reason']] = ($kept[$result['reason']] ?? 0) + 1;
                    }
                    gc_collect_cycles();
                    if ($sleepMicros) {
                        usleep($sleepMicros);
                    }
                }
            }

            $totals['replaced'] += $replaced;
            $totals['kept'] += array_sum($kept);
            $totals['before'] += $batchBefore;
            $totals['after'] += $force ? $batchAfter : $batchBefore;

            $rows[] = [
                $batch->id,
                $plan['flow'],
                count($candidates),
                $this->mb($batchBefore),
                $force ? $this->mb($batchAfter) : '-',
                $force ? $replaced : '-',
                $kept ? collect($kept)->map(fn ($n, $why) => "{$n}× {$why}")->implode('; ') : '',
            ];

            if ($force) {
                $this->line("batch {$batch->id}: {$replaced}/" . count($candidates) . ' replaced, '
                    . $this->mb($batchBefore) . ' -> ' . $this->mb($batchAfter)
                    . ($kept ? ' | kept: ' . end($rows)[6] : ''));
            }
        }

        if (!$force) {
            $this->table(['Batch', 'Flow', 'Old PDFs', 'Size', 'After', 'Replaced', 'Kept'], $rows);
        }

        if ($skippedBatches) {
            $this->line('Batches not handled:');
            foreach ($skippedBatches as $why => $n) {
                $this->line("  {$n}× {$why}");
            }
        }

        $this->info($force
            ? "Done: {$totals['batches']} batches, {$totals['replaced']} PDFs replaced, {$totals['kept']} kept as they were. "
                . $this->mb($totals['before']) . ' -> ' . $this->mb($totals['after']) . '.'
            : "Dry-run: {$totals['batches']} batches hold " . $this->mb($totals['before'])
                . ' of old PDFs. Try one first: --batch=<id> --force');

        return self::SUCCESS;
    }

    /**
     * Decide whether a batch can be re-rendered faithfully, and how.
     *
     * @return array{0: ?array{flow: string, template: TicketTemplate, event: Event}, 1: string}
     */
    protected function planFor(InviteBatch $batch): array
    {
        if (!$batch->marketplace_client_id) {
            return [null, 'tenant batch (Blade design, nothing to gain)'];
        }
        if (!ctype_digit((string) $batch->event_ref) || !($event = Event::find((int) $batch->event_ref))) {
            return [null, 'event not found'];
        }
        $endDate = $event->range_end_date ?? $event->event_date;
        if ($endDate && $endDate->lt(now()->startOfDay())) {
            return [null, 'event already ended (prune removes these)'];
        }

        $isOrganizerFlow = (bool) $batch->marketplace_organizer_id;
        $template = $batch->template;

        if (!$template && !$isOrganizerFlow && $this->option('allow-default-template')) {
            $template = TicketTemplate::where('marketplace_client_id', $batch->marketplace_client_id)
                ->where('status', 'active')
                ->where('is_default', true)
                ->first();
        }
        if (!$template) {
            return [null, $isOrganizerFlow
                ? 'no template on the batch (Blade design, nothing to gain)'
                : 'admin batch without its own template (see --allow-default-template)'];
        }
        if (empty($template->template_data['layers'] ?? null)) {
            return [null, 'template has no layers (Blade design, nothing to gain)'];
        }

        return [['flow' => $isOrganizerFlow ? 'organizer' : 'admin', 'template' => $template, 'event' => $event], ''];
    }

    /**
     * @return array{replaced: bool, size: int, reason: string}
     */
    protected function rerender(Invite $invite, InviteBatch $batch, array $plan, string $path, int $oldSize): array
    {
        $keep = fn (string $reason) => ['replaced' => false, 'size' => $oldSize, 'reason' => $reason];

        // The QR must encode exactly what the delivered invitation encodes.
        $qrData = (string) $invite->qr_data;
        if (!preg_match('#^(https?://[^/]+)/verify/' . preg_quote((string) $invite->invite_code, '#') . '$#', $qrData, $m)) {
            return $keep('no stored QR data');
        }
        // url() has no request to read the host from in the console.
        URL::forceRootUrl($m[1]);
        URL::forceScheme(str_starts_with($m[1], 'https') ? 'https' : 'http');

        try {
            $failuresBefore = TicketPreviewGenerator::$assetFailures;
            $pdf = $this->renderPdf($invite, $batch, $plan, $qrData);
            if (TicketPreviewGenerator::$assetFailures !== $failuresBefore) {
                return $keep('QR/image could not be fetched');
            }
        } catch (\Throwable $e) {
            return $keep('render failed: ' . mb_substr($e->getMessage(), 0, 60));
        }

        if ($pdf === null) {
            return $keep('template rendered empty');
        }
        if (!str_starts_with($pdf, '%PDF') || strlen($pdf) < 2048) {
            return $keep('output is not a valid PDF');
        }
        if (strlen($pdf) > $oldSize * 0.9) {
            return $keep('no size gain');
        }

        // Write next to the original, then swap in one atomic rename.
        $disk = Storage::disk('local');
        $tmpPath = $path . '.tmp';
        $disk->put($tmpPath, $pdf);
        if ($disk->size($tmpPath) !== strlen($pdf) || !@rename($disk->path($tmpPath), $disk->path($path))) {
            $disk->delete($tmpPath);

            return $keep('could not write the new file');
        }

        return ['replaced' => true, 'size' => strlen($pdf), 'reason' => ''];
    }

    /**
     * Same HTML and dompdf options as the flow that produced the batch,
     * minus the fallback to the Blade design.
     */
    protected function renderPdf(Invite $invite, InviteBatch $batch, array $plan, string $qrData): ?string
    {
        /** @var TicketTemplate $template */
        $template = $plan['template'];
        /** @var Event $event */
        $event = $plan['event'];
        $generator = app(TicketPreviewGenerator::class);
        $ticketLabel = is_array($batch->options ?? null) ? ($batch->options['ticket_label'] ?? null) : null;

        $size = $template->getSize();
        $widthPt = round(($size['width'] ?? 210) * 2.8346, 2);
        $heightPt = round(($size['height'] ?? 297) * 2.8346, 2);
        $bgColor = $template->template_data['meta']['background']['color'] ?? '#ffffff';
        $singlePage = fn (string $content) => '<!DOCTYPE html><html><head><meta charset="UTF-8">'
            . "<style>@page { margin: 0; size: {$widthPt}pt {$heightPt}pt; } * { margin: 0; padding: 0; } body { margin: 0; padding: 0; width: {$widthPt}pt; height: {$heightPt}pt; background-color: {$bgColor}; font-family: 'DejaVu Sans', sans-serif; overflow: hidden; }</style>"
            . "</head><body>{$content}</body></html>";

        if ($plan['flow'] === 'organizer') {
            // The organizer controller owns this dictionary; call its own
            // builders instead of copying them.
            $data = \Closure::bind(function () use ($invite, $event, $qrData, $ticketLabel) {
                [$eventTitle, $eventSubtitle, $eventDate, $eventTime, $venueName] = $this->buildEventContext($event);

                return $this->buildTemplateData($invite, $event, [
                    'eventTitle' => $eventTitle,
                    'eventSubtitle' => $eventSubtitle,
                    'eventDate' => $eventDate,
                    'eventTime' => $eventTime,
                    'venueName' => $venueName,
                    'qrData' => $qrData,
                    'ticketLabel' => $ticketLabel,
                ], app(TicketVariableService::class));
            }, app(InvitationsController::class), InvitationsController::class)();

            $content = $generator->renderToHtml($template->template_data, $data);
            if (trim($content) === '') {
                return null;
            }
            $html = $singlePage($content);
        } else {
            $data = app(InvitationTemplateData::class)->build($invite, $event, [
                'ticket_label' => $ticketLabel,
                'qrcode' => $qrData,
            ]);

            $content = $generator->renderToHtml($template->template_data, $data);
            if (trim($content) === '') {
                return null;
            }

            // Optional second page (template_data.page_2.enabled). Unlike the
            // interactive flow, a failing page 2 aborts instead of silently
            // producing a one-page PDF.
            $page2 = $template->template_data['page_2'] ?? null;
            $page2Section = '';
            if (is_array($page2) && ($page2['enabled'] ?? false) && !empty($page2['layers'] ?? [])) {
                $page2Content = $generator->renderToHtml($page2, $data);
                if (trim($page2Content) !== '') {
                    $page2Bg = $page2['meta']['background']['color'] ?? $bgColor;
                    $page2Section = "<div style=\"page-break-before: always; position: relative; width: {$widthPt}pt; height: {$heightPt}pt; overflow: hidden; background-color: {$page2Bg};\">" . str_replace('position: fixed;', 'position: absolute;', $page2Content) . '</div>';
                }
            }

            $html = $page2Section !== ''
                ? "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><style>@page { margin: 0; size: {$widthPt}pt {$heightPt}pt; } * { margin: 0; padding: 0; } body { margin: 0; padding: 0; background-color: {$bgColor}; font-family: 'DejaVu Sans', sans-serif; } .ep-ticket-page { width: {$widthPt}pt; height: {$heightPt}pt; overflow: hidden; position: relative; }</style></head><body><div class=\"ep-ticket-page\">{$content}</div>{$page2Section}</body></html>"
                : $singlePage($content);
        }

        return Pdf::loadHTML($html)
            ->setPaper([0, 0, $widthPt, $heightPt])
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->output();
    }

    protected function mb(int $bytes): string
    {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
}
