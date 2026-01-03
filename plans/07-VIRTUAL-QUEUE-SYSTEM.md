# Virtual Queue System Implementation Plan

## Scope & Problem Statement

### What This Feature Solves
High-demand events (concerts, sports finals, limited releases) cause:
1. **Server crashes**: Thousands of simultaneous requests overwhelm the system
2. **Unfair access**: Those with fast connections/bots get tickets first
3. **Customer frustration**: Constant refreshing, timeouts, and failures
4. **Scalper advantage**: Bots can make more requests than humans
5. **Poor user experience**: No visibility into wait time or position

### What This Feature Does
Implements a fair, transparent virtual queue system that:
- Assigns random queue positions to prevent race conditions
- Provides real-time position updates via WebSockets
- Shows estimated wait times
- Implements bot protection (CAPTCHA, device fingerprinting)
- Manages session timeouts for fairness
- Allows priority access for members/subscribers
- Scales to handle millions of concurrent users

---

## Technical Implementation

### 1. Database Migrations

Create `database/migrations/2026_01_03_000025_create_virtual_queue_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name')->default('General Sale');
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('queue_opens_at');
            $table->timestamp('queue_closes_at')->nullable();
            $table->integer('max_concurrent_users')->default(100);
            $table->integer('session_timeout_minutes')->default(10);
            $table->integer('max_tickets_per_session')->default(6);
            $table->boolean('priority_access_enabled')->default(false);
            $table->integer('priority_head_start_minutes')->default(15);
            $table->boolean('captcha_enabled')->default(true);
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['pending', 'registration', 'active', 'paused', 'completed'])->default('pending');
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
        });

        Schema::create('queue_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_queue_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->string('session_id', 64)->unique();
            $table->string('email')->nullable();
            $table->integer('original_position');
            $table->integer('current_position');
            $table->enum('status', ['waiting', 'ready', 'active', 'completed', 'expired', 'abandoned'])->default('waiting');
            $table->boolean('is_priority')->default(false);
            $table->timestamp('registered_at');
            $table->timestamp('queue_entered_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('device_fingerprint')->nullable();
            $table->decimal('risk_score', 5, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['virtual_queue_id', 'status', 'current_position']);
            $table->index(['session_id']);
            $table->index(['ip_address']);
        });

        Schema::create('queue_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_queue_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->integer('hour')->nullable();
            $table->integer('total_registrations')->default(0);
            $table->integer('total_entered')->default(0);
            $table->integer('total_completed')->default(0);
            $table->integer('total_abandoned')->default(0);
            $table->integer('total_expired')->default(0);
            $table->integer('peak_waiting')->default(0);
            $table->integer('avg_wait_seconds')->default(0);
            $table->integer('avg_session_seconds')->default(0);
            $table->decimal('completion_rate', 5, 2)->default(0);
            $table->integer('blocked_bots')->default(0);
            $table->timestamps();

            $table->unique(['virtual_queue_id', 'date', 'hour']);
        });

        Schema::create('queue_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64);
            $table->string('challenge_type'); // captcha, puzzle, etc
            $table->string('challenge_id');
            $table->string('expected_answer')->nullable();
            $table->boolean('solved')->default(false);
            $table->integer('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['session_id', 'solved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_challenges');
        Schema::dropIfExists('queue_analytics');
        Schema::dropIfExists('queue_entries');
        Schema::dropIfExists('virtual_queues');
    }
};
```

### 2. Models

