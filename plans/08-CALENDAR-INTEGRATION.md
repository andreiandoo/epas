# Calendar Integration Implementation Plan

## Scope & Problem Statement

### What This Feature Solves
After purchasing tickets, customers have no easy way to add events to their calendars. This causes:
1. **Forgotten events**: Customers miss events they paid for
2. **Manual entry**: Users must manually type event details into calendars
3. **No updates**: If event details change, customers aren't notified via calendar
4. **Lost engagement**: Missed opportunity for event to appear in customer's daily view

### What This Feature Does
Implements calendar integration that:
- Generates .ics files for download
- Integrates with Google Calendar API for auto-sync
- Supports Apple Calendar and Outlook
- Updates calendar events when event details change
- Includes venue, time, ticket info in calendar entry
- Adds reminders before the event

---

## Technical Implementation

### 1. Database Migrations

Create `database/migrations/2026_01_03_000030_create_calendar_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('provider'); // google, outlook, apple
            $table->string('external_calendar_id')->nullable();
            $table->string('calendar_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('auto_sync')->default(true);
            $table->json('sync_settings')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'provider']);
        });

        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_connection_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->string('external_event_id')->nullable();
            $table->string('external_event_link')->nullable();
            $table->enum('status', ['pending', 'synced', 'failed', 'deleted'])->default('pending');
            $table->string('error_message')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['calendar_connection_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('calendar_connections');
    }
};
```

### 2. Configuration

Create `config/calendar.php`:

```php
<?php

return [
    'providers' => [
        'google' => [
            'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
            'redirect_uri' => env('GOOGLE_CALENDAR_REDIRECT_URI', '/calendar/google/callback'),
            'scopes' => [
                'https://www.googleapis.com/auth/calendar.events',
            ],
        ],
        'outlook' => [
            'client_id' => env('OUTLOOK_CALENDAR_CLIENT_ID'),
            'client_secret' => env('OUTLOOK_CALENDAR_CLIENT_SECRET'),
            'redirect_uri' => env('OUTLOOK_CALENDAR_REDIRECT_URI', '/calendar/outlook/callback'),
        ],
    ],

    'reminders' => [
        ['method' => 'popup', 'minutes' => 60],      // 1 hour before
        ['method' => 'popup', 'minutes' => 1440],    // 1 day before
    ],

    'auto_sync_on_purchase' => env('CALENDAR_AUTO_SYNC', true),
];
```

### 3. Models

Create `app/Models/CalendarConnection.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarConnection extends Model
{
    protected $fillable = [
        'customer_id', 'tenant_id', 'provider', 'external_calendar_id',
        'calendar_name', 'access_token', 'refresh_token', 'token_expires_at',
        'auto_sync', 'sync_settings', 'last_synced_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'auto_sync' => 'boolean',
        'sync_settings' => 'array',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) return false;
        return $this->token_expires_at->isPast();
    }
}
```

Create `app/Models/CalendarEvent.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    protected $fillable = [
        'calendar_connection_id', 'order_id', 'event_id',
        'external_event_id', 'external_event_link', 'status',
        'error_message', 'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(CalendarConnection::class, 'calendar_connection_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
```

### 4. ICS Generator Service

Create `app/Services/Calendar/IcsGeneratorService.php`:

```php
<?php

namespace App\Services\Calendar;

use App\Models\Event;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Str;

class IcsGeneratorService
{
    /**
     * Generate ICS content for an event
     */
    public function generateForEvent(Event $event, ?Order $order = null): string
    {
        $uid = $this->generateUid($event, $order);
        $now = Carbon::now('UTC')->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Tixello//Event Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$now}",
            "DTSTART:{$this->formatDate($event->start_date)}",
        ];

        if ($event->end_date) {
            $lines[] = "DTEND:{$this->formatDate($event->end_date)}";
        }

        $lines[] = "SUMMARY:{$this->escape($event->name)}";

        // Description
        $description = $this->buildDescription($event, $order);
        $lines[] = "DESCRIPTION:{$this->escape($description)}";

        // Location
        if ($event->venue) {
            $location = $event->venue->name;
            if ($event->venue->address) {
                $location .= ', ' . $event->venue->address;
            }
            $lines[] = "LOCATION:{$this->escape($location)}";

            // Geo coordinates if available
            if ($event->venue->latitude && $event->venue->longitude) {
                $lines[] = "GEO:{$event->venue->latitude};{$event->venue->longitude}";
            }
        }

        // URL
        $lines[] = "URL:{$this->getEventUrl($event)}";

        // Organizer
        if ($event->tenant) {
            $lines[] = "ORGANIZER;CN={$this->escape($event->tenant->name)}:mailto:{$event->tenant->email}";
        }

        // Categories
        if ($event->category) {
            $lines[] = "CATEGORIES:{$this->escape($event->category->name)}";
        }

        // Reminders/Alarms
        foreach (config('calendar.reminders', []) as $reminder) {
            $lines = array_merge($lines, $this->generateAlarm($reminder));
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines);
    }

    /**
     * Generate ICS for an order (includes ticket info)
     */
    public function generateForOrder(Order $order): string
    {
        return $this->generateForEvent($order->event, $order);
    }

    /**
     * Generate ICS for multiple events
     */
    public function generateMultiple(array $events): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Tixello//Event Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($events as $event) {
            $lines = array_merge($lines, $this->generateEventLines($event));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines);
    }

    protected function generateEventLines(Event $event): array
    {
        $uid = $this->generateUid($event);
        $now = Carbon::now('UTC')->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$now}",
            "DTSTART:{$this->formatDate($event->start_date)}",
        ];

        if ($event->end_date) {
            $lines[] = "DTEND:{$this->formatDate($event->end_date)}";
        }

        $lines[] = "SUMMARY:{$this->escape($event->name)}";
        $lines[] = "DESCRIPTION:{$this->escape($event->description ?? '')}";

        if ($event->venue) {
            $lines[] = "LOCATION:{$this->escape($event->venue->name)}";
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    protected function generateUid(Event $event, ?Order $order = null): string
    {
        $base = "event-{$event->id}";
        if ($order) {
            $base .= "-order-{$order->id}";
        }
        return $base . '@' . parse_url(config('app.url'), PHP_URL_HOST);
    }

    protected function formatDate(Carbon $date): string
    {
        return $date->utc()->format('Ymd\THis\Z');
    }

    protected function escape(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace("\n", '\\n', $text);
        $text = str_replace(',', '\\,', $text);
        $text = str_replace(';', '\\;', $text);
        return $text;
    }

    protected function buildDescription(Event $event, ?Order $order = null): string
    {
        $parts = [];

        if ($event->short_description) {
            $parts[] = $event->short_description;
        }

        if ($order) {
            $parts[] = '';
            $parts[] = 'TICKET INFORMATION:';
            $parts[] = "Order #: {$order->order_number}";
            $parts[] = "Tickets: {$order->tickets->count()}";

            foreach ($order->tickets->groupBy('ticket_type_id') as $tickets) {
                $type = $tickets->first()->ticketType;
                $parts[] = "- {$tickets->count()}x {$type->name}";
            }
        }

        $parts[] = '';
        $parts[] = "View event: {$this->getEventUrl($event)}";

        if ($order) {
            $parts[] = "Download tickets: " . url("/orders/{$order->id}/tickets");
        }

        return implode('\n', $parts);
    }

    protected function getEventUrl(Event $event): string
    {
        return url("/events/{$event->slug}");
    }

    protected function generateAlarm(array $reminder): array
    {
        $trigger = "-PT{$reminder['minutes']}M";

        return [
            'BEGIN:VALARM',
            "TRIGGER:{$trigger}",
            'ACTION:DISPLAY',
            'DESCRIPTION:Event Reminder',
            'END:VALARM',
        ];
    }
}
```

### 5. Google Calendar Service

Create `app/Services/Calendar/GoogleCalendarService.php`:

