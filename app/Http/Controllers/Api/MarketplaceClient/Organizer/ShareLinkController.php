<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceShareLink;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Organizer share-monitoring links — DB-backed replacement for the
 * proxy.php file storage that kept disappearing across deploys.
 * Endpoints all scoped to the authenticated organizer.
 */
class ShareLinkController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $links = MarketplaceShareLink::where('marketplace_organizer_id', $organizer->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->success([
            'links' => $links->map(fn ($l) => $this->formatLink($l))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $validated = $request->validate([
            'event_ids' => 'required|array|min:1|max:20',
            'event_ids.*' => 'integer',
            'name' => 'nullable|string|max:100',
            'password' => 'nullable|string|max:100',
            'show_participants' => 'nullable|boolean',
            'show_revenue' => 'nullable|boolean',
        ]);

        $count = MarketplaceShareLink::where('marketplace_organizer_id', $organizer->id)->count();
        if ($count >= 50) {
            return $this->error('Maximum 50 share links per organizer', 400);
        }

        $eventIds = collect($validated['event_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($eventIds)) {
            return $this->error('Invalid event_ids', 400);
        }

        $code = $this->generateUniqueCode();
        $password = trim((string) ($validated['password'] ?? ''));
        $name = trim(strip_tags($validated['name'] ?? ''));
        if ($name === '') $name = 'Link #' . ($count + 1);

        $showRevenue = !empty($validated['show_revenue']);
        $showParticipants = !empty($validated['show_participants']);

        $link = MarketplaceShareLink::create([
            'code' => $code,
            'marketplace_client_id' => $organizer->marketplace_client_id,
            'marketplace_organizer_id' => $organizer->id,
            'name' => mb_substr($name, 0, 100),
            'event_ids' => $eventIds,
            'is_active' => true,
            'has_password' => $password !== '',
            'password_hash' => $password !== '' ? password_hash($password, PASSWORD_BCRYPT) : null,
            'show_participants' => $showParticipants,
            'show_revenue' => $showRevenue,
            'ticket_data' => $this->fetchTicketTotals($organizer, $eventIds),
            'participants_data' => $showParticipants ? $this->fetchParticipants($organizer, $eventIds) : null,
            'ticket_data_updated_at' => now(),
        ]);

        return $this->success([
            'data' => $this->formatLink($link),
            'url' => $this->buildPublicUrl($organizer, $code),
        ], 'Link creat', 201);
    }

    public function show(Request $request, string $code): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $link = MarketplaceShareLink::where('code', $code)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$link) return $this->error('Share link not found', 404);

        return $this->success(['data' => $this->formatLink($link)]);
    }

    public function update(Request $request, string $code): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $link = MarketplaceShareLink::where('code', $code)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$link) return $this->error('Share link not found', 404);

        $input = $request->all();
        $dirty = false;

        if (isset($input['name'])) {
            $name = trim(strip_tags((string) $input['name']));
            $link->name = mb_substr($name, 0, 100);
            $dirty = true;
        }
        if (isset($input['event_ids'])) {
            $eventIds = collect((array) $input['event_ids'])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
            if (!empty($eventIds) && count($eventIds) <= 20) {
                $link->event_ids = $eventIds;
                $link->ticket_data = $this->fetchTicketTotals($organizer, $eventIds);
                $link->ticket_data_updated_at = now();
                $dirty = true;
            }
        }
        if (isset($input['is_active'])) {
            $link->is_active = (bool) $input['is_active'];
            $dirty = true;
        }
        if (array_key_exists('password', $input)) {
            $pw = trim((string) ($input['password'] ?? ''));
            if ($pw === '') {
                $link->password_hash = null;
                $link->has_password = false;
            } else {
                $link->password_hash = password_hash($pw, PASSWORD_BCRYPT);
                $link->has_password = true;
            }
            $dirty = true;
        }
        if (isset($input['show_participants'])) {
            $link->show_participants = (bool) $input['show_participants'];
            if ($link->show_participants) {
                $link->participants_data = $this->fetchParticipants($organizer, $link->event_ids ?? []);
            }
            $dirty = true;
        }
        if (isset($input['show_revenue'])) {
            $link->show_revenue = (bool) $input['show_revenue'];
            $dirty = true;
        }
        if (!empty($input['refresh_data'])) {
            $link->ticket_data = $this->fetchTicketTotals($organizer, $link->event_ids ?? []);
            if ($link->show_participants) {
                $link->participants_data = $this->fetchParticipants($organizer, $link->event_ids ?? []);
            }
            $link->ticket_data_updated_at = now();
            $dirty = true;
        }

        if ($dirty) {
            $link->save();
        }

        return $this->success(['data' => $this->formatLink($link)]);
    }

    public function destroy(Request $request, string $code): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $link = MarketplaceShareLink::where('code', $code)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$link) return $this->error('Share link not found', 404);

        $link->delete();

        return $this->success(null, 'Share link deleted');
    }

    protected function generateUniqueCode(): string
    {
        do {
            $code = $this->randomCode(10);
        } while (MarketplaceShareLink::where('code', $code)->exists());

        return $code;
    }

    protected function randomCode(int $length = 10): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }

    /**
     * Cached per-event ticket totals (sold/total/revenue) so the public
     * view doesn't refetch on every load. Computed at create/update/refresh.
     */
    protected function fetchTicketTotals(MarketplaceOrganizer $organizer, array $eventIds): array
    {
        return MarketplaceShareLink::computeFreshTicketStats($eventIds, $organizer->id);
    }

    /**
     * Cached per-event participants (name + phone + ticket type) for the
     * share view. Only loaded when show_participants=true.
     */
    protected function fetchParticipants(MarketplaceOrganizer $organizer, array $eventIds): array
    {
        $validStatuses = ['paid', 'confirmed', 'completed'];
        $byEvent = [];

        $tickets = Ticket::whereIn('event_id', $eventIds)
            ->whereHas('order', fn ($q) => $q->whereIn('status', $validStatuses))
            ->whereIn('status', ['valid', 'used'])
            ->with(['order.marketplaceCustomer', 'ticketType'])
            ->get();

        foreach ($tickets as $t) {
            $eid = (string) $t->event_id;
            $customer = $t->order?->marketplaceCustomer;
            $name = $customer
                ? trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''))
                : ($t->order?->customer_name ?? 'N/A');
            $phone = $customer?->phone ?? $t->order?->customer_phone ?? '';
            $byEvent[$eid][] = [
                'name' => $name ?: 'N/A',
                'phone' => $phone,
                'ticket_type' => $t->ticketType?->name ?? '',
                'seat_label' => $t->seat_label ?? null,
            ];
        }

        return $byEvent;
    }

    protected function buildPublicUrl(MarketplaceOrganizer $organizer, string $code): string
    {
        $client = $organizer->marketplaceClient;
        $domain = $client?->domain ?: parse_url(config('app.url'), PHP_URL_HOST);
        $base = (str_starts_with((string) $domain, 'http') ? $domain : 'https://' . $domain);
        return rtrim($base, '/') . '/view/' . $code;
    }

    protected function formatLink(MarketplaceShareLink $link): array
    {
        return [
            'code' => $link->code,
            'organizer_id' => $link->marketplace_organizer_id,
            'name' => $link->name,
            'event_ids' => $link->event_ids ?? [],
            'is_active' => (bool) $link->is_active,
            'created_at' => $link->created_at?->toIso8601String(),
            'access_count' => (int) $link->access_count,
            'last_accessed_at' => $link->last_accessed_at?->toIso8601String(),
            'has_password' => (bool) $link->has_password,
            'show_participants' => (bool) $link->show_participants,
            'show_revenue' => (bool) $link->show_revenue,
            'ticket_data_updated_at' => $link->ticket_data_updated_at?->toIso8601String(),
        ];
    }

    protected function requireOrganizer(Request $request): MarketplaceOrganizer
    {
        $organizer = $request->user();
        if (!$organizer instanceof MarketplaceOrganizer) {
            abort(401, 'Unauthorized');
        }
        return $organizer;
    }
}
