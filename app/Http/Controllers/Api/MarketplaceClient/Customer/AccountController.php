<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AccountController extends BaseController
{
    /**
     * Get customer's orders
     */
    public function orders(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        // Load both marketplace events and tenant events relationships
        $query = Order::where('marketplace_customer_id', $customer->id)
            ->with([
                'marketplaceEvent:id,name,slug,starts_at,venue_name,venue_city,image,marketplace_organizer_id,target_price',
                'marketplaceEvent.marketplaceOrganizer:id,default_commission_mode,commission_rate',
                'event:id,title,slug,event_date,featured_image,poster_url,commission_mode,commission_rate,marketplace_organizer_id,target_price,venue_id',
                'event.venue:id,name,city',
                'event.marketplaceOrganizer:id,default_commission_mode,commission_rate',
                'marketplaceClient:id,commission_mode',
                'tickets.ticketType:id,name,is_refundable',
                'tickets.marketplaceTicketType:id,name,is_refundable',
                'tickets:id,order_id,ticket_type_id,marketplace_ticket_type_id,price',
            ]);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('upcoming')) {
            $query->where(function ($q) {
                $q->whereHas('marketplaceEvent', function ($sub) {
                    $sub->where('starts_at', '>=', now());
                })->orWhereHas('event', function ($sub) {
                    $sub->where('event_date', '>=', now());
                });
            });
        }

        $query->orderByDesc('created_at');

        $perPage = min((int) $request->get('per_page', 20), 50);
        $orders = $query->paginate($perPage);

        return $this->paginated($orders, function ($order) {
            // Handle both marketplace events and tenant events
            $eventData = null;

            if ($order->marketplaceEvent) {
                $eventData = [
                    'id' => $order->marketplaceEvent->id,
                    'title' => $order->marketplaceEvent->name,
                    'name' => $order->marketplaceEvent->name,
                    'slug' => $order->marketplaceEvent->slug,
                    'date' => $order->marketplaceEvent->starts_at?->toIso8601String(),
                    'venue' => $order->marketplaceEvent->venue_name,
                    'city' => $order->marketplaceEvent->venue_city,
                    'image' => $order->marketplaceEvent->image_url,
                    'is_upcoming' => $order->marketplaceEvent->starts_at >= now(),
                ];
            } elseif ($order->event) {
                // Get full URL for featured image
                $imageUrl = null;
                if ($order->event->featured_image) {
                    $imageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($order->event->featured_image);
                } elseif ($order->event->poster_url) {
                    $imageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($order->event->poster_url);
                }

                $eventData = [
                    'id' => $order->event->id,
                    'title' => is_array($order->event->title)
                        ? ($order->event->title['ro'] ?? $order->event->title['en'] ?? reset($order->event->title))
                        : $order->event->title,
                    'name' => is_array($order->event->title)
                        ? ($order->event->title['ro'] ?? $order->event->title['en'] ?? reset($order->event->title))
                        : $order->event->title,
                    'slug' => $order->event->slug,
                    'date' => $order->event->event_date?->toIso8601String(),
                    'venue' => $order->event->venue?->name ?? null,
                    'city' => $order->event->venue?->city ?? null,
                    'image' => $imageUrl,
                    'featured_image' => $imageUrl,
                    'is_upcoming' => $order->event->event_date ? $order->event->event_date >= now() : false,
                ];
            }

            // Get commission mode from event or organizer
            $commissionMode = 'included';
            if ($order->event) {
                $commissionMode = $order->event->commission_mode
                    ?? $order->event->marketplaceOrganizer?->default_commission_mode
                    ?? $order->marketplaceClient?->commission_mode
                    ?? 'included';
            } elseif ($order->marketplaceEvent) {
                $commissionMode = $order->marketplaceEvent->marketplaceOrganizer?->default_commission_mode
                    ?? $order->marketplaceClient?->commission_mode
                    ?? 'included';
            }

            // Format payment method for display - check payment_processor first, then meta, then default
            $paymentMethod = null;
            if ($order->payment_processor) {
                $paymentMethod = match ($order->payment_processor) {
                    'netopia', 'payment-netopia' => 'Card bancar (Netopia)',
                    'stripe' => 'Card bancar (Stripe)',
                    'paypal' => 'PayPal',
                    'cash' => 'Numerar',
                    'bank_transfer' => 'Transfer bancar',
                    default => ucfirst(str_replace(['_', '-'], ' ', $order->payment_processor)),
                };
            } elseif (!empty($order->meta['payment_method'])) {
                $paymentMethod = $order->meta['payment_method'];
            } elseif ($order->status === 'paid' || $order->status === 'confirmed') {
                // If order is paid but no payment method recorded, default to Card
                $paymentMethod = 'Card';
            }

            // Get commission from EVENT (marketplace commission for organizer), not from Order
            $eventCommissionRate = 0;
            $eventCommissionAmount = 0;
            if ($order->event) {
                $eventCommissionRate = (float) ($order->event->commission_rate
                    ?? $order->event->marketplaceOrganizer?->commission_rate
                    ?? 0);
                // Calculate commission amount from tickets value
                $ticketsValue = $order->tickets->sum('price');
                $eventCommissionAmount = $ticketsValue * ($eventCommissionRate / 100);
            } elseif ($order->marketplaceEvent) {
                $eventCommissionRate = (float) ($order->marketplaceEvent->marketplaceOrganizer?->commission_rate ?? 0);
                $ticketsValue = $order->tickets->sum('price');
                $eventCommissionAmount = $ticketsValue * ($eventCommissionRate / 100);
            }

            // Calculate savings (promo discount + target price savings)
            $savings = 0;
            // Add promo discount
            $discount = (float) ($order->discount_amount ?? $order->meta['discount_amount'] ?? $order->meta['discount'] ?? 0);
            $savings += $discount;

            // Add target price savings
            $targetPrice = 0;
            if ($order->event) {
                $targetPrice = (float) ($order->event->target_price ?? 0);
            } elseif ($order->marketplaceEvent) {
                $targetPrice = (float) ($order->marketplaceEvent->target_price ?? 0);
            }

            if ($targetPrice > 0) {
                foreach ($order->tickets as $ticket) {
                    $ticketPrice = (float) ($ticket->price ?? 0);
                    if ($targetPrice > $ticketPrice && $ticketPrice > 0) {
                        $savings += ($targetPrice - $ticketPrice);
                    }
                }
            }

            // Determine refund eligibility
            $canRequestRefund = false;
            $refundReason = null;

            // Check if event is cancelled or postponed
            if ($order->marketplaceEvent) {
                if ($order->marketplaceEvent->isCancelled()) {
                    $canRequestRefund = true;
                    $refundReason = 'event_cancelled';
                }
            } elseif ($order->event) {
                if ($order->event->is_cancelled) {
                    $canRequestRefund = true;
                    $refundReason = 'event_cancelled';
                } elseif ($order->event->is_postponed) {
                    $canRequestRefund = true;
                    $refundReason = 'event_postponed';
                }
            }

            // Check if any ticket type is refundable (only if not already eligible)
            if (!$canRequestRefund) {
                foreach ($order->tickets as $ticket) {
                    $isRefundable = (bool) ($ticket->marketplaceTicketType?->is_refundable ?? $ticket->ticketType?->is_refundable ?? false);
                    if ($isRefundable) {
                        $canRequestRefund = true;
                        $refundReason = 'ticket_refundable';
                        break;
                    }
                }
            }

            // Only allow refund requests for paid/confirmed orders
            if (!in_array($order->status, ['completed', 'paid', 'confirmed'])) {
                $canRequestRefund = false;
                $refundReason = null;
            }

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'reference' => $order->order_number,
                'status' => $order->status,
                'subtotal' => (float) ($order->subtotal ?? $order->total),
                'total' => (float) $order->total,
                'currency' => $order->currency,
                'tickets_count' => $order->tickets->count(),
                'promo_discount' => (float) ($order->discount_amount ?? 0),
                'discount' => (float) ($order->discount_amount ?? 0),
                'promo_code' => $order->meta['promo_code'] ?? $order->promo_code ?? null,
                // Commission info - using EVENT's commission (marketplace -> organizer)
                'commission_rate' => $eventCommissionRate,
                'commission_amount' => round($eventCommissionAmount, 2),
                'commission_mode' => $commissionMode, // 'included' or 'on_top'
                // Savings info (promo discount + target price savings)
                'savings' => round($savings, 2),
                // Payment info
                'payment_method' => $paymentMethod,
                'payment_processor' => $order->payment_processor,
                'payment_status' => $order->payment_status,
                'paid_at' => $order->paid_at?->toIso8601String(),
                'event' => $eventData,
                'items' => $order->tickets->groupBy(function ($ticket) {
                    // Group by marketplace_ticket_type_id if set, otherwise ticket_type_id
                    return $ticket->marketplace_ticket_type_id ?? $ticket->ticket_type_id ?? 0;
                })->map(function ($tickets, $typeId) use ($order, $eventCommissionAmount) {
                    $first = $tickets->first();
                    $basePrice = (float) ($first->price ?? 0);
                    $quantity = $tickets->count();

                    // Calculate commission per ticket using EVENT's commission
                    $commissionPerTicket = 0;
                    if ($eventCommissionAmount > 0 && $order->tickets->count() > 0) {
                        $commissionPerTicket = $eventCommissionAmount / $order->tickets->count();
                    }

                    // Get ticket type (marketplace or tenant)
                    $ticketType = $first->marketplaceTicketType ?? $first->ticketType;
                    $isRefundable = (bool) ($ticketType?->is_refundable ?? false);

                    return [
                        'name' => $ticketType?->name ?? 'Bilet',
                        'quantity' => $quantity,
                        'base_price' => $basePrice,
                        'price' => $basePrice, // Total price per ticket (includes commission if on_top was already added)
                        'commission_per_ticket' => round($commissionPerTicket, 2),
                        'is_refundable' => $isRefundable,
                    ];
                })->values()->toArray(),
                'can_request_refund' => $canRequestRefund,
                'refund_reason' => $refundReason,
                'created_at' => $order->created_at->toIso8601String(),
            ];
        });
    }

    /**
     * Get single order details
     */
    public function orderDetail(Request $request, string $orderId): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $order = Order::where(function ($q) use ($orderId) {
                if (is_numeric($orderId)) {
                    $q->where('id', (int) $orderId);
                } else {
                    $q->where('order_number', $orderId);
                }
            })
            ->where('marketplace_customer_id', $customer->id)
            ->with([
                'marketplaceEvent',
                'event.venue',
                'tickets.marketplaceTicketType',
                'tickets.ticketType',
            ])
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        // Determine refund eligibility
        $canRequestRefund = false;
        $refundReason = null;

        // Check if event is cancelled or postponed
        if ($order->marketplaceEvent) {
            if ($order->marketplaceEvent->isCancelled()) {
                $canRequestRefund = true;
                $refundReason = 'event_cancelled';
            }
        } elseif ($order->event) {
            if ($order->event->is_cancelled) {
                $canRequestRefund = true;
                $refundReason = 'event_cancelled';
            } elseif ($order->event->is_postponed) {
                $canRequestRefund = true;
                $refundReason = 'event_postponed';
            }
        }

        // Check if any ticket type is refundable (only if not already eligible)
        if (!$canRequestRefund) {
            foreach ($order->tickets as $ticket) {
                $isRefundable = false;
                if ($ticket->marketplaceTicketType) {
                    $isRefundable = (bool) $ticket->marketplaceTicketType->is_refundable;
                } elseif ($ticket->ticketType) {
                    $isRefundable = (bool) $ticket->ticketType->is_refundable;
                }

                if ($isRefundable) {
                    $canRequestRefund = true;
                    $refundReason = 'ticket_refundable';
                    break;
                }
            }
        }

        // Only allow refund requests for paid/confirmed orders that haven't been cancelled/refunded
        if (!in_array($order->status, ['completed', 'paid', 'confirmed'])) {
            $canRequestRefund = false;
            $refundReason = null;
        }

        // Build event data
        $eventData = null;
        if ($order->marketplaceEvent) {
            $eventData = [
                'id' => $order->marketplaceEvent->id,
                'name' => $order->marketplaceEvent->name,
                'slug' => $order->marketplaceEvent->slug,
                'description' => $order->marketplaceEvent->short_description,
                'date' => $order->marketplaceEvent->starts_at->toIso8601String(),
                'end_date' => $order->marketplaceEvent->ends_at?->toIso8601String(),
                'doors_open' => $order->marketplaceEvent->doors_open_at?->toIso8601String(),
                'venue' => $order->marketplaceEvent->venue_name,
                'venue_address' => $order->marketplaceEvent->venue_address,
                'city' => $order->marketplaceEvent->venue_city,
                'image' => $order->marketplaceEvent->image_url,
                'is_upcoming' => $order->marketplaceEvent->starts_at >= now(),
                'is_cancelled' => $order->marketplaceEvent->isCancelled(),
                'is_postponed' => false,
            ];
        } elseif ($order->event) {
            $imageUrl = null;
            if ($order->event->featured_image) {
                $imageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($order->event->featured_image);
            } elseif ($order->event->poster_url) {
                $imageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($order->event->poster_url);
            }

            $eventTitle = is_array($order->event->title)
                ? ($order->event->title['ro'] ?? $order->event->title['en'] ?? reset($order->event->title))
                : $order->event->title;

            $eventData = [
                'id' => $order->event->id,
                'name' => $eventTitle,
                'slug' => $order->event->slug,
                'description' => $order->event->short_description ?? null,
                'date' => $order->event->event_date?->toIso8601String(),
                'end_date' => null,
                'doors_open' => $order->event->door_time,
                'venue' => $order->event->venue?->name ?? null,
                'venue_address' => $order->event->venue?->address ?? null,
                'city' => $order->event->venue?->city ?? null,
                'image' => $imageUrl,
                'is_upcoming' => $order->event->event_date ? $order->event->event_date >= now() : false,
                'is_cancelled' => (bool) $order->event->is_cancelled,
                'is_postponed' => (bool) $order->event->is_postponed,
            ];
        }

        // Status label for display
        $statusLabels = [
            'pending' => 'În așteptare',
            'confirmed' => 'Confirmată',
            'paid' => 'Plătită',
            'completed' => 'Finalizată',
            'cancelled' => 'Anulată',
            'refunded' => 'Rambursată',
        ];
        $statusLabel = $statusLabels[$order->status] ?? ucfirst($order->status);

        // Payment method display
        $paymentMethod = match($order->payment_processor) {
            'netopia', 'payment-netopia' => 'Card bancar (Netopia)',
            'stripe', 'payment-stripe' => 'Card bancar (Stripe)',
            'paypal' => 'PayPal',
            'cash' => 'Numerar',
            'bank_transfer' => 'Transfer bancar',
            default => $order->payment_processor ? ucfirst(str_replace(['_', '-'], ' ', $order->payment_processor)) : 'Card',
        };

        // Build items grouped by ticket type (for frontend display)
        $items = $order->tickets->groupBy(function ($ticket) {
            return $ticket->marketplace_ticket_type_id ?? $ticket->ticket_type_id ?? 0;
        })->map(function ($tickets, $typeId) use ($eventData) {
            $first = $tickets->first();
            $ticketType = $first->marketplaceTicketType ?? $first->ticketType;
            $typeName = $ticketType?->name ?? 'Bilet';
            $price = (float) ($first->price ?? $ticketType?->price ?? 0);

            return [
                'name' => $typeName,
                'quantity' => $tickets->count(),
                'price' => $price,
                'total' => $price * $tickets->count(),
                'event_title' => $eventData['name'] ?? '',
                'event_date' => $eventData['date'] ?? null,
                'image' => $eventData['image'] ?? null,
            ];
        })->values()->toArray();

        // Build timeline
        $timeline = [];
        $timeline[] = [
            'title' => 'Comanda plasata',
            'date' => $order->created_at->format('d M Y, H:i'),
            'status' => 'completed',
        ];
        if ($order->paid_at) {
            $timeline[] = [
                'title' => 'Plata confirmata',
                'date' => $order->paid_at->format('d M Y, H:i'),
                'status' => 'completed',
            ];
        }
        if (in_array($order->status, ['confirmed', 'completed', 'paid'])) {
            $timeline[] = [
                'title' => 'Bilete emise',
                'date' => ($order->paid_at ?? $order->created_at)->format('d M Y, H:i'),
                'status' => 'completed',
            ];
        }
        if ($order->status === 'cancelled') {
            $timeline[] = [
                'title' => 'Comanda anulata',
                'date' => ($order->cancelled_at ?? $order->updated_at)->format('d M Y, H:i'),
                'status' => 'cancelled',
            ];
        }
        if ($order->status === 'refunded') {
            $timeline[] = [
                'title' => 'Comanda rambursata',
                'date' => ($order->refunded_at ?? $order->updated_at)->format('d M Y, H:i'),
                'status' => 'refunded',
            ];
        }

        // Service fee (commission)
        $serviceFee = (float) ($order->commission_amount ?? 0);

        // Discount
        $discount = (float) ($order->discount_amount ?? $order->promo_discount ?? 0);

        return $this->success([
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'number' => $order->order_number, // Alias for frontend
                'status' => $order->status,
                'status_label' => $statusLabel,
                'payment_status' => $order->payment_status,
                'date' => $order->created_at->format('d M Y, H:i'),
                'subtotal' => number_format((float) $order->subtotal, 2, '.', ''),
                'service_fee' => number_format($serviceFee, 2, '.', ''),
                'discount' => number_format($discount, 2, '.', ''),
                'total' => number_format((float) $order->total, 2, '.', ''),
                'currency' => $order->currency ?? 'RON',
                'tickets_count' => $order->tickets->count(),
                'event' => $eventData,
                'items' => $items,
                'timeline' => $timeline,
                'tickets' => $order->tickets->map(function ($ticket) {
                    $ticketType = $ticket->marketplaceTicketType ?? $ticket->ticketType;
                    return [
                        'id' => $ticket->id,
                        'barcode' => $ticket->barcode,
                        'type' => $ticketType?->name,
                        'price' => (float) ($ticketType?->price ?? $ticket->price ?? 0),
                        'status' => $ticket->status,
                        'attendee_name' => $ticket->attendee_name,
                        'checked_in' => $ticket->checked_in_at !== null,
                        'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
                        'is_refundable' => (bool) ($ticketType?->is_refundable ?? false),
                    ];
                }),
                'customer_email' => $order->customer_email,
                'customer_name' => $order->customer_name,
                'payment_method' => $paymentMethod,
                'payment_exp' => $order->meta['card_exp'] ?? '',
                'billing_address' => $order->meta['billing_address'] ?? $order->customer_name ?? '',
                'invoice_filename' => $order->meta['invoice_filename'] ?? null,
                'can_download_tickets' => in_array($order->status, ['completed', 'paid', 'confirmed']) || $order->payment_status === 'paid',
                'can_request_refund' => $canRequestRefund,
                'refund_reason' => $refundReason,
                'created_at' => $order->created_at->toIso8601String(),
                'paid_at' => $order->paid_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get customer's upcoming tickets
     */
    public function upcomingTickets(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $orders = Order::where('marketplace_customer_id', $customer->id)
            ->whereIn('status', ['completed', 'paid', 'confirmed'])
            ->whereHas('marketplaceEvent', function ($q) {
                $q->where('starts_at', '>=', now());
            })
            ->with([
                'marketplaceEvent:id,name,slug,starts_at,venue_name,venue_city,image',
                'tickets.marketplaceTicketType:id,name',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $tickets = $orders->flatMap(function ($order) {
            return $order->tickets->map(function ($ticket) use ($order) {
                return [
                    'id' => $ticket->id,
                    'barcode' => $ticket->barcode,
                    'type' => $ticket->marketplaceTicketType?->name,
                    'status' => $ticket->status,
                    'order_number' => $order->order_number,
                    'event' => [
                        'id' => $order->marketplaceEvent->id,
                        'name' => $order->marketplaceEvent->name,
                        'slug' => $order->marketplaceEvent->slug,
                        'date' => $order->marketplaceEvent->starts_at->toIso8601String(),
                        'venue' => $order->marketplaceEvent->venue_name,
                        'city' => $order->marketplaceEvent->venue_city,
                        'image' => $order->marketplaceEvent->image_url,
                    ],
                ];
            });
        })->sortBy(function ($ticket) {
            return $ticket['event']['date'];
        })->values();

        return $this->success([
            'tickets' => $tickets,
        ]);
    }

    /**
     * Get single ticket details
     */
    public function ticketDetail(Request $request, int $ticketId): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        // Find the ticket through orders
        $ticket = null;
        $order = null;

        $orders = Order::where('marketplace_customer_id', $customer->id)
            ->whereIn('status', ['completed', 'paid', 'confirmed'])
            ->with([
                'marketplaceEvent',
                'tickets.marketplaceTicketType',
            ])
            ->get();

        foreach ($orders as $o) {
            foreach ($o->tickets as $t) {
                if ($t->id === $ticketId) {
                    $ticket = $t;
                    $order = $o;
                    break 2;
                }
            }
        }

        if (!$ticket || !$order) {
            return $this->error('Ticket not found', 404);
        }

        $event = $order->marketplaceEvent;

        // Count ticket position
        $ticketPosition = 1;
        $totalTicketsInOrder = $order->tickets->count();
        foreach ($order->tickets as $index => $t) {
            if ($t->id === $ticketId) {
                $ticketPosition = $index + 1;
                break;
            }
        }

        return $this->success([
            'ticket' => [
                'id' => $ticket->id,
                'code' => $ticket->barcode,
                'type' => $ticket->marketplaceTicketType?->name ?? 'Standard',
                'type_description' => $ticket->marketplaceTicketType?->description ?? '',
                'price' => number_format((float) ($ticket->marketplaceTicketType?->price ?? 0), 2, ',', '.') . ' RON',
                'status' => $ticket->status,
                'attendee_name' => $ticket->attendee_name ?? $customer->full_name,
                'attendee_email' => $ticket->attendee_email ?? $customer->email,
                'checked_in' => $ticket->checked_in_at !== null,
                'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
                'transferable' => $ticket->is_transferable ?? true,
                'event' => [
                    'id' => $event->id,
                    'title' => $event->name,
                    'subtitle' => $event->short_description ?? '',
                    'slug' => $event->slug,
                    'date' => $event->starts_at->format('l, d F Y'),
                    'time' => 'Ora ' . $event->starts_at->format('H:i') . ($event->doors_open_at ? ' (Porti: ' . $event->doors_open_at->format('H:i') . ')' : ''),
                    'venue' => $event->venue_name,
                    'address' => $event->venue_address ?? '',
                    'city' => $event->venue_city,
                    'image' => $event->image_url,
                    'is_upcoming' => $event->starts_at >= now(),
                ],
                'order' => [
                    'id' => $order->id,
                    'number' => $order->order_number,
                    'purchase_date' => $order->created_at->format('d M Y'),
                ],
                'ticket_index' => $ticketPosition . ' din ' . $totalTicketsInOrder,
                'qr_data' => $ticket->barcode, // For QR code generation
            ],
        ]);
    }

    /**
     * Get all tickets (upcoming and past)
     */
    public function allTickets(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $filter = $request->get('filter', 'upcoming'); // upcoming, past, all

        $query = Order::where('marketplace_customer_id', $customer->id)
            ->whereIn('status', ['completed', 'paid', 'confirmed'])
            ->with([
                'marketplaceEvent:id,name,slug,starts_at,venue_name,venue_city,image',
                'event:id,title,slug,event_date,featured_image,poster_url',
                'event.venue:id,name,city',
                'tickets.marketplaceTicketType:id,name',
                'tickets.ticketType:id,name,is_refundable',
                'tickets.marketplaceTicketType:id,name,is_refundable',
            ]);

        if ($filter === 'upcoming') {
            $query->where(function ($q) {
                $q->whereHas('marketplaceEvent', function ($sub) {
                    $sub->where('starts_at', '>=', now());
                })->orWhereHas('event', function ($sub) {
                    $sub->where('event_date', '>=', now());
                });
            });
        } elseif ($filter === 'past') {
            $query->where(function ($q) {
                $q->whereHas('marketplaceEvent', function ($sub) {
                    $sub->where('starts_at', '<', now());
                })->orWhereHas('event', function ($sub) {
                    $sub->where('event_date', '<', now());
                });
            });
        }

        $orders = $query->orderByDesc('created_at')->get();

        $tickets = $orders->flatMap(function ($order) {
            $customer = $order->marketplaceCustomer;
            return $order->tickets->map(function ($ticket) use ($order, $customer) {
                // Handle both marketplace events and tenant events
                if ($order->marketplaceEvent) {
                    $event = $order->marketplaceEvent;
                    $seatMeta = $ticket->meta ?? [];
                    return [
                        'id' => $ticket->id,
                        'code' => $ticket->barcode,
                        'type' => $ticket->marketplaceTicketType?->name ?? 'Standard',
                        'status' => $ticket->status,
                        'order_number' => $order->order_number,
                        'checked_in' => $ticket->checked_in_at !== null,
                        'attendee_name' => $ticket->attendee_name ?? $customer?->full_name ?? null,
                        'seat_label' => $ticket->seat_label,
                        'seat' => $ticket->seat_label ? [
                            'section_name' => $seatMeta['section_name'] ?? null,
                            'row_label' => $seatMeta['row_label'] ?? null,
                            'seat_number' => $seatMeta['seat_number'] ?? null,
                        ] : null,
                        'event' => [
                            'id' => $event->id,
                            'name' => $event->name,
                            'slug' => $event->slug,
                            'date' => $event->starts_at->toIso8601String(),
                            'date_formatted' => $event->starts_at->format('d M Y'),
                            'time' => $event->starts_at->format('H:i'),
                            'doors_time' => $event->doors_open_at?->format('H:i'),
                            'end_time' => $event->ends_at?->format('H:i'),
                            'venue' => $event->venue_name,
                            'city' => $event->venue_city,
                            'image' => $event->image_url,
                            'is_upcoming' => $event->starts_at >= now(),
                        ],
                    ];
                } elseif ($order->event) {
                    $event = $order->event;
                    // Get full URL for featured image
                    $imageUrl = null;
                    if ($event->featured_image) {
                        $imageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($event->featured_image);
                    } elseif ($event->poster_url) {
                        $imageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($event->poster_url);
                    }

                    $eventTitle = is_array($event->title)
                        ? ($event->title['ro'] ?? $event->title['en'] ?? reset($event->title))
                        : $event->title;

                    $seatMeta = $ticket->meta ?? [];
                    return [
                        'id' => $ticket->id,
                        'code' => $ticket->barcode,
                        'type' => $ticket->ticketType?->name ?? 'Standard',
                        'status' => $ticket->status,
                        'order_number' => $order->order_number,
                        'checked_in' => $ticket->checked_in_at !== null,
                        'attendee_name' => $ticket->attendee_name ?? $customer?->full_name ?? null,
                        'seat_label' => $ticket->seat_label,
                        'seat' => $ticket->seat_label ? [
                            'section_name' => $seatMeta['section_name'] ?? null,
                            'row_label' => $seatMeta['row_label'] ?? null,
                            'seat_number' => $seatMeta['seat_number'] ?? null,
                        ] : null,
                        'event' => [
                            'id' => $event->id,
                            'name' => $eventTitle,
                            'slug' => $event->slug,
                            'date' => $event->event_date?->toIso8601String(),
                            'date_formatted' => $event->event_date?->format('d M Y'),
                            'time' => $event->start_time,
                            'doors_time' => $event->door_time,
                            'end_time' => $event->end_time,
                            'venue' => $event->venue?->name ?? null,
                            'city' => $event->venue?->city ?? null,
                            'image' => $imageUrl,
                            'is_upcoming' => $event->event_date ? $event->event_date >= now() : false,
                        ],
                    ];
                }

                return null;
            })->filter();
        });

        // Sort by event date
        if ($filter === 'upcoming') {
            $tickets = $tickets->sortBy('event.date')->values();
        } else {
            $tickets = $tickets->sortByDesc('event.date')->values();
        }

        return $this->success([
            'tickets' => $tickets,
            'stats' => [
                'total' => $tickets->count(),
                'upcoming' => $tickets->where('event.is_upcoming', true)->count(),
                'past' => $tickets->where('event.is_upcoming', false)->count(),
            ],
        ]);
    }

    /**
     * Get past events
     */
    public function pastEvents(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $orders = Order::where('marketplace_customer_id', $customer->id)
            ->whereIn('status', ['completed', 'paid', 'confirmed'])
            ->whereHas('marketplaceEvent', function ($q) {
                $q->where('starts_at', '<', now());
            })
            ->with('marketplaceEvent:id,name,slug,starts_at,venue_name,venue_city,image')
            ->withCount('tickets')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $events = $orders->map(function ($order) {
            return [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'tickets_count' => $order->tickets_count,
                'event' => [
                    'id' => $order->marketplaceEvent->id,
                    'name' => $order->marketplaceEvent->name,
                    'slug' => $order->marketplaceEvent->slug,
                    'date' => $order->marketplaceEvent->starts_at->toIso8601String(),
                    'venue' => $order->marketplaceEvent->venue_name,
                    'city' => $order->marketplaceEvent->venue_city,
                    'image' => $order->marketplaceEvent->image_url,
                ],
            ];
        });

        return $this->success([
            'past_events' => $events,
        ]);
    }

    /**
     * Delete account
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $validated = $request->validate([
            'password' => 'required|string',
            'reason' => 'nullable|string|max:500',
        ]);

        if (!$customer->password || !password_verify($validated['password'], $customer->password)) {
            return $this->error('Invalid password', 422);
        }

        // Check for upcoming events
        $hasUpcoming = Order::where('marketplace_customer_id', $customer->id)
            ->whereIn('status', ['completed', 'paid', 'confirmed'])
            ->where(function ($query) {
                $query->whereHas('marketplaceEvent', function ($q) {
                    $q->where('starts_at', '>=', now());
                })->orWhereHas('event', function ($q) {
                    $q->where('event_date', '>=', now());
                });
            })
            ->exists();

        if ($hasUpcoming) {
            return $this->error('Cannot delete account with upcoming events. Please contact support.', 400);
        }

        // Soft delete and anonymize
        $customer->update([
            'email' => 'deleted_' . $customer->id . '@deleted.local',
            'first_name' => 'Deleted',
            'last_name' => 'User',
            'phone' => null,
            'address' => null,
            'status' => 'deleted',
        ]);
        $customer->delete();

        return $this->success(null, 'Account deleted successfully');
    }

    /**
     * Require authenticated customer
     */
    protected function requireCustomer(Request $request): MarketplaceCustomer
    {
        $customer = $request->user();

        if (!$customer instanceof MarketplaceCustomer) {
            abort(401, 'Unauthorized');
        }

        return $customer;
    }
}
