<?php

namespace App\Http\Controllers\Api\TixelloApp;

use App\Http\Controllers\Api\TixelloApp\Concerns\ResolvesLinkedOrganizer;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\Ticket;
use App\Models\TixelloAccount;
use App\Models\TixelloAccountLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Partea de ORGANIZATOR a aplicației Tixello.
 *
 * Un organizator exista deja intr-o lume de partener (marketplace). Aplicatia
 * nu-i creeaza un cont paralel: se autentifica cu credentialele lui de acolo,
 * iar rezultatul e o LEGATURA (`tixello_account_links`).
 *
 * PARTENERUL RAMANE AUTORITATEA. Statusul organizatorului se verifica la
 * fiecare cerere, nu doar la login: daca partenerul il dezactiveaza,
 * aplicatia se inchide.
 *
 * GRANITA: aplicatia face OPERATIONAL (evenimente, scanare, vanzare la usa,
 * rapoarte). Crearea evenimentelor, preturile, contractele si deconturile
 * raman in panoul partenerului — altfel construim un al doilea panou de
 * administrare, in conflict cu al lor.
 */
class OrganizerController extends Controller
{
    use ResolvesLinkedOrganizer;

    /**
     * Conecteaza un cont de organizator de la un partener.
     *
     * Aici credentialele sunt acceptabile (spre deosebire de partea de client,
     * unde am evitat legarea prin parola): parteneriatele sunt publice, iar un
     * organizator apartine unui singur partener — deci nu se poate deduce
     * nimic din faptul ca legarea reuseste.
     */
    public function connect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'marketplace_client_id' => 'required|integer',
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        /** @var TixelloAccount $account */
        $account = $request->attributes->get('tixello_account');

        if (! $account->isVerified()) {
            return response()->json([
                'success' => false,
                'error' => 'Verifică-ți adresa de email înainte de a conecta un cont.',
            ], 422);
        }

