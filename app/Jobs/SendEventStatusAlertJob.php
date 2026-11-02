<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceRefundRequest;
use App\Models\Order;
use App\Models\Ticket;
use App\Services\EventStatsCache;
use App\Services\Marketplace\SalesBreakdownService;
use App\Services\MarketplaceEmailService;
use App\Support\TestPos;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Anunta adresa Contact email din setarile marketplace-ului cand un organizator
 * isi anuleaza sau isi amana evenimentul, cu situatia evenimentului in acel moment.
 */
class SendEventStatusAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const ACTION_CANCELLED = 'cancelled';
    public const ACTION_POSTPONED = 'postponed';

    private const TIMEZONE = 'Europe/Bucharest';

    public int $tries = 3;
    public int $backoff = 60;

    /**
     * @param  array{organizer: string, organizer_email: ?string, organizer_phone: ?string, member_name: ?string, member_email: ?string}  $actor
     */
    public function __construct(
        protected int $eventId,
        protected string $action,
        protected array $actor,
        protected string $occurredAt,
        protected int $cancelledPendingOrders = 0
    ) {}

    public function handle(): void
    {
        $event = Event::with(['marketplaceClient', 'venue'])->find($this->eventId);
        $marketplace = $event?->marketplaceClient;
        if (!$marketplace) {
            return;
        }

        $recipient = trim((string) $marketplace->contact_email);
        if ($recipient === '') {
            Log::warning('Event status alert skipped — marketplace has no contact email', [
                'event_id' => $event->id,
                'marketplace_id' => $marketplace->id,
            ]);
            return;
        }

        $cancelled = $this->action === self::ACTION_CANCELLED;
        $occurredAt = Carbon::parse($this->occurredAt)->setTimezone(self::TIMEZONE);
        $title = $this->eventTitle($event);
        $currency = $event->currency ?: ($marketplace->currency ?: 'RON');

        $html = view('emails.alerts.event-status-change', [
            'cancelled' => $cancelled,
            'marketplaceName' => $marketplace->name,
            'title' => $title,
            'eventId' => $event->id,
            'actor' => $this->actor,
            'occurredAt' => $occurredAt->format('d.m.Y, H:i'),
            'reason' => $this->reason($event, $cancelled),
            'startDate' => $this->startDate($event)?->format('d.m.Y'),
            'startTime' => $event->start_time ?: $event->range_start_time,
            'venue' => $this->venueName($event),
            'isPublished' => (bool) $event->is_published,
            'postponedTo' => $cancelled ? null : $this->postponedTo($event),
            'daysLabel' => $this->daysLabel($event, $occurredAt),
            'stats' => $this->stats($event, $currency),
            'eventUrl' => route('filament.marketplace.resources.events.edit', ['record' => $event->id]),
        ])->render();

        (new MarketplaceEmailService($marketplace))->sendAdminNotification(
            slug: $cancelled ? 'admin_event_cancelled' : 'admin_event_postponed',
            settingKey: 'contact_email',
            variables: [
                'event_name' => $title,
                'organizer_name' => $this->actor['organizer'] ?? '',
            ],
            fallbackSubject: ($cancelled ? 'Eveniment anulat' : 'Eveniment amânat') . ': {{event_name}} ({{organizer_name}})',
            fallbackHtml: $html,
            recipient: $recipient,
        );
    }

    /** Cifrele sunt citite direct din baza de date, nu din cache-ul de 60s. */
    protected function stats(Event $event, string $currency): array
    {
        $tickets = EventStatsCache::compute($event->id);
        $sold = (int) ($tickets['total_tickets_sold'] ?? 0);
        $capacity = (int) $event->total_capacity;

        $orders = fn () => TestPos::excludeOrders(Order::where('event_id', $event->id), 'orders.source');

        $refundedOrders = $orders()
            ->where(function ($q) {
                $q->whereIn('status', ['refunded', 'partially_refunded'])
                    ->orWhere('refunded_amount', '>', 0);
            })
            ->get(['id', 'status', 'total', 'refunded_amount']);

        $refundedAmount = $refundedOrders->sum(function (Order $order) {
            $amount = (float) $order->refunded_amount;

            // Comenzile vechi rambursate integral nu au refunded_amount completat
            return $amount > 0 ? $amount : ($order->status === 'refunded' ? (float) $order->total : 0);
        });

        $openRefunds = MarketplaceRefundRequest::whereIn('order_id', $orders()->select('id'))
            ->whereIn('status', [
                MarketplaceRefundRequest::STATUS_PENDING,
                MarketplaceRefundRequest::STATUS_UNDER_REVIEW,
                MarketplaceRefundRequest::STATUS_APPROVED,
                MarketplaceRefundRequest::STATUS_PROCESSING,
            ])
            ->get(['id', 'requested_amount']);

        return [
            'tickets_sold' => $sold,
            'tickets_paid' => (int) ($tickets['total_tickets_paid'] ?? 0),
            'invitations' => (int) ($tickets['total_invitations'] ?? 0),
            'tickets_used' => $this->ticketCount($event, 'used'),
            'capacity' => $capacity > 0 ? $capacity : null,
            'sold_percent' => $capacity > 0 ? round($sold / $capacity * 100, 1) : null,
            'paid_orders' => $orders()->whereIn('status', SalesBreakdownService::PAID_ORDER_STATUSES)->count(),
            'cancelled_pending_orders' => $this->cancelledPendingOrders,
            'revenue' => $this->money((float) ($tickets['total_revenue'] ?? 0), $currency),
            'refunded_orders' => $refundedOrders->count(),
            'refunded_tickets' => $this->ticketCount($event, 'refunded'),
            'refunded_amount' => $this->money($refundedAmount, $currency),
            'open_refunds' => $openRefunds->count(),
            'open_refunds_amount' => $this->money((float) $openRefunds->sum('requested_amount'), $currency),
            'paid_out_amount' => $this->money(
                (float) MarketplacePayout::where('event_id', $event->id)->where('status', 'completed')->sum('amount'),
                $currency
            ),
        ];
    }

    protected function ticketCount(Event $event, string $status): int
    {
        return TestPos::excludeTickets(
            Ticket::where('event_id', $event->id)->where('status', $status),
            'tickets.ticket_type_id'
        )->count();
    }

    /** starts_at nu are cast, deci data poate veni si ca text. */
    protected function startDate(Event $event): ?Carbon
    {
        $date = $event->start_date;

        return $date ? Carbon::parse($date) : null;
    }

    protected function daysLabel(Event $event, Carbon $occurredAt): string
    {
        $start = $this->startDate($event);
        if (!$start) {
            return 'data evenimentului nu este setată';
        }

        $days = (int) Carbon::parse($occurredAt->format('Y-m-d'))
            ->diffInDays(Carbon::parse($start->format('Y-m-d')), false);

        return match (true) {
            $days === 0 => 'evenimentul era programat astăzi',
            $days === 1 => '1 zi',
            $days > 1 => $days . ' zile',
            $days === -1 => 'data evenimentului a trecut de 1 zi',
            default => 'data evenimentului a trecut de ' . abs($days) . ' zile',
        };
    }

    protected function postponedTo(Event $event): string
    {
        if (!$event->postponed_date) {
            return 'nu a fost anunțată';
        }

        return trim($event->postponed_date->format('d.m.Y') . ' ' . substr((string) $event->postponed_start_time, 0, 5));
    }

    /** Motivul poate veni din editorul cu formatare, deci il trimitem ca text simplu. */
    protected function reason(Event $event, bool $cancelled): ?string
    {
        $reason = $cancelled ? $event->cancel_reason : $event->postponed_reason;
        if ($reason === 'Event cancelled by organizer') {
            return null;
        }

        $text = trim(html_entity_decode(strip_tags((string) $reason), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $text !== '' ? $text : null;
    }

    protected function eventTitle(Event $event): string
    {
        return $event->getTranslation('title', 'ro')
            ?: $event->getTranslation('title', 'en')
            ?: $event->getTranslation('title')
            ?: 'Eveniment #' . $event->id;
    }

    protected function venueName(Event $event): ?string
    {
        $name = $event->venue?->getTranslation('name', 'ro') ?: $event->venue?->getTranslation('name');

        return $name ?: $event->suggested_venue_name;
    }

    protected function money(float $amount, string $currency): string
    {
        return number_format($amount, 2, ',', '.') . ' ' . $currency;
    }
}