Create `app/Models/VirtualQueue.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VirtualQueue extends Model
{
    protected $fillable = [
        'event_id', 'tenant_id', 'name', 'registration_opens_at', 'queue_opens_at',
        'queue_closes_at', 'max_concurrent_users', 'session_timeout_minutes',
        'max_tickets_per_session', 'priority_access_enabled', 'priority_head_start_minutes',
        'captcha_enabled', 'is_active', 'status', 'settings',
    ];

    protected $casts = [
        'registration_opens_at' => 'datetime',
        'queue_opens_at' => 'datetime',
        'queue_closes_at' => 'datetime',
        'priority_access_enabled' => 'boolean',
        'captcha_enabled' => 'boolean',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(QueueEntry::class);
    }

    public function waitingEntries(): HasMany
    {
        return $this->entries()->where('status', 'waiting')->orderBy('current_position');
    }

    public function activeEntries(): HasMany
    {
        return $this->entries()->where('status', 'active');
    }

    public function getWaitingCountAttribute(): int
    {
        return $this->entries()->where('status', 'waiting')->count();
    }

    public function getActiveCountAttribute(): int
    {
        return $this->entries()->where('status', 'active')->count();
    }

    public function canAcceptMore(): bool
    {
        return $this->active_count < $this->max_concurrent_users;
    }

    public function isOpen(): bool
    {
        return $this->status === 'active'
            && $this->queue_opens_at->isPast()
            && (!$this->queue_closes_at || $this->queue_closes_at->isFuture());
    }

    public function isRegistrationOpen(): bool
    {
        if (!$this->registration_opens_at) {
            return $this->isOpen();
        }

        return $this->registration_opens_at->isPast()
            && $this->queue_opens_at->isFuture();
    }
}
```

Create `app/Models/QueueEntry.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueEntry extends Model
{
    protected $fillable = [
        'virtual_queue_id', 'customer_id', 'session_id', 'email',
        'original_position', 'current_position', 'status', 'is_priority',
        'registered_at', 'queue_entered_at', 'activated_at', 'expires_at',
        'completed_at', 'ip_address', 'user_agent', 'device_fingerprint',
        'risk_score', 'metadata',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'queue_entered_at' => 'datetime',
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_priority' => 'boolean',
        'device_fingerprint' => 'array',
        'metadata' => 'array',
        'risk_score' => 'decimal:2',
    ];

    public function queue(): BelongsTo
    {
        return $this->belongsTo(VirtualQueue::class, 'virtual_queue_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getEstimatedWaitSecondsAttribute(): int
    {
        if ($this->status !== 'waiting') {
            return 0;
        }

        $queue = $this->queue;
        $avgSessionTime = $queue->session_timeout_minutes * 60;
        $throughput = $queue->max_concurrent_users / $avgSessionTime;

        return (int) ($this->current_position / max($throughput, 0.1));
    }

    public function getEstimatedWaitFormattedAttribute(): string
    {
        $seconds = $this->estimated_wait_seconds;

        if ($seconds < 60) {
            return 'Less than a minute';
        }

        $minutes = ceil($seconds / 60);

        if ($minutes < 60) {
            return "{$minutes} minute" . ($minutes > 1 ? 's' : '');
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return "{$hours} hour" . ($hours > 1 ? 's' : '') .
            ($mins > 0 ? " {$mins} min" : '');
    }
}
```

### 3. Services

Create `app/Services/Queue/VirtualQueueService.php`:

