<?php

namespace App\Http\Controllers\Api\TixelloApp;

use App\Http\Controllers\Controller;
use App\Models\TixelloAccount;
use App\Models\TixelloEventVisibility;
use App\Models\TixelloFriendInvite;
use App\Services\Friends\FriendshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Prietenii, în aplicaţia Tixello.
 *
 * DESCOPERIRE DOAR PRIN COD sau link de invitaţie. Nu există căutare după nume
 * şi nici după email: ambele ar transforma aplicaţia într-un director de
 * clienţi, în care oricine poate afla dacă o anumită persoană are cont.
 *
 * VIZIBILITATEA participării e închisă implicit şi se deschide de om, global sau
 * per eveniment. Biletele spun unde eşti, la ce oră şi cu cine.
 */
class FriendsController extends Controller
{
    public function __construct(private readonly FriendshipService $friends) {}

    /**
     * Contul curent.
     *
     * `attributes`, NU `$request->user()`: middleware-ul `tixello.app.auth` nu
     * trece prin gardianul Laravel — verifica tokenul propriu si aseaza contul
     * in atributele cererii. `user()` ar fi intors mereu null, iar toate rutele
     * de aici ar fi crapat la prima folosire.
     */
    private function me(Request $request): TixelloAccount
    {
        return $request->attributes->get('tixello_account');
    }

    /**
     * GET /api/app/friends
     *
     * Prietenii + cererile, într-un singur răspuns: ecranul le arată împreună,
     * iar trei apeluri ar fi însemnat trei momente diferite de încărcare pe
     * acelaşi ecran.
     */
    public function index(Request $request): JsonResponse
    {
        $me = $this->me($request);

        $friendIds = $this->friends->friendIds($me);
        $pending = $this->friends->pending($me);

        /* `toBase()` peste tot: vezi FriendshipService::friendIds() — o colectie
           Eloquent GOALA ramane Eloquent chiar dupa `map()`, iar `merge()` pe ea
           cheama `getKey()` pe elemente. Cu id-uri intregi, asta crapa exact in
           cazul in care listele sunt goale, adica la primul utilizator. */
        $ids = $friendIds
            ->merge($pending['pending_in']->map(fn ($f) => $f->otherId($me->id))->toBase())
            ->merge($pending['pending_out']->map(fn ($f) => $f->otherId($me->id))->toBase())
            ->unique();

        $accounts = TixelloAccount::whereIn('id', $ids)->get()->keyBy('id');

        $card = fn (int $id) => $this->publicCard($accounts->get($id));

        return response()->json(['success' => true, 'data' => [
            'friends' => $friendIds->map($card)->filter()->values(),
            'requests' => $pending['pending_in']->map(fn ($f) => [
                'id' => $f->id,
                'source' => $f->source,
                'account' => $card($f->otherId($me->id)),
            ])->filter(fn ($r) => $r['account'] !== null)->values(),
            'sent' => $pending['pending_out']->map(fn ($f) => [
                'id' => $f->id,
                'account' => $card($f->otherId($me->id)),
            ])->filter(fn ($r) => $r['account'] !== null)->values(),
            'invite_code' => $this->friends->inviteCodeFor($me),
            'invite_url' => url('/invit/'.$this->friends->inviteCodeFor($me)),
            /* Invitaţiile trimise unor oameni care încă n-au cont: altfel ar
               dispărea din interfaţă şi ai crede că n-ai trimis nimic. */
            'invited' => TixelloFriendInvite::where('inviter_id', $me->id)
                ->whereNull('converted_at')
                ->orderByDesc('id')
                ->limit(50)
                ->get(['email', 'name', 'source'])
                ->all(),
            'visibility' => $me->friends_visibility ?? 'nobody',
        ]]);
    }

    /** POST /api/app/friends/redeem  {code} */
    public function redeem(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|max:24']);

        $result = $this->friends->requestByCode($this->me($request), $request->input('code'));

        return response()->json(['success' => $result['ok'], 'message' => $result['message']], $result['ok'] ? 200 : 422);
    }

