<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Invite;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Print event invitations as a tiled PDF sheet (N per page) with
 * configurable paper size + bleed. Two-step flow:
 *   - GET  without ?paper=... → show config form
 *   - GET  with paper + orientation + per_page + bleed_mm → generate PDF
 *
 * Auth is via the marketplace_admin guard (route middleware); this
 * controller additionally enforces that the event belongs to the
 * admin's marketplace_client_id, so one marketplace can't peek at
 * another's invitations by tweaking the URL.
 */
class PrintInvitationsController extends Controller
{
    /**
     * Grid layouts by per_page. Rows × cols is oriented for a portrait
     * page; for landscape the controller swaps them before rendering.
     */
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

        $invites = Invite::query()
            ->whereHas('batch', fn ($q) => $q->where('marketplace_event_id', $event->id))
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
            'bleed_mm' => 'required|numeric|min:0|max:20',
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

        // Group invites into pages of $perPage. array_chunk preserves order
        // and gives the blade a simple foreach.
        $pages = array_chunk($invites->all(), $perPage);

        $pdf = Pdf::loadView('pdf.invitations-print-sheet', [
            'event' => $event,
            'pages' => $pages,
            'perPage' => $perPage,
            'cols' => $layout['cols'],
            'rows' => $layout['rows'],
            'paper' => $data['paper'],
            'orientation' => $orientation,
            'bleedMm' => (float) $data['bleed_mm'],
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
