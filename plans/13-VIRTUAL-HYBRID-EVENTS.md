# Virtual & Hybrid Events Implementation Plan

## Scope & Problem Statement

### What This Feature Solves
The platform currently focuses on in-person events only. This limits:
1. **Market reach**: Can't serve virtual-only or hybrid events
2. **Revenue during restrictions**: No fallback when in-person isn't possible
3. **Geographic limitations**: Only local attendees can participate
4. **Engagement features**: No live streaming, chat, Q&A, or polls
5. **Recording/replay**: No on-demand content after events

### What This Feature Does
- Support virtual, hybrid, and in-person event types
- Integrate live streaming (YouTube, Vimeo, custom RTMP)
- Real-time chat and Q&A during streams
- Interactive polls and audience engagement
- Breakout rooms for networking
- Recording and on-demand replay
- Virtual attendance tracking and analytics

---

## Technical Implementation

### 1. Database Migrations

```php
// 2026_01_03_000060_create_virtual_events_tables.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Virtual event configuration
        Schema::create('virtual_event_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->enum('event_type', ['in_person', 'virtual', 'hybrid'])->default('in_person');
            $table->enum('stream_provider', ['youtube', 'vimeo', 'zoom', 'teams', 'custom'])->nullable();
            $table->string('stream_url')->nullable();
            $table->string('stream_key')->nullable();
            $table->text('embed_code')->nullable();
            $table->string('meeting_id')->nullable();
            $table->string('meeting_password')->nullable();
            $table->boolean('chat_enabled')->default(true);
            $table->boolean('qa_enabled')->default(true);
            $table->boolean('polls_enabled')->default(true);
            $table->boolean('recording_enabled')->default(false);
            $table->boolean('on_demand_enabled')->default(false);
            $table->integer('max_concurrent_viewers')->nullable();
            $table->integer('viewer_count')->default(0);
            $table->json('stream_settings')->nullable();
            $table->json('access_settings')->nullable();
            $table->timestamps();
        });

        // Virtual sessions (for multi-track events)
        Schema::create('virtual_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_session_id')->nullable()->constrained('virtual_sessions')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('stream_url')->nullable();
            $table->string('room_name')->nullable();
            $table->enum('session_type', ['main', 'breakout', 'networking', 'workshop'])->default('main');
            $table->integer('max_attendees')->nullable();
            $table->json('speakers')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['event_id', 'starts_at']);
        });

        // Virtual attendees (tracking)
        Schema::create('virtual_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_session_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('access_token', 64)->unique();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->integer('watch_time_seconds')->default(0);
            $table->integer('join_count')->default(0);
            $table->json('engagement_data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->unique(['virtual_session_id', 'customer_id']);
        });

        // Chat messages
        Schema::create('virtual_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_session_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->text('message');
            $table->enum('type', ['text', 'emoji', 'gif', 'system'])->default('text');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_highlighted')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->foreignId('deleted_by')->nullable()->constrained('users');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['virtual_session_id', 'created_at']);
        });

        // Polls
        Schema::create('virtual_polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_session_id')->constrained()->onDelete('cascade');
            $table->string('question');
            $table->json('options');
            $table->enum('poll_type', ['single', 'multiple', 'rating'])->default('single');
            $table->boolean('is_active')->default(false);
            $table->boolean('show_results')->default(false);
            $table->boolean('anonymous')->default(false);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->timestamps();
        });

        Schema::create('virtual_poll_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_poll_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->json('selected_options');
            $table->timestamps();

            $table->unique(['virtual_poll_id', 'customer_id']);
        });

        // Q&A
        Schema::create('virtual_qa_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_session_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->text('question');
            $table->text('answer')->nullable();
            $table->foreignId('answered_by')->nullable()->constrained('users');
            $table->timestamp('answered_at')->nullable();
            $table->integer('upvotes')->default(0);
            $table->boolean('is_highlighted')->default(false);
            $table->boolean('is_approved')->default(true);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->index(['virtual_session_id', 'upvotes']);
        });

        Schema::create('virtual_qa_upvotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_qa_question_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['virtual_qa_question_id', 'customer_id']);
        });

        // Recordings
        Schema::create('virtual_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_session_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('storage_path');
            $table->string('url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->integer('duration_seconds');
            $table->bigInteger('file_size')->nullable();
            $table->enum('status', ['processing', 'ready', 'failed', 'deleted'])->default('processing');
            $table->boolean('is_public')->default(false);
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->integer('view_count')->default(0);
            $table->timestamps();
        });

        // Recording views
        Schema::create('virtual_recording_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_recording_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('watch_time_seconds')->default(0);
            $table->integer('last_position_seconds')->default(0);
            $table->boolean('completed')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_recording_views');
        Schema::dropIfExists('virtual_recordings');
        Schema::dropIfExists('virtual_qa_upvotes');
        Schema::dropIfExists('virtual_qa_questions');
        Schema::dropIfExists('virtual_poll_responses');
        Schema::dropIfExists('virtual_polls');
        Schema::dropIfExists('virtual_chat_messages');
        Schema::dropIfExists('virtual_attendees');
        Schema::dropIfExists('virtual_sessions');
        Schema::dropIfExists('virtual_event_configs');
    }
};
```

