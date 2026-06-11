<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\EventEditingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class EditPresenceController extends Controller
{
    /**
     * Window in seconds during which a heartbeat keeps a session "active".
     * Frontend should ping ~ every (WINDOW / 2) seconds to keep the session alive.
     */
    private const ACTIVE_WINDOW_SECONDS = 30;

    /**
     * Heartbeat: upsert this admin's editing session for the event, prune stale rows,
     * and return the list of OTHER admins currently editing the same event.
     */
    public function heartbeat(Request $request, int $eventId): JsonResponse
    {
        $admin = Auth::guard('marketplace_admin')->user();
        if (!$admin) {
            return response()->json(['ok' => false, 'editors' => []], 401);
        }

        $now = Carbon::now();
        $cutoff = $now->copy()->subSeconds(self::ACTIVE_WINDOW_SECONDS);

        EventEditingSession::updateOrCreate(
            ['event_id' => $eventId, 'admin_id' => $admin->id],
            ['admin_name' => $admin->name ?? $admin->email ?? ('Admin #' . $admin->id), 'last_seen_at' => $now]
        );

        // Best-effort prune: don't let the table grow unbounded.
        EventEditingSession::where('last_seen_at', '<', $cutoff->copy()->subMinutes(5))->delete();

        $editors = EventEditingSession::where('event_id', $eventId)
            ->where('admin_id', '!=', $admin->id)
            ->where('last_seen_at', '>=', $cutoff)
            ->get(['admin_id', 'admin_name', 'last_seen_at'])
            ->map(fn ($s) => [
                'id' => $s->admin_id,
                'name' => $s->admin_name,
                'last_seen_at' => $s->last_seen_at->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'editors' => $editors,
        ]);
    }

    /**
     * Mark this admin as no longer editing (best-effort; called on page unload).
     */
    public function leave(Request $request, int $eventId): JsonResponse
    {
        $admin = Auth::guard('marketplace_admin')->user();
        if (!$admin) {
            return response()->json(['ok' => false], 401);
        }

        EventEditingSession::where('event_id', $eventId)
            ->where('admin_id', $admin->id)
            ->delete();

        return response()->json(['ok' => true]);
    }
}
