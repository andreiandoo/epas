<?php

namespace App\Http\Controllers\Api\TixelloApp;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\GroupBooking;
use App\Models\TicketType;
use App\Models\TixelloAccount;
use App\Services\Friends\FriendshipService;
use App\Services\Tixello\GroupPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * „Cumpărăm împreună" — API-ul de client pentru `group_bookings`.
 *
 * Tabelele existau de mult, dar nu aveau nicio rută de client: grupurile se
 * făceau doar din panoul organizatorului. Aici le deschidem cumpărătorului.
 *
 * Contul vine din middleware (`tixello_account`), nu din `$request->user()`:
 * aplicația are propriul strat de identitate, peste cel al marketplace-urilor.
 */
class GroupsController extends Controller
{
    public function __construct(
        private readonly GroupPurchaseService $groups,
        private readonly FriendshipService $friends,
    ) {}

    /** Grupurile mele: cele pe care le-am deschis și cele în care sunt invitat. */
    public function index(Request $request): JsonResponse
    {
        $me = $this->me($request);

        $mine = GroupBooking::query()
            ->where('tixello_account_id', $me->id)
            ->latest()
            ->limit(20)
            ->get();

        $joined = GroupBooking::query()
            ->whereHas('members', fn ($q) => $q->where('tixello_account_id', $me->id))
            ->where('tixello_account_id', '!=', $me->id)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'initiated' => $mine->map(fn (GroupBooking $g) => $this->card($g))->all(),
                'joined' => $joined->map(fn (GroupBooking $g) => $this->card($g))->all(),
            ],
        ]);
    }

    /**
     * Deschide un grup pentru un eveniment.
     *
     * Se verifică fereastra de rezervare ÎNAINTE de orice altceva: un grup
     * pentru un eveniment de mâine n-are cum să se închege, iar deschiderea lui
     * ar fi doar o promisiune care expiră.
     */
    public function store(Request $request): JsonResponse
    {
        $me = $this->me($request);

        $data = $request->validate([
            'event_id' => 'required|integer',
            'ticket_type_id' => 'required|integer',
            'seats' => 'required|integer|min:2|max:20',
            'payment_type' => 'nullable|in:full,split',
            'friends' => 'nullable|array',
            'friends.*.account_id' => 'nullable|integer',
            'friends.*.email' => 'nullable|email',
            'friends.*.name' => 'nullable|string|max:120',
        ]);

        $event = Event::find($data['event_id']);
        $type = TicketType::find($data['ticket_type_id']);

        if (! $event || ! $type || $type->event_id !== $event->id) {
            return response()->json(['success' => false, 'error' => 'Evenimentul sau tipul de bilet nu există.'], 404);
        }

        try {
            $group = $this->groups->open(
                $me,
                $event,
                $type,
                (int) $data['seats'],
                $data['payment_type'] ?? GroupBooking::PAYMENT_SPLIT,
            );
        } catch (\RuntimeException $e) {
            /* 422, nu 500: nu e o defecțiune, e un refuz cu motiv — iar
               aplicația trebuie să poată arăta motivul. */
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        /* Inițiatorul e membru de la început: altfel n-ar avea link de plată
           pentru propriul lui bilet. */
        $this->groups->invite($group, [['account_id' => $me->id]]);

        if (! empty($data['friends'])) {
            $this->groups->invite($group, $this->onlyFriends($me, $data['friends']));
        }

        return response()->json(['success' => true, 'data' => $this->groups->progress($group->fresh())], 201);
    }

    public function show(Request $request, int $group): JsonResponse
    {
        $me = $this->me($request);
        $model = $this->visibleGroup($me, $group);

        if (! $model) {
            return response()->json(['success' => false, 'error' => 'Grupul nu există.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->groups->progress($model)]);
    }

    /** Adaugă membri la un grup deschis de mine. */
    public function invite(Request $request, int $group): JsonResponse
    {
        $me = $this->me($request);

        $model = GroupBooking::where('id', $group)->where('tixello_account_id', $me->id)->first();

        if (! $model) {
            return response()->json(['success' => false, 'error' => 'Grupul nu există.'], 404);
        }

        if ($model->hold_expires_at && $model->hold_expires_at->isPast()) {
            return response()->json(['success' => false, 'error' => 'Grupul a expirat.'], 422);
        }

        $data = $request->validate([
            'friends' => 'required|array|min:1',
            'friends.*.account_id' => 'nullable|integer',
            'friends.*.email' => 'nullable|email',
            'friends.*.name' => 'nullable|string|max:120',
        ]);

        $added = $this->groups->invite($model, $this->onlyFriends($me, $data['friends']));

        return response()->json([
            'success' => true,
            'data' => $this->groups->progress($model->fresh()) + ['added' => count($added)],
        ]);
    }

    public function cancel(Request $request, int $group): JsonResponse
    {
        $me = $this->me($request);
        $model = GroupBooking::where('id', $group)->where('tixello_account_id', $me->id)->first();

        if (! $model) {
            return response()->json(['success' => false, 'error' => 'Grupul nu există.'], 404);
        }

        /* Un grup în care s-a plătit deja nu se anulează dintr-un buton:
           banii sunt la organizator, iar biletele plătite rămân valabile. */
        if ($model->members()->where('payment_status', 'paid')->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Cineva a plătit deja. Anularea se face prin organizator.',
            ], 422);
        }

        $model->update(['status' => GroupBooking::STATUS_CANCELLED]);

        return response()->json(['success' => true]);
    }

    /**
     * Cine poate fi invitat: doar prietenii tăi, sau adrese scrise de tine.
     *
     * Fără filtrul ăsta, oricine ar putea trimite invitații cu `account_id`-ul
     * altcuiva și ar afla, din răspuns, dacă un cont există.
     *
     * @param  array<int, array<string, mixed>>  $people
     * @return array<int, array<string, mixed>>
     */
    private function onlyFriends(TixelloAccount $me, array $people): array
    {
        $friendIds = $this->friends->friendIds($me);

        return array_values(array_filter($people, function (array $p) use ($friendIds) {
            $id = isset($p['account_id']) ? (int) $p['account_id'] : null;

            if ($id) {
                return in_array($id, $friendIds, true);
            }

            return ! empty($p['email']);
        }));
    }

    private function visibleGroup(TixelloAccount $me, int $id): ?GroupBooking
    {
        return GroupBooking::query()
            ->where('id', $id)
            ->where(function ($q) use ($me) {
                $q->where('tixello_account_id', $me->id)
                    ->orWhereHas('members', fn ($m) => $m->where('tixello_account_id', $me->id));
            })
            ->first();
    }

    private function card(GroupBooking $g): array
    {
        return [
            'id' => $g->id,
            'event_id' => $g->event_id,
            'name' => $g->group_name,
            'seats' => (int) $g->total_tickets,
            'status' => $g->status,
            'expires_at' => $g->hold_expires_at?->toIso8601String(),
        ];
    }

    private function me(Request $request): TixelloAccount
    {
        return $request->attributes->get('tixello_account');
    }
}
