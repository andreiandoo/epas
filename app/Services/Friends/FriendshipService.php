<?php

namespace App\Services\Friends;

use App\Models\TixelloAccount;
use App\Models\TixelloEventVisibility;
use App\Models\TixelloFriendInvite;
use App\Models\TixelloFriendship;
use Illuminate\Support\Collection;

/**
 * Prieteniile din aplicaţia Tixello.
 *
 * Tot ce ţine de perechi trece pe aici, dintr-un motiv practic: perechea se
 * păstrează canonic (id mic, id mare), iar dacă fiecare apelant ar face singur
 * ordonarea, prima greşeală ar produce două rânduri pentru aceiaşi doi oameni —
 * adică doi prieteni care nu se văd unul pe altul.
 */
class FriendshipService
{
    /** Lungimea codului de invitaţie. Scurt cât să poată fi dictat la telefon. */
    private const CODE_LENGTH = 8;

    /**
     * Codul propriu, generat la prima cerere.
     *
     * Fără ambiguităţi vizuale (0/O, 1/I/L): codul se citeşte cu voce tare şi se
     * scrie de mână, iar un cod pe care nu-l poţi dicta corect nu e folosibil.
     */
    public function inviteCodeFor(TixelloAccount $account): string
    {
        if ($account->invite_code) {
            return $account->invite_code;
        }

        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (TixelloAccount::where('invite_code', $code)->exists());

        $account->forceFill(['invite_code' => $code])->save();

        return $code;
    }

    /**
     * Cererea de prietenie, din cod de invitaţie.
     *
     * @return array{ok: bool, message: string, status?: string}
     */
    public function requestByCode(TixelloAccount $me, string $code): array
    {
        $code = strtoupper(trim($code));

        $other = TixelloAccount::where('invite_code', $code)->first();

        if (! $other) {
            /* Acelaşi mesaj şi pentru cod inexistent, şi pentru cod al propriului
               cont: altfel codul devine un instrument de verificat cine există. */
            return ['ok' => false, 'message' => 'Codul nu este valid.'];
        }

        if ($other->id === $me->id) {
            return ['ok' => false, 'message' => 'Codul nu este valid.'];
        }

        return $this->request($me, $other, 'invite_code');
    }

    /**
     * Cererea de prietenie între două conturi existente.
     *
     * @return array{ok: bool, message: string, status?: string}
     */
    public function request(TixelloAccount $me, TixelloAccount $other, string $source = 'manual'): array
    {
        [$a, $b] = $this->pair($me->id, $other->id);

        $existing = TixelloFriendship::where('account_a_id', $a)->where('account_b_id', $b)->first();

        if ($existing) {
            if ($existing->status === 'blocked') {
                /* Nu spunem CINE a blocat şi nici că e vorba de blocare: cel
                   blocat n-are de ce să afle, iar mesajul ar fi o notificare
                   nedorită pentru cel care s-a protejat. */
                return ['ok' => false, 'message' => 'Cererea nu poate fi trimisă.'];
            }

            if ($existing->status === 'accepted') {
                return ['ok' => true, 'message' => 'Sunteți deja prieteni.', 'status' => 'accepted'];
            }

            /* Cerere în aşteptare de la CELĂLALT + o cerere de la mine =
               amândoi vor. Se acceptă direct, în loc să rămână două cereri
               care se aşteaptă una pe alta. */
            if ($existing->status === 'pending' && $existing->requested_by !== $me->id) {
                $existing->update(['status' => 'accepted', 'responded_at' => now()]);

                return ['ok' => true, 'message' => 'Sunteți prieteni.', 'status' => 'accepted'];
            }

            if ($existing->status === 'declined') {
                // Un refuz nu e definitiv; cererea se poate relua.
                $existing->update(['status' => 'pending', 'requested_by' => $me->id, 'responded_at' => null, 'source' => $source]);

                return ['ok' => true, 'message' => 'Cerere trimisă.', 'status' => 'pending'];
            }

            return ['ok' => true, 'message' => 'Cererea a fost deja trimisă.', 'status' => 'pending'];
        }

        TixelloFriendship::create([
            'account_a_id' => $a,
            'account_b_id' => $b,
            'requested_by' => $me->id,
            'status' => 'pending',
            'source' => $source,
        ]);

        return ['ok' => true, 'message' => 'Cerere trimisă.', 'status' => 'pending'];
    }