```php
<?php

namespace App\Services\Calendar;

use App\Models\CalendarConnection;
use App\Models\CalendarEvent;
use App\Models\Event;
use App\Models\Order;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event as GoogleEvent;
use Google\Service\Calendar\EventDateTime;

class GoogleCalendarService
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setClientId(config('calendar.providers.google.client_id'));
        $this->client->setClientSecret(config('calendar.providers.google.client_secret'));
        $this->client->setRedirectUri(url(config('calendar.providers.google.redirect_uri')));
        $this->client->setScopes(config('calendar.providers.google.scopes'));
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    /**
     * Get OAuth authorization URL
     */
    public function getAuthUrl(array $state = []): string
    {
        if (!empty($state)) {
            $this->client->setState(encrypt($state));
        }

        return $this->client->createAuthUrl();
    }

    /**
     * Exchange authorization code for tokens
     */
    public function handleCallback(string $code): array
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \Exception($token['error_description'] ?? 'Failed to get access token');
        }

        return $token;
    }

    /**
     * Create a calendar connection
     */
    public function createConnection(
        int $customerId,
        int $tenantId,
        array $token
    ): CalendarConnection {
        $this->client->setAccessToken($token);

        // Get primary calendar
        $calendarService = new Calendar($this->client);
        $calendar = $calendarService->calendars->get('primary');

        return CalendarConnection::updateOrCreate(
            ['customer_id' => $customerId, 'provider' => 'google'],
            [
                'tenant_id' => $tenantId,
                'external_calendar_id' => $calendar->getId(),
                'calendar_name' => $calendar->getSummary(),
                'access_token' => encrypt($token['access_token']),
                'refresh_token' => isset($token['refresh_token'])
                    ? encrypt($token['refresh_token'])
                    : null,
                'token_expires_at' => now()->addSeconds($token['expires_in'] ?? 3600),
            ]
        );
    }

    /**
     * Sync an order to Google Calendar
     */
    public function syncOrder(Order $order, CalendarConnection $connection): CalendarEvent
    {
        $this->authenticate($connection);

        $event = $order->event;
        $calendarService = new Calendar($this->client);

        // Check if already synced
        $existingSync = CalendarEvent::where('calendar_connection_id', $connection->id)
            ->where('order_id', $order->id)
            ->first();

        $googleEvent = $this->buildGoogleEvent($event, $order);

        try {
            if ($existingSync && $existingSync->external_event_id) {
                // Update existing event
                $result = $calendarService->events->update(
                    $connection->external_calendar_id,
                    $existingSync->external_event_id,
                    $googleEvent
                );
            } else {
                // Create new event
                $result = $calendarService->events->insert(
                    $connection->external_calendar_id,
                    $googleEvent
                );
            }

            $calendarEvent = CalendarEvent::updateOrCreate(
                [
                    'calendar_connection_id' => $connection->id,
                    'order_id' => $order->id,
                ],
                [
                    'event_id' => $event->id,
                    'external_event_id' => $result->getId(),
                    'external_event_link' => $result->getHtmlLink(),
                    'status' => 'synced',
                    'synced_at' => now(),
                ]
            );

            return $calendarEvent;

        } catch (\Exception $e) {
            $calendarEvent = CalendarEvent::updateOrCreate(
                [
                    'calendar_connection_id' => $connection->id,
                    'order_id' => $order->id,
                ],
                [
                    'event_id' => $event->id,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Delete an event from Google Calendar
     */
    public function deleteEvent(CalendarEvent $calendarEvent): void
    {
        $connection = $calendarEvent->connection;
        $this->authenticate($connection);

        $calendarService = new Calendar($this->client);

        try {
            $calendarService->events->delete(
                $connection->external_calendar_id,
                $calendarEvent->external_event_id
            );

            $calendarEvent->update(['status' => 'deleted']);
        } catch (\Exception $e) {
            // Event may not exist, mark as deleted anyway
            $calendarEvent->update(['status' => 'deleted']);
        }
    }

    /**
     * Build Google Calendar event object
     */
    protected function buildGoogleEvent(Event $event, Order $order): GoogleEvent
    {
        $googleEvent = new GoogleEvent();

        $googleEvent->setSummary($event->name);
        $googleEvent->setDescription($this->buildDescription($event, $order));

        // Start time
        $start = new EventDateTime();
        $start->setDateTime($event->start_date->toRfc3339String());
        $start->setTimeZone($event->timezone ?? config('app.timezone'));
        $googleEvent->setStart($start);

        // End time
        $end = new EventDateTime();
        $endDate = $event->end_date ?? $event->start_date->addHours(2);
        $end->setDateTime($endDate->toRfc3339String());
        $end->setTimeZone($event->timezone ?? config('app.timezone'));
        $googleEvent->setEnd($end);

        // Location
        if ($event->venue) {
            $location = $event->venue->name;
            if ($event->venue->address) {
                $location .= ', ' . $event->venue->address;
            }
            $googleEvent->setLocation($location);
        }

        // Reminders
        $googleEvent->setReminders([
            'useDefault' => false,
            'overrides' => config('calendar.reminders'),
        ]);

        return $googleEvent;
    }

    protected function buildDescription(Event $event, Order $order): string
    {
        $lines = [];

        if ($event->short_description) {
            $lines[] = $event->short_description;
            $lines[] = '';
        }

        $lines[] = '🎫 TICKET INFORMATION';
        $lines[] = "Order #: {$order->order_number}";
        $lines[] = "Tickets: {$order->tickets->count()}";

        foreach ($order->tickets->groupBy('ticket_type_id') as $tickets) {
            $type = $tickets->first()->ticketType;
            $lines[] = "• {$tickets->count()}x {$type->name}";
        }

        $lines[] = '';
        $lines[] = '🔗 LINKS';
        $lines[] = "Event: " . url("/events/{$event->slug}");
        $lines[] = "Tickets: " . url("/orders/{$order->id}/tickets");

        return implode("\n", $lines);
    }

    /**
     * Authenticate with stored tokens
     */
    protected function authenticate(CalendarConnection $connection): void
    {
        $token = [
            'access_token' => decrypt($connection->access_token),
        ];

        if ($connection->refresh_token) {
            $token['refresh_token'] = decrypt($connection->refresh_token);
        }

        $this->client->setAccessToken($token);

        // Refresh if expired
        if ($this->client->isAccessTokenExpired()) {
            if ($connection->refresh_token) {
                $newToken = $this->client->fetchAccessTokenWithRefreshToken(
                    decrypt($connection->refresh_token)
                );

                $connection->update([
                    'access_token' => encrypt($newToken['access_token']),
                    'token_expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600),
                ]);

                $this->client->setAccessToken($newToken);
            } else {
                throw new \Exception('Token expired and no refresh token available');
            }
        }
    }
}
```

