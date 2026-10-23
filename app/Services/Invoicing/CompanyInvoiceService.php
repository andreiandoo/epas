<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceEmailLog;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplacePayout;
use App\Models\Order;
use App\Models\OrderCompanyInvoice;
use App\Services\Accounting\AccountingService;
use App\Services\AnafService;
use App\Services\OrganizerNotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Factură pe persoană juridică la o comandă online (checkout Ambilet,
 * whitelabel, contul clientului sau operatorul din admin).
 *
 * Reguli (decise 2026-10-05 / 2026-10-23):
 * - marketplace-ul facturează tot ce nu e bilet: comisionul peste preț + TVA,
 *   asigurarea, suprataxa card cultural, taxa de procesare trecută la client.
 *   Factura se emite imediat după plată, fiscal (nu ciornă), în Oblio, pe seria
 *   din conector, se trimite în SPV și pleacă la client pe email cu PDF.
 * - fiecare organizator facturează valoarea biletelor lui (după reduceri); i se
 *   cere factura, o încarcă, iar noi o trimitem clientului. Organizatorul
 *   persoană fizică nu emite factură: rândul lui rămâne „not_applicable”.
 * - doar firme românești, cu CUI activ la ANAF; datele firmei vin de la ANAF.
 * - cererea se refuză dacă un decont al comenzii are deja factura
 *   „Client Divers” emisă (comisionul a fost facturat acolo).
 */
class CompanyInvoiceService
{
    public const RECIPIENT_TYPE = 'order_company';

    public const ACTION_URL = '/organizator/facturi-clienti';

    public function isEnabled(?MarketplaceClient $client): bool
    {
        return $client !== null && (bool) data_get($client->settings, 'company_invoicing.enabled', false);
    }

    // ------------------------------------------------------------------
    // Firma
    // ------------------------------------------------------------------

    /**
     * Datele firmei de la ANAF. Aruncă \InvalidArgumentException cu mesaj
     * pentru client când CUI-ul nu e bun.
     */
    public function lookupCompany(string $cui): array
    {
        $anaf = app(AnafService::class);
        $clean = strtoupper(preg_replace('/\s+/', '', $cui));
        if (!$anaf->isValidCui($clean)) {
            throw new \InvalidArgumentException('CUI-ul nu este valid. Folosește un CUI românesc (ex. 12345678 sau RO12345678).');
        }

        $data = Cache::remember('anaf_company_' . preg_replace('/^RO/', '', $clean), now()->addHours(6), function () use ($anaf, $clean) {
            $found = $anaf->lookupByCui($clean);
            if ($found) {
                unset($found['raw_data']);
            }

            return $found;
        });

        if (!$data) {
            Cache::forget('anaf_company_' . preg_replace('/^RO/', '', $clean));
            throw new \InvalidArgumentException('Nu am găsit firma la ANAF. Verifică CUI-ul sau încearcă din nou în câteva minute.');
        }
        if (empty($data['is_active'])) {
            throw new \InvalidArgumentException('Firma nu este activă la ANAF și nu poate primi factură.');
        }

        $parsed = $data['parsed_address'] ?? [];

        return [
            'name' => (string) ($data['company_name'] ?? ''),
            'cui' => (string) $data['cui'],
            'vat_payer' => (bool) ($data['vat_payer'] ?? false),
            'reg_com' => (string) ($data['reg_com'] ?? ''),
            'address' => (string) ($data['address'] ?? ''),
            'city' => (string) ($parsed['city'] ?? $data['city'] ?? ''),
            'county' => (string) ($parsed['county'] ?? $data['state'] ?? ''),
            'country' => 'RO',
            'verified_at' => now()->toIso8601String(),
        ];
    }

    /** CUI-ul așa cum apare pe factură (RO în față pentru plătitorii de TVA). */
    public function fiscalCode(array $company): string
    {
        $cui = (string) ($company['cui'] ?? '');

        return !empty($company['vat_payer']) ? 'RO' . $cui : $cui;
    }

    // ------------------------------------------------------------------
    // Eligibilitate
    // ------------------------------------------------------------------

    /** Motivul pentru care nu se poate cere factură pe firmă, sau null. */
    public function ineligibleReason(Order $order, bool $requirePaid = true): ?string
    {
        if (!$this->isEnabled($order->marketplaceClient)) {
            return 'Facturarea pe persoană juridică nu este activă.';
        }
        if ($order->source !== 'marketplace') {
            return 'Factura pe firmă se poate cere doar pentru comenzile online.';
        }
        if (!empty($order->meta['is_test_order'])) {
            return 'Comanda este de test.';
        }
        if ((float) $order->total <= 0) {
            return 'Comanda nu are valoare de facturat.';
        }
        if ($requirePaid && !in_array($order->status, ['paid', 'confirmed', 'completed', 'partially_refunded'], true)) {
            return 'Comanda nu este plătită.';
        }
        if (OrderCompanyInvoice::where('order_id', $order->id)->exists()) {
            return 'Factura pe firmă a fost deja cerută pentru această comandă.';
        }

        return $this->cutoffReason($order);
    }

