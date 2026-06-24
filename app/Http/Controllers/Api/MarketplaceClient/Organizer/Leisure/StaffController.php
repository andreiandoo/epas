<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer\Leisure;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\LeisureStaffCheckin;
use App\Models\LeisureStaffMember;
use App\Models\MarketplaceOrganizer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD pentru angajații permanent ai unui organizer leisure + raport check-in.
 *
 * Endpoints:
 *   GET    /organizer/leisure/staff           — listă (cu count check-ins + last)
 *   POST   /organizer/leisure/staff           — create (auto-generează QR)
 *   PUT    /organizer/leisure/staff/{id}      — update (QR-ul rămâne fix)
 *   DELETE /organizer/leisure/staff/{id}      — soft delete (set active=false)
 *   GET    /organizer/leisure/staff/checkins  — listă check-ins (filtre)
 *   GET    /organizer/leisure/staff/export    — export CSV check-ins
 *
 * Filtre raport: ?staff_id=X, ?from=YYYY-MM-DD, ?to=YYYY-MM-DD, ?event_id=X.
 */
class StaffController extends BaseController
{
    /**
     * GET /organizer/leisure/staff
     * Returnează lista de staff cu metadate de activitate (last_checkin_at, total).
     */
    public function index(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $staff = LeisureStaffMember::query()
            ->where('marketplace_organizer_id', $organizer->id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (LeisureStaffMember $s) {
                $lastCheckin = $s->checkins()->latest('checked_in_at')->first();
                return [
                    'id'              => $s->id,
                    'first_name'      => $s->first_name,
                    'last_name'       => $s->last_name,
                    'full_name'       => $s->full_name,
                    'phone'           => $s->phone,
                    'position'        => $s->position,
                    'qr_code'         => $s->qr_code,
                    'active'          => $s->active,
                    'notes'           => $s->notes,
                    'checkins_count'  => $s->checkins()->count(),
                    'last_checkin_at' => optional($lastCheckin?->checked_in_at)->toIso8601String(),
                    'created_at'      => $s->created_at?->toIso8601String(),
                ];
            });

        return $this->success(['staff' => $staff]);
    }