### 2. Models

Create `app/Models/Virtual/VirtualEventConfig.php`:

```php
<?php

namespace App\Models\Virtual;

use App\Models\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VirtualEventConfig extends Model
{
    protected $fillable = [
        'event_id', 'event_type', 'stream_provider', 'stream_url', 'stream_key',
        'embed_code', 'meeting_id', 'meeting_password', 'chat_enabled', 'qa_enabled',
        'polls_enabled', 'recording_enabled', 'on_demand_enabled', 'max_concurrent_viewers',
        'viewer_count', 'stream_settings', 'access_settings',
    ];

    protected $casts = [
        'chat_enabled' => 'boolean',
        'qa_enabled' => 'boolean',
        'polls_enabled' => 'boolean',
        'recording_enabled' => 'boolean',
        'on_demand_enabled' => 'boolean',
        'stream_settings' => 'array',
        'access_settings' => 'array',
    ];

    protected $hidden = ['stream_key', 'meeting_password'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(VirtualSession::class, 'event_id', 'event_id');
    }

    public function isVirtual(): bool
    {
        return in_array($this->event_type, ['virtual', 'hybrid']);
    }

    public function isHybrid(): bool
    {
        return $this->event_type === 'hybrid';
    }

    public function getStreamEmbedUrl(): ?string
    {
        if ($this->embed_code) {
            return $this->embed_code;
        }

        return match ($this->stream_provider) {
            'youtube' => $this->getYouTubeEmbedUrl(),
            'vimeo' => $this->getVimeoEmbedUrl(),
            default => $this->stream_url,
        };
    }

    protected function getYouTubeEmbedUrl(): ?string
    {
        if (!$this->stream_url) return null;

        preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\s]+)/', $this->stream_url, $matches);
        $videoId = $matches[1] ?? null;

        return $videoId ? "https://www.youtube.com/embed/{$videoId}?autoplay=1" : null;
    }

    protected function getVimeoEmbedUrl(): ?string
    {
        if (!$this->stream_url) return null;

        preg_match('/vimeo\.com\/(\d+)/', $this->stream_url, $matches);
        $videoId = $matches[1] ?? null;

        return $videoId ? "https://player.vimeo.com/video/{$videoId}?autoplay=1" : null;
    }
}
```

Create `app/Models/Virtual/VirtualSession.php`:

```php
<?php

namespace App\Models\Virtual;

use App\Models\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VirtualSession extends Model
{
    protected $fillable = [
        'event_id', 'parent_session_id', 'title', 'description', 'starts_at', 'ends_at',
        'stream_url', 'room_name', 'session_type', 'max_attendees', 'speakers', 'order', 'is_active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'speakers' => 'array',
        'is_active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function parentSession(): BelongsTo
    {
        return $this->belongsTo(VirtualSession::class, 'parent_session_id');
    }

    public function breakoutSessions(): HasMany
    {
        return $this->hasMany(VirtualSession::class, 'parent_session_id');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(VirtualAttendee::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(VirtualChatMessage::class);
    }

    public function polls(): HasMany
    {
        return $this->hasMany(VirtualPoll::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(VirtualQaQuestion::class);
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(VirtualRecording::class);
    }

    public function isLive(): bool
    {
        $now = now();
        return $this->starts_at <= $now && $this->ends_at >= $now && $this->is_active;
    }

    public function isUpcoming(): bool
    {
        return $this->starts_at > now();
    }

    public function getCurrentViewerCount(): int
    {
        return $this->attendees()
            ->whereNotNull('joined_at')
            ->whereNull('left_at')
            ->count();
    }
}
```

### 3. Services

Create `app/Services/Virtual/VirtualEventService.php`:

```php
<?php

namespace App\Services\Virtual;

use App\Models\Event;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Virtual\VirtualEventConfig;
use App\Models\Virtual\VirtualSession;
use App\Models\Virtual\VirtualAttendee;
use Illuminate\Support\Str;

class VirtualEventService
{
    /**
     * Create or update virtual config for an event
     */
    public function configureEvent(Event $event, array $data): VirtualEventConfig
    {
        return VirtualEventConfig::updateOrCreate(
            ['event_id' => $event->id],
            $data
        );
    }

    /**
     * Create a virtual session
     */
    public function createSession(Event $event, array $data): VirtualSession
    {
        return VirtualSession::create([
            'event_id' => $event->id,
            ...$data,
        ]);
    }

    /**
     * Generate access token for a customer
     */
    public function generateAccessToken(Order $order, VirtualSession $session): VirtualAttendee
    {
        return VirtualAttendee::updateOrCreate(
            [
                'virtual_session_id' => $session->id,
                'customer_id' => $order->customer_id,
            ],
            [
                'order_id' => $order->id,
                'access_token' => Str::random(64),
            ]
        );
    }

    /**
     * Validate access token and get attendee
     */
    public function validateAccess(string $token): ?VirtualAttendee
    {
        $attendee = VirtualAttendee::where('access_token', $token)->first();

        if (!$attendee) {
            return null;
        }

        // Check if order is valid
        if ($attendee->order->status !== 'paid') {
            return null;
        }

        return $attendee;
    }

    /**
     * Record join event
     */
    public function recordJoin(VirtualAttendee $attendee, array $deviceData = []): void
    {
        $attendee->update([
            'joined_at' => now(),
            'left_at' => null,
            'join_count' => $attendee->join_count + 1,
            'ip_address' => $deviceData['ip'] ?? request()->ip(),
            'user_agent' => $deviceData['user_agent'] ?? request()->userAgent(),
        ]);

        // Update viewer count
        $config = $attendee->session->event->virtualConfig;
        if ($config) {
            $config->increment('viewer_count');
        }
    }

    /**
     * Record leave event
     */
    public function recordLeave(VirtualAttendee $attendee): void
    {
        $joinedAt = $attendee->joined_at;
        $watchTime = $joinedAt ? now()->diffInSeconds($joinedAt) : 0;

        $attendee->update([
            'left_at' => now(),
            'watch_time_seconds' => $attendee->watch_time_seconds + $watchTime,
        ]);

        // Update viewer count
        $config = $attendee->session->event->virtualConfig;
        if ($config && $config->viewer_count > 0) {
            $config->decrement('viewer_count');
        }
    }

    /**
     * Get stream access info for customer
     */
    public function getStreamAccess(Order $order): array
    {
        $event = $order->event;
        $config = $event->virtualConfig;

        if (!$config || !$config->isVirtual()) {
            return ['error' => 'Not a virtual event'];
        }

        $sessions = $event->virtualSessions()
            ->where('is_active', true)
            ->orderBy('starts_at')
            ->get();

        $accessTokens = [];
        foreach ($sessions as $session) {
            $attendee = $this->generateAccessToken($order, $session);
            $accessTokens[$session->id] = $attendee->access_token;
        }

        return [
            'event' => $event,
            'config' => $config,
            'sessions' => $sessions,
            'access_tokens' => $accessTokens,
        ];
    }

    /**
     * Get live session data
     */
    public function getLiveSessionData(VirtualSession $session, VirtualAttendee $attendee): array
    {
        return [
            'session' => $session,
            'stream_url' => $session->stream_url ?? $session->event->virtualConfig->getStreamEmbedUrl(),
            'chat_enabled' => $session->event->virtualConfig->chat_enabled,
            'qa_enabled' => $session->event->virtualConfig->qa_enabled,
            'polls_enabled' => $session->event->virtualConfig->polls_enabled,
            'viewer_count' => $session->getCurrentViewerCount(),
            'is_live' => $session->isLive(),
        ];
    }
}
```