    /**
     * Refuz după decont: există un decont al unui eveniment din comandă care
     * acoperă data plății și are deja factura „Client Divers” emisă.
     */
    public function cutoffReason(Order $order): ?string
    {
        if (!$order->paid_at) {
            return null;
        }

        $paidDate = $order->paid_at->toDateString();
        $blocked = MarketplacePayout::query()
            ->where('marketplace_client_id', $order->marketplace_client_id)
            ->whereIn('event_id', $this->eventIds($order) ?: [0])
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->where(fn ($q) => $q->whereNull('period_start')->orWhereDate('period_start', '<=', $paidDate))
            ->where(fn ($q) => $q->whereNull('period_end')->orWhereDate('period_end', '>=', $paidDate))
            ->whereHas('invoice')
            ->exists();

        return $blocked
            ? 'Comanda a fost deja decontată și facturată. Pentru factura pe firmă contactează-ne.'
            : null;
    }

    /** @return array<int, int> */
    public function eventIds(Order $order): array
    {
        $ids = array_map('intval', (array) ($order->meta['event_ids'] ?? []));
        if ($order->event_id) {
            $ids[] = (int) $order->event_id;
        }
        if (empty($ids)) {
            $ids = $order->tickets()->whereNotNull('event_id')->distinct()->pluck('event_id')->map(fn ($id) => (int) $id)->all();
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Organizatorii (din evenimentele date) care sunt persoane fizice și nu
     * pot emite factură pentru bilete. Pentru mesajul din checkout.
     *
     * @return array<int, string>
     */
    public function organizersWithoutInvoice(int $marketplaceClientId, array $eventIds): array
    {
        $eventIds = array_values(array_filter(array_map('intval', $eventIds)));
        if (!$eventIds) {
            return [];
        }

        return MarketplaceOrganizer::query()
            ->where('marketplace_client_id', $marketplaceClientId)
            ->where('person_type', 'pf')
            ->whereIn('id', \App\Models\Event::whereIn('id', $eventIds)->whereNotNull('marketplace_organizer_id')->pluck('marketplace_organizer_id'))
            ->get(['id', 'name', 'company_name'])
            ->map(fn ($o) => (string) ($o->name ?: $o->company_name))
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    // Cerere
    // ------------------------------------------------------------------

    /**
     * Cererea venită din checkout: comanda e încă neplătită. Se verifică firma,
     * se salvează pe comandă și în profil; facturile pornesc la plată.
     * Nu aruncă niciodată: checkout-ul nu trebuie să cadă din cauza asta.
     */
    public function attachFromCheckout(Order $order, ?array $input): void
    {
        $cui = trim((string) ($input['cui'] ?? ''));
        if ($cui === '' || !$this->isEnabled($order->marketplaceClient) || $order->source !== 'marketplace') {
            return;
        }

        try {
            $company = $this->lookupCompany($cui);
        } catch (\Throwable $e) {
            // ANAF picat: păstrăm CUI-ul, firma se verifică din nou la plată
            $company = ['cui' => preg_replace('/^RO/i', '', preg_replace('/\s+/', '', $cui)), 'verified_at' => null];
            Log::info('[CompanyInvoice] checkout lookup failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        $this->storeOnOrder($order, $company, 'checkout');

        // Comandă gratuită / confirmată pe loc: observer-ul de plată a trecut deja
        if (in_array($order->status, ['paid', 'confirmed', 'completed'], true)) {
            $this->processPaidOrder($order->fresh());
        }
    }

    /**
     * Cerere după cumpărare (contul clientului sau operatorul din admin).
     * Aruncă \InvalidArgumentException cu mesajul pentru utilizator.
     *
     * @return array<int, OrderCompanyInvoice>
     */
    public function requestForPaidOrder(Order $order, string $cui, string $via, ?string $actor = null): array
    {
        if ($reason = $this->ineligibleReason($order)) {
            throw new \InvalidArgumentException($reason);
        }

        $company = $this->lookupCompany($cui);
        $this->storeOnOrder($order, $company, $via, $actor);

        return $this->processPaidOrder($order->fresh());
    }

    protected function storeOnOrder(Order $order, array $company, string $via, ?string $actor = null): void
    {
        $meta = $order->meta ?? [];
        $meta['company_billing'] = $company;
        $meta['invoice_requested'] = true;
        $meta['invoice_requested_via'] = $via;
        $meta['invoice_requested_at'] = now()->toIso8601String();
        if ($actor) {
            $meta['invoice_requested_by'] = $actor;
        }
        $order->meta = $meta;
        $order->saveQuietly();

        // Pentru comenzile următoare ale aceluiași client
        if (!empty($company['name']) && $order->marketplace_customer_id) {
            $customer = MarketplaceCustomer::find($order->marketplace_customer_id);
            if ($customer) {
                $settings = $customer->settings ?? [];
                $settings['company_billing'] = array_intersect_key($company, array_flip(['cui', 'name', 'reg_com', 'address', 'city', 'county', 'vat_payer']));
                $customer->settings = $settings;
                $customer->saveQuietly();
            }
        }
    }

    /**
     * Comanda plătită are cerere de factură: creează rândurile (o singură
     * dată), pornește emiterea marketplace-ului și anunță organizatorii.
     *
     * @return array<int, OrderCompanyInvoice>
     */
    public function processPaidOrder(Order $order): array
    {
        $meta = $order->meta ?? [];
        if (empty($meta['invoice_requested']) || empty($meta['company_billing']['cui'])) {
            return [];
        }

        $existing = OrderCompanyInvoice::where('order_id', $order->id)->get();
        if ($existing->isNotEmpty()) {
            return $existing->all();
        }

        // Firma neverificată în checkout (ANAF picat): o verificăm acum
        $company = $meta['company_billing'];
        if (empty($company['verified_at'])) {
            try {
                $company = $this->lookupCompany((string) $company['cui']);
                $meta['company_billing'] = $company;
                $order->meta = $meta;
                $order->saveQuietly();
            } catch (\Throwable $e) {
                Log::warning('[CompanyInvoice] company still not verified', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                \App\Jobs\ProcessOrderCompanyInvoiceJob::dispatch($order->id)->delay(now()->addMinutes(15));

                return [];
            }
        }

        $rows = DB::transaction(function () use ($order, $company) {
            // a doua cerere paralelă vede rândurile primei
            Order::whereKey($order->id)->lockForUpdate()->first();
            if (OrderCompanyInvoice::where('order_id', $order->id)->exists()) {
                return null;
            }

            $rows = [];
            $mp = $this->marketplacePart($order);
            if ($mp['amount'] > 0.005) {
                $rows[] = OrderCompanyInvoice::create([
                    'marketplace_client_id' => $order->marketplace_client_id,
                    'order_id' => $order->id,
                    'issuer' => OrderCompanyInvoice::ISSUER_MARKETPLACE,
                    'status' => OrderCompanyInvoice::STATUS_PENDING,
                    'company' => $company,
                    'lines' => $mp['lines'],
                    'subtotal' => $mp['subtotal'],
                    'vat_amount' => $mp['vat_amount'],
                    'amount' => $mp['amount'],
                    'currency' => $order->currency ?? 'RON',
                ]);
            }

            foreach ($this->organizerParts($order) as $part) {
                if ($part['amount'] <= 0.005) {
                    continue;
                }
                $rows[] = OrderCompanyInvoice::create([
                    'marketplace_client_id' => $order->marketplace_client_id,
                    'order_id' => $order->id,
                    'issuer' => OrderCompanyInvoice::ISSUER_ORGANIZER,
                    'marketplace_organizer_id' => $part['organizer']?->id,
                    'status' => $part['can_invoice'] ? OrderCompanyInvoice::STATUS_REQUESTED : OrderCompanyInvoice::STATUS_NOT_APPLICABLE,
                    'company' => $company,
                    'lines' => $part['lines'],
                    'subtotal' => $part['amount'],
                    'vat_amount' => 0,
                    'amount' => $part['amount'],
                    'currency' => $order->currency ?? 'RON',
                ]);
            }

            return $rows;
        });

        if ($rows === null) {
            return OrderCompanyInvoice::where('order_id', $order->id)->get()->all();
        }

        foreach ($rows as $row) {
            if ($row->isMarketplace()) {
                \App\Jobs\ProcessOrderCompanyInvoiceJob::dispatch($order->id, $row->id);
            } elseif ($row->status === OrderCompanyInvoice::STATUS_REQUESTED) {
                $this->notifyOrganizer($row);
            }
        }

        return $rows;
    }

    // ------------------------------------------------------------------
    // Împărțirea comenzii
    // ------------------------------------------------------------------

    /**
     * Ce facturează marketplace-ul. Liniile sunt NETE, fiecare cu cota ei.
     *
     * @return array{lines: array, subtotal: float, vat_amount: float, amount: float}
     */
    public function marketplacePart(Order $order): array
    {
        $meta = $order->meta ?? [];
        $client = $order->marketplaceClient;
        $rate = isset($meta['vat_rate'])
            ? (float) $meta['vat_rate']
            : ($client ? $client->vatRate($order->paid_at ?? $order->created_at) : 0.0);
        $eventName = $this->eventLabel($order);
        $lines = [];

        // Comisionul peste preț, pe tip de bilet
        foreach ((array) ($meta['commission_details'] ?? []) as $d) {
            if (!in_array($d['commission_mode'] ?? '', ['on_top', 'added_on_top', 'add_on_top'], true)) {
                continue;
            }
            $net = round((float) ($d['commission_amount'] ?? 0), 2);
            if ($net <= 0.005) {
                continue;
            }
            $vat = array_key_exists('commission_vat', $d)
                ? round((float) $d['commission_vat'], 2)
                : ($rate > 0 ? round($net * $rate / 100, 2) : 0.0);
            $lines[] = $this->line(
                'Comision servicii ticketing',
                'Bilete "' . ($d['ticket_type'] ?? 'Bilet') . '"' . ($eventName ? ' - ' . $eventName : ''),
                $net,
                $vat,
                $vat > 0 ? $rate : 0.0,
                (int) ($d['quantity'] ?? 1)
            );
        }

        // Asigurarea biletelor (insurance_amount e brut)
        $insurance = round((float) ($meta['insurance_amount'] ?? 0), 2);
        if (!empty($meta['ticket_insurance']) && $insurance > 0.005) {
            $vat = array_key_exists('insurance_vat', $meta) ? round((float) $meta['insurance_vat'], 2) : 0.0;
            $lines[] = $this->line('Asigurare bilete', 'Asigurare bilete comanda ' . $order->order_number, round($insurance - $vat, 2), $vat, $vat > 0 ? $rate : 0.0);
        }

        // Suprataxa card cultural (brut)
        $cultural = round((float) ($meta['cultural_card_surcharge'] ?? 0), 2);
        if ($cultural > 0.005) {
            $vat = round((float) ($meta['cultural_card_vat'] ?? 0), 2);
            $lines[] = $this->line('Suprataxă plată card cultural', 'Comanda ' . $order->order_number, round($cultural - $vat, 2), $vat, $vat > 0 ? $rate : 0.0);
        }

        // Taxa de procesare trecută la client (brută)
        if ($order->processing_fee_passed && (int) $order->processing_fee_cents > 0) {
            $gross = round($order->processing_fee_cents / 100, 2);
            $net = $rate > 0 ? round($gross / (1 + $rate / 100), 2) : $gross;
            $lines[] = $this->line('Taxă procesare plată', 'Comanda ' . $order->order_number, $net, round($gross - $net, 2), $rate);
        }

        $subtotal = round(array_sum(array_column($lines, 'amount')), 2);
        $vatTotal = round(array_sum(array_column($lines, 'vat_amount')), 2);

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'vat_amount' => $vatTotal,
            'amount' => round($subtotal + $vatTotal, 2),
        ];
    }

    /**
     * Ce facturează fiecare organizator: biletele lui, la prețul plătit
     * (după codul promoțional), cu TVA-ul lui inclus.
     *
     * @return array<int, array{organizer: ?MarketplaceOrganizer, can_invoice: bool, lines: array, amount: float}>
     */
    public function organizerParts(Order $order): array
    {
        $tickets = $order->tickets()
            ->with('ticketType:id,name,event_id')
            ->whereNotIn('status', ['cancelled', 'refunded', 'void'])
            ->get();
        $events = \App\Models\Event::whereIn('id', $tickets->map(fn ($t) => $t->event_id ?: $t->ticketType?->event_id)->filter()->unique()->values())
            ->get(['id', 'marketplace_organizer_id', 'title'])
            ->keyBy('id');

        $groups = [];
        foreach ($tickets as $t) {
            $event = $events->get($t->event_id ?: $t->ticketType?->event_id);
            $organizerId = (int) ($event?->marketplace_organizer_id ?? $order->marketplace_organizer_id ?? 0);
            $price = (float) ($t->getRawOriginal('price') ?? 0);
            $paid = round(max(0, $price - (float) ($t->meta['discount_amount'] ?? 0)), 2);
            $typeName = (string) ($t->ticketType?->name ?? 'Bilet');
            $key = $typeName . '|' . number_format($paid, 2, '.', '');

            $groups[$organizerId]['event'] ??= $event?->name;
            $groups[$organizerId]['lines'][$key] ??= ['name' => $typeName, 'quantity' => 0, 'unit_price' => $paid];
            $groups[$organizerId]['lines'][$key]['quantity']++;
        }

        $parts = [];
        foreach ($groups as $organizerId => $group) {
            $organizer = $organizerId ? MarketplaceOrganizer::find($organizerId) : null;
            $lines = [];
            foreach ($group['lines'] as $l) {
                $lines[] = [
                    'name' => 'Bilet "' . $l['name'] . '"' . (!empty($group['event']) ? ' - ' . $group['event'] : ''),
                    'quantity' => $l['quantity'],
                    'unit_price' => $l['unit_price'],
                    'amount' => round($l['quantity'] * $l['unit_price'], 2),
                ];
            }
            $parts[] = [
                'organizer' => $organizer,
                'can_invoice' => $organizer !== null && $organizer->person_type !== 'pf',
                'lines' => $lines,
                'amount' => round(array_sum(array_column($lines, 'amount')), 2),
            ];
        }

        return $parts;
    }

    protected function line(string $name, string $description, float $net, float $vat, float $rate, int $qty = 1): array
    {
        $net = round($net, 2);
        $unit = $net;
        if ($qty > 1 && abs(round(round($net / $qty, 2) * $qty, 2) - $net) < 0.005) {
            $unit = round($net / $qty, 2);
        } else {
            $qty = 1;
        }

        return [
            'name' => $name,
            'description' => $description,
            'quantity' => $qty,
            'unit_price' => $unit,
            'amount' => $net,
            'vat_rate' => $rate,
            'vat_amount' => round($vat, 2),
        ];
    }

    protected function eventLabel(Order $order): string
    {
        $event = $order->event;
        if (!$event) {
            return '';
        }
        $date = $event->event_date?->format('d.m.Y') ?? $event->range_start_date?->format('d.m.Y');

        return trim($event->name . ($date ? ', ' . $date : ''));
    }

    // ------------------------------------------------------------------
    // Factura marketplace-ului
    // ------------------------------------------------------------------

    /**
     * Emite (sau continuă emiterea) facturii marketplace-ului: factura locală,
     * Oblio fiscal, SPV, PDF, email către client. Fiecare pas e idempotent,
     * deci jobul poate fi reluat. Aruncă la eroare (jobul reîncearcă).
     */
    public function issueMarketplaceInvoice(OrderCompanyInvoice $row): void
    {
        $order = $row->order;
        $client = $row->marketplaceClient;
        if (!$order || !$client) {
            throw new \RuntimeException('Comanda sau marketplace-ul lipsește.');
        }

        $row->increment('attempts');

        // 1. Factura locală (apare în Facturi Organizatori)
        $invoice = $row->invoice;
        if (!$invoice) {
            $invoice = $this->createLocalInvoice($row, $order, $client);
            $row->update(['invoice_id' => $invoice->id]);
        }

        $meta = $invoice->meta ?? [];

        // 2. Oblio, ca factură fiscală (nu ciornă)
        if (empty($meta['accounting']['external_ref'])) {
            $result = app(AccountingService::class)->issueMarketplaceInvoice($client->id, $invoice->number, $this->oblioPayload($invoice, $client, $order));
            if (empty($result['success']) || empty($result['external_ref'])) {
                throw new \RuntimeException('Oblio nu a emis factura.');
            }
            $meta['accounting'] = [
                'external_ref' => $result['external_ref'],
                'invoice_number' => $result['invoice_number'],
                'doc_type' => 'invoice',
                'provider' => $this->providerName($client->id),
                'sent_at' => now()->toIso8601String(),
            ];
            $invoice->update(['meta' => $meta]);
        }

        $row->update(['status' => OrderCompanyInvoice::STATUS_ISSUED, 'last_error' => null]);

        // 3. SPV prin Oblio. O eroare aici nu oprește trimiterea către client.
        if (empty($meta['accounting']['efactura_sent_at'])) {
            try {
                $spv = app(AccountingService::class)->submitMarketplaceEFactura($client->id, $meta['accounting']['external_ref']);
                if (!empty($spv['supported'])) {
                    $meta['accounting']['efactura_sent_at'] = now()->toIso8601String();
                    $invoice->update(['meta' => $meta]);
                }
            } catch (\Throwable $e) {
                $meta['accounting']['efactura_error'] = mb_substr($e->getMessage(), 0, 500);
                $invoice->update(['meta' => $meta]);
                Log::warning('[CompanyInvoice] e-Factura submit failed', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
            }
        }

        // 4. Clientul primește factura pe email
        if (!$row->sent_to_customer_at) {
            $this->sendToCustomer($row->fresh());
        }
    }

    protected function createLocalInvoice(OrderCompanyInvoice $row, Order $order, MarketplaceClient $client): Invoice
    {
        $company = $row->company ?? [];

        // Numerotarea locală e aceeași cu a facturilor de decont (F-000123);
        // lock ca două emiteri simultane să nu ia același număr.
        return Cache::lock('invoice-number-' . $client->id, 15)->block(10, function () use ($row, $order, $client, $company) {
            $last = Invoice::where('marketplace_client_id', $client->id)->orderByDesc('id')->first();
            $next = $last ? ((int) preg_replace('/\D/', '', $last->number) + 1) : 1;

            return Invoice::create([
                'marketplace_client_id' => $client->id,
                'marketplace_organizer_id' => null,
                'order_id' => $order->id,
                'number' => 'F-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT),
                'type' => 'fiscal',
                'description' => 'Comanda ' . $order->order_number . ' - ' . ($company['name'] ?? ''),
                'issue_date' => now(),
                'due_date' => now(),
                'subtotal' => $row->subtotal,
                'vat_rate' => (float) collect($row->lines ?? [])->max('vat_rate'),
                'vat_amount' => $row->vat_amount,
                'amount' => $row->amount,
                'currency' => $row->currency,
                'status' => 'paid',
                'paid_at' => $order->paid_at ?? now(),
                'meta' => [
                    'recipient_type' => self::RECIPIENT_TYPE,
                    'order_number' => $order->order_number,
                    'issuer' => [
                        'name' => $client->company_name ?? $client->name,
                        'cui' => $client->cui ?? '',
                        'reg_com' => $client->reg_com ?? '',
                        'address' => $client->address ?? '',
                        'vat_payer' => (bool) $client->vat_payer,
                    ],
                    'client' => [
                        'name' => $company['name'] ?? '',
                        'cui' => $this->fiscalCode($company),
                        'reg_com' => $company['reg_com'] ?? '',
                        'address' => $company['address'] ?? '',
                        'is_pf' => false,
                    ],
                    'items' => $row->lines ?? [],
                ],
            ]);
        });
    }

    protected function oblioPayload(Invoice $invoice, MarketplaceClient $client, Order $order): array
    {
        $meta = $invoice->meta ?? [];
        $company = OrderCompanyInvoice::where('invoice_id', $invoice->id)->value('company') ?? [];
        if (is_string($company)) {
            $company = json_decode($company, true) ?: [];
        }

        $payload = [
            'seller_vat' => $client->cui ?? '',
            'issue_date' => $invoice->issue_date?->format('Y-m-d') ?? date('Y-m-d'),
            'due_date' => $invoice->due_date?->format('Y-m-d') ?? date('Y-m-d'),
            'currency' => $invoice->currency ?? 'RON',
            'number' => $invoice->number,
            'is_draft' => false,
            'doc_type' => 'invoice',
            'customer' => [
                'name' => $meta['client']['name'] ?? '',
                'vat_number' => $meta['client']['cui'] ?? '',
                'reg_number' => $meta['client']['reg_com'] ?? '',
                'email' => $order->customer_email ?? '',
                'address' => [
                    'street' => $company['address'] ?? ($meta['client']['address'] ?? ''),
                    'city' => $company['city'] ?? '',
                    'county' => $company['county'] ?? '',
                    'country' => 'Romania',
                ],
            ],
            'lines' => array_map(fn ($item) => [
                'product_name' => $item['name'] ?? 'Comision servicii ticketing',
                'description' => $item['description'] ?? '',
                'quantity' => (float) ($item['quantity'] ?? 1),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'tax_rate' => (float) ($item['vat_rate'] ?? 0),
                'vat_included' => false,
                'unit' => 'buc',
            ], $meta['items'] ?? []),
        ];

        $preparer = trim((string) data_get($client->settings, 'invoice_preparer', ''));
        if ($preparer !== '') {
            $payload['issuer_name'] = $preparer;
            $payload['issuer_cnp'] = trim((string) data_get($client->settings, 'invoice_preparer_cnp', ''));
        }

        return $payload;
    }

    protected function providerName(int $clientId): string
    {
        return (string) (DB::table('acc_connectors')->where('marketplace_client_id', $clientId)->where('status', 'connected')->value('provider') ?? 'unknown');
    }

    // ------------------------------------------------------------------
    // Factura organizatorului
    // ------------------------------------------------------------------

    public function notifyOrganizer(OrderCompanyInvoice $row): void
    {
        $organizer = $row->organizer;
        $order = $row->order;
        if (!$organizer || !$order) {
            return;
        }
        $company = $row->company ?? [];
        $amount = number_format((float) $row->amount, 2, ',', '.') . ' ' . $row->currency;

        try {
            OrganizerNotificationService::notify(
                $organizer,
                'company_invoice_request',
                'Factură cerută pe firmă: ' . ($company['name'] ?? ''),
                'Comanda ' . $order->order_number . ', ' . $amount . '. Emite factura și încarc-o în cont, o trimitem noi clientului.',
                self::ACTION_URL,
                $row,
                ['order_id' => $order->id, 'order_number' => $order->order_number]
            );
        } catch (\Throwable $e) {
            Log::warning('[CompanyInvoice] organizer notification failed', ['row_id' => $row->id, 'error' => $e->getMessage()]);
        }

        $email = $organizer->email;
        if (!$email) {
            return;
        }

        $rows = '';
        foreach ($row->lines ?? [] as $l) {
            $rows .= '<tr><td style="padding:6px 10px;border-bottom:1px solid #eee;">' . e($l['name']) . '</td>'
                . '<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:center;">' . (int) $l['quantity'] . '</td>'
                . '<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;">' . number_format((float) $l['unit_price'], 2, ',', '.') . '</td>'
                . '<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;">' . number_format((float) $l['amount'], 2, ',', '.') . '</td></tr>';
        }
        $link = $this->siteUrl($row->marketplaceClient) . self::ACTION_URL;

        $html = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#222;line-height:1.5;">'
            . '<p>Bună ziua,</p>'
            . '<p>Un client a cerut factură pe firmă pentru comanda <strong>' . e($order->order_number) . '</strong>. '
            . 'Te rugăm să emiți factura pentru biletele de mai jos (cu TVA-ul tău inclus în preț, dacă ești plătitor) și să o încarci în contul de organizator. O trimitem noi clientului.</p>'
            . '<table style="border-collapse:collapse;margin:12px 0;font-size:13px;">'
            . '<tr><td style="padding:3px 12px 3px 0;color:#777;">Firmă</td><td><strong>' . e($company['name'] ?? '') . '</strong></td></tr>'
            . '<tr><td style="padding:3px 12px 3px 0;color:#777;">CUI</td><td>' . e($this->fiscalCode($company)) . '</td></tr>'
            . '<tr><td style="padding:3px 12px 3px 0;color:#777;">Reg. Com.</td><td>' . e($company['reg_com'] ?? '') . '</td></tr>'
            . '<tr><td style="padding:3px 12px 3px 0;color:#777;">Adresă</td><td>' . e($company['address'] ?? '') . '</td></tr>'
            . '</table>'
            . '<table style="border-collapse:collapse;font-size:13px;min-width:420px;">'
            . '<tr style="background:#f5f5f5;"><th style="padding:6px 10px;text-align:left;">Bilete</th><th style="padding:6px 10px;">Cant.</th><th style="padding:6px 10px;text-align:right;">Preț</th><th style="padding:6px 10px;text-align:right;">Total</th></tr>'
            . $rows
            . '<tr><td colspan="3" style="padding:8px 10px;text-align:right;"><strong>Total de facturat</strong></td><td style="padding:8px 10px;text-align:right;"><strong>' . e($amount) . '</strong></td></tr>'
            . '</table>'
            . '<p style="margin-top:18px;"><a href="' . e($link) . '" style="background:#A51C30;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;">Încarcă factura</a></p>'
            . '<p style="color:#777;font-size:12px;">Comisionul și celelalte servicii ale platformei le facturează separat ' . e($row->marketplaceClient?->name ?? '') . '.</p>'
            . '</div>';

        $this->sendEmail($row->marketplaceClient, $email, $organizer->name, 'Factură cerută pe firmă - comanda ' . $order->order_number, $html, null, null, [
            'marketplace_organizer_id' => $organizer->id,
            'order_id' => $order->id,
            'template_slug' => 'company_invoice_request',
        ]);
    }

    /** Organizatorul (sau operatorul în numele lui) încarcă factura; pleacă imediat la client. */
    public function storeOrganizerUpload(OrderCompanyInvoice $row, UploadedFile $file, string $actor): OrderCompanyInvoice
    {
        if ($row->isMarketplace()) {
            throw new \InvalidArgumentException('Factura platformei se emite automat.');
        }
        if ($row->status === OrderCompanyInvoice::STATUS_NOT_APPLICABLE) {
            throw new \InvalidArgumentException('Pentru această comandă nu se cere factură de la organizator.');
        }

        $dir = 'company-invoices/' . $row->marketplace_client_id . '/' . $row->order_id;
        $path = $file->storeAs($dir, 'org-' . $row->id . '-' . time() . '.pdf', 'local');
        $old = $row->file_path;

        $row->update([
            'file_path' => $path,
            'file_name' => mb_substr($file->getClientOriginalName() ?: 'factura.pdf', 0, 200),
            'uploaded_at' => now(),
            'uploaded_by' => $actor,
            'status' => OrderCompanyInvoice::STATUS_UPLOADED,
            'sent_to_customer_at' => null,
        ]);
        if ($old && $old !== $path) {
            Storage::disk('local')->delete($old);
        }

        $this->sendToCustomer($row->fresh());

        return $row->fresh();
    }

    // ------------------------------------------------------------------
    // Livrare către client
    // ------------------------------------------------------------------

    /** PDF-ul facturii: [conținut, nume fișier] sau null dacă încă nu există. */
    public function documentFor(OrderCompanyInvoice $row): ?array
    {
        if ($row->isMarketplace()) {
            $invoice = $row->invoice;
            $ref = $invoice?->meta['accounting']['external_ref'] ?? null;
            if (!$invoice || !$ref) {
                return null;
            }
            $url = $invoice->meta['accounting']['pdf_url'] ?? null;
            if (!$url) {
                $pdf = app(AccountingService::class)->getMarketplaceInvoicePdf($row->marketplace_client_id, $ref, 'invoice');
                $url = $pdf['pdf_url'] ?? null;
                if ($url) {
                    $meta = $invoice->meta;
                    $meta['accounting']['pdf_url'] = $url;
                    $meta['accounting']['pdf_fetched_at'] = now()->toIso8601String();
                    $invoice->update(['meta' => $meta]);
                }
            }
            if (!$url) {
                return null;
            }
            $response = Http::timeout(20)->get($url);
            if (!$response->successful() || !str_starts_with($response->body(), '%PDF')) {
                return null;
            }

            return [$response->body(), 'Factura-' . str_replace('/', '-', $ref) . '.pdf'];
        }

        if (!$row->file_path || !Storage::disk('local')->exists($row->file_path)) {
            return null;
        }

        return [Storage::disk('local')->get($row->file_path), $row->file_name ?: 'factura.pdf'];
    }

    public function sendToCustomer(OrderCompanyInvoice $row): void
    {
        $order = $row->order;
        $email = $order?->customer_email;
        if (!$order || !$email) {
            return;
        }

        $doc = $this->documentFor($row);
        if (!$doc) {
            throw new \RuntimeException('PDF-ul facturii nu este încă disponibil.');
        }
        [$content, $fileName] = $doc;

        $client = $row->marketplaceClient;
        $company = $row->company ?? [];
        $issuerName = $row->isMarketplace()
            ? ($client?->company_name ?: $client?->name)
            : ($row->organizer?->company_name ?: $row->organizer?->name);
        $what = $row->isMarketplace() ? 'comisionul și serviciile platformei' : 'biletele cumpărate';

        $html = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#222;line-height:1.5;">'
            . '<p>Bună ziua,</p>'
            . '<p>Atașat găsiți factura emisă de <strong>' . e($issuerName) . '</strong> pe numele <strong>' . e($company['name'] ?? '') . '</strong>'
            . ' pentru ' . $what . ', comanda <strong>' . e($order->order_number) . '</strong>.</p>'
            . '<p>Factura este disponibilă și în contul dumneavoastră, la detaliile comenzii.</p>'
            . '<p>Cu respect,<br><strong>' . e($client?->name ?? '') . '</strong></p>'
            . '</div>';

        $this->sendEmail($client, $email, $order->customer_name, 'Factura pentru comanda ' . $order->order_number, $html, $content, $fileName, [
            'order_id' => $order->id,
            'marketplace_customer_id' => $order->marketplace_customer_id,
            'template_slug' => 'invoice_send',
        ]);

        $row->update(['sent_to_customer_at' => now()]);
    }

    protected function sendEmail(?MarketplaceClient $client, string $to, ?string $toName, string $subject, string $html, ?string $attachment, ?string $attachmentName, array $log): void
    {
        if (!$client || (!$client->hasMailConfigured() && !$client->hasTransactionalMailConfigured())) {
            throw new \RuntimeException('Mail-ul marketplace-ului nu este configurat.');
        }

        $fromAddress = $client->getTransactionalEmailFromAddress();
        $fromName = $client->getTransactionalEmailFromName();
        $email = (new \Symfony\Component\Mime\Email())
            ->from(new \Symfony\Component\Mime\Address($fromAddress, $fromName))
            ->to($to)
            ->subject($subject)
            ->html($html);
        if ($attachment !== null) {
            $email->attach($attachment, $attachmentName ?: 'factura.pdf', 'application/pdf');
        }

        $result = $client->sendTransactionalEmail($email);
        if (empty($result['success'])) {
            throw new \RuntimeException('Emailul nu a plecat: ' . ($result['error'] ?? 'eroare necunoscută'));
        }

        try {
            MarketplaceEmailLog::create(array_merge([
                'marketplace_client_id' => $client->id,
                'from_email' => $fromAddress,
                'from_name' => $fromName,
                'to_email' => $to,
                'to_name' => $toName ?: $to,
                'subject' => $subject,
                'body_html' => $html,
                'status' => 'sent',
                'sent_at' => now(),
                'message_id' => $result['message_id'] ?? null,
                'metadata' => ['transport_used' => $result['transport_used'] ?? null, 'attachment' => $attachmentName],
            ], $log));
        } catch (\Throwable $e) {
            // logul nu trebuie să blocheze trimiterea
        }
    }

    public function siteUrl(?MarketplaceClient $client): string
    {
        $domain = trim((string) ($client?->domain ?? ''), '/ ');
        if ($domain === '') {
            return '';
        }

        return str_starts_with($domain, 'http') ? $domain : 'https://' . $domain;
    }

    /** Ce vede clientul la detaliile comenzii. Nu aruncă. */
    public function customerView(Order $order): array
    {
        try {
            if (!$this->isEnabled($order->marketplaceClient) || $order->source !== 'marketplace') {
                return ['enabled' => false];
            }

            $rows = OrderCompanyInvoice::with('organizer:id,name,company_name')->where('order_id', $order->id)->get();
            $reason = $rows->isEmpty() ? $this->ineligibleReason($order) : null;
            $saved = (array) data_get(MarketplaceCustomer::find($order->marketplace_customer_id)?->settings, 'company_billing', []);
            $client = $order->marketplaceClient;

            return [
                'enabled' => true,
                'can_request' => $rows->isEmpty() && $reason === null,
                'reason' => $rows->isEmpty() ? $reason : null,
                'company' => $order->meta['company_billing'] ?? null,
                'saved_company' => $saved ?: null,
                'invoices' => $rows->map(fn (OrderCompanyInvoice $r) => [
                    'id' => $r->id,
                    'issuer' => $r->issuer,
                    'issuer_name' => $r->isMarketplace()
                        ? ($client?->company_name ?: $client?->name)
                        : ($r->organizer?->company_name ?: $r->organizer?->name),
                    'subject' => $r->isMarketplace() ? 'Comision și servicii' : 'Bilete',
                    'status' => $r->status,
                    'status_label' => $r->isMarketplace() || $r->status !== OrderCompanyInvoice::STATUS_REQUESTED
                        ? $r->statusLabel()
                        : 'În așteptare de la organizator',
                    'amount' => (float) $r->amount,
                    'currency' => $r->currency,
                    'has_document' => $r->hasDocument(),
                ])->values()->all(),
            ];
        } catch (\Throwable $e) {
            Log::warning('[CompanyInvoice] customerView failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);

            return ['enabled' => false];
        }
    }

    // ------------------------------------------------------------------
    // Decont: comisionul facturat deja pe firmă iese din „Client Divers”
    // ------------------------------------------------------------------

    /**
     * Comisionul peste preț (NET) al comenzilor din perioada decontului care au
     * deja factură pe firmă de la marketplace, pe tip de bilet.
     *
     * @return array<int, array{ticket_type_name: string, qty: int, commission_amount: float}>
     */
    public function invoicedOnTopRowsForPayout(MarketplacePayout $payout): array
    {
        if (!$payout->event_id || !$this->isEnabled($payout->marketplaceClient)) {
            return [];
        }
        if (!\Illuminate\Support\Facades\Schema::hasTable('order_company_invoices')) {
            return [];
        }

        $orders = Order::query()
            ->where('marketplace_client_id', $payout->marketplace_client_id)
            ->whereIn('id', OrderCompanyInvoice::where('issuer', OrderCompanyInvoice::ISSUER_MARKETPLACE)
                ->whereIn('status', [OrderCompanyInvoice::STATUS_PENDING, OrderCompanyInvoice::STATUS_ISSUED])
                ->select('order_id'))
            ->where(fn ($q) => $q->where('event_id', $payout->event_id)
                ->orWhereRaw("(meta->'event_ids')::jsonb @> ?::jsonb", [json_encode([(int) $payout->event_id])]))
            ->when($payout->period_start, fn ($q) => $q->where('paid_at', '>=', $payout->period_start->copy()->startOfDay()))
            ->when($payout->period_end, fn ($q) => $q->where('paid_at', '<=', $payout->period_end->copy()->endOfDay()))
            ->get(['id', 'meta']);

        $rows = [];
        foreach ($orders as $order) {
            foreach ((array) ($order->meta['commission_details'] ?? []) as $d) {
                if (!in_array($d['commission_mode'] ?? '', ['on_top', 'added_on_top', 'add_on_top'], true)) {
                    continue;
                }
                $name = (string) ($d['ticket_type'] ?? '');
                $rows[$name]['ticket_type_name'] = $name;
                $rows[$name]['qty'] = ($rows[$name]['qty'] ?? 0) + (int) ($d['quantity'] ?? 0);
                $rows[$name]['commission_amount'] = round(($rows[$name]['commission_amount'] ?? 0) + (float) ($d['commission_amount'] ?? 0), 2);
            }
        }

        return array_values($rows);
    }
}