```php
<?php

namespace App\Services\Queue;

use App\Models\VirtualQueue;
use App\Models\QueueEntry;
use App\Models\Customer;
use App\Events\QueuePositionUpdated;
use App\Events\QueueEntryActivated;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class VirtualQueueService
{
    public function __construct(
        protected QueueBotProtectionService $botProtection
    ) {}

    /**
     * Create a new virtual queue for an event
     */
    public function createQueue(array $data): VirtualQueue
    {
        return VirtualQueue::create($data);
    }

    /**
     * Register for a queue (pre-registration before queue opens)
     */
    public function register(
        VirtualQueue $queue,
        ?Customer $customer = null,
        ?string $email = null,
        array $deviceData = []
    ): QueueEntry {
        if (!$queue->isRegistrationOpen() && !$queue->isOpen()) {
            throw new \Exception('Registration is not open');
        }

        // Check for existing registration
        if ($customer) {
            $existing = $queue->entries()
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['waiting', 'ready', 'active'])
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // Bot check
        $riskScore = $this->botProtection->assessRisk($deviceData);
        if ($riskScore > 0.8) {
            throw new \Exception('Access denied');
        }

        // Check priority eligibility
        $isPriority = $customer && $this->checkPriorityAccess($customer, $queue);

        $sessionId = Str::random(64);

        $entry = QueueEntry::create([
            'virtual_queue_id' => $queue->id,
            'customer_id' => $customer?->id,
            'session_id' => $sessionId,
            'email' => $email ?? $customer?->email,
            'original_position' => 0, // Will be set when queue opens
            'current_position' => 0,
            'status' => $queue->isOpen() ? 'waiting' : 'registered',
            'is_priority' => $isPriority,
            'registered_at' => now(),
            'ip_address' => $deviceData['ip'] ?? request()->ip(),
            'user_agent' => $deviceData['user_agent'] ?? request()->userAgent(),
            'device_fingerprint' => $deviceData['fingerprint'] ?? null,
            'risk_score' => $riskScore,
        ]);

        // If queue is already open, assign position
        if ($queue->isOpen()) {
            $this->assignPosition($entry);
        }

        return $entry;
    }

    /**
     * Assign positions when queue opens
     */
    public function openQueue(VirtualQueue $queue): void
    {
        // Get all registered entries
        $entries = $queue->entries()
            ->where('status', 'registered')
            ->get();

        // Separate priority and regular
        $priorityEntries = $entries->where('is_priority', true)->shuffle();
        $regularEntries = $entries->where('is_priority', false)->shuffle();

        // Assign positions (priority first)
        $position = 1;

        foreach ($priorityEntries as $entry) {
            $entry->update([
                'original_position' => $position,
                'current_position' => $position,
                'status' => 'waiting',
                'queue_entered_at' => now(),
            ]);
            $position++;
        }

        foreach ($regularEntries as $entry) {
            $entry->update([
                'original_position' => $position,
                'current_position' => $position,
                'status' => 'waiting',
                'queue_entered_at' => now(),
            ]);
            $position++;
        }

        $queue->update(['status' => 'active']);

        // Start processing
        $this->processQueue($queue);
    }

    /**
     * Assign position to a new entry (queue already open)
     */
    protected function assignPosition(QueueEntry $entry): void
    {
        $queue = $entry->queue;

        // Get current max position
        $maxPosition = $queue->entries()
            ->whereIn('status', ['waiting', 'ready', 'active'])
            ->max('current_position') ?? 0;

        $position = $maxPosition + 1;

        $entry->update([
            'original_position' => $position,
            'current_position' => $position,
            'queue_entered_at' => now(),
        ]);
    }

    /**
     * Process queue - activate next entries
     */
    public function processQueue(VirtualQueue $queue): void
    {
        // Expire old sessions
        $this->expireSessions($queue);

        // Check how many slots available
        $activeCount = $queue->active_count;
        $availableSlots = $queue->max_concurrent_users - $activeCount;

        if ($availableSlots <= 0) {
            return;
        }

        // Get next waiting entries
        $nextEntries = $queue->waitingEntries()
            ->limit($availableSlots)
            ->get();

        foreach ($nextEntries as $entry) {
            $this->activateEntry($entry);
        }

        // Update positions for remaining entries
        $this->recalculatePositions($queue);
    }

    /**
     * Activate an entry - give them access to purchase
     */
    protected function activateEntry(QueueEntry $entry): void
    {
        $queue = $entry->queue;

        $entry->update([
            'status' => 'active',
            'activated_at' => now(),
            'expires_at' => now()->addMinutes($queue->session_timeout_minutes),
        ]);

        // Broadcast activation
        event(new QueueEntryActivated($entry));
    }

    /**
     * Recalculate positions after entries are processed
     */
    protected function recalculatePositions(VirtualQueue $queue): void
    {
        $entries = $queue->waitingEntries()->get();

        $position = 1;
        foreach ($entries as $entry) {
            if ($entry->current_position !== $position) {
                $entry->update(['current_position' => $position]);
                event(new QueuePositionUpdated($entry));
            }
            $position++;
        }
    }

    /**
     * Expire old sessions
     */
    protected function expireSessions(VirtualQueue $queue): void
    {
        $expired = $queue->entries()
            ->where('status', 'active')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $entry) {
            $entry->update(['status' => 'expired']);
        }
    }

    /**
     * Get queue status for an entry
     */
    public function getStatus(string $sessionId): array
    {
        $entry = QueueEntry::where('session_id', $sessionId)->first();

        if (!$entry) {
            return ['error' => 'Session not found'];
        }

        $queue = $entry->queue;

        return [
            'status' => $entry->status,
            'position' => $entry->current_position,
            'estimated_wait' => $entry->estimated_wait_formatted,
            'estimated_wait_seconds' => $entry->estimated_wait_seconds,
            'is_priority' => $entry->is_priority,
            'expires_at' => $entry->expires_at?->toIso8601String(),
            'queue' => [
                'name' => $queue->name,
                'waiting_count' => $queue->waiting_count,
                'status' => $queue->status,
            ],
        ];
    }

    /**
     * Complete a session (user finished purchasing)
     */
    public function completeSession(string $sessionId): void
    {
        $entry = QueueEntry::where('session_id', $sessionId)->first();

        if ($entry && $entry->status === 'active') {
            $entry->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Process queue to let next person in
            $this->processQueue($entry->queue);
        }
    }

    /**
     * Abandon a session
     */
    public function abandonSession(string $sessionId): void
    {
        $entry = QueueEntry::where('session_id', $sessionId)->first();

        if ($entry && in_array($entry->status, ['waiting', 'active'])) {
            $entry->update(['status' => 'abandoned']);

            $this->processQueue($entry->queue);
        }
    }

    /**
     * Heartbeat - keep session alive
     */
    public function heartbeat(string $sessionId): bool
    {
        $entry = QueueEntry::where('session_id', $sessionId)->first();

        if (!$entry || $entry->status !== 'active') {
            return false;
        }

        // Extend expiration
        $entry->update([
            'expires_at' => now()->addMinutes($entry->queue->session_timeout_minutes),
        ]);

        return true;
    }

    /**
     * Check if customer has priority access
     */
    protected function checkPriorityAccess(Customer $customer, VirtualQueue $queue): bool
    {
        if (!$queue->priority_access_enabled) {
            return false;
        }

        // Check for active subscription
        $hasSubscription = $customer->subscriptions()
            ->where('status', 'active')
            ->exists();

        if ($hasSubscription) {
            return true;
        }

        // Check membership tier
        $membership = $customer->membership;
        if ($membership && $membership->tier->early_access) {
            return true;
        }

        return false;
    }
}
```