Create `app/Services/Virtual/VirtualChatService.php`:

```php
<?php

namespace App\Services\Virtual;

use App\Models\Virtual\VirtualSession;
use App\Models\Virtual\VirtualChatMessage;
use App\Models\Customer;
use App\Events\Virtual\ChatMessageSent;
use Illuminate\Support\Collection;

class VirtualChatService
{
    /**
     * Send a chat message
     */
    public function sendMessage(VirtualSession $session, Customer $customer, string $message, string $type = 'text'): VirtualChatMessage
    {
        // Validate message
        if (strlen($message) > 500) {
            throw new \Exception('Message too long');
        }

        $chatMessage = VirtualChatMessage::create([
            'virtual_session_id' => $session->id,
            'customer_id' => $customer->id,
            'message' => $message,
            'type' => $type,
        ]);

        // Broadcast to other viewers
        event(new ChatMessageSent($chatMessage));

        return $chatMessage;
    }

    /**
     * Get recent messages
     */
    public function getMessages(VirtualSession $session, int $limit = 50, ?int $beforeId = null): Collection
    {
        $query = VirtualChatMessage::where('virtual_session_id', $session->id)
            ->where('is_deleted', false)
            ->with('customer:id,first_name,last_name,avatar_url')
            ->orderBy('id', 'desc')
            ->limit($limit);

        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }

        return $query->get()->reverse()->values();
    }

    /**
     * Pin a message
     */
    public function pinMessage(VirtualChatMessage $message): void
    {
        // Unpin other messages
        VirtualChatMessage::where('virtual_session_id', $message->virtual_session_id)
            ->where('is_pinned', true)
            ->update(['is_pinned' => false]);

        $message->update(['is_pinned' => true]);
    }

    /**
     * Delete a message (moderator action)
     */
    public function deleteMessage(VirtualChatMessage $message, int $deletedBy): void
    {
        $message->update([
            'is_deleted' => true,
            'deleted_by' => $deletedBy,
            'deleted_at' => now(),
        ]);
    }
}
```

Create `app/Services/Virtual/VirtualPollService.php`:

```php
<?php

namespace App\Services\Virtual;

use App\Models\Virtual\VirtualSession;
use App\Models\Virtual\VirtualPoll;
use App\Models\Virtual\VirtualPollResponse;
use App\Models\Customer;
use App\Events\Virtual\PollOpened;
use App\Events\Virtual\PollClosed;
use App\Events\Virtual\PollResultsUpdated;

class VirtualPollService
{
    /**
     * Create a poll
     */
    public function createPoll(VirtualSession $session, array $data): VirtualPoll
    {
        return VirtualPoll::create([
            'virtual_session_id' => $session->id,
            'question' => $data['question'],
            'options' => $data['options'],
            'poll_type' => $data['poll_type'] ?? 'single',
            'anonymous' => $data['anonymous'] ?? false,
            'duration_seconds' => $data['duration_seconds'] ?? null,
        ]);
    }

    /**
     * Open a poll for responses
     */
    public function openPoll(VirtualPoll $poll): void
    {
        $poll->update([
            'is_active' => true,
            'opened_at' => now(),
        ]);

        event(new PollOpened($poll));

        // Auto-close after duration
        if ($poll->duration_seconds) {
            dispatch(function () use ($poll) {
                $this->closePoll($poll->fresh());
            })->delay(now()->addSeconds($poll->duration_seconds));
        }
    }

    /**
     * Close a poll
     */
    public function closePoll(VirtualPoll $poll): void
    {
        if (!$poll->is_active) {
            return;
        }

        $poll->update([
            'is_active' => false,
            'closed_at' => now(),
        ]);

        event(new PollClosed($poll));
    }

    /**
     * Submit a response
     */
    public function submitResponse(VirtualPoll $poll, Customer $customer, array $selectedOptions): VirtualPollResponse
    {
        if (!$poll->is_active) {
            throw new \Exception('Poll is closed');
        }

        // Validate options
        $validOptions = collect($poll->options)->pluck('id')->toArray();
        foreach ($selectedOptions as $option) {
            if (!in_array($option, $validOptions)) {
                throw new \Exception('Invalid option');
            }
        }

        // Single choice validation
        if ($poll->poll_type === 'single' && count($selectedOptions) > 1) {
            throw new \Exception('Only one option allowed');
        }

        $response = VirtualPollResponse::updateOrCreate(
            [
                'virtual_poll_id' => $poll->id,
                'customer_id' => $customer->id,
            ],
            [
                'selected_options' => $selectedOptions,
            ]
        );

        // Broadcast updated results
        event(new PollResultsUpdated($poll));

        return $response;
    }

    /**
     * Get poll results
     */
    public function getResults(VirtualPoll $poll): array
    {
        $responses = $poll->responses;
        $totalResponses = $responses->count();

        $results = collect($poll->options)->map(function ($option) use ($responses) {
            $count = $responses->filter(function ($r) use ($option) {
                return in_array($option['id'], $r->selected_options);
            })->count();

            return [
                'id' => $option['id'],
                'text' => $option['text'],
                'count' => $count,
                'percentage' => $totalResponses > 0 ? round(($count / $totalResponses) * 100, 1) : 0,
            ];
        });

        return [
            'total_responses' => $totalResponses,
            'results' => $results,
        ];
    }
}
```

Create `app/Services/Virtual/VirtualQaService.php`:

