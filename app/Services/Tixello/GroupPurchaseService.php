<?php

namespace App\Services\Tixello;

use App\Models\Event;
use App\Models\GroupBooking;
use App\Models\GroupBookingMember;
use App\Models\TicketType;
use App\Models\TixelloAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * „Cumpărăm împreună" — capitolul 2 din planul friends-social.
 *
 * Extinde `GroupBooking`, care exista deja pentru panoul organizatorului, către
 * cumpărătorul din aplicație: grupul îl face el, membrii sunt conturi tics, iar
 * fiecare plătește partea lui.
 *
 * DOUĂ DECIZII CARE DAU FORMA ÎNTREGULUI SERVICIU
 *
 * 1. REZERVAREA ȚINE 48 DE ORE, DAR NU INTRĂ ÎN ZIUA EVENIMENTULUI.
 *    Biletele trebuie luate cu cel puțin o zi înainte. Fără regula asta am fi
 *    ținut locuri până în seara concertului: dacă grupul nu se închega, pierdeam
 *    și vânzarea, și grupul. Când nu mai încape o fereastră utilă (evenimentul e
 *    mâine), grupul pur și simplu nu se poate deschide — și se spune de ce.
 *
 * 2. FIECARE PLĂTEȘTE LA PROCESATORUL ORGANIZATORULUI.
 *    Nu există un cont-intermediar care încasează și distribuie. Procesatorul
 *    diferă de la organizator la organizator (Netopia la unii, Stripe la alții),
 *    iar un intermediar ne-ar face comerciant de înregistrare, cu tot ce
 *    înseamnă asta fiscal. Membrul primește un link către checkout-ul obișnuit;
 *    grupul e umbrela care leagă comenzile și arată progresul.
 *
 * CE NU FACE (încă), ca să nu existe iluzii: nu ține efectiv locurile în
 * inventar. `hold_expires_at` e termenul grupului; legarea de `seat_holds` /
 * cote se face separat, la pasul următor. Până atunci, „rezervarea" e o
 * promisiune de interfață, nu una de stoc — de aceea nu o afișăm ca atare.
 */
class GroupPurchaseService
{
    /** Cât ține rezervarea, dacă evenimentul e destul de departe. */
    public const HOLD_HOURS = 48;

    /** Cu cât timp înainte de eveniment trebuie încheiată cumpărarea. */
    public const MIN_LEAD_DAYS = 1;

    /**
     * Până când poate rămâne deschis un grup pentru evenimentul ăsta.
     *
     * `null` = prea târziu, grupul nu se poate deschide.
     */
    public function holdDeadline(Event $event, ?Carbon $now = null): ?Carbon
    {
        $now = $now ?? now();
        $eventDate = $event->event_date ?? $event->range_start_date;

        if (! $eventDate) {
            /* Fără dată nu putem calcula termenul. Un festival fără dată e o
               eroare de date, nu un caz de tratat cu o valoare implicită. */
            return null;
        }

        /* Ultima clipă permisă: sfârșitul zilei dinaintea evenimentului.
           „Cu cel puțin o zi înainte" înseamnă că ziua evenimentului nu intră
           în fereastră deloc. */
        $latest = Carbon::parse($eventDate)->startOfDay()->subDays(self::MIN_LEAD_DAYS)->endOfDay();

        $wanted = $now->copy()->addHours(self::HOLD_HOURS);
        $deadline = $wanted->lessThan($latest) ? $wanted : $latest;

        /* Sub o oră nu are rost: n-ai timp nici să inviți, nici să plătească
           cineva. Mai bine spui „e prea târziu" decât să deschizi un grup care
           expiră cât scrii mesajul. */
        return $deadline->greaterThan($now->copy()->addHour()) ? $deadline : null;
    }

    /**
     * Deschide un grup. Inițiatorul e primul membru și e deja „plătit" doar
     * dacă a cumpărat; altfel intră ca oricare altul.
     *
     * @throws \RuntimeException cand evenimentul e prea aproape
     */
    public function open(
        TixelloAccount $initiator,
        Event $event,
        TicketType $ticketType,
        int $seats,
        string $paymentType = GroupBooking::PAYMENT_SPLIT,
    ): GroupBooking {
        $deadline = $this->holdDeadline($event);

        if (! $deadline) {
            throw new \RuntimeException(
                'Evenimentul e prea aproape pentru un grup: biletele trebuie luate cu cel puțin o zi înainte.',
            );
        }

        $seats = max(2, min($seats, 20));
        $unit = (float) $ticketType->display_price;

        return GroupBooking::create([
            'marketplace_client_id' => $event->marketplace_client_id ?? null,
            'tenant_id' => $event->tenant_id ?? null,
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'tixello_account_id' => $initiator->id,
            'group_name' => trim((string) $initiator->name) !== '' ? "Grupul lui {$initiator->name}" : 'Grup tics',
            'group_type' => 'friends',
            'total_tickets' => $seats,
            'total_amount' => $unit * $seats,
            'status' => GroupBooking::STATUS_PENDING,
            'payment_type' => $paymentType,
            'deadline_at' => $deadline,
            'hold_expires_at' => $deadline,
            'invite_code' => $this->uniqueCode(),
        ]);
    }

