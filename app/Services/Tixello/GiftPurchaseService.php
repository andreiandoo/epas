<?php

namespace App\Services\Tixello;

use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceTicketTransfer;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TixelloAccount;
use App\Models\TixelloGiftIntent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * „Cumpăr eu, biletul e al lui" — capitolul 3 din planul friends-social.
 *
 * NU reinventează transferul. `MarketplaceTicketTransfer` există de mult, cu
 * token, expirare la 7 zile, accept/refuz și mutarea proprietății; are și API
 * de client. Serviciul ăsta doar ORCHESTREAZĂ: după ce comanda e plătită,
 * transformă intențiile în transferuri în așteptare.
 *
 * DE CE TRANSFER ÎN AȘTEPTARE și nu bilet emis direct pe numele prietenului:
 * biletul e un contract cu organizatorul, iar prietenul trebuie să-l accepte
 * ca să devină al lui. În plus, dacă adresa e greșită, transferul expiră și
 * biletul rămâne la cumpărător — pe când un bilet emis direct s-ar fi pierdut.
 *
 * LIMITĂ CUNOSCUTĂ: merge doar pentru evenimentele de marketplace. Transferul
 * e legat de `marketplace_customers`; pentru evenimentele de tenant nu există
 * un echivalent, iar aplicația nu oferă opțiunea acolo.
 */
class GiftPurchaseService
{
    /**
     * Scrie intențiile la crearea comenzii.
     *
     * @param  array<int, array<string, mixed>>  $gifts  [{account_id?, email?, name?, quantity?, message?}]
     * @return int cate intentii s-au scris
     */
    public function record(Order $order, ?TixelloAccount $giver, array $gifts): int
    {
        $written = 0;

        foreach ($gifts as $g) {
            $email = trim((string) ($g['email'] ?? ''));
            $accountId = isset($g['account_id']) ? (int) $g['account_id'] : null;
            $name = trim((string) ($g['name'] ?? ''));

            /* Contul are prioritate: dacă prietenul are cont tics, îi luăm
               adresa de acolo. Adresa scrisă de mână se poate să fie veche. */
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

            TixelloGiftIntent::create([
                'order_id' => $order->id,
                'marketplace_client_id' => $order->marketplace_client_id,
                'tixello_account_id' => $giver?->id,
                'recipient_account_id' => $accountId,
                'recipient_email' => mb_strtolower($email),
                'recipient_name' => $name !== '' ? $name : null,
                'quantity' => max(1, (int) ($g['quantity'] ?? 1)),
                'message' => isset($g['message']) ? mb_substr((string) $g['message'], 0, 500) : null,
                'status' => TixelloGiftIntent::STATUS_PENDING,
            ]);

            $written++;
        }

        return $written;
    }

    /**
     * Transformă intențiile plătite în transferuri.
     *
     * Se cheamă din observatorul de comandă, după ce plata a intrat. E
     * IDEMPOTENT: o intenție deja convertită nu se mai atinge, iar biletele
     * deja promise altcuiva nu se mai iau — o comandă poate trece prin
     * „paid" de mai multe ori (webhook repetat, reconciliere manuală).
     */
    public function convert(Order $order): void
    {
        $intents = TixelloGiftIntent::query()
            ->where('order_id', $order->id)
            ->where('status', TixelloGiftIntent::STATUS_PENDING)
            ->get();

        if ($intents->isEmpty()) {
            return;
        }

        $tickets = $order->tickets()->get();

        /* Biletele deja implicate într-un transfer în așteptare nu se pot
           dărui a doua oară. */
        $taken = MarketplaceTicketTransfer::query()
            ->whereIn('ticket_id', $tickets->pluck('id'))
            ->whereIn('status', ['pending', 'accepted'])
            ->pluck('ticket_id')
            ->all();

        $available = $tickets->reject(fn (Ticket $t) => in_array($t->id, $taken, true))->values();
        $cursor = 0;

        foreach ($intents as $intent) {
            $slice = $available->slice($cursor, $intent->quantity);

            if ($slice->isEmpty()) {
                $intent->update([
                    'status' => TixelloGiftIntent::STATUS_FAILED,
                    'error' => 'Comanda nu mai are bilete disponibile pentru acest cadou.',
                ]);

                continue;
            }

            try {
                foreach ($slice as $ticket) {
                    $this->transferFor($order, $ticket, $intent);
                }

                $cursor += $slice->count();

                $intent->update([
                    'status' => TixelloGiftIntent::STATUS_CONVERTED,
                    'converted_at' => now(),
                ]);
            } catch (\Throwable $e) {
                /* Un cadou pierdut trebuie să poată fi explicat clientului
                   care întreabă, deci motivul se scrie, nu se înghite. */
                Log::warning('Cadoul nu a putut fi transformat in transfer', [
                    'intent_id' => $intent->id,
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);

                $intent->update([
                    'status' => TixelloGiftIntent::STATUS_FAILED,
                    'error' => mb_substr($e->getMessage(), 0, 300),
                ]);
            }
        }
    }

    private function transferFor(Order $order, Ticket $ticket, TixelloGiftIntent $intent): void
    {
        /* Destinatarul, dacă are deja cont de client pe marketplace-ul ăsta:
           transferul îi apare direct în „Primite", fără să treacă prin email. */
        $to = MarketplaceCustomer::query()
            ->when($order->marketplace_client_id, fn ($q) => $q->where('marketplace_client_id', $order->marketplace_client_id))
            ->where('email', $intent->recipient_email)
            ->first();

        MarketplaceTicketTransfer::create([
            'marketplace_client_id' => $order->marketplace_client_id,
            'ticket_id' => $ticket->id,
            'from_customer_id' => $order->marketplace_customer_id,
            'from_email' => $order->customer_email ?? null,
            'from_name' => $order->customer_name ?? null,
            'to_email' => $intent->recipient_email,
            'to_name' => $intent->recipient_name,
            'to_customer_id' => $to?->id,
            'token' => Str::random(48),
            'status' => 'pending',
            'message' => $intent->message,
            /* Aceeași fereastră ca la transferurile obișnuite. Nu una mai
               lungă „pentru că e cadou": un bilet blocat într-un transfer
               neacceptat e un loc care nu se poate revinde. */
            'expires_at' => now()->addDays(7),
        ]);
    }
}