```php
<?php

namespace App\Services\Virtual;

use App\Models\Virtual\VirtualSession;
use App\Models\Virtual\VirtualQaQuestion;
use App\Models\Virtual\VirtualQaUpvote;
use App\Models\Customer;
use App\Events\Virtual\QuestionAsked;
use App\Events\Virtual\QuestionAnswered;

class VirtualQaService
{
    /**
     * Ask a question
     */
    public function askQuestion(VirtualSession $session, Customer $customer, string $question): VirtualQaQuestion
    {
        $qaQuestion = VirtualQaQuestion::create([
            'virtual_session_id' => $session->id,
            'customer_id' => $customer->id,
            'question' => $question,
        ]);

        event(new QuestionAsked($qaQuestion));

        return $qaQuestion;
    }

    /**
     * Answer a question
     */
    public function answerQuestion(VirtualQaQuestion $question, string $answer, int $answeredBy): void
    {
        $question->update([
            'answer' => $answer,
            'answered_by' => $answeredBy,
            'answered_at' => now(),
        ]);

        event(new QuestionAnswered($question));
    }

    /**
     * Upvote a question
     */
    public function upvote(VirtualQaQuestion $question, Customer $customer): bool
    {
        $existing = VirtualQaUpvote::where('virtual_qa_question_id', $question->id)
            ->where('customer_id', $customer->id)
            ->first();

        if ($existing) {
            // Remove upvote
            $existing->delete();
            $question->decrement('upvotes');
            return false;
        }

        VirtualQaUpvote::create([
            'virtual_qa_question_id' => $question->id,
            'customer_id' => $customer->id,
        ]);

        $question->increment('upvotes');
        return true;
    }

    /**
     * Get questions for session
     */
    public function getQuestions(VirtualSession $session, string $sort = 'popular'): \Illuminate\Support\Collection
    {
        $query = VirtualQaQuestion::where('virtual_session_id', $session->id)
            ->where('is_hidden', false)
            ->with('customer:id,first_name,last_name');

        return match ($sort) {
            'popular' => $query->orderBy('upvotes', 'desc')->orderBy('created_at', 'desc')->get(),
            'recent' => $query->orderBy('created_at', 'desc')->get(),
            'answered' => $query->whereNotNull('answered_at')->orderBy('answered_at', 'desc')->get(),
            'unanswered' => $query->whereNull('answered_at')->orderBy('upvotes', 'desc')->get(),
            default => $query->orderBy('created_at', 'desc')->get(),
        };
    }

    /**
     * Highlight a question
     */
    public function highlightQuestion(VirtualQaQuestion $question): void
    {
        // Unhighlight others
        VirtualQaQuestion::where('virtual_session_id', $question->virtual_session_id)
            ->where('is_highlighted', true)
            ->update(['is_highlighted' => false]);

        $question->update(['is_highlighted' => true]);
    }
}
```

### 4. Controller

Create `app/Http/Controllers/Api/TenantClient/VirtualEventController.php`:

```php
<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Virtual\VirtualSession;
use App\Services\Virtual\VirtualEventService;
use App\Services\Virtual\VirtualChatService;
use App\Services\Virtual\VirtualPollService;
use App\Services\Virtual\VirtualQaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VirtualEventController extends Controller
{
    public function __construct(
        protected VirtualEventService $eventService,
        protected VirtualChatService $chatService,
        protected VirtualPollService $pollService,
        protected VirtualQaService $qaService
    ) {}

    /**
     * Get virtual access for an order
     */
    public function getAccess(Request $request, Order $order): JsonResponse
    {
        $customer = $request->user('customer');

        if ($order->customer_id !== $customer->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $access = $this->eventService->getStreamAccess($order);

        if (isset($access['error'])) {
            return response()->json($access, 400);
        }

        return response()->json($access);
    }

    /**
     * Join a session
     */
    public function joinSession(Request $request, string $accessToken): JsonResponse
    {
        $attendee = $this->eventService->validateAccess($accessToken);

        if (!$attendee) {
            return response()->json(['error' => 'Invalid access token'], 403);
        }

        $this->eventService->recordJoin($attendee, [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $sessionData = $this->eventService->getLiveSessionData($attendee->session, $attendee);

        return response()->json($sessionData);
    }

    /**
     * Leave a session
     */
    public function leaveSession(string $accessToken): JsonResponse
    {
        $attendee = $this->eventService->validateAccess($accessToken);

        if ($attendee) {
            $this->eventService->recordLeave($attendee);
        }

        return response()->json(['message' => 'Left session']);
    }

    /**
     * Send chat message
     */
    public function sendChatMessage(Request $request, VirtualSession $session): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:500']);

        $customer = $request->user('customer');

        $message = $this->chatService->sendMessage($session, $customer, $request->message);

        return response()->json($message);
    }

    /**
     * Get chat messages
     */
    public function getChatMessages(Request $request, VirtualSession $session): JsonResponse
    {
        $messages = $this->chatService->getMessages(
            $session,
            $request->input('limit', 50),
            $request->input('before_id')
        );

        return response()->json(['messages' => $messages]);
    }

    /**
     * Get active polls
     */
    public function getPolls(VirtualSession $session): JsonResponse
    {
        $polls = $session->polls()
            ->where(function ($q) {
                $q->where('is_active', true)->orWhere('show_results', true);
            })
            ->get()
            ->map(fn($poll) => [
                'poll' => $poll,
                'results' => $poll->show_results ? $this->pollService->getResults($poll) : null,
            ]);

        return response()->json(['polls' => $polls]);
    }

    /**
     * Submit poll response
     */
    public function submitPollResponse(Request $request, VirtualSession $session, int $pollId): JsonResponse
    {
        $request->validate(['options' => 'required|array']);

        $poll = $session->polls()->findOrFail($pollId);
        $customer = $request->user('customer');

        $response = $this->pollService->submitResponse($poll, $customer, $request->options);

        return response()->json(['response' => $response]);
    }

    /**
     * Get Q&A questions
     */
    public function getQuestions(Request $request, VirtualSession $session): JsonResponse
    {
        $questions = $this->qaService->getQuestions($session, $request->input('sort', 'popular'));

        return response()->json(['questions' => $questions]);
    }

    /**
     * Ask a question
     */
    public function askQuestion(Request $request, VirtualSession $session): JsonResponse
    {
        $request->validate(['question' => 'required|string|max:500']);

        $customer = $request->user('customer');
        $question = $this->qaService->askQuestion($session, $customer, $request->question);

        return response()->json($question);
    }

    /**
     * Upvote a question
     */
    public function upvoteQuestion(Request $request, VirtualSession $session, int $questionId): JsonResponse
    {
        $question = $session->questions()->findOrFail($questionId);
        $customer = $request->user('customer');

        $upvoted = $this->qaService->upvote($question, $customer);

        return response()->json([
            'upvoted' => $upvoted,
            'upvotes' => $question->fresh()->upvotes,
        ]);
    }
}
```

