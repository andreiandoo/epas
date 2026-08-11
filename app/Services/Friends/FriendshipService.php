<?php

namespace App\Services\Friends;

use App\Models\TixelloAccount;
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

    /** Id-urile prietenilor acceptati. */
    public function friendIds(TixelloAccount $me): Collection
    {
        return TixelloFriendship::where('status', 'accepted')
            ->where(fn ($q) => $q->where('account_a_id', $me->id)->orWhere('account_b_id', $me->id))
            ->get()
            ->map(fn (TixelloFriendship $f) => $f->account_a_id === $me->id ? $f->account_b_id : $f->account_a_id)
            ->values();
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

    /** Perechea canonică: id mic întâi, mereu. */
    private function pair(int $x, int $y): array
    {
        return $x < $y ? [$x, $y] : [$y, $x];
    }
}
