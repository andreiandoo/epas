<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerToken;
use App\Models\Event;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketTemplate;
use App\Services\TicketCustomizer\TicketPreviewGenerator;
use App\Services\TicketCustomizer\TicketVariableService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        try {
            $pdf = $this->renderWithTemplate($tenant, $event, $tickets);
        } catch (\Throwable $e) {
            Log::warning('Storefront ticket PDF: template render failed, using the plain ticket', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            $pdf = null;
        }
        $pdf ??= $this->renderPlain($tenant, $event, $order, $tickets);

        return $pdf->download($name . '.pdf');
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
            'event'         => $ev ? [
                'title'      => $ev->getTranslation('title', 'ro') ?: $ev->getTranslation('title', 'en'),
                'slug'       => $ev->slug,
                'start_date' => $ev->start_date?->toIso8601String(),
                'end_date'   => $ev->end_date?->toIso8601String(),
                'venue'      => $ev->venue?->getTranslation('name', 'ro'),
                'city'       => $ev->venue?->city,
            ] : null,
            // Doar comenzile plătite au bilete de descărcat
            'access_token'  => $paid ? self::orderToken($order) : null,
        ];
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