Create `app/Services/Queue/QueueBotProtectionService.php`:

```php
<?php

namespace App\Services\Queue;

use App\Models\QueueChallenge;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class QueueBotProtectionService
{
    /**
     * Assess risk score for a request
     */
    public function assessRisk(array $deviceData): float
    {
        $score = 0.0;

        // Check IP rate limiting
        $ip = $deviceData['ip'] ?? request()->ip();
        if ($this->isIpRateLimited($ip)) {
            $score += 0.3;
        }

        // Check device fingerprint
        $fingerprint = $deviceData['fingerprint'] ?? [];
        $score += $this->analyzeFingerprint($fingerprint);

        // Check user agent
        $userAgent = $deviceData['user_agent'] ?? request()->userAgent();
        $score += $this->analyzeUserAgent($userAgent);

        // Check for headless browser indicators
        if ($this->detectHeadlessBrowser($deviceData)) {
            $score += 0.4;
        }

        return min($score, 1.0);
    }

    /**
     * Check IP rate limiting
     */
    protected function isIpRateLimited(string $ip): bool
    {
        $key = "queue_ip:{$ip}";

        if (RateLimiter::tooManyAttempts($key, 10)) { // 10 attempts per minute
            return true;
        }

        RateLimiter::hit($key, 60);

        return false;
    }

    /**
     * Analyze device fingerprint
     */
    protected function analyzeFingerprint(array $fingerprint): float
    {
        $score = 0.0;

        // No fingerprint is suspicious
        if (empty($fingerprint)) {
            return 0.2;
        }

        // Check for common bot indicators
        if (empty($fingerprint['webgl']) || empty($fingerprint['canvas'])) {
            $score += 0.1;
        }

        // Check screen size (very small or very large is suspicious)
        $screenWidth = $fingerprint['screen_width'] ?? 0;
        if ($screenWidth < 320 || $screenWidth > 4000) {
            $score += 0.1;
        }

        // Check for automation indicators
        if (!empty($fingerprint['webdriver'])) {
            $score += 0.3;
        }

        return $score;
    }

    /**
     * Analyze user agent
     */
    protected function analyzeUserAgent(?string $userAgent): float
    {
        if (!$userAgent) {
            return 0.2;
        }

        $botIndicators = [
            'bot', 'crawler', 'spider', 'headless', 'phantom',
            'selenium', 'puppeteer', 'playwright'
        ];

        $ua = strtolower($userAgent);

        foreach ($botIndicators as $indicator) {
            if (str_contains($ua, $indicator)) {
                return 0.5;
            }
        }

        return 0.0;
    }

    /**
     * Detect headless browser
     */
    protected function detectHeadlessBrowser(array $deviceData): bool
    {
        $fingerprint = $deviceData['fingerprint'] ?? [];

        // Check navigator properties
        if (isset($fingerprint['navigator'])) {
            $nav = $fingerprint['navigator'];

            if (($nav['webdriver'] ?? false) === true) {
                return true;
            }

            if (empty($nav['plugins']) || count($nav['plugins']) === 0) {
                return true;
            }

            if (empty($nav['languages'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a CAPTCHA challenge
     */
    public function createChallenge(string $sessionId): array
    {
        // Using hCaptcha or reCAPTCHA
        $challengeId = Str::random(32);

        QueueChallenge::create([
            'session_id' => $sessionId,
            'challenge_type' => 'captcha',
            'challenge_id' => $challengeId,
            'expires_at' => now()->addMinutes(5),
        ]);

        return [
            'type' => 'captcha',
            'challenge_id' => $challengeId,
            'site_key' => config('captcha.site_key'),
        ];
    }

    /**
     * Verify a challenge response
     */
    public function verifyChallenge(string $sessionId, string $response): bool
    {
        $challenge = QueueChallenge::where('session_id', $sessionId)
            ->where('solved', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$challenge) {
            return false;
        }

        $challenge->increment('attempts');

        // Verify with CAPTCHA provider
        $verified = $this->verifyCaptchaResponse($response);

        if ($verified) {
            $challenge->update(['solved' => true]);
        }

        return $verified;
    }

    /**
     * Verify CAPTCHA response with provider
     */
    protected function verifyCaptchaResponse(string $response): bool
    {
        // hCaptcha verification
        $result = Http::asForm()->post('https://hcaptcha.com/siteverify', [
            'secret' => config('captcha.secret_key'),
            'response' => $response,
            'remoteip' => request()->ip(),
        ]);

        return $result->json('success', false);
    }
}
```

