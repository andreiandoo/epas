# Real-Time WebSockets Implementation Plan

## Scope & Problem Statement

### What This Feature Solves
Current HTTP-only architecture lacks real-time capabilities:
1. **Stale data**: Users see outdated ticket availability
2. **Polling overhead**: Frequent API calls waste resources
3. **No live updates**: Can't show real-time seat selection
4. **Limited interactivity**: Chat, notifications require refresh
5. **Poor UX**: Users miss important updates

### What This Feature Does
- Real-time ticket availability updates
- Live seat map with occupied seats
- Push notifications for order updates
- Live chat integration
- Event countdown and status changes
- Admin dashboard real-time metrics

---

## Technical Implementation

### 1. Package Installation

```bash
composer require pusher/pusher-php-server
composer require beyondcode/laravel-websockets  # Self-hosted option

# Or use Laravel Reverb (official Laravel WebSocket server)
composer require laravel/reverb
```

### 2. Configuration

```php
// config/broadcasting.php
return [
    'default' => env('BROADCAST_DRIVER', 'pusher'),

    'connections' => [
        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER').'.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => [],
        ],

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST', 'localhost'),
                'port' => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
        ],
    ],
];

// config/reverb.php (for self-hosted)
return [
    'default' => env('REVERB_SERVER', 'reverb'),
    'servers' => [
        'reverb' => [
            'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
            'port' => env('REVERB_SERVER_PORT', 8080),
            'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 10_000),
            'scaling' => [
                'enabled' => env('REVERB_SCALING_ENABLED', false),
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
            ],
        ],
    ],
];
```

### 3. Channel Definitions

```php
// routes/channels.php
<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\Customer;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Public Channels
|--------------------------------------------------------------------------
*/

// Event updates (ticket availability, status changes)
Broadcast::channel('event.{eventId}', function () {
    return true; // Public channel
});

// Tenant-wide announcements
Broadcast::channel('tenant.{tenantId}', function () {
    return true;
});

/*
|--------------------------------------------------------------------------
| Private Channels (require authentication)
|--------------------------------------------------------------------------
*/

// Customer's personal channel
Broadcast::channel('customer.{customerId}', function (Customer $customer, $customerId) {
    return (int) $customer->id === (int) $customerId;
});

// Order updates
Broadcast::channel('order.{orderId}', function (Customer $customer, $orderId) {
    $order = Order::find($orderId);
    return $order && $order->customer_id === $customer->id;
});

// Chat conversation
Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    $conversation = \App\Models\ChatConversation::find($conversationId);
    return $conversation && (
        $conversation->customer_id === $user->id ||
        $conversation->assigned_agent_id === $user->id
    );
});

/*
|--------------------------------------------------------------------------
| Presence Channels (show who's online)
|--------------------------------------------------------------------------
*/

// Seat selection - show who's selecting what
Broadcast::channel('event.{eventId}.seats', function (Customer $customer, $eventId) {
    return [
        'id' => $customer->id,
        'name' => $customer->first_name,
    ];
});

// Virtual event attendees
Broadcast::channel('virtual-event.{eventId}', function (Customer $customer, $eventId) {
    return [
        'id' => $customer->id,
        'name' => $customer->full_name,
        'avatar' => $customer->avatar_url,
    ];
});
```

### 4. Event Classes

