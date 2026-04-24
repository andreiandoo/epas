<?php

namespace App\Http\Controllers\Api\MarketplaceClient\VenueOwner;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\VenueOwnerNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends BaseController
{
    /**
     * Step 1 (authenticated): request an export. Either returns a short-lived
     * signed download URL the mobile can open in the system browser, or sends
     * the CSV by email.
     */
    public function request(Request $request, int $event): JsonResponse
    {
        /** @var Event|null $eventModel */
        $eventModel = $request->attributes->get('venue_owner_event');
        if (!$eventModel instanceof Event) {
            $eventModel = Event::find($event);
        }
        if (!$eventModel) {
            return $this->error('Event not found', 404);
        }

        $validated = $request->validate([
            'destination' => 'required|in:download,email',
            'email' => 'required_if:destination,email|email|max:255',
        ]);

        if ($validated['destination'] === 'download') {
            $url = URL::temporarySignedRoute(
                'api.marketplace-client.venue-owner.events.export.download',
                now()->addMinutes(30),
                ['event' => $eventModel->id]
            );

            return $this->success([
                'download_url' => $url,
                'filename' => $this->filenameFor($eventModel),
                'expires_in_seconds' => 30 * 60,
            ]);
        }

        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('venue_owner_tenant');
        if (!$tenant instanceof Tenant) {
            return $this->error('Venue owner tenant not resolved', 500);
        }

        $csv = $this->generateCsv($eventModel, $tenant);
        $filename = $this->filenameFor($eventModel);
        $client = $this->requireClient($request);

        $this->sendCsvEmail($client, $validated['email'], $eventModel, $csv, $filename);

        return $this->success(null, 'Exportul a fost trimis la ' . $validated['email']);
    }

    /**
     * Step 2 (public via signed URL): streams the CSV as a download. The URL
     * itself is the authentication — it's HMAC-signed, short-lived, and
     * unguessable. Access to the venue-owner endpoint that generates the URL
     * is what gates the export; anyone with the link in the next 30 minutes
     * can open it.
     */
    public function download(Request $request, int $event): StreamedResponse
    {
        /** @var Event|null $eventModel */
        $eventModel = Event::with('venue:id,tenant_id,name')->find($event);
        if (!$eventModel) {
            abort(404, 'Event not found');
        }

        $tenantId = $eventModel->venue?->tenant_id;
        if (!$tenantId) {
            abort(404, 'Venue not found');
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            abort(404, 'Tenant not found');
        }

        $csv = $this->generateCsv($eventModel, $tenant);
        $filename = $this->filenameFor($eventModel);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    // =========================================
    // Helpers
    // =========================================

    protected function filenameFor(Event $event): string
    {
        $slug = Str::slug((string) ($event->getTranslation('title') ?? $event->slug ?? ('event-' . $event->id)));
        return 'bilete-' . ($slug ?: ('event-' . $event->id)) . '-' . now()->format('Ymd-His') . '.csv';
    }

    /**
     * Build the CSV body (UTF-8 with BOM so Excel opens it correctly). Only
     * valid/used tickets on paid/confirmed/completed orders are included.
     * Notes concatenate ticket + order + customer-level notes for that ticket.
     */
    protected function generateCsv(Event $event, Tenant $tenant): string
    {
        $tickets = Ticket::where('event_id', $event->id)
            ->whereIn('status', ['valid', 'used'])
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['paid', 'confirmed', 'completed']))
            ->with([
                'order:id,order_number,customer_name,customer_phone,customer_id,marketplace_customer_id,paid_at,created_at,status',
                'order.customer:id,first_name,last_name,phone',
                'order.marketplaceCustomer:id,first_name,last_name,phone',
                'ticketType:id,name',
            ])
            ->orderBy('id')
            ->get();

        $notesMap = $this->buildNotesMap($tenant->id, $tickets);

        $lines = [];
        $lines[] = $this->csvRow([
            'ID Comandă',
            'ID Bilet',
            'Tip Bilet',
            'Nume',
            'Prenume',
            'Telefon',
            'Data Comandă',
            'Mențiuni',
        ]);

