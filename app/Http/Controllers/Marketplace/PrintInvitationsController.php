<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Invite;
use App\Models\TicketTemplate;
use App\Services\TicketCustomizer\TicketPreviewGenerator;
use App\Services\TicketCustomizer\TicketVariableService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Print event invitations as a tiled PDF sheet (N per page) with
 * configurable paper size + bleed. Two-step flow:
 *   - GET  without ?paper=... → show config form
 *   - GET  with paper + orientation + per_page + bleed_(x|y)_mm → generate PDF
 *
 * Each tile embeds the invitation rendered through the SAME ticket
 * template chain the single-invitation download uses (batch template
 * → marketplace default → simple HTML fallback), scaled to fit the
 * tile via CSS transform.
 */
class PrintInvitationsController extends Controller
{
    protected array $layouts = [
        1  => ['cols' => 1, 'rows' => 1],
        2  => ['cols' => 1, 'rows' => 2],
        3  => ['cols' => 1, 'rows' => 3],
        4  => ['cols' => 2, 'rows' => 2],
        6  => ['cols' => 2, 'rows' => 3],
        8  => ['cols' => 2, 'rows' => 4],
        9  => ['cols' => 3, 'rows' => 3],
        12 => ['cols' => 3, 'rows' => 4],
    ];

    public function index(Request $request, Event $event)
    {
        $admin = auth('marketplace_admin')->user();
        abort_unless($admin && $event->marketplace_client_id === $admin->marketplace_client_id, 403);

        // Marketplace-admin batches store the event id in `event_ref`
        // (string OR int); organizer / tenant-side flows populate
        // `marketplace_event_id`. Match either.
        $invites = Invite::query()
            ->with('batch.template')
            ->whereHas('batch', function ($q) use ($event) {
                $q->where('event_ref', (string) $event->id)
                    ->orWhere('event_ref', $event->id)
                    ->orWhere('marketplace_event_id', $event->id);
            })
            ->whereNotIn('status', ['void'])
            ->orderBy('id')
            ->get();

        if (!$request->has('paper')) {
            return view('marketplace.print-invitations-form', [
                'event' => $event,
                'inviteCount' => $invites->count(),
                'layouts' => array_keys($this->layouts),
            ]);
        }

        $data = $request->validate([
            'paper' => 'required|in:A3,A4,A5',
            'orientation' => 'required|in:portrait,landscape',
            'per_page' => ['required', 'integer', 'in:' . implode(',', array_keys($this->layouts))],
            'bleed_x_mm' => 'required|numeric|min:0|max:20',
            'bleed_y_mm' => 'required|numeric|min:0|max:20',
        ]);

        if ($invites->isEmpty()) {
            abort(404, 'Nu există invitații emise pentru acest eveniment.');
        }

        $perPage = (int) $data['per_page'];
        $orientation = $data['orientation'];
        $layout = $this->layouts[$perPage];
        if ($orientation === 'landscape') {
            [$layout['cols'], $layout['rows']] = [$layout['rows'], $layout['cols']];
        }

        $pages = array_chunk($invites->all(), $perPage);

        // Resolve the template used for each invitation. Same chain as the
        // single-invitation download: batch's linked template first, marketplace
        // default second. Rendering happens per invite so recipient / code /
        // seat placeholders are populated.
        $template = $invites->first()?->batch?->template
            ?? TicketTemplate::where('marketplace_client_id', $admin->marketplace_client_id)
                ->where('status', 'active')
                ->where('is_default', true)
                ->first();

        $renderedHtmls = [];
        $templateWidthMm = null;
        $templateHeightMm = null;
        $templateBg = '#ffffff';

        // Compute the scale from template natural size to actual tile size,
        // then multiply every `pt` value in the rendered HTML by it. This is
        // way more reliable than DomPDF's `zoom` — which shrinks visually
        // but not layout box, so tiles overflow their cells.
        $paperDims = match ($data['paper']) {
            'A3' => ['w' => 297, 'h' => 420],
            'A5' => ['w' => 148, 'h' => 210],
            default => ['w' => 210, 'h' => 297],
        };
        if ($orientation === 'landscape') {
            [$paperDims['w'], $paperDims['h']] = [$paperDims['h'], $paperDims['w']];
        }
        $innerWmm = $paperDims['w'] - 2 * (float) $data['bleed_x_mm'];
        $innerHmm = $paperDims['h'] - 2 * (float) $data['bleed_y_mm'];
        $tileWmm = $innerWmm / $layout['cols'];
        $tileHmm = $innerHmm / $layout['rows'];

        if ($template && !empty($template->template_data['layers'])) {
            try {
                $generator = app(TicketPreviewGenerator::class);
                $variableService = app(TicketVariableService::class);
                $size = $template->getSize();
                $templateWidthMm = (float) $size['width'];
                $templateHeightMm = (float) $size['height'];
                $templateBg = $template->template_data['meta']['background']['color'] ?? '#ffffff';

                // Fit-scale keeping aspect ratio: how much of the tile the
                // scaled template actually occupies (leaves whitespace on
                // one axis if aspect ratios differ).
                $ptScale = min(
                    $tileWmm / max($templateWidthMm, 0.01),
                    $tileHmm / max($templateHeightMm, 0.01)
                );

                $eventTitle = is_array($event->title)
                    ? ($event->title['ro'] ?? $event->title['en'] ?? reset($event->title) ?? '')
                    : ($event->title ?? '');
                $eventDate = $event->event_date
                    ? \Carbon\Carbon::parse($event->event_date)->translatedFormat('d F Y')
                    : ($event->range_start_date
                        ? \Carbon\Carbon::parse($event->range_start_date)->translatedFormat('d F Y')
                        : '');
                $eventTime = $event->start_time ? substr($event->start_time, 0, 5) : '';
                $venueName = null;
                if ($event->venue) {
                    $vn = $event->venue->name ?? null;
                    $venueName = is_array($vn) ? ($vn['ro'] ?? $vn['en'] ?? reset($vn)) : $vn;
                }

                foreach ($invites as $invite) {
                    try {
                        $recipient = is_array($invite->recipient) ? $invite->recipient : [];
                        $recipientName = trim(
                            ($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? '')
                        );
                        if ($recipientName === '') {
                            $recipientName = $recipient['name'] ?? 'Invitat';
                        }
                        $recipientEmail = $recipient['email'] ?? '';
                        $qrData = $invite->qr_data ?: url("/verify/{$invite->invite_code}");

                        $d = $variableService->getSampleData();
                        $d['event'] = array_merge($d['event'], [
                            'name' => $eventTitle,
                            'date' => $eventDate,
                            'time' => $eventTime,
                            'venue' => $venueName ?? '',
                        ]);
                        $d['ticket'] = array_merge($d['ticket'], [
                            'type' => 'INVITAȚIE',
                            'price' => 'GRATUIT',
                            'price_detail' => 'Invitație',
                            'code_short' => $invite->invite_code,
                            'code_long' => $invite->invite_code,
                            'serial' => $invite->invite_code,
                            'seat' => $invite->seat_ref ?? '',
                        ]);
                        $d['buyer'] = array_merge($d['buyer'], [
                            'name' => $recipientName,
                            'first_name' => explode(' ', $recipientName)[0] ?? $recipientName,
                            'last_name' => explode(' ', $recipientName, 2)[1] ?? '',
                            'email' => $recipientEmail,
                        ]);
                        $d['barcode'] = $invite->invite_code;
                        $d['qrcode'] = $qrData;

                        // Two mutations to make the template safely embeddable
                        // in a small grid cell:
                        //  1. `position: fixed` → `position: absolute` so
                        //     layers scope to the tile wrapper instead of
                        //     the page (same trick TicketsController uses).
                        //  2. Multiply every `Npt` value by $ptScale so the
                        //     rendered template physically shrinks — DomPDF
                        //     `zoom` isn't reliable, so we scale the pt
                        //     values in the emitted CSS directly.
                        $rawHtml = $generator->renderToHtml($template->template_data, $d);
                        $rawHtml = str_replace('position: fixed;', 'position: absolute;', $rawHtml);
                        $renderedHtmls[$invite->id] = preg_replace_callback(
                            '/(\d+(?:\.\d+)?)pt/',
                            fn ($m) => round(((float) $m[1]) * $ptScale, 2) . 'pt',
                            $rawHtml
                        );
                    } catch (\Throwable $e) {
                        Log::warning('Print invitations: template render failed for invite', [
                            'invite_id' => $invite->id,
                            'error' => $e->getMessage(),
                        ]);
                        $renderedHtmls[$invite->id] = null;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Print invitations: template setup failed, falling back to simple layout', [
                    'template_id' => $template->id ?? null,
                    'error' => $e->getMessage(),
                ]);
                $renderedHtmls = [];
                $templateWidthMm = null;
                $templateHeightMm = null;
            }
        }

        $pdf = Pdf::loadView('pdf.invitations-print-sheet', [
            'event' => $event,
            'pages' => $pages,
            'perPage' => $perPage,
            'cols' => $layout['cols'],
            'rows' => $layout['rows'],
            'paper' => $data['paper'],
            'orientation' => $orientation,
            'bleedXMm' => (float) $data['bleed_x_mm'],
            'bleedYMm' => (float) $data['bleed_y_mm'],
            'renderedHtmls' => $renderedHtmls,
            'templateWidthMm' => $templateWidthMm,
            'templateHeightMm' => $templateHeightMm,
            'templateBg' => $templateBg,
        ])
            ->setPaper(strtolower($data['paper']), $orientation)
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        $filename = sprintf(
            'invitatii-event-%d-%dpp-%s-%s.pdf',
            $event->id,
            $perPage,
            $data['paper'],
            $orientation,
        );

        return $pdf->download($filename);
    }
}