### 4. Events

Create `app/Events/QueuePositionUpdated.php`:

```php
<?php

namespace App\Events;

use App\Models\QueueEntry;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueuePositionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public QueueEntry $entry
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('queue.' . $this->entry->session_id);
    }

    public function broadcastAs(): string
    {
        return 'position.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'position' => $this->entry->current_position,
            'estimated_wait' => $this->entry->estimated_wait_formatted,
            'estimated_wait_seconds' => $this->entry->estimated_wait_seconds,
        ];
    }
}
```

Create `app/Events/QueueEntryActivated.php`:

```php
<?php

namespace App\Events;

use App\Models\QueueEntry;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueueEntryActivated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public QueueEntry $entry
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('queue.' . $this->entry->session_id);
    }

    public function broadcastAs(): string
    {
        return 'entry.activated';
    }

    public function broadcastWith(): array
    {
        return [
            'status' => 'active',
            'expires_at' => $this->entry->expires_at->toIso8601String(),
            'checkout_url' => url("/events/{$this->entry->queue->event_id}/checkout?session={$this->entry->session_id}"),
        ];
    }
}
```

### 5. Controller

Create `app/Http/Controllers/Api/QueueController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VirtualQueue;
use App\Services\Queue\VirtualQueueService;
use App\Services\Queue\QueueBotProtectionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QueueController extends Controller
{
    public function __construct(
        protected VirtualQueueService $queueService,
        protected QueueBotProtectionService $botProtection
    ) {}

    /**
     * Get queue info for an event
     */
    public function getQueueInfo(int $eventId): JsonResponse
    {
        $queue = VirtualQueue::where('event_id', $eventId)
            ->where('is_active', true)
            ->first();

        if (!$queue) {
            return response()->json(['queue_required' => false]);
        }

        return response()->json([
            'queue_required' => true,
            'queue' => [
                'id' => $queue->id,
                'name' => $queue->name,
                'status' => $queue->status,
                'opens_at' => $queue->queue_opens_at->toIso8601String(),
                'registration_opens_at' => $queue->registration_opens_at?->toIso8601String(),
                'is_open' => $queue->isOpen(),
                'is_registration_open' => $queue->isRegistrationOpen(),
                'waiting_count' => $queue->waiting_count,
                'captcha_required' => $queue->captcha_enabled,
            ],
        ]);
    }

    /**
     * Join the queue
     */
    public function join(Request $request, int $eventId): JsonResponse
    {
        $request->validate([
            'email' => 'nullable|email',
            'fingerprint' => 'nullable|array',
            'captcha_response' => 'nullable|string',
        ]);

        $queue = VirtualQueue::where('event_id', $eventId)
            ->where('is_active', true)
            ->firstOrFail();

        // Verify CAPTCHA if required
        if ($queue->captcha_enabled) {
            if (!$request->captcha_response) {
                return response()->json([
                    'require_captcha' => true,
                    'site_key' => config('captcha.site_key'),
                ], 403);
            }

            if (!$this->botProtection->verifyCaptchaResponse($request->captcha_response)) {
                return response()->json(['error' => 'CAPTCHA verification failed'], 403);
            }
        }

        try {
            $entry = $this->queueService->register(
                $queue,
                $request->user('customer'),
                $request->email,
                [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'fingerprint' => $request->fingerprint,
                ]
            );

            return response()->json([
                'session_id' => $entry->session_id,
                'status' => $entry->status,
                'position' => $entry->current_position,
                'estimated_wait' => $entry->estimated_wait_formatted,
                'is_priority' => $entry->is_priority,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get queue status
     */
    public function getStatus(string $sessionId): JsonResponse
    {
        $status = $this->queueService->getStatus($sessionId);

        if (isset($status['error'])) {
            return response()->json($status, 404);
        }

        return response()->json($status);
    }

    /**
     * Leave the queue
     */
    public function leave(string $sessionId): JsonResponse
    {
        $this->queueService->abandonSession($sessionId);

        return response()->json(['message' => 'Left the queue']);
    }

    /**
     * Heartbeat to keep session alive
     */
    public function heartbeat(string $sessionId): JsonResponse
    {
        $success = $this->queueService->heartbeat($sessionId);

        if (!$success) {
            return response()->json(['error' => 'Session expired or invalid'], 400);
        }

        return response()->json(['message' => 'Session extended']);
    }

    /**
     * Complete session (called after successful purchase)
     */
    public function complete(string $sessionId): JsonResponse
    {
        $this->queueService->completeSession($sessionId);

        return response()->json(['message' => 'Session completed']);
    }
}
```