```php
// app/Events/TicketAvailabilityUpdated.php
<?php

namespace App\Events;

use App\Models\Event;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketAvailabilityUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Event $event,
        public array $availability
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("event.{$this->event->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'availability.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->event->id,
            'ticket_types' => $this->availability,
            'updated_at' => now()->toISOString(),
        ];
    }
}

// app/Events/OrderStatusUpdated.php
<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("customer.{$this->order->customer_id}"),
            new PrivateChannel("order.{$this->order->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'message' => $this->getStatusMessage(),
        ];
    }

    protected function getStatusMessage(): string
    {
        return match ($this->order->status) {
            'paid' => 'Your order has been confirmed!',
            'cancelled' => 'Your order has been cancelled.',
            'refunded' => 'Your refund has been processed.',
            default => "Order status: {$this->order->status}",
        };
    }
}

// app/Events/SeatSelected.php
<?php

namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class SeatSelected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $eventId,
        public int $customerId,
        public array $seats,
        public string $action = 'select' // select, deselect, lock
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("event.{$this->eventId}.seats"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'seat.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'customer_id' => $this->customerId,
            'seats' => $this->seats,
            'action' => $this->action,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastToEveryoneExcept(): array
    {
        return [$this->customerId]; // Don't broadcast back to selector
    }
}

// app/Events/ChatMessageSent.php
<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatMessage $message
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chat.{$this->message->conversation_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'sender_type' => $this->message->sender_type,
            'content' => $this->message->content,
            'created_at' => $this->message->created_at->toISOString(),
            'quick_replies' => $this->message->quick_replies,
        ];
    }
}

// app/Events/NotificationPushed.php
<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NotificationPushed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $customerId,
        public string $title,
        public string $message,
        public string $type = 'info',
        public ?array $data = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("customer.{$this->customerId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification';
    }

    public function broadcastWith(): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'data' => $this->data,
            'timestamp' => now()->toISOString(),
        ];
    }
}
```

### 5. Broadcasting Service

```php
// app/Services/Broadcasting/BroadcastService.php
<?php

namespace App\Services\Broadcasting;

use App\Models\Event;
use App\Models\Order;
use App\Models\Customer;
use App\Events\TicketAvailabilityUpdated;
use App\Events\OrderStatusUpdated;
use App\Events\SeatSelected;
use App\Events\NotificationPushed;

class BroadcastService
{
    /**
     * Broadcast ticket availability update
     */
    public function ticketAvailabilityUpdated(Event $event): void
    {
        $availability = $event->ticketTypes->map(fn($tt) => [
            'id' => $tt->id,
            'name' => $tt->name,
            'available' => $tt->available_quantity,
            'sold' => $tt->sold_quantity,
            'status' => $tt->available_quantity > 0 ? 'available' : 'sold_out',
        ])->toArray();

        broadcast(new TicketAvailabilityUpdated($event, $availability));
    }

    /**
     * Broadcast order status change
     */
    public function orderStatusUpdated(Order $order): void
    {
        broadcast(new OrderStatusUpdated($order));
    }

    /**
     * Broadcast seat selection
     */
    public function seatSelected(Event $event, Customer $customer, array $seats, string $action = 'select'): void
    {
        broadcast(new SeatSelected($event->id, $customer->id, $seats, $action));
    }

    /**
     * Push notification to customer
     */
    public function pushNotification(
        Customer $customer,
        string $title,
        string $message,
        string $type = 'info',
        ?array $data = null
    ): void {
        broadcast(new NotificationPushed($customer->id, $title, $message, $type, $data));
    }

    /**
     * Broadcast event going live
     */
    public function eventLive(Event $event): void
    {
        broadcast(new \App\Events\EventWentLive($event));
    }
}
```

### 6. Automatic Broadcasting on Model Events

```php
// app/Observers/TicketObserver.php
<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Services\Broadcasting\BroadcastService;

class TicketObserver
{
    public function __construct(protected BroadcastService $broadcastService) {}

    public function created(Ticket $ticket): void
    {
        $this->broadcastService->ticketAvailabilityUpdated($ticket->event);
    }

    public function updated(Ticket $ticket): void
    {
        if ($ticket->isDirty('status')) {
            $this->broadcastService->ticketAvailabilityUpdated($ticket->event);
        }
    }
}

// app/Observers/OrderObserver.php
<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Broadcasting\BroadcastService;

class OrderObserver
{
    public function __construct(protected BroadcastService $broadcastService) {}

    public function updated(Order $order): void
    {
        if ($order->isDirty('status')) {
            $this->broadcastService->orderStatusUpdated($order);
        }
    }
}

// Register in AppServiceProvider
public function boot(): void
{
    Ticket::observe(TicketObserver::class);
    Order::observe(OrderObserver::class);
}
```

### 7. WebSocket Authentication

