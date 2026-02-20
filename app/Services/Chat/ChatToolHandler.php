<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceEvent;
use App\Models\KnowledgeBase\KbArticle;
use App\Models\Order;

class ChatToolHandler
{
    /**
     * Get tool definitions for OpenAI
     */
    public function getToolDefinitions(bool $isAuthenticated): array
    {
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_knowledge_base',
                    'description' => 'Caută în baza de cunoștințe (FAQ, articole ajutor) pentru a găsi informații relevante',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Termenul de căutare',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_events',
                    'description' => 'Caută evenimente disponibile pe platformă',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Numele evenimentului sau termeni de căutare',
                            ],
                            'city' => [
                                'type' => 'string',
                                'description' => 'Orașul în care se desfășoară evenimentul',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_event_details',
                    'description' => 'Obține detalii despre un eveniment specific (prețuri, date, locație)',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'event_slug' => [
                                'type' => 'string',
                                'description' => 'Slug-ul evenimentului',
                            ],
                        ],
                        'required' => ['event_slug'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'escalate_to_human',
                    'description' => 'Transferă conversația la un agent de suport uman când nu poți rezolva problema',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reason' => [
                                'type' => 'string',
                                'description' => 'Motivul escaladării',
                            ],
                        ],
                        'required' => ['reason'],
                    ],
                ],
            ],
        ];

        if ($isAuthenticated) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'get_customer_orders',
                    'description' => 'Obține lista comenzilor clientului autentificat',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'status' => [
                                'type' => 'string',
                                'enum' => ['pending', 'confirmed', 'completed', 'cancelled', 'refunded'],
                                'description' => 'Filtrează după status',
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Numărul maxim de comenzi returnate',
                                'default' => 5,
                            ],
                        ],
                    ],
                ],
            ];

            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'get_order_details',
                    'description' => 'Obține detaliile unei comenzi specifice a clientului',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'order_number' => [
                                'type' => 'string',
                                'description' => 'Numărul comenzii sau ID-ul',
                            ],
                        ],
                        'required' => ['order_number'],
                    ],
                ],
            ];

            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'get_customer_tickets',
                    'description' => 'Obține biletele clientului autentificat',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'upcoming_only' => [
                                'type' => 'boolean',
                                'description' => 'Doar biletele pentru evenimente viitoare',
                                'default' => false,
                            ],
                        ],
                    ],
                ],
            ];
        }

        return $tools;
    }

    /**
     * Execute a tool call and return the result
     */
    public function execute(
        string $toolName,
        array $arguments,
        MarketplaceClient $client,
        ?MarketplaceCustomer $customer,
        ?ChatConversation $conversation
    ): array {
        return match ($toolName) {
            'search_knowledge_base' => $this->searchKnowledgeBase($client, $arguments),
            'search_events' => $this->searchEvents($client, $arguments),
            'get_event_details' => $this->getEventDetails($client, $arguments),
            'get_customer_orders' => $this->getCustomerOrders($client, $customer, $arguments),
            'get_order_details' => $this->getOrderDetails($client, $customer, $arguments),
            'get_customer_tickets' => $this->getCustomerTickets($client, $customer, $arguments),
            'escalate_to_human' => $this->escalateToHuman($conversation, $arguments),
            default => ['error' => 'Tool necunoscut'],
        };
    }

    protected function searchKnowledgeBase(MarketplaceClient $client, array $args): array
    {
        $language = $client->language ?? 'ro';
        $query = $args['query'] ?? '';

        $articles = KbArticle::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->where(function ($q) use ($query, $language) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(title, '$.{$language}')) LIKE ?", ["%{$query}%"])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(content, '$.{$language}')) LIKE ?", ["%{$query}%"])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(question, '$.{$language}')) LIKE ?", ["%{$query}%"]);
            })
            ->orderByDesc('view_count')
            ->limit(5)
            ->get();

        return [
            'articles' => $articles->map(function ($article) use ($language) {
                $data = [
                    'type' => $article->type,
                    'slug' => $article->slug,
                ];

                if ($article->type === 'faq') {
                    $data['question'] = $article->getTranslation('question', $language);
                    $data['answer'] = $article->getTranslation('content', $language);
                } else {
                    $data['title'] = $article->getTranslation('title', $language);
                    $content = $article->getTranslation('content', $language) ?? '';
                    $data['content'] = strlen($content) > 500 ? substr($content, 0, 500) . '...' : $content;
                }

                return $data;
            })->toArray(),
            'total' => $articles->count(),
        ];
    }

    protected function searchEvents(MarketplaceClient $client, array $args): array
    {
        $query = $args['query'] ?? '';
        $city = $args['city'] ?? null;

        $eventsQuery = MarketplaceEvent::query()
            ->where('marketplace_client_id', $client->id)
            ->where('status', 'published')
            ->where('starts_at', '>=', now())
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('venue_name', 'LIKE', "%{$query}%");
            });

        if ($city) {
            $eventsQuery->where('venue_city', 'LIKE', "%{$city}%");
        }

        $events = $eventsQuery->orderBy('starts_at')
            ->limit(5)
            ->get();

        return [
            'events' => $events->map(fn($e) => [
                'name' => $e->name,
                'slug' => $e->slug,
                'date' => $e->starts_at?->format('d.m.Y H:i'),
                'venue' => $e->venue_name,
                'city' => $e->venue_city,
                'price_from' => $e->target_price,
            ])->toArray(),
            'total' => $events->count(),
        ];
    }

    protected function getEventDetails(MarketplaceClient $client, array $args): array
    {
        $slug = $args['event_slug'] ?? '';

        $event = MarketplaceEvent::query()
            ->where('marketplace_client_id', $client->id)
            ->where('slug', $slug)
            ->first();

        if (!$event) {
            return ['error' => 'Evenimentul nu a fost găsit'];
        }

        return [
            'name' => $event->name,
            'slug' => $event->slug,
            'date' => $event->starts_at?->format('d.m.Y H:i'),
            'ends_at' => $event->ends_at?->format('d.m.Y H:i'),
            'venue' => $event->venue_name,
            'city' => $event->venue_city,
            'address' => $event->venue_address,
            'description' => $event->short_description ?? substr(strip_tags($event->description ?? ''), 0, 300),
            'price_from' => $event->target_price,
            'status' => $event->status,
            'is_upcoming' => $event->starts_at >= now(),
        ];
    }

    protected function getCustomerOrders(MarketplaceClient $client, ?MarketplaceCustomer $customer, array $args): array
    {
        if (!$customer) {
            return ['error' => 'Trebuie să fii autentificat pentru a vedea comenzile'];
        }

        $query = Order::where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->with(['marketplaceEvent:id,name,slug,starts_at,venue_name,venue_city']);

        if (!empty($args['status'])) {
            $query->where('status', $args['status']);
        }

        $limit = min($args['limit'] ?? 5, 10);
        $orders = $query->orderByDesc('created_at')->limit($limit)->get();

        return [
            'orders' => $orders->map(fn($o) => [
                'order_number' => $o->order_number,
                'status' => $o->status,
                'total' => number_format($o->total, 2) . ' ' . ($o->currency ?? 'RON'),
                'created_at' => $o->created_at?->format('d.m.Y H:i'),
                'event' => $o->marketplaceEvent ? [
                    'name' => $o->marketplaceEvent->name,
                    'date' => $o->marketplaceEvent->starts_at?->format('d.m.Y H:i'),
                    'venue' => $o->marketplaceEvent->venue_name,
                ] : null,
                'tickets_count' => $o->tickets_count ?? $o->tickets()->count(),
            ])->toArray(),
            'total' => $orders->count(),
        ];
    }

    protected function getOrderDetails(MarketplaceClient $client, ?MarketplaceCustomer $customer, array $args): array
    {
        if (!$customer) {
            return ['error' => 'Trebuie să fii autentificat pentru a vedea detaliile comenzii'];
        }

        $orderNumber = $args['order_number'] ?? '';

        $order = Order::where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->where(function ($q) use ($orderNumber) {
                $q->where('order_number', $orderNumber)
                    ->orWhere('id', $orderNumber);
            })
            ->with([
                'marketplaceEvent:id,name,slug,starts_at,venue_name,venue_city',
                'tickets.marketplaceTicketType:id,name',
            ])
            ->first();

        if (!$order) {
            return ['error' => 'Comanda nu a fost găsită. Verifică numărul comenzii.'];
        }

        return [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'total' => number_format($order->total, 2) . ' ' . ($order->currency ?? 'RON'),
            'payment_method' => $order->payment_method,
            'created_at' => $order->created_at?->format('d.m.Y H:i'),
            'event' => $order->marketplaceEvent ? [
                'name' => $order->marketplaceEvent->name,
                'date' => $order->marketplaceEvent->starts_at?->format('d.m.Y H:i'),
                'venue' => $order->marketplaceEvent->venue_name,
                'city' => $order->marketplaceEvent->venue_city,
            ] : null,
            'tickets' => $order->tickets->map(fn($t) => [
                'type' => $t->marketplaceTicketType?->name ?? 'Standard',
                'price' => number_format($t->price, 2) . ' ' . ($order->currency ?? 'RON'),
                'status' => $t->status ?? 'valid',
            ])->toArray(),
        ];
    }

    protected function getCustomerTickets(MarketplaceClient $client, ?MarketplaceCustomer $customer, array $args): array
    {
        if (!$customer) {
            return ['error' => 'Trebuie să fii autentificat pentru a vedea biletele'];
        }

        $query = Order::where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->where('status', 'completed')
            ->with([
                'marketplaceEvent:id,name,slug,starts_at,venue_name,venue_city',
                'tickets.marketplaceTicketType:id,name',
            ]);

        if (!empty($args['upcoming_only'])) {
            $query->whereHas('marketplaceEvent', function ($sub) {
                $sub->where('starts_at', '>=', now());
            });
        }

        $orders = $query->orderByDesc('created_at')->limit(10)->get();

        $tickets = [];
        foreach ($orders as $order) {
            foreach ($order->tickets as $ticket) {
                $tickets[] = [
                    'type' => $ticket->marketplaceTicketType?->name ?? 'Standard',
                    'event' => $order->marketplaceEvent?->name,
                    'event_date' => $order->marketplaceEvent?->starts_at?->format('d.m.Y H:i'),
                    'venue' => $order->marketplaceEvent?->venue_name,
                    'order_number' => $order->order_number,
                ];
            }
        }

        return [
            'tickets' => array_slice($tickets, 0, 10),
            'total' => count($tickets),
        ];
    }

    protected function escalateToHuman(?ChatConversation $conversation, array $args): array
    {
        if ($conversation) {
            $conversation->markEscalated();
        }

        return [
            'status' => 'escalated',
            'message' => 'Conversația a fost transferată la echipa de suport. Vei fi contactat în cel mai scurt timp.',
            'reason' => $args['reason'] ?? '',
        ];
    }
}