### 6. Controller

Create `app/Http/Controllers/Api/TenantClient/CalendarController.php`:

```php
<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\CalendarConnection;
use App\Models\Event;
use App\Models\Order;
use App\Services\Calendar\IcsGeneratorService;
use App\Services\Calendar\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CalendarController extends Controller
{
    public function __construct(
        protected IcsGeneratorService $icsGenerator,
        protected GoogleCalendarService $googleCalendar
    ) {}

    /**
     * Get available calendar providers
     */
    public function providers(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        $connections = $customer->calendarConnections ?? collect();

        return response()->json([
            'providers' => [
                [
                    'id' => 'google',
                    'name' => 'Google Calendar',
                    'connected' => $connections->where('provider', 'google')->isNotEmpty(),
                ],
                [
                    'id' => 'outlook',
                    'name' => 'Outlook Calendar',
                    'connected' => $connections->where('provider', 'outlook')->isNotEmpty(),
                ],
                [
                    'id' => 'ics',
                    'name' => 'Download (.ics)',
                    'connected' => true,
                ],
            ],
        ]);
    }

    /**
     * Connect Google Calendar
     */
    public function connectGoogle(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        $tenantId = $request->attributes->get('tenant_id');

        $authUrl = $this->googleCalendar->getAuthUrl([
            'customer_id' => $customer->id,
            'tenant_id' => $tenantId,
        ]);

        return response()->json(['url' => $authUrl]);
    }

    /**
     * Google Calendar OAuth callback
     */
    public function googleCallback(Request $request): Response
    {
        $code = $request->query('code');
        $state = $request->query('state');

        try {
            $stateData = decrypt($state);
            $token = $this->googleCalendar->handleCallback($code);

            $this->googleCalendar->createConnection(
                $stateData['customer_id'],
                $stateData['tenant_id'],
                $token
            );

            return response()->view('calendar.connected', [
                'provider' => 'Google Calendar',
                'success' => true,
            ]);

        } catch (\Exception $e) {
            return response()->view('calendar.connected', [
                'provider' => 'Google Calendar',
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Disconnect a calendar
     */
    public function disconnect(Request $request, string $provider): JsonResponse
    {
        $customer = $request->user('customer');

        $connection = CalendarConnection::where('customer_id', $customer->id)
            ->where('provider', $provider)
            ->first();

        if ($connection) {
            $connection->delete();
        }

        return response()->json(['message' => 'Disconnected']);
    }

    /**
     * Sync order to calendar
     */
    public function syncOrder(Request $request, Order $order): JsonResponse
    {
        $customer = $request->user('customer');

        // Verify order belongs to customer
        if ($order->customer_id !== $customer->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $provider = $request->input('provider', 'google');

        $connection = CalendarConnection::where('customer_id', $customer->id)
            ->where('provider', $provider)
            ->first();

        if (!$connection) {
            return response()->json(['error' => 'Calendar not connected'], 400);
        }

        try {
            if ($provider === 'google') {
                $calendarEvent = $this->googleCalendar->syncOrder($order, $connection);

                return response()->json([
                    'success' => true,
                    'calendar_link' => $calendarEvent->external_event_link,
                ]);
            }

            return response()->json(['error' => 'Provider not supported'], 400);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Download ICS file for an event
     */
    public function downloadEventIcs(Event $event): Response
    {
        $ics = $this->icsGenerator->generateForEvent($event);

        $filename = Str::slug($event->name) . '.ics';

        return response($ics)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Download ICS file for an order
     */
    public function downloadOrderIcs(Request $request, Order $order): Response
    {
        $customer = $request->user('customer');

        if ($order->customer_id !== $customer->id) {
            abort(403);
        }

        $ics = $this->icsGenerator->generateForOrder($order);

        $filename = 'ticket-' . $order->order_number . '.ics';

        return response($ics)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Get connected calendars
     */
    public function connections(Request $request): JsonResponse
    {
        $customer = $request->user('customer');

        $connections = $customer->calendarConnections->map(fn($c) => [
            'provider' => $c->provider,
            'calendar_name' => $c->calendar_name,
            'auto_sync' => $c->auto_sync,
            'last_synced_at' => $c->last_synced_at,
        ]);

        return response()->json(['connections' => $connections]);
    }
}
```