### 6. Routes

Add to `routes/api.php`:

```php
Route::prefix('queue')->group(function () {
    Route::get('/event/{eventId}', [QueueController::class, 'getQueueInfo']);
    Route::post('/event/{eventId}/join', [QueueController::class, 'join']);
    Route::get('/{sessionId}/status', [QueueController::class, 'getStatus']);
    Route::delete('/{sessionId}/leave', [QueueController::class, 'leave']);
    Route::post('/{sessionId}/heartbeat', [QueueController::class, 'heartbeat']);
    Route::post('/{sessionId}/complete', [QueueController::class, 'complete']);
});
```

### 7. Scheduled Job

Create `app/Jobs/ProcessVirtualQueues.php`:

```php
<?php

namespace App\Jobs;

use App\Models\VirtualQueue;
use App\Services\Queue\VirtualQueueService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessVirtualQueues implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(VirtualQueueService $service): void
    {
        $activeQueues = VirtualQueue::where('status', 'active')->get();

        foreach ($activeQueues as $queue) {
            $service->processQueue($queue);
        }
    }
}
```

Add to scheduler:

```php
Schedule::job(new ProcessVirtualQueues())->everyTenSeconds();
```

---

## Testing Checklist

1. [ ] Queue creation works
2. [ ] Pre-registration before queue opens
3. [ ] Random position assignment when queue opens
4. [ ] Priority users get earlier positions
5. [ ] Real-time position updates via WebSocket
6. [ ] Session activation when slot available
7. [ ] Session timeout and expiration
8. [ ] Heartbeat extends session
9. [ ] CAPTCHA challenge works
10. [ ] Bot detection blocks suspicious requests
11. [ ] Rate limiting prevents abuse
12. [ ] Queue completion after purchase
13. [ ] Analytics are recorded