        $org = MarketplaceOrganizer::where('marketplace_client_id', $data['marketplace_client_id'])
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['email']))])
            ->first();

        if (! $org || ! $org->password || ! Hash::check($data['password'], $org->password)) {
            return response()->json(['success' => false, 'error' => 'Date de autentificare incorecte.'], 422);
        }

        if (($org->status ?? 'active') !== 'active') {
            return response()->json(['success' => false, 'error' => 'Cont de organizator inactiv.'], 403);
        }

        $link = TixelloAccountLink::updateOrCreate(
            ['tixello_account_id' => $account->id, 'kind' => 'marketplace_organizer', 'linked_id' => $org->id],
            [
                'marketplace_client_id' => $org->marketplace_client_id,
                'consent_source' => 'organizer_login',
                'consented_at' => now(),
                'consent_ip' => $request->ip(),
                'status' => 'active',
                'revoked_at' => null,
            ]
        );

        $account->forceFill(['is_organizer' => true])->save();

        return response()->json(['success' => true, 'data' => [
            'link_id' => $link->id,
            'organizer' => ['id' => $org->id, 'name' => $org->name],
        ]]);
    }

    /** Evenimentele organizatorului — pentru selectorul din aplicație. */
    public function events(Request $request): JsonResponse
    {
        $org = $this->organizerFor($request);
        if (! $org) {
            return $this->noOrganizer();
        }

        $events = Event::query()
            ->where('marketplace_organizer_id', $org->id)
            ->orderByDesc('event_date')
            ->limit(60)
            ->get(['id', 'title', 'event_date', 'start_time', 'venue_id', 'status']);

        return response()->json(['success' => true, 'data' => $events->map(fn ($e) => [
            'id' => (string) $e->id,
            'title' => $e->getTranslation('title', 'ro'),
            'date' => $e->event_date?->toIso8601String(),
            'time' => $e->start_time,
            'status' => $e->status,
        ])]);
    }

    /**
     * Inventarul de bilete pentru scanare offline.
     *
     * Se trimit doar campurile de care are nevoie poarta. Nu trimitem date
     * personale in plus: telefonul poate fi pierdut, iar inventarul ramane pe
     * el pana la stergere.
     */
    public function tickets(Request $request, string $eventId): JsonResponse
    {
        $org = $this->organizerFor($request);
        if (! $org) {
            return $this->noOrganizer();
        }

        // scoping-ul se impune AICI, din legatura — niciodata din ce trimite aplicatia
        $event = Event::where('id', $eventId)->where('marketplace_organizer_id', $org->id)->first();
        if (! $event) {
            return response()->json(['success' => false, 'error' => 'Eveniment inexistent.'], 404);
        }

        /* Evenimentul poate fi in meta biletului SAU in meta comenzii. Gruparea
           e obligatorie: un `orWhere` la nivelul de sus ar scapa de sub filtru
           si ar intoarce bilete de la alte evenimente. */
        $tickets = Ticket::query()
            ->where(function ($q) use ($event) {
                $q->where('meta->event_id', $event->id)
                    ->orWhereHas('order', fn ($o) => $o->where('meta->event_id', $event->id));
            })
            ->get(['code', 'status', 'checked_in_at', 'ticket_type_id', 'meta']);

        return response()->json(['success' => true, 'data' => $tickets->map(fn ($t) => [
            'code' => $t->code,
            'eventId' => (string) $event->id,
            'status' => $t->checked_in_at ? 'used' : ($t->status === 'valid' ? 'valid' : 'void'),
            'seat' => $t->meta['seat_label'] ?? null,
            'usedAt' => $t->checked_in_at?->toIso8601String(),
        ])]);
    }

    /**
     * Primeste un lot de scanuri de pe telefon.
     *
     * IDEMPOTENT dupa `id`-ul generat pe dispozitiv: daca raspunsul se pierde
     * pe drum si aplicatia retrimite, nu se numara de doua ori.
     *
     * "PRIMUL CASTIGA" se decide dupa `scanned_at` (ora NORMALIZATA trimisa de
     * aplicatie — vezi clock.ts de ce nu se poate folosi ceasul brut).
     * Al doilea scan e inregistrat, dar marcat drept duplicat: omul a intrat
     * deja, deci rezultatul e o alerta pentru organizator, nu o usa inchisa.
     */
    public function scans(Request $request): JsonResponse
    {
        $org = $this->organizerFor($request);
        if (! $org) {
            return $this->noOrganizer();
        }

        $data = $request->validate([
            'scans' => 'required|array|max:200',
            'scans.*.id' => 'required|string|max:64',
            'scans.*.code' => 'required|string|max:120',
            'scans.*.event_id' => 'required|integer',
            'scans.*.device_id' => 'required|string|max:100',
            'scans.*.gate_id' => 'nullable|string|max:40',
            'scans.*.scanned_at' => 'required|date',
            'scans.*.device_at' => 'nullable|date',
            'scans.*.seq' => 'nullable|integer',
            'scans.*.skew_ms' => 'nullable|integer',
            'scans.*.clock_trusted' => 'nullable|boolean',
            'scans.*.local_result' => 'nullable|string|max:20',
        ]);

        $acks = [];

        foreach ($data['scans'] as $s) {
            try {
                $acks[] = DB::transaction(function () use ($s, $org) {
                    // idempotenta: acelasi id, acelasi raspuns
                    $seen = DB::table('ticket_scans')->where('client_scan_id', $s['id'])->first();
                    if ($seen) {
                        return ['id' => $s['id'], 'result' => $seen->result];
                    }

                    $ticket = Ticket::where('code', $s['code'])->lockForUpdate()->first();

                    $result = 'valid';
                    if (! $ticket) {
                        $result = 'unknown';
                    } elseif (($ticket->meta['event_id'] ?? null) && (int) $ticket->meta['event_id'] !== (int) $s['event_id']) {
                        $result = 'wrong-event';
                    } elseif ($ticket->status !== 'valid') {
                        $result = 'void';
                    } elseif ($ticket->checked_in_at) {
                        // deja intrat — primul a castigat
                        $result = 'duplicate';
                    }

                    $at = \Carbon\Carbon::parse($s['scanned_at']);

                    if ($result === 'valid') {
                        /* Pe bilet marcam doar intrarea. Poarta, dispozitivul si
                           organizatorul stau in `ticket_scans` — tabelul
                           `tickets` nu are coloanele astea, si nici n-ar trebui:
                           el retine PRIMA intrare, jurnalul retine toate. */
                        $ticket->forceFill([
                            'checked_in_at' => $at,
                            'scanned_at' => $at,
                        ])->save();
                    }

                    DB::table('ticket_scans')->insert([
                        'client_scan_id' => $s['id'],
                        'ticket_code' => $s['code'],
                        'event_id' => (int) $s['event_id'],
                        'marketplace_organizer_id' => $org->id,
                        'device_id' => $s['device_id'],
                        'gate_id' => $s['gate_id'] ?? null,
                        'scanned_at' => $at,
                        'device_at' => isset($s['device_at']) ? \Carbon\Carbon::parse($s['device_at']) : null,
                        'seq' => $s['seq'] ?? 0,
                        'skew_ms' => $s['skew_ms'] ?? 0,
                        'clock_trusted' => (bool) ($s['clock_trusted'] ?? false),
                        'local_result' => $s['local_result'] ?? null,
                        'result' => $result,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return ['id' => $s['id'], 'result' => $result];
                });
            } catch (\Throwable $e) {
                // un scan problematic nu trebuie sa blocheze lotul
                $acks[] = ['id' => $s['id'], 'error' => 'store_failed'];
            }
        }

        return response()->json(['success' => true, 'acks' => $acks]);
    }
}