    /**
     * Adaugă membri. Prietenii cu cont primesc grupul în aplicație; ceilalți,
     * un link pe email.
     *
     * @param  array<int, array{account_id?: int, email?: string, name?: string}>  $people
     * @return array<int, GroupBookingMember>
     */
    public function invite(GroupBooking $group, array $people): array
    {
        $unit = $group->total_tickets > 0
            ? round((float) $group->total_amount / $group->total_tickets, 2)
            : 0.0;

        $added = [];

        foreach ($people as $p) {
            $accountId = isset($p['account_id']) ? (int) $p['account_id'] : null;
            $email = trim((string) ($p['email'] ?? ''));
            $name = trim((string) ($p['name'] ?? ''));

            if ($accountId) {
                $account = TixelloAccount::find($accountId);

                if (! $account) {
                    continue;
                }

                $email = $account->email;
                $name = $name !== '' ? $name : (string) $account->name;
            }

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            /* Aceeași adresă nu intră de două ori: altfel, o invitație trimisă
               din greșeală ar dubla partea de plată a omului. */
            $exists = $group->members()->where('email', mb_strtolower($email))->exists();

            if ($exists) {
                continue;
            }

            $token = Str::random(48);

            $added[] = GroupBookingMember::create([
                'group_booking_id' => $group->id,
                'tixello_account_id' => $accountId,
                'name' => $name !== '' ? $name : $email,
                'email' => mb_strtolower($email),
                'amount_due' => $unit,
                'amount_paid' => 0,
                'payment_status' => GroupBookingMember::STATUS_PENDING,
                'invite_token' => $token,
                'invited_at' => now(),
                'payment_link' => $this->paymentLink($group, $token),
            ]);
        }

        return $added;
    }

    /** Starea grupului, în forma pe care o consumă aplicația. */
    public function progress(GroupBooking $group): array
    {
        $members = $group->members()->get();
        $paid = $members->where('payment_status', GroupBookingMember::STATUS_PAID);

        return [
            'id' => $group->id,
            'invite_code' => $group->invite_code,
            'status' => $group->status,
            'payment_type' => $group->payment_type,
            'seats' => (int) $group->total_tickets,
            'total' => (float) $group->total_amount,
            'paid_count' => $paid->count(),
            'paid_amount' => (float) $paid->sum('amount_paid'),
            'members_count' => $members->count(),
            /* Locurile pentru care încă n-a plătit nimeni. Se calculează, nu se
               ține într-o coloană: o coloană ar putea rămâne în urmă. */
            'unclaimed' => max(0, (int) $group->total_tickets - $members->count()),
            'expires_at' => $group->hold_expires_at?->toIso8601String(),
            'expired' => $group->hold_expires_at ? $group->hold_expires_at->isPast() : false,
            'members' => $members->map(fn (GroupBookingMember $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
                'account_id' => $m->tixello_account_id,
                'amount_due' => (float) $m->amount_due,
                'paid' => $m->payment_status === GroupBookingMember::STATUS_PAID,
                'paid_at' => $m->paid_at?->toIso8601String(),
                'payment_link' => $m->payment_link,
            ])->values()->all(),
        ];
    }

    /** Marchează plata unui membru, după comanda lui. */
    public function markPaid(GroupBookingMember $member, int $orderId, float $amount): void
    {
        $member->update([
            'order_id' => $orderId,
            'amount_paid' => $amount,
            'payment_status' => GroupBookingMember::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $group = $member->booking;

        if (! $group) {
            return;
        }

        $allPaid = ! $group->members()
            ->where('payment_status', '!=', GroupBookingMember::STATUS_PAID)
            ->exists();

        if ($allPaid && $group->members()->count() >= $group->total_tickets) {
            $group->update(['status' => GroupBooking::STATUS_PAID, 'confirmed_at' => now()]);
        }
    }

    private function paymentLink(GroupBooking $group, string $token): string
    {
        return rtrim(config('app.url'), '/')."/grup/{$group->invite_code}/{$token}";
    }

    private function uniqueCode(): string
    {
        do {
            $code = mb_strtoupper(Str::random(8));
        } while (GroupBooking::where('invite_code', $code)->exists());

        return $code;
    }
}
