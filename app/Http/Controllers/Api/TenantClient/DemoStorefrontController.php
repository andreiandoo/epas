<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerToken;
use App\Models\Domain;
use App\Models\Event;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketTemplate;
use App\Services\TicketCustomizer\TicketPreviewGenerator;
use App\Services\TenantMailService;
use App\Services\TicketCustomizer\TicketVariableService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contul de client și descărcarea biletelor pentru site-urile demo de tenant
 * (cele care cumpără prin DemoCheckoutController).
 *
 * Are adrese proprii (/tenant-client/storefront/*) pentru că /tenant-client/account/orders
 * și /account/tickets sunt declarate de două ori în routes/api.php, cu controllere și
 * răspunsuri diferite — un site nu poate ști care dintre ele îi răspunde.
 *
 * Biletele se descarcă pe baza unui token semnat al comenzii (HMAC cu cheia aplicației),
 * primit la plasarea comenzii sau din contul autentificat; ID-ul comenzii singur nu ajunge.
 */
class DemoStorefrontController extends Controller
{
    use ResolvesTenant;

    private const PAID = ['paid', 'confirmed', 'completed'];

    /** Tokenul care dă acces la biletele unei comenzi. */
    public static function orderToken(Order $order): string
    {
        return hash_hmac('sha256', 'storefront-order:' . $order->id . ':' . $order->tenant_id, (string) config('app.key'));
    }

    /** Comenzile clientului autentificat (după cont sau după adresa de email a comenzii). */
    public function orders(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$tenant, $customer] = $ctx;

        $orders = $this->ordersQuery($tenant, $customer)->withCount('tickets')->latest()->get();
        $events = Event::with('venue')
            ->whereIn('id', $orders->pluck('meta.event_id')->filter()->unique()->all())
            ->get()->keyBy('id');

        return response()->json([
            'success' => true,
            'data' => $orders->map(fn (Order $o) => $this->formatOrder($o, $events))->values(),
        ]);
    }

    /** O comandă cu biletele ei. */
    public function order(Request $request, int $id): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$tenant, $customer] = $ctx;

        $order = $this->ordersQuery($tenant, $customer)->withCount('tickets')->find($id);
        if (! $order) {
            return response()->json(['success' => false, 'error' => 'Comanda nu a fost găsită.'], 404);
        }

        $eventId = $order->meta['event_id'] ?? null;
        $events = $eventId ? Event::with('venue')->whereKey($eventId)->get()->keyBy('id') : collect();

        $tickets = $order->tickets()->with('ticketType')->get()->map(fn (Ticket $t) => [
            'code'       => $t->code,
            'type'       => $t->ticketType?->name,
            'seat_label' => $t->meta['seat_label'] ?? null,
            'status'     => $t->status,
        ])->values();

        return response()->json([
            'success' => true,
            'data' => $this->formatOrder($order, $events) + ['tickets' => $tickets],
        ]);
    }

    /** Toate biletele clientului din comenzile plătite, cu datele competiției și tokenul de descărcare. */
    public function tickets(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$tenant, $customer] = $ctx;

        $orders = $this->ordersQuery($tenant, $customer)->whereIn('status', self::PAID)->latest()->get()->keyBy('id');
        $events = Event::with('venue')
            ->whereIn('id', $orders->pluck('meta.event_id')->filter()->unique()->all())
            ->get()->keyBy('id');

        $tickets = Ticket::with('ticketType')->whereIn('order_id', $orders->keys())->orderByDesc('id')->get();

        $today = now()->toDateString();
        $data = $tickets->map(function (Ticket $t) use ($orders, $events, $today) {
            $order = $orders[$t->order_id];
            $ev = $events[$order->meta['event_id'] ?? 0] ?? null;
            $last = $ev ? ($ev->end_date ?: $ev->start_date) : null;

            return [
                'code'         => $t->code,
                'type'         => $t->ticketType?->name,
                'seat_label'   => $t->meta['seat_label'] ?? null,
                'status'       => $t->status,
                'order_id'     => $order->id,
                'access_token' => self::orderToken($order),
                'is_upcoming'  => $last ? $last->toDateString() >= $today : true,
                'event'        => $this->formatEvent($ev),
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Biletele unei comenzi plătite, ca PDF: ?order=ID&token=…[&code=COD pentru un singur bilet].
     * Folosește șablonul din Ticket Customizer al evenimentului sau al tenantului; fără șablon
     * (sau dacă randarea lui eșuează) cade pe un bilet simplu, ca descărcarea să nu rămână fără răspuns.
     */
    public function ticketsPdf(Request $request): Response
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['success' => false, 'error' => 'Tenant not found'], 404);
        }
        $tenant = $resolved['tenant'];

        $order = Order::where('tenant_id', $tenant->id)->find((int) $request->query('order'));
        $token = (string) $request->query('token', '');
        if (! $order || $token === '' || ! hash_equals(self::orderToken($order), $token)) {
            return response()->json(['success' => false, 'error' => 'Link de descărcare invalid.'], 403);
        }
        if (! in_array($order->status, self::PAID, true)) {
            return response()->json(['success' => false, 'error' => 'Comanda nu este plătită.'], 409);
        }

        $tickets = $order->tickets()->with(['ticketType.event.venue', 'order'])->get();
        if ($code = (string) $request->query('code', '')) {
            $tickets = $tickets->where('code', $code)->values();
        }
        if ($tickets->isEmpty()) {
            return response()->json(['success' => false, 'error' => 'Biletul nu a fost găsit.'], 404);
        }

        $event = $tickets->first()->ticketType?->event
            ?: (! empty($order->meta['event_id']) ? Event::with('venue')->find($order->meta['event_id']) : null);

        $name = $tickets->count() === 1 ? 'bilet-' . $tickets->first()->code : 'bilete-comanda-' . $order->id;

        return $this->buildPdf($tenant, $event, $order, $tickets)->download($name . '.pdf');
    }

    /** PDF-ul biletelor: șablonul din Ticket Customizer, cu biletul simplu ca rezervă. */
    private function buildPdf(Tenant $tenant, ?Event $event, Order $order, $tickets)
    {
        try {
            $pdf = $this->renderWithTemplate($tenant, $event, $tickets);
        } catch (\Throwable $e) {
            Log::warning('Storefront ticket PDF: template render failed, using the plain ticket', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            $pdf = null;
        }

        return $pdf ?? $this->renderPlain($tenant, $event, $order, $tickets);
    }

    /* ------------------------------------------------------------------ */
    /* Emailuri                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Emailul de confirmare cu biletele atașate, trimis o singură dată când comanda devine plătită.
     * Activ doar pentru tenanții cu settings.storefront.order_emails = true, ca celelalte site-uri
     * demo să rămână neschimbate. Nu aruncă niciodată: plata nu trebuie să depindă de email.
     */
    public static function sendOrderEmail(Order $order, bool $force = false): bool
    {
        try {
            $tenant = Tenant::find($order->tenant_id);
            $cfg = $tenant && is_array($tenant->settings) ? ($tenant->settings['storefront'] ?? []) : [];
            // $force = retrimitere cerută din panou: trece peste „deja trimis” și peste setarea de trimitere automată
            if (! $tenant || empty($order->customer_email) || (! $force && empty($cfg['order_emails']))) {
                return false;
            }
            $meta = $order->meta ?? [];
            if ((! $force && ! empty($meta['confirmation_email_sent_at'])) || ! in_array($order->status, self::PAID, true)) {
                return false;
            }

            $tickets = $order->tickets()->with(['ticketType.event.venue', 'order'])->get();
            if ($tickets->isEmpty()) {
                return false;
            }
            $event = $tickets->first()->ticketType?->event
                ?: (! empty($meta['event_id']) ? Event::with('venue')->find($meta['event_id']) : null);

            $self = app(self::class);
            $site = self::siteUrl($tenant);
            $e = fn ($v) => e((string) $v);

            $title = $event ? ($event->getTranslation('title', 'ro') ?: $event->getTranslation('title', 'en')) : 'Comanda ta';
            $when = '';
            try {
                $start = $event?->start_date;
                $end = $event?->end_date;
                if ($start) {
                    $when = $start->format('d.m.Y') . ($end && $end->toDateString() !== $start->toDateString() ? ' – ' . $end->format('d.m.Y') : '');
                }
            } catch (\Throwable $ex) {
                $when = '';
            }
            $where = implode(', ', array_filter([$event?->venue?->getTranslation('name', 'ro'), $event?->venue?->city]));

            $rows = '';
            foreach ($tickets as $t) {
                $rows .= '<tr><td style="padding:10px 0;border-bottom:1px solid #E3E9F5;font-size:15px;color:#0A0F33;">'
                    . $e($t->ticketType?->name ?: 'Bilet')
                    . (! empty($t->meta['seat_label']) ? '<br><span style="font-size:13px;color:#5B6488;">' . $e($t->meta['seat_label']) . '</span>' : '')
                    . '</td><td style="padding:10px 0;border-bottom:1px solid #E3E9F5;text-align:right;font-family:Consolas,monospace;font-size:14px;font-weight:bold;letter-spacing:1px;color:#0A0F33;">'
                    . $e($t->code) . '</td></tr>';
            }

            $download = $site ? $site . '/confirmare?order=' . $order->id : null;
            $pdfUrl = rtrim((string) config('app.url'), '/') . '/api/tenant-client/storefront/tickets.pdf?' . http_build_query([
                'hostname' => $site ? parse_url($site, PHP_URL_HOST) : null,
                'order'    => $order->id,
                'token'    => self::orderToken($order),
            ]);

            $first = trim((string) ($meta['customer_first_name'] ?? ''));
            $body = '<p style="margin:0 0 14px;font-size:16px;color:#0A0F33;">' . ($first !== '' ? $e($first) . ', ' : '') . 'plata a fost confirmată. '
                . ($tickets->count() === 1 ? 'Biletul tău este atașat' : 'Cele ' . $tickets->count() . ' bilete sunt atașate') . ' acestui email, în format PDF.</p>'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0;background:#F4F7FD;border-radius:12px;"><tr><td style="padding:18px 20px;">'
                . '<div style="font-size:12px;letter-spacing:1.5px;text-transform:uppercase;color:#1151D3;font-weight:bold;">Competiție</div>'
                . '<div style="margin-top:6px;font-size:20px;font-weight:bold;color:#0A0F33;">' . $e($title) . '</div>'
                . '<div style="margin-top:6px;font-size:14px;color:#5B6488;">' . $e(implode(' · ', array_filter([$when, $where]))) . '</div>'
                . '</td></tr></table>'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rows
                . '<tr><td style="padding:14px 0 0;font-size:15px;font-weight:bold;color:#0A0F33;">Total plătit</td>'
                . '<td style="padding:14px 0 0;text-align:right;font-size:18px;font-weight:bold;color:#0A0F33;">' . number_format(($order->total_cents ?? 0) / 100, 2, ',', '.') . ' lei</td></tr></table>'
                . '<p style="margin:22px 0 0;font-size:14px;color:#5B6488;">La intrare arăți codul QR de pe bilet, de pe telefon sau tipărit. Comanda are numărul #' . $e($order->id) . '.</p>';

            $html = self::mailLayout($tenant, 'Biletele tale sunt gata', $body, 'Descarcă biletele (PDF)', $pdfUrl,
                $download ? 'Le găsești oricând și în <a href="' . $e($site . '/biletele-mele') . '" style="color:#1151D3;">Biletele mele</a>, după autentificare.' : '');

            $pdf = $self->buildPdf($tenant, $event, $order, $tickets)->output();
            $fileName = $tickets->count() === 1 ? 'bilet-' . $tickets->first()->code . '.pdf' : 'bilete-comanda-' . $order->id . '.pdf';
            $tenantName = $tenant->public_name ?: $tenant->name;

            app(TenantMailService::class)->send($tenant, function ($message) use ($order, $html, $pdf, $fileName, $tenantName, $title) {
                $message->to($order->customer_email)
                    ->from(config('mail.from.address'), $tenantName)
                    ->subject('Biletele tale — ' . $title)
                    ->html($html)
                    ->attachData($pdf, $fileName, ['mime' => 'application/pdf']);
            });

            $meta['confirmation_email_sent_at'] = now()->toIso8601String();
            $order->forceFill(['meta' => $meta])->saveQuietly();

            return true;
        } catch (\Throwable $e) {
            Log::warning('Storefront order confirmation email failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Adresa PDF-ului cu biletele unei comenzi plătite (pentru panou și emailuri); null dacă tenantul nu are domeniu. */
    public static function ticketsPdfUrl(Order $order): ?string
    {
        $tenant = Tenant::find($order->tenant_id);
        $site = $tenant ? self::siteUrl($tenant) : null;
        if (! $site || ! in_array($order->status, self::PAID, true)) {
            return null;
        }

        return rtrim((string) config('app.url'), '/') . '/api/tenant-client/storefront/tickets.pdf?' . http_build_query([
            'hostname' => parse_url($site, PHP_URL_HOST),
            'order'    => $order->id,
            'token'    => self::orderToken($order),
        ]);
    }

    /** Adresa site-ului public al tenantului (domeniul primar activ). */
    private static function siteUrl(Tenant $tenant, ?int $domainId = null): ?string
    {
        $domain = ($domainId ? Domain::find($domainId) : null)
            ?: $tenant->domains()->where('is_active', true)->orderByDesc('is_primary')->first();

        return $domain ? 'https://' . $domain->domain : null;
    }

    /**
     * Cadrul comun al emailurilor: antet în culorile organizatorului, cu sigla lui, un buton și subsol.
     * Culorile și sigla vin din settings.storefront (brand_color, brand_dark, logo_url).
     */
    private static function mailLayout(Tenant $tenant, string $heading, string $bodyHtml, ?string $buttonLabel = null, ?string $buttonUrl = null, string $afterHtml = ''): string
    {
        $cfg = is_array($tenant->settings) ? ($tenant->settings['storefront'] ?? []) : [];
        $dark = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($cfg['brand_dark'] ?? '')) ? $cfg['brand_dark'] : '#0B1030';
        $accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($cfg['brand_color'] ?? '')) ? $cfg['brand_color'] : '#1151D3';
        $logo = filter_var($cfg['logo_url'] ?? '', FILTER_VALIDATE_URL) ? $cfg['logo_url'] : null;
        $name = e($tenant->public_name ?: $tenant->name);
        $site = self::siteUrl($tenant);

        $button = ($buttonLabel && $buttonUrl)
            ? '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:26px 0 6px;"><tr><td style="border-radius:10px;background:' . $accent . ';">'
              . '<a href="' . e($buttonUrl) . '" style="display:inline-block;padding:14px 26px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;">' . e($buttonLabel) . '</a></td></tr></table>'
            : '';

        return '<!DOCTYPE html><html lang="ro"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#EEF3FB;font-family:Arial,Helvetica,sans-serif;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EEF3FB;"><tr><td align="center" style="padding:28px 12px;">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;">'
            . '<tr><td style="background:' . $dark . ';padding:26px 30px;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0"><tr>'
            . ($logo ? '<td style="padding-right:14px;"><img src="' . e($logo) . '" width="52" height="52" alt="" style="display:block;border-radius:26px;background:#ffffff;"></td>' : '')
            . '<td style="font-size:15px;font-weight:bold;color:#ffffff;line-height:1.3;">' . $name . '</td></tr></table>'
            . '<div style="margin-top:22px;font-size:26px;font-weight:bold;color:#ffffff;line-height:1.2;">' . e($heading) . '</div>'
            . '</td></tr>'
            . '<tr><td style="height:4px;background:' . $accent . ';font-size:0;line-height:0;">&nbsp;</td></tr>'
            . '<tr><td style="padding:28px 30px 30px;">' . $bodyHtml . $button
            . ($afterHtml !== '' ? '<p style="margin:14px 0 0;font-size:14px;color:#5B6488;">' . $afterHtml . '</p>' : '')
            . '</td></tr>'
            . '<tr><td style="padding:18px 30px 24px;border-top:1px solid #E3E9F5;font-size:12px;color:#7A84A8;line-height:1.5;">'
            . $name . ($site ? ' · <a href="' . e($site) . '" style="color:#7A84A8;">' . e(parse_url($site, PHP_URL_HOST)) . '</a>' : '')
            . '<br>Bilete emise prin platforma Tixello.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    /**
     * Trimite pe email linkul de setare a parolei. Servește și la „am uitat parola”, și la
     * conturile fără parolă create de o comandă fără autentificare (altfel „cont nou” le refuză
     * ca existente și clientul rămâne fără nicio cale de a intra). Răspunsul e același indiferent
     * dacă adresa există, ca să nu se poată afla cine are cont.
     */
    public function passwordLink(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => 'required|email|max:255']);

        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['success' => false, 'error' => 'Tenant not found'], 404);
        }
        $tenant = $resolved['tenant'];

        $customer = Customer::where('tenant_id', $tenant->id)
            ->where('email', strtolower(trim($validated['email'])))
            ->first();
        if ($customer) {
            self::sendPasswordLink($tenant, $customer, $resolved['domain_id'] ?? null);
        }

        return response()->json(['success' => true]);
    }

    /** Setează parola pe baza linkului primit pe email: {c, e, s, password, password_confirmation}. */
    public function passwordSet(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'c'        => 'required|integer',
            'e'        => 'required|integer',
            's'        => 'required|string|size:64',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['success' => false, 'error' => 'Tenant not found'], 404);
        }

        $customer = Customer::where('tenant_id', $resolved['tenant']->id)->find($validated['c']);
        $expired = (int) $validated['e'] < time();
        if (! $customer || $expired || ! hash_equals(self::passwordSignature($customer, (int) $validated['e']), $validated['s'])) {
            return response()->json([
                'success' => false,
                'error'   => 'Linkul a expirat sau a fost deja folosit. Cere unul nou.',
            ], 422);
        }

        $customer->password = Hash::make($validated['password']);
        // Linkul a ajuns pe adresa lui: emailul e confirmat
        if (empty($customer->email_verified_at)) {
            $customer->email_verified_at = now();
        }
        $customer->save();

        return response()->json(['success' => true, 'data' => ['email' => $customer->email]]);
    }

    /** Linkul e legat de parola curentă: după ce parola se schimbă, nu mai e valabil. */
    private static function passwordSignature(Customer $customer, int $expires): string
    {
        return hash_hmac(
            'sha256',
            'storefront-password:' . $customer->id . ':' . $expires . ':' . sha1((string) $customer->password),
            (string) config('app.key')
        );
    }

    public static function sendPasswordLink(Tenant $tenant, Customer $customer, ?int $domainId = null): void
    {
        try {
            $domain = ($domainId ? Domain::find($domainId) : null)
                ?: $tenant->domains()->where('is_active', true)->orderByDesc('is_primary')->first();
            if (! $domain) {
                return;
            }

            $expires = time() + 2 * 3600;
            $url = 'https://' . $domain->domain . '/parola-noua?' . http_build_query([
                'c' => $customer->id,
                'e' => $expires,
                's' => self::passwordSignature($customer, $expires),
            ]);
            $tenantName = $tenant->public_name ?: $tenant->name;
            $first = trim((string) $customer->first_name);
            $body = '<p style="margin:0 0 14px;font-size:16px;color:#0A0F33;">' . ($first !== '' ? e($first) . ', ai' : 'Ai')
                . ' cerut setarea unei parole pentru contul tău. Apasă butonul de mai jos și alege parola.</p>'
                . '<p style="margin:0;font-size:14px;color:#5B6488;">Linkul este valabil 2 ore. Dacă nu ai cerut tu acest email, îl poți ignora: parola rămâne neschimbată.</p>';
            $html = self::mailLayout($tenant, 'Setează parola contului', $body, 'Setează parola', $url,
                'Dacă butonul nu merge, copiază în browser adresa:<br><span style="word-break:break-all;">' . e($url) . '</span>');

            app(TenantMailService::class)->send($tenant, function ($message) use ($customer, $tenantName, $html) {
                $message->to($customer->email)
                    ->from(config('mail.from.address'), $tenantName)
                    ->subject('Setează parola contului — ' . $tenantName)
                    ->html($html);
            });
        } catch (\Throwable $e) {
            Log::warning('Storefront password link failed', [
                'customer_id' => $customer->id,
                'tenant_id'   => $tenant->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /* ------------------------------------------------------------------ */

    /** PDF din șablonul Ticket Customizer; null dacă nu există un șablon utilizabil. */
    private function renderWithTemplate(Tenant $tenant, ?Event $event, $tickets)
    {
        $template = null;
        $eventTemplate = $event?->ticketTemplate;
        if ($eventTemplate && $eventTemplate->status === 'active' && ! empty($eventTemplate->template_data['layers'] ?? [])) {
            $template = $eventTemplate;
        } else {
            $template = TicketTemplate::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->orderByDesc('last_used_at')
                ->get()
                ->first(fn ($t) => ! empty($t->template_data['layers'] ?? []));
        }
        if (! $template) {
            return null;
        }

        $variables = app(TicketVariableService::class);
        $generator = app(TicketPreviewGenerator::class);

        $size = $template->getSize();
        $widthPt = round($size['width'] * 2.8346, 2);
        $heightPt = round($size['height'] * 2.8346, 2);
        $bg = $template->template_data['meta']['background']['color'] ?? '#ffffff';

        $pages = '';
        foreach ($tickets->values() as $i => $ticket) {
            $locale = $variables->resolveOrderLocale($ticket);
            $content = $generator->renderToHtml($template->template_data, $variables->resolveTicketData($ticket, $locale), $locale);
            if (trim($content) === '') {
                return null;
            }
            // Generatorul poziționează straturile cu position: fixed, iar DomPDF repetă elementele
            // fixe pe fiecare pagină — la mai multe bilete s-ar suprapune. Le ancorăm de pagina lor.
            $content = str_replace('position: fixed', 'position: absolute', $content);
            $break = $i > 0 ? 'page-break-before: always;' : '';
            $pages .= "<div class=\"ep-ticket-page\" style=\"{$break}\">{$content}</div>";
        }

        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>"
            . "@page{margin:0;size:{$widthPt}pt {$heightPt}pt;}*{margin:0;padding:0;}"
            . "body{margin:0;padding:0;background-color:{$bg};font-family:'DejaVu Sans',sans-serif;}"
            . ".ep-ticket-page{width:{$widthPt}pt;height:{$heightPt}pt;overflow:hidden;position:relative;}"
            . "</style></head><body>{$pages}</body></html>";

        $pdf = Pdf::loadHTML($html)
            ->setPaper([0, 0, $widthPt, $heightPt])
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        try {
            $template->markAsUsed();
        } catch (\Throwable $e) {
            // doar statistică
        }

        return $pdf;
    }

    /** Bilet simplu (fără șablon): un bilet pe pagină, cu cod QR. */
    private function renderPlain(Tenant $tenant, ?Event $event, Order $order, $tickets)
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $title = $event ? ($event->getTranslation('title', 'ro') ?: $event->getTranslation('title', 'en')) : 'Bilet';
        $venue = $event?->venue;
        $where = implode(', ', array_filter([$venue?->getTranslation('name', 'ro'), $venue?->city]));
        $when = '';
        try {
            $start = $event?->start_date;
            $end = $event?->end_date;
            if ($start) {
                $when = $start->format('d.m.Y');
                if ($end && $end->format('Y-m-d') !== $start->format('Y-m-d')) {
                    $when .= ' – ' . $end->format('d.m.Y');
                }
            }
        } catch (\Throwable $ex) {
            $when = '';
        }
        $organizer = $tenant->public_name ?: $tenant->name;

        $pages = '';
        foreach ($tickets->values() as $i => $ticket) {
            $qr = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=0&data=' . urlencode($ticket->getVerifyUrl());
            $seat = $ticket->meta['seat_label'] ?? null;
            $break = $i > 0 ? 'page-break-before: always;' : '';
            $pages .= '<div class="page" style="' . $break . '">'
                . '<div class="main">'
                . '<div class="org">' . $e($organizer) . '</div>'
                . '<div class="title">' . $e($title) . '</div>'
                . '<div class="meta">' . $e(implode('  ·  ', array_filter([$when, $where]))) . '</div>'
                . '<div class="type">' . $e($ticket->ticketType?->name ?: 'Bilet') . '</div>'
                . ($seat ? '<div class="seat">' . $e($seat) . '</div>' : '')
                . '<div class="foot">Comanda #' . $e($order->id) . '  ·  Ticketing by Tixello</div>'
                . '</div>'
                . '<div class="stub"><img src="' . $e($qr) . '" width="150" height="150"><div class="code">' . $e($ticket->code) . '</div></div>'
                . '</div>';
        }

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
            . '@page{margin:0;size:595pt 255pt;}*{margin:0;padding:0;}'
            . "body{font-family:'DejaVu Sans',sans-serif;color:#ffffff;}"
            . '.page{position:relative;width:595pt;height:255pt;background:#01012F;overflow:hidden;}'
            . '.main{position:absolute;left:28pt;top:26pt;width:360pt;}'
            . '.org{font-size:8pt;letter-spacing:1.5pt;text-transform:uppercase;color:#9DBBFF;}'
            . '.title{margin-top:10pt;font-size:21pt;font-weight:bold;line-height:1.1;text-transform:uppercase;}'
            . '.meta{margin-top:10pt;font-size:10pt;color:#DDE6FF;}'
            . '.type{margin-top:18pt;font-size:13pt;font-weight:bold;color:#4B86FF;}'
            . '.seat{margin-top:4pt;font-size:11pt;}'
            . '.foot{position:absolute;left:0;top:192pt;font-size:7.5pt;color:#8C97C4;}'
            . '.stub{position:absolute;left:410pt;top:0;width:185pt;height:255pt;background:#EEF3FB;color:#0A0F33;text-align:center;}'
            . '.stub img{margin-top:34pt;}'
            . '.code{margin-top:12pt;font-size:13pt;font-weight:bold;letter-spacing:2pt;}'
            . '</style></head><body>' . $pages . '</body></html>';

        return Pdf::loadHTML($html)
            ->setPaper([0, 0, 595, 255])
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);
    }

    private function formatOrder(Order $order, $events): array
    {
        $meta = $order->meta ?? [];
        $ev = ! empty($meta['event_id']) ? ($events[$meta['event_id']] ?? null) : null;
        $paid = in_array($order->status, self::PAID, true);

        return [
            'id'            => $order->id,
            'status'        => $order->status,
            'is_paid'       => $paid,
            'created_at'    => $order->created_at?->toIso8601String(),
            'total'         => ($order->total_cents ?? 0) / 100,
            'discount'      => ((int) ($meta['discount_cents'] ?? 0)) / 100,
            'promo_code'    => $meta['promo_code']['code'] ?? null,
            'tickets_count' => $order->tickets_count ?? 0,
            'subtotal'      => ((int) ($meta['subtotal_cents'] ?? ($order->total_cents ?? 0))) / 100,
            'processing_fee' => ((int) ($meta['processing_fee_cents'] ?? 0)) / 100,
            'payment_method' => 'Card',
            'event'         => $this->formatEvent($ev),
            // Doar comenzile plătite au bilete de descărcat
            'access_token'  => $paid ? self::orderToken($order) : null,
        ];
    }

    private function formatEvent(?Event $ev): ?array
    {
        return $ev ? [
            'title'      => $ev->getTranslation('title', 'ro') ?: $ev->getTranslation('title', 'en'),
            'slug'       => $ev->slug,
            'start_date' => $ev->start_date?->toIso8601String(),
            'end_date'   => $ev->end_date?->toIso8601String(),
            'venue'      => $ev->venue?->getTranslation('name', 'ro'),
            'city'       => $ev->venue?->city,
        ] : null;
    }

    /** @return array{0: Tenant, 1: Customer}|JsonResponse */
    private function ctx(Request $request): array|JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['success' => false, 'error' => 'Tenant not found'], 404);
        }

        $customer = null;
        if ($bearer = $request->bearerToken()) {
            $row = CustomerToken::where('token', hash('sha256', $bearer))->with('customer')->first();
            if ($row && ! (method_exists($row, 'isExpired') && $row->isExpired())) {
                $customer = $row->customer;
            }
        }
        if (! $customer) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
        }

        return [$resolved['tenant'], $customer];
    }

    private function ordersQuery(Tenant $tenant, Customer $customer)
    {
        return Order::where('tenant_id', $tenant->id)->where(function ($q) use ($customer) {
            $q->where('customer_id', $customer->id);
            if (! empty($customer->email)) {
                $q->orWhere('customer_email', $customer->email);
            }
        });
    }
}