### 7. Routes

Add to `routes/api.php`:

```php
Route::prefix('tenant-client/calendar')->middleware(['tenant', 'auth:customer'])->group(function () {
    Route::get('/providers', [CalendarController::class, 'providers']);
    Route::get('/connections', [CalendarController::class, 'connections']);
    Route::post('/connect/google', [CalendarController::class, 'connectGoogle']);
    Route::delete('/disconnect/{provider}', [CalendarController::class, 'disconnect']);
    Route::post('/sync/order/{order}', [CalendarController::class, 'syncOrder']);
    Route::get('/events/{event}/ics', [CalendarController::class, 'downloadEventIcs']);
    Route::get('/orders/{order}/ics', [CalendarController::class, 'downloadOrderIcs']);
});

// Public callback route
Route::get('/calendar/google/callback', [CalendarController::class, 'googleCallback']);
```

---

## Testing Checklist

1. [ ] ICS file generation works correctly
2. [ ] ICS includes proper event details
3. [ ] ICS reminders are included
4. [ ] Google Calendar OAuth flow works
5. [ ] Calendar connection is stored securely
6. [ ] Event syncs to Google Calendar
7. [ ] Event updates are reflected in calendar
8. [ ] Token refresh works when expired
9. [ ] Disconnect removes calendar connection
10. [ ] Multiple calendars can be connected
11. [ ] Auto-sync on purchase works