    /**
     * POST /organizer/leisure/staff
     */
    public function store(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'phone'      => 'nullable|string|max:30',
            'position'   => 'nullable|string|max:120',
            'notes'      => 'nullable|string|max:1000',
        ]);

        $staff = LeisureStaffMember::create(array_merge($validated, [
            'marketplace_organizer_id' => $organizer->id,
            'active' => true,
        ]));

        return $this->success(['staff' => $staff->fresh()], 'Angajat creat', 201);
    }

    /**
     * PUT /organizer/leisure/staff/{id}
     * QR-ul rămâne fix per staff — operatorul nu îl poate schimba prin update.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $staff = LeisureStaffMember::where('marketplace_organizer_id', $organizer->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:100',
            'last_name'  => 'sometimes|required|string|max:100',
            'phone'      => 'nullable|string|max:30',
            'position'   => 'nullable|string|max:120',
            'notes'      => 'nullable|string|max:1000',
            'active'     => 'sometimes|boolean',
        ]);

        $staff->update($validated);

        return $this->success(['staff' => $staff->fresh()], 'Angajat actualizat');
    }

    /**
     * DELETE /organizer/leisure/staff/{id}
     * Soft delete + active=false. Check-ins existente rămân pentru raport istoric.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $staff = LeisureStaffMember::where('marketplace_organizer_id', $organizer->id)
            ->findOrFail($id);

        $staff->update(['active' => false]);
        $staff->delete();

        return $this->success([], 'Angajat dezactivat');
    }

    /**
     * GET /organizer/leisure/staff/checkins?from=YYYY-MM-DD&to=YYYY-MM-DD&staff_id=X&event_id=X
     */
    public function checkins(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $validated = $request->validate([
            'from'      => 'nullable|date',
            'to'        => 'nullable|date|after_or_equal:from',
            'staff_id'  => 'nullable|integer',
            'event_id'  => 'nullable|integer',
            'limit'     => 'nullable|integer|min:1|max:1000',
        ]);

        $query = $this->buildCheckinsQuery($organizer->id, $validated);
        $limit = $validated['limit'] ?? 200;

        $items = $query->orderByDesc('checked_in_at')->limit($limit)->get()
            ->map(function (LeisureStaffCheckin $c) {
                return [
                    'id'              => $c->id,
                    'staff_member_id' => $c->staff_member_id,
                    'staff_name'      => $c->staffMember?->full_name ?? '(șters)',
                    'position'        => $c->staffMember?->position,
                    'event_id'        => $c->event_id,
                    'event_name'      => $c->event?->name ? (is_array($c->event->name) ? ($c->event->name['ro'] ?? reset($c->event->name)) : $c->event->name) : null,
                    'location'        => $c->location,
                    'checked_in_at'   => $c->checked_in_at?->toIso8601String(),
                ];
            });

        // Agregate pentru sumar UI (per staff)
        $perStaff = (clone $query)
            ->selectRaw('staff_member_id, COUNT(*) as total, MIN(checked_in_at) as first_at, MAX(checked_in_at) as last_at')
            ->groupBy('staff_member_id')
            ->get()
            ->map(function ($row) {
                $staff = LeisureStaffMember::withTrashed()->find($row->staff_member_id);
                return [
                    'staff_member_id' => $row->staff_member_id,
                    'staff_name'      => $staff?->full_name ?? '(șters)',
                    'position'        => $staff?->position,
                    'total'           => (int) $row->total,
                    'first_at'        => $row->first_at,
                    'last_at'         => $row->last_at,
                ];
            });

        return $this->success([
            'checkins'   => $items,
            'per_staff'  => $perStaff,
            'total_count' => (clone $query)->count(),
        ]);
    }

    /**
     * GET /organizer/leisure/staff/export?from=YYYY-MM-DD&to=YYYY-MM-DD&staff_id=X
     * Returnează CSV streamed cu detaliile fiecărei scanări.
     */
    public function export(Request $request): StreamedResponse
    {
        $organizer = $this->requireOrganizer($request);

        $validated = $request->validate([
            'from'     => 'nullable|date',
            'to'       => 'nullable|date|after_or_equal:from',
            'staff_id' => 'nullable|integer',
            'event_id' => 'nullable|integer',
        ]);

        $query = $this->buildCheckinsQuery($organizer->id, $validated)
            ->orderByDesc('checked_in_at');

        $filename = 'staff-checkins-' . now()->format('Ymd-His') . '.csv';

        return response()->stream(function () use ($query) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM ca să se deschidă corect în Excel
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Angajat', 'Telefon', 'Pozitie', 'Eveniment', 'Punct check-in', 'Data si ora'], ';');

            $query->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $c) {
                    $eventName = $c->event?->name
                        ? (is_array($c->event->name) ? ($c->event->name['ro'] ?? reset($c->event->name)) : $c->event->name)
                        : '';
                    fputcsv($out, [
                        $c->id,
                        $c->staffMember?->full_name ?? '(șters)',
                        $c->staffMember?->phone ?? '',
                        $c->staffMember?->position ?? '',
                        $eventName,
                        $c->location ?? '',
                        $c->checked_in_at?->format('Y-m-d H:i:s'),
                    ], ';');
                }
            });
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Construiește query-ul de check-ins cu filtre comune (folosit de checkins() + export()).
     */
    protected function buildCheckinsQuery(int $organizerId, array $filters)
    {
        $query = LeisureStaffCheckin::query()
            ->with(['staffMember', 'event'])
            ->whereHas('staffMember', fn ($q) => $q->where('marketplace_organizer_id', $organizerId)->withTrashed());

        if (!empty($filters['from'])) {
            $query->where('checked_in_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }
        if (!empty($filters['to'])) {
            $query->where('checked_in_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }
        if (!empty($filters['staff_id'])) {
            $query->where('staff_member_id', $filters['staff_id']);
        }
        if (!empty($filters['event_id'])) {
            $query->where('event_id', $filters['event_id']);
        }

        return $query;
    }

    /**
     * Helper local (duplicat din pattern-ul existent in alte controllere
     * organizer — vezi BillingController::365, LeisureController::2639).
     * Verifica ca request-ul vine de la un organizator autentificat prin Sanctum.
     */
    protected function requireOrganizer(Request $request): MarketplaceOrganizer
    {
        $organizer = $request->user();
        if (!$organizer instanceof MarketplaceOrganizer) {
            abort(401, 'Unauthorized');
        }
        return $organizer;
    }
}
