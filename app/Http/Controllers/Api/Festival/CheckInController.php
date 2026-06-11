<?php

namespace App\Http\Controllers\Api\Festival;

use App\Http\Controllers\Controller;
use App\Models\FestivalEdition;
use App\Models\FestivalExternalTicket;
use App\Models\FestivalPassPurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckInController extends Controller
{
    /**
     * Check in a ticket by barcode for a festival edition.
     * Searches both festival pass purchases and external tickets.
     */
    public function checkIn(Request $request, int $editionId): JsonResponse
    {
        $data = $request->validate([
            'barcode'    => 'required|string',
            'gate'       => 'nullable|string|max:100',
            'day_id'     => 'nullable|integer',
            'operator'   => 'nullable|string|max:100',
        ]);

        $edition = FestivalEdition::findOrFail($editionId);
        $barcode = $data['barcode'];
        $gate = $data['gate'] ?? null;
        $operator = $data['operator'] ?? null;
        $dayId = $data['day_id'] ?? null;

        // 1. Search in festival pass purchases (internal tickets)
        $purchase = FestivalPassPurchase::where('code', $barcode)
            ->whereHas('festivalPass', function ($q) use ($edition) {
                $q->where('festival_edition_id', $edition->id);
            })
            ->first();

        if ($purchase) {
            return $this->checkInPurchase($purchase, $dayId, $gate, $operator);
        }

        // 2. Fallback: search external tickets
        $extTicket = FestivalExternalTicket::where('barcode', $barcode)
            ->where('festival_edition_id', $edition->id)
            ->first();

        if ($extTicket) {
            return $this->checkInExternalTicket($extTicket, $dayId, $gate, $operator);
        }

        return response()->json([
            'success' => false,
            'message' => 'Ticket not found or invalid.',
        ], 404);
    }

    /**
     * Check in by scanning (across all editions for a tenant).
     */
    public function checkInByCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id'  => 'required|integer|exists:tenants,id',
            'barcode'    => 'required|string',
            'gate'       => 'nullable|string|max:100',
            'day_id'     => 'nullable|integer',
            'operator'   => 'nullable|string|max:100',
        ]);

        $barcode = $data['barcode'];
        $gate = $data['gate'] ?? null;
        $operator = $data['operator'] ?? null;
        $dayId = $data['day_id'] ?? null;

        // Get active edition IDs for this tenant
        $editionIds = FestivalEdition::where('tenant_id', $data['tenant_id'])
            ->whereIn('status', ['active', 'announced'])
            ->pluck('id');

        // 1. Search in festival pass purchases
        $purchase = FestivalPassPurchase::where('code', $barcode)
            ->where('tenant_id', $data['tenant_id'])
            ->whereHas('festivalPass', function ($q) use ($editionIds) {
                $q->whereIn('festival_edition_id', $editionIds);
            })
            ->first();

        if ($purchase) {
            return $this->checkInPurchase($purchase, $dayId, $gate, $operator);
        }

        // 2. Fallback: search external tickets
        $extTicket = FestivalExternalTicket::where('barcode', $barcode)
            ->where('tenant_id', $data['tenant_id'])
            ->whereIn('festival_edition_id', $editionIds)
            ->first();

        if ($extTicket) {
            return $this->checkInExternalTicket($extTicket, $dayId, $gate, $operator);
        }

        return response()->json([
            'success' => false,
            'message' => 'Ticket not found or invalid.',
        ], 404);
    }

    /**
     * Undo check-in for a ticket.
     */
    public function undoCheckIn(Request $request, int $editionId, string $barcode): JsonResponse
    {
        $edition = FestivalEdition::findOrFail($editionId);

        $data = $request->validate([
            'day_id' => 'nullable|integer',
        ]);
        $dayId = $data['day_id'] ?? null;

        // Search in festival pass purchases
        $purchase = FestivalPassPurchase::where('code', $barcode)
            ->whereHas('festivalPass', function ($q) use ($edition) {
                $q->where('festival_edition_id', $edition->id);
            })
            ->first();

        if ($purchase) {
            if (! $purchase->checked_in_at) {
                return response()->json(['success' => false, 'message' => 'Ticket is not checked in.'], 400);
            }

            if ($dayId) {
                $checkins = $purchase->day_checkins ?? [];
                unset($checkins[$dayId]);
                $purchase->update(['day_checkins' => $checkins]);
            } else {
                $purchase->update([
                    'checked_in_at'  => null,
                    'checked_in_gate' => null,
                    'status'         => 'active',
                ]);
            }

            return response()->json(['success' => true, 'message' => 'Check-in undone.']);
        }

        // Fallback: search external tickets
        $extTicket = FestivalExternalTicket::where('barcode', $barcode)
            ->where('festival_edition_id', $edition->id)
            ->first();

        if (! $extTicket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
        }

        if (! $extTicket->checked_in_at) {
            return response()->json(['success' => false, 'message' => 'Ticket is not checked in.'], 400);
        }

        if ($dayId) {
            $checkins = $extTicket->day_checkins ?? [];
            unset($checkins[$dayId]);
            $extTicket->update(['day_checkins' => $checkins]);
        } else {
            $extTicket->update([
                'checked_in_at'  => null,
                'checked_in_by'  => null,
                'checked_in_gate' => null,
                'status'         => 'valid',
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Check-in undone.']);
    }

    /**
     * Import external tickets via API (CSV alternative).
     */
    public function importExternal(Request $request, int $editionId): JsonResponse
    {
        $edition = FestivalEdition::findOrFail($editionId);

        $data = $request->validate([
            'source_name' => 'nullable|string|max:200',
            'tickets'     => 'required|array|min:1|max:5000',
            'tickets.*.barcode'        => 'required|string|max:255',
            'tickets.*.first_name'     => 'nullable|string|max:255',
            'tickets.*.last_name'      => 'nullable|string|max:255',
            'tickets.*.email'          => 'nullable|email|max:255',
            'tickets.*.ticket_type'    => 'nullable|string|max:255',
            'tickets.*.original_id'    => 'nullable|string|max:255',
            'tickets.*.meta'           => 'nullable|array',
        ]);

        $batchId = (string) \Illuminate\Support\Str::ulid();
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($data['tickets'] as $ticket) {
            $barcode = trim($ticket['barcode']);
            if (empty($barcode)) {
                $errors[] = 'Empty barcode';
                continue;
            }

            $exists = FestivalExternalTicket::where('festival_edition_id', $edition->id)
                ->where('barcode', $barcode)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            FestivalExternalTicket::create([
                'tenant_id'           => $edition->tenant_id,
                'festival_edition_id' => $edition->id,
                'import_batch_id'     => $batchId,
                'source_name'         => $data['source_name'] ?? null,
                'barcode'             => $barcode,
                'attendee_first_name' => $ticket['first_name'] ?? null,
                'attendee_last_name'  => $ticket['last_name'] ?? null,
                'attendee_email'      => $ticket['email'] ?? null,
                'ticket_type_name'    => $ticket['ticket_type'] ?? null,
                'original_id'         => $ticket['original_id'] ?? null,
                'meta'                => $ticket['meta'] ?? null,
            ]);

            $imported++;
        }

        return response()->json([
            'batch_id' => $batchId,
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => count($errors),
        ], 201);
    }

    /**
     * List external tickets for an edition.
     */
    public function listExternal(Request $request, int $editionId): JsonResponse
    {
        $edition = FestivalEdition::findOrFail($editionId);

        $tickets = FestivalExternalTicket::where('festival_edition_id', $edition->id)
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 50));

        return response()->json($tickets);
    }

    // ── Private helpers ──

    private function checkInPurchase(FestivalPassPurchase $purchase, ?int $dayId, ?string $gate, ?string $operator): JsonResponse
    {
        if ($purchase->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'This ticket has been cancelled.',
            ], 400);
        }

        // Day-specific check-in
        if ($dayId && $purchase->hasCheckedInForDay($dayId)) {
            return response()->json([
                'success' => false,
                'message' => 'Already checked in for this day.',
                'ticket'  => $this->formatPurchase($purchase),
            ], 400);
        }

        // General check-in (no day)
        if (! $dayId && $purchase->checked_in_at) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket already checked in at ' . $purchase->checked_in_at->format('Y-m-d H:i:s'),
                'ticket'  => $this->formatPurchase($purchase),
            ], 400);
        }

        if ($dayId) {
            $purchase->checkInForDay($dayId, $gate);
        } else {
            $purchase->update([
                'checked_in_at'  => now(),
                'checked_in_gate' => $gate,
                'status'         => 'checked_in',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ticket checked in successfully.',
            'source'  => 'internal',
            'ticket'  => $this->formatPurchase($purchase->fresh()),
        ]);
    }

    private function checkInExternalTicket(FestivalExternalTicket $ticket, ?int $dayId, ?string $gate, ?string $operator): JsonResponse
    {
        if ($ticket->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'This ticket has been cancelled.',
            ], 400);
        }

        // Day-specific check-in
        if ($dayId && $ticket->hasCheckedInForDay($dayId)) {
            return response()->json([
                'success' => false,
                'message' => 'Already checked in for this day.',
                'ticket'  => $this->formatExternal($ticket),
            ], 400);
        }

        // General check-in
        if (! $dayId && $ticket->checked_in_at) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket already checked in at ' . $ticket->checked_in_at->format('Y-m-d H:i:s'),
                'ticket'  => $this->formatExternal($ticket),
            ], 400);
        }

        if ($dayId) {
            $ticket->checkInForDay($dayId, $gate, $operator);
        } else {
            $ticket->update([
                'checked_in_at'  => now(),
                'checked_in_by'  => $operator,
                'checked_in_gate' => $gate,
                'status'         => 'used',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ticket checked in successfully.',
            'source'  => 'external',
            'ticket'  => $this->formatExternal($ticket->fresh()),
        ]);
    }

    private function formatPurchase(FestivalPassPurchase $purchase): array
    {
        return [
            'id'             => $purchase->id,
            'code'           => $purchase->code,
            'holder_name'    => $purchase->holder_name,
            'holder_email'   => $purchase->holder_email,
            'ticket_type'    => $purchase->festivalPass?->name,
            'status'         => $purchase->status,
            'checked_in_at'  => $purchase->checked_in_at?->toIso8601String(),
            'checked_in_gate' => $purchase->checked_in_gate,
            'day_checkins'   => $purchase->day_checkins,
        ];
    }

    private function formatExternal(FestivalExternalTicket $ticket): array
    {
        return [
            'id'             => $ticket->id,
            'barcode'        => $ticket->barcode,
            'attendee_name'  => $ticket->attendee_name,
            'attendee_email' => $ticket->attendee_email,
            'ticket_type'    => $ticket->ticket_type_name,
            'source'         => $ticket->source_name ?? 'external',
            'status'         => $ticket->status,
            'checked_in_at'  => $ticket->checked_in_at?->toIso8601String(),
            'checked_in_by'  => $ticket->checked_in_by,
            'checked_in_gate' => $ticket->checked_in_gate,
            'day_checkins'   => $ticket->day_checkins,
        ];
    }
}