        foreach ($tickets as $ticket) {
            $order = $ticket->order;
            $customer = $this->resolveCustomer($ticket);

            $placedAt = $order?->paid_at ?? $order?->created_at;
            $placedStr = $placedAt ? $placedAt->format('Y-m-d H:i') : '';

            $notes = $notesMap->get($ticket->id, collect())
                ->map(fn ($n) => $n->note)
                ->implode(" | ");

            $lines[] = $this->csvRow([
                $order?->order_number ?? '',
                (string) $ticket->id,
                $ticket->ticketType?->name ?? '',
                $customer['first_name'] ?? '',
                $customer['last_name'] ?? '',
                $customer['phone'] ?? '',
                $placedStr,
                $notes,
            ]);
        }

        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    protected function resolveCustomer(Ticket $ticket): array
    {
        $order = $ticket->order;
        if (!$order) {
            return ['first_name' => '', 'last_name' => '', 'phone' => ''];
        }

        if ($order->marketplaceCustomer) {
            $c = $order->marketplaceCustomer;
            return [
                'first_name' => $c->first_name ?? '',
                'last_name' => $c->last_name ?? '',
                'phone' => $c->phone ?? $order->customer_phone ?? '',
            ];
        }
        if ($order->customer) {
            $c = $order->customer;
            return [
                'first_name' => $c->first_name ?? '',
                'last_name' => $c->last_name ?? '',
                'phone' => $c->phone ?? $order->customer_phone ?? '',
            ];
        }

        $parts = preg_split('/\s+/', trim((string) $order->customer_name), 2);
        return [
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? '',
            'phone' => $order->customer_phone ?? '',
        ];
    }

    /**
     * Return a map ticket_id => Collection of notes that apply to that ticket
     * (direct ticket notes + order notes + customer notes).
     */
    protected function buildNotesMap(int $tenantId, $tickets): \Illuminate\Support\Collection
    {
        if ($tickets->isEmpty()) {
            return collect();
        }

        $ticketIds = $tickets->pluck('id')->filter()->unique()->values();
        $orderIds = $tickets->pluck('order_id')->filter()->unique()->values();
        $customerIds = $tickets->map(function ($t) {
            return $t->order?->marketplace_customer_id ?? $t->order?->customer_id ?? null;
        })->filter()->unique()->values();

        $ticketNotes = VenueOwnerNote::where('tenant_id', $tenantId)
            ->where('target_type', VenueOwnerNote::TARGET_TICKET)
            ->whereIn('target_id', $ticketIds)
            ->get()
            ->groupBy('target_id');

        $orderNotes = $orderIds->isEmpty() ? collect() : VenueOwnerNote::where('tenant_id', $tenantId)
            ->where('target_type', VenueOwnerNote::TARGET_ORDER)
            ->whereIn('target_id', $orderIds)
            ->get()
            ->groupBy('target_id');

        $customerNotes = $customerIds->isEmpty() ? collect() : VenueOwnerNote::where('tenant_id', $tenantId)
            ->where('target_type', VenueOwnerNote::TARGET_CUSTOMER)
            ->whereIn('target_id', $customerIds)
            ->get()
            ->groupBy('target_id');

        return $tickets->mapWithKeys(function ($t) use ($ticketNotes, $orderNotes, $customerNotes) {
            $cid = $t->order?->marketplace_customer_id ?? $t->order?->customer_id ?? null;
            $notes = collect()
                ->merge($ticketNotes->get($t->id, collect()))
                ->merge($t->order_id ? $orderNotes->get($t->order_id, collect()) : collect())
                ->merge($cid ? $customerNotes->get($cid, collect()) : collect());
            return [$t->id => $notes];
        });
    }

    protected function csvRow(array $cells): string
    {
        return implode(',', array_map(function ($v) {
            $s = (string) ($v ?? '');
            $needsQuoting = str_contains($s, ',') || str_contains($s, '"') || str_contains($s, "\n") || str_contains($s, "\r");
            $s = str_replace('"', '""', $s);
            return $needsQuoting ? '"' . $s . '"' : $s;
        }, $cells));
    }

    protected function sendCsvEmail(MarketplaceClient $client, string $to, Event $event, string $csv, string $filename): void
    {
        $eventTitle = (string) ($event->getTranslation('title') ?? 'Eveniment');
        $subject = 'Export bilete — ' . $eventTitle;

        $html = '<p>Salut,</p>' .
                '<p>Găsești atașat exportul biletelor valide pentru evenimentul <strong>' . e($eventTitle) . '</strong>.</p>' .
                '<p>Fișierul <code>' . e($filename) . '</code> e codificat UTF-8 și se deschide direct în Excel / Google Sheets.</p>' .
                '<p>—<br>Tixello / ' . e($client->name ?? 'Venue Owner') . '</p>';

        $fromAddress = $client->getEmailFromAddress() ?: config('mail.from.address');
        $fromName = $client->getEmailFromName() ?: config('mail.from.name');

        try {
            Mail::html($html, function ($message) use ($to, $subject, $fromAddress, $fromName, $csv, $filename) {
                $message->from($fromAddress, $fromName)
                    ->to($to)
                    ->subject($subject)
                    ->attachData($csv, $filename, ['mime' => 'text/csv']);
            });

            Log::channel('marketplace')->info('Venue owner CSV export emailed', [
                'marketplace_client_id' => $client->id,
                'event_id' => $event->id,
                'to' => $to,
            ]);
        } catch (\Throwable $e) {
            Log::channel('marketplace')->error('Failed to email venue owner CSV export', [
                'marketplace_client_id' => $client->id,
                'event_id' => $event->id,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