    /**
     * Invită pe cineva după email — folosit şi de beneficiarii din checkout.
     *
     * Dacă emailul are deja cont, devine direct o cerere de prietenie. Dacă nu,
     * se reţine invitaţia şi se materializează când omul îşi face cont
     * ({@see convertPendingInvites}).
     */
    public function inviteByEmail(TixelloAccount $me, string $email, ?string $name = null, string $source = 'beneficiary'): array
    {
        $email = mb_strtolower(trim($email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Adresa de email nu este validă.'];
        }

        if ($email === mb_strtolower($me->email)) {
            return ['ok' => false, 'message' => 'Este adresa ta.'];
        }

        $other = TixelloAccount::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($other) {
            return $this->request($me, $other, $source);
        }

        TixelloFriendInvite::updateOrCreate(
            ['inviter_id' => $me->id, 'email' => $email],
            ['name' => $name, 'source' => $source],
        );

        return ['ok' => true, 'message' => 'Invitație trimisă.', 'status' => 'invited'];
    }

    /**
     * Materializează invitaţiile care aşteptau acest email.
     *
     * Se cheamă la crearea contului. Rezultatul e o CERERE în aşteptare, nu o
     * prietenie: omul tocmai s-a înscris, n-a acceptat încă nimic.
     */
    public function convertPendingInvites(TixelloAccount $account): int
    {
        $email = mb_strtolower(trim($account->email));

        $invites = TixelloFriendInvite::whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('converted_at')
            ->get();

        $made = 0;

        foreach ($invites as $invite) {
            $inviter = TixelloAccount::find($invite->inviter_id);

            if (! $inviter) {
                continue;
            }

            $result = $this->request($inviter, $account, $invite->source);

            $invite->update(['converted_at' => now(), 'converted_account_id' => $account->id]);

            if ($result['ok']) {
                $made++;
            }
        }

        return $made;
    }

    /**
     * Beneficiarii unei comenzi devin invitatii de prietenie.
     *
     * Se cheama DUPA ce comanda a fost creata, si niciodata inaintea ei: o
     * eroare in partea sociala n-are voie sa impiedice o vanzare. Din acelasi
     * motiv, apelantul o inveleste in try/catch.
     *
     * Cumparatorul se recunoaste dupa EMAIL. Daca n-are cont Tixello (cumparare
     * de pe site, fara aplicatie), nu se intampla nimic — n-avem cui atribui
     * invitatia, iar a o lega de altcineva ar fi o greseala mai mare decat a nu
     * face nimic.
     *
     * Rezultatul e tot o CERERE, nu o prietenie. Un beneficiar poate fi un coleg
     * caruia i-ai luat bilet o data.
     *
     * @param  array<int, array<string, mixed>>  $beneficiaries
     * @return int cate invitatii s-au creat
     */
    public function inviteFromBeneficiaries(?string $buyerEmail, array $beneficiaries): int
    {
        $buyerEmail = mb_strtolower(trim((string) $buyerEmail));

        if ($buyerEmail === '' || $beneficiaries === []) {
            return 0;
        }

        $buyer = TixelloAccount::whereRaw('LOWER(email) = ?', [$buyerEmail])->first();

        if (! $buyer) {
            return 0;
        }

        $made = 0;

        foreach ($beneficiaries as $b) {
            $email = is_array($b) ? ($b['email'] ?? null) : null;

            /* Beneficiarii fara email nu pot fi invitati — si e in regula:
               multi sunt copii sau rude pentru care cumperi biletul, fara
               adresa proprie. */
            if (! is_string($email) || trim($email) === '') {
                continue;
            }

            $result = $this->inviteByEmail($buyer, $email, is_array($b) ? ($b['name'] ?? null) : null, 'beneficiary');

            if ($result['ok']) {
                $made++;
            }
        }

        return $made;
    }

    /** @return array{ok: bool, message: string} */
    public function respond(TixelloAccount $me, int $friendshipId, bool $accept): array
    {
        $row = TixelloFriendship::find($friendshipId);

        if (! $row || ! in_array($me->id, [$row->account_a_id, $row->account_b_id], true)) {
            return ['ok' => false, 'message' => 'Cererea nu există.'];
        }

        if ($row->status !== 'pending') {
            return ['ok' => false, 'message' => 'Cererea nu mai este în așteptare.'];
        }

        /* Doar DESTINATARUL poate răspunde. Fără verificarea asta, cel care a
           trimis cererea şi-ar putea accepta singur prietenia. */
        if ($row->requested_by === $me->id) {
            return ['ok' => false, 'message' => 'Cererea ta așteaptă răspunsul celuilalt.'];
        }

        $row->update(['status' => $accept ? 'accepted' : 'declined', 'responded_at' => now()]);

        return ['ok' => true, 'message' => $accept ? 'Sunteți prieteni.' : 'Cerere refuzată.'];
    }

    public function remove(TixelloAccount $me, int $otherId): bool
    {
        [$a, $b] = $this->pair($me->id, $otherId);

        return TixelloFriendship::where('account_a_id', $a)->where('account_b_id', $b)->delete() > 0;
    }

    public function block(TixelloAccount $me, int $otherId): bool
    {
        [$a, $b] = $this->pair($me->id, $otherId);

        TixelloFriendship::updateOrCreate(
            ['account_a_id' => $a, 'account_b_id' => $b],
            ['requested_by' => $me->id, 'status' => 'blocked', 'blocked_by' => $me->id, 'responded_at' => now()],
        );

        return true;
    }

    public function unblock(TixelloAccount $me, int $otherId): bool
    {
        [$a, $b] = $this->pair($me->id, $otherId);

        /* Doar cine a blocat poate debloca — altfel blocarea n-ar proteja pe
           nimeni. */
        return TixelloFriendship::where('account_a_id', $a)
            ->where('account_b_id', $b)
            ->where('status', 'blocked')
            ->where('blocked_by', $me->id)
            ->delete() > 0;
    }

    /**
     * Id-urile prietenilor acceptati.
     *
     * `toBase()` NU e cosmetic. `Eloquent\Collection::map()` intoarce o colectie
     * de baza doar daca rezultatul contine ceva ce nu e model — la o colectie
     * GOALA n-are ce contine, deci ramane Eloquent. Iar `merge()` pe o colectie
     * Eloquent cheama `getKey()` pe fiecare element, ceea ce pe niste intregi
     * inseamna „Call to a member function getKey() on int".
     * Adica: mergea cat timp aveai prieteni si crapa fix cand n-aveai niciunul.
     */
    public function friendIds(TixelloAccount $me): Collection
    {
        return TixelloFriendship::where('status', 'accepted')
            ->where(fn ($q) => $q->where('account_a_id', $me->id)->orWhere('account_b_id', $me->id))
            ->get()
            ->map(fn (TixelloFriendship $f) => $f->account_a_id === $me->id ? $f->account_b_id : $f->account_a_id)
            ->values()
            ->toBase();
    }

    /** @return array{pending_in: Collection, pending_out: Collection} */
    public function pending(TixelloAccount $me): array
    {
        $rows = TixelloFriendship::where('status', 'pending')
            ->where(fn ($q) => $q->where('account_a_id', $me->id)->orWhere('account_b_id', $me->id))
            ->get();

        return [
            'pending_in' => $rows->where('requested_by', '!=', $me->id)->values(),
            'pending_out' => $rows->where('requested_by', $me->id)->values(),
        ];
    }

    public function areFriends(int $x, int $y): bool
    {
        [$a, $b] = $this->pair($x, $y);

        return TixelloFriendship::where('account_a_id', $a)
            ->where('account_b_id', $b)
            ->where('status', 'accepted')
            ->exists();
    }

    /**
     * Prietenii care merg la un eveniment — DOAR cei care au ales să se vadă.
     *
     * Trei filtre, in ordinea asta, si niciunul nu e optional:
     *   1. sa fie prieten acceptat;
     *   2. sa aiba bilet platit la evenimentul asta;
     *   3. sa fi permis sa se stie: regula lui generala, plus exceptia pe
     *      evenimentul acesta, daca a pus una.
     *
     * Legatura dintre cont si bilet se face pe EMAIL. Nu e ideal — un om poate
     * cumpara cu alta adresa — dar e singura care traverseaza toate lumile:
     * comenzile stau in `orders` cu emailul cumparatorului, indiferent daca
     * evenimentul e de tenant sau de marketplace. Alternativa, prin
     * `tixello_account_links`, ar fi acoperit doar conturile deja legate.
     *
     * @return array<int, int> id-uri de conturi
     */
    public function attendingFriendIds(TixelloAccount $me, int $eventId): array
    {
        $friendIds = $this->friendIds($me)->all();

        if ($friendIds === []) {
            return [];
        }

        $accounts = TixelloAccount::whereIn('id', $friendIds)->get(['id', 'email', 'friends_visibility']);

        /* Exceptiile per eveniment, pentru toti prietenii deodata: altfel ar fi
           o interogare pe fiecare. */
        $overrides = TixelloEventVisibility::whereIn('tixello_account_id', $friendIds)
            ->where('event_id', $eventId)
            ->pluck('visible', 'tixello_account_id');

        $visible = $accounts->filter(function ($a) use ($overrides) {
            /* Exceptia bate regula, in ambele sensuri: poti ascunde un eveniment
               desi in general esti vizibil, si invers. */
            if ($overrides->has($a->id)) {
                return (bool) $overrides->get($a->id);
            }

            return $a->friends_visibility === 'friends';
        });

        if ($visible->isEmpty()) {
            return [];
        }

        $emails = $visible->pluck('email')->map(fn ($e) => mb_strtolower((string) $e))->all();

        try {
            $buyers = \App\Models\Order::query()
                ->where('event_id', $eventId)
                ->whereIn('status', ['paid', 'confirmed', 'completed'])
                ->pluck('customer_email')
                ->map(fn ($e) => mb_strtolower((string) $e))
                ->unique()
                ->all();
        } catch (\Throwable) {
            return [];
        }

        $going = array_intersect($emails, $buyers);

        return $visible
            ->filter(fn ($a) => in_array(mb_strtolower((string) $a->email), $going, true))
            ->pluck('id')
            ->all();
    }

    /** Vizibilitatea mea la un eveniment: exceptia, daca exista, altfel regula. */
    public function visibilityFor(TixelloAccount $me, int $eventId): bool
    {
        $override = TixelloEventVisibility::where('tixello_account_id', $me->id)
            ->where('event_id', $eventId)
            ->value('visible');

        return $override !== null ? (bool) $override : $me->friends_visibility === 'friends';
    }

    /** Perechea canonică: id mic întâi, mereu. */
    private function pair(int $x, int $y): array
    {
        return $x < $y ? [$x, $y] : [$y, $x];
    }
}