### 5. Routes

```php
Route::prefix('tenant-client/virtual')->middleware(['tenant', 'auth:customer'])->group(function () {
    Route::get('/orders/{order}/access', [VirtualEventController::class, 'getAccess']);
    Route::post('/sessions/join/{accessToken}', [VirtualEventController::class, 'joinSession']);
    Route::post('/sessions/leave/{accessToken}', [VirtualEventController::class, 'leaveSession']);

    Route::prefix('/sessions/{session}')->group(function () {
        // Chat
        Route::get('/chat', [VirtualEventController::class, 'getChatMessages']);
        Route::post('/chat', [VirtualEventController::class, 'sendChatMessage']);

        // Polls
        Route::get('/polls', [VirtualEventController::class, 'getPolls']);
        Route::post('/polls/{pollId}/respond', [VirtualEventController::class, 'submitPollResponse']);

        // Q&A
        Route::get('/questions', [VirtualEventController::class, 'getQuestions']);
        Route::post('/questions', [VirtualEventController::class, 'askQuestion']);
        Route::post('/questions/{questionId}/upvote', [VirtualEventController::class, 'upvoteQuestion']);
    });
});
```

### 6. WebSocket Events

```php
// app/Events/Virtual/ChatMessageSent.php
class ChatMessageSent implements ShouldBroadcast
{
    public function __construct(public VirtualChatMessage $message) {}

    public function broadcastOn(): Channel
    {
        return new Channel('session.' . $this->message->virtual_session_id . '.chat');
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'message' => $this->message->message,
            'customer' => [
                'id' => $this->message->customer->id,
                'name' => $this->message->customer->full_name,
                'avatar' => $this->message->customer->avatar_url,
            ],
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
```

---

## Testing Checklist

1. [ ] Virtual event config is created correctly
2. [ ] Sessions can be created with schedules
3. [ ] Access tokens are generated for ticket holders
4. [ ] Join/leave tracking works
5. [ ] Chat messages send and broadcast
6. [ ] Polls open, accept responses, close
7. [ ] Poll results calculate correctly
8. [ ] Q&A questions can be asked
9. [ ] Upvoting works (toggle)
10. [ ] Viewer count updates in real-time
11. [ ] Stream embed URLs generate correctly
12. [ ] Recordings are accessible after event