```php
// app/Http/Controllers/Api/BroadcastAuthController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

class BroadcastAuthController extends Controller
{
    /**
     * Authenticate private/presence channel
     */
    public function authenticate(Request $request)
    {
        return Broadcast::auth($request);
    }
}

// routes/api.php
Route::post('/broadcasting/auth', [BroadcastAuthController::class, 'authenticate'])
    ->middleware('auth:sanctum');
```

### 8. Frontend Integration (JavaScript)

```javascript
// resources/js/websocket.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
    authEndpoint: '/api/broadcasting/auth',
    auth: {
        headers: {
            Authorization: `Bearer ${getAuthToken()}`,
        },
    },
});

// Subscribe to public event channel
function subscribeToEvent(eventId) {
    echo.channel(`event.${eventId}`)
        .listen('.availability.updated', (data) => {
            updateTicketAvailability(data.ticket_types);
        });
}

// Subscribe to private customer channel
function subscribeToCustomer(customerId) {
    echo.private(`customer.${customerId}`)
        .listen('.order.updated', (data) => {
            showOrderNotification(data);
        })
        .listen('.notification', (data) => {
            showToast(data.title, data.message, data.type);
        });
}

// Join presence channel for seat selection
function joinSeatSelection(eventId) {
    echo.join(`event.${eventId}.seats`)
        .here((users) => {
            console.log('Users selecting seats:', users);
        })
        .joining((user) => {
            console.log('User joined:', user);
        })
        .leaving((user) => {
            console.log('User left:', user);
        })
        .listen('.seat.changed', (data) => {
            updateSeatMap(data.seats, data.action, data.customer_id);
        });
}

// Subscribe to chat
function subscribeToChat(conversationId) {
    echo.private(`chat.${conversationId}`)
        .listen('.message.sent', (data) => {
            appendMessage(data);
        });
}

export { echo, subscribeToEvent, subscribeToCustomer, joinSeatSelection, subscribeToChat };
```

### 9. Admin Real-Time Dashboard

```php
// app/Events/AdminMetricsUpdated.php
<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AdminMetricsUpdated implements ShouldBroadcast
{
    public function __construct(
        public int $tenantId,
        public array $metrics
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("admin.{$this->tenantId}.dashboard"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'metrics.updated';
    }
}

// Broadcast channel
Broadcast::channel('admin.{tenantId}.dashboard', function ($user, $tenantId) {
    return $user->tenant_id === (int) $tenantId && $user->hasRole('admin');
});
```

### 10. Rate Limiting & Security

```php
// app/Http/Middleware/WebSocketRateLimiter.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\RateLimiter;

class WebSocketRateLimiter
{
    public function handle($request, Closure $next)
    {
        $key = 'websocket:' . ($request->user()?->id ?? $request->ip());

        if (RateLimiter::tooManyAttempts($key, 100)) { // 100 messages per minute
            return response()->json(['error' => 'Too many requests'], 429);
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
```

### 11. Queue Configuration for Broadcasting

```php
// config/queue.php - Add broadcasting queue
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
        'after_commit' => false,
    ],
],

// Supervisor config for broadcast worker
// /etc/supervisor/conf.d/broadcast-worker.conf
[program:broadcast-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/artisan queue:work redis --queue=broadcasts --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
```

### 12. Self-Hosted WebSocket Server (Reverb)

```bash
# Start Reverb server
php artisan reverb:start

# Or with supervisor
[program:reverb]
command=php /var/www/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
```

---

## Testing Checklist

1. [ ] WebSocket server starts and accepts connections
2. [ ] Public channels work without authentication
3. [ ] Private channels require valid auth token
4. [ ] Presence channels show/track members correctly
5. [ ] Ticket availability broadcasts on purchase
6. [ ] Order status updates broadcast to customer
7. [ ] Seat selection broadcasts to other users
8. [ ] Chat messages broadcast in real-time
9. [ ] Push notifications reach customer channel
10. [ ] Admin dashboard receives live metrics
11. [ ] Rate limiting prevents abuse
12. [ ] Reconnection handling works
13. [ ] Multiple browser tabs receive updates