    /** POST /api/app/friends/invite  {email, name?} */
    public function invite(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:190',
            'name' => 'nullable|string|max:190',
        ]);

        $result = $this->friends->inviteByEmail(
            $this->me($request),
            $request->input('email'),
            $request->input('name'),
            'invite_code',
        );

        return response()->json(['success' => $result['ok'], 'message' => $result['message']], $result['ok'] ? 200 : 422);
    }

    /** POST /api/app/friends/{friendship}/respond  {accept} */
    public function respond(Request $request, int $friendship): JsonResponse
    {
        $request->validate(['accept' => 'required|boolean']);

        $result = $this->friends->respond($this->me($request), $friendship, (bool) $request->input('accept'));

        return response()->json(['success' => $result['ok'], 'message' => $result['message']], $result['ok'] ? 200 : 422);
    }

    /** DELETE /api/app/friends/{account} */
    public function remove(Request $request, int $account): JsonResponse
    {
        $this->friends->remove($this->me($request), $account);

        return response()->json(['success' => true]);
    }

    /** POST /api/app/friends/{account}/block  şi DELETE pentru deblocare */
    public function block(Request $request, int $account): JsonResponse
    {
        $this->friends->block($this->me($request), $account);

        return response()->json(['success' => true]);
    }

    public function unblock(Request $request, int $account): JsonResponse
    {
        /* Raspunsul spune ce s-a intamplat CU ADEVARAT.
           Inainte intorcea mereu `success: true`, chiar cand nu deblocase nimic
           — de exemplu cand cel blocat incearca sa se deblocheze singur.
           Serverul se comporta corect (blocarea ramanea), dar clientul ar fi
           afisat „deblocat" si omul ar fi crezut ca s-a intamplat ceva. */
        if (! $this->friends->unblock($this->me($request), $account)) {
            return response()->json([
                'success' => false,
                'message' => 'Nu ai ce debloca.',
            ], 422);
        }

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/app/friends/{account}/profile
     *
     * Profilul altcuiva. Se răspunde DOAR prietenilor: un profil deschis
     * oricui ar face din id-uri consecutive o listă de clienţi.
     */
    public function profile(Request $request, int $account): JsonResponse
    {
        $me = $this->me($request);

        if ($account !== $me->id && ! $this->friends->areFriends($me->id, $account)) {
            return response()->json(['success' => false, 'message' => 'Profil indisponibil.'], 403);
        }

        $other = TixelloAccount::find($account);

        if (! $other) {
            return response()->json(['success' => false, 'message' => 'Profil indisponibil.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->publicCard($other)]);
    }

    /**
     * POST /api/app/friends/visibility  {scope: global|event, visible, event_id?}
     *
     * Comutatorul global şi excepţiile per eveniment, în acelaşi loc: e aceeaşi
     * decizie a utilizatorului, la două nivele.
     */
    public function visibility(Request $request): JsonResponse
    {
        $request->validate([
            'scope' => 'required|in:global,event',
            'visible' => 'required|boolean',
            'event_id' => 'required_if:scope,event|integer',
        ]);

        $me = $this->me($request);
        $visible = (bool) $request->input('visible');

        if ($request->input('scope') === 'global') {
            $me->forceFill(['friends_visibility' => $visible ? 'friends' : 'nobody'])->save();

            return response()->json(['success' => true, 'data' => ['visibility' => $me->friends_visibility]]);
        }

        TixelloEventVisibility::updateOrCreate(
            ['tixello_account_id' => $me->id, 'event_id' => (int) $request->input('event_id')],
            ['visible' => $visible],
        );

        return response()->json(['success' => true]);
    }

    /**
     * Cartea de vizită publică a unui cont.
     *
     * Deliberat SĂRACĂ: nume, poză, oraş. Fără email, fără telefon, fără
     * istoricul comenzilor — nimic din ce n-ar trebui să treacă de la un
     * prieten la altul fără o decizie explicită.
     *
     * @return array<string, mixed>|null
     */
    private function publicCard(?TixelloAccount $account): ?array
    {
        if (! $account) {
            return null;
        }

        return [
            'id' => $account->id,
            'name' => $account->name ?: explode('@', $account->email)[0],
            'avatar' => $account->avatar,
        ];
    }
}
