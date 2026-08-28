<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\Chat\ChatCannedResponse;
use App\Models\Chat\ChatConversation;
use App\Models\Chat\ChatOperatorStatus;
use App\Services\Chat\ChatConversationService;
use App\Services\Chat\ChatPresenceService;
use App\Services\Chat\ChatRoutingService;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Operator console for the `live-chat` microservice. Three panes: queue +
 * active conversations, the selected thread, and the visitor context. Presence
 * (online/away/offline) and capacity are managed here. F1 refreshes via Livewire
 * polling; F3 will push updates over Reverb.
 */
class ChatConsole extends Page
{
    use HasMarketplaceContext;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Chat live';
    protected static UnitEnum|string|null $navigationGroup = 'Chat';
    protected static ?int $navigationSort = 5;
    protected static ?string $slug = 'chat-console';
    protected string $view = 'filament.marketplace.pages.chat-console';

    public string $presence = ChatOperatorStatus::PRESENCE_OFFLINE;
    public ?string $activeReference = null;

    public function getTitle(): string
    {
        return 'Chat live';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('live-chat');
    }

    public static function canAccess(): bool
    {
        return static::marketplaceHasMicroservice('live-chat');
    }

    public static function getNavigationBadge(): ?string
    {
        $clientId = static::getMarketplaceClientId();
        if (!$clientId) {
            return null;
        }
        $count = ChatConversation::query()
            ->where('marketplace_client_id', $clientId)
            ->whereIn('status', [ChatConversation::STATUS_QUEUED, ChatConversation::STATUS_OFFLINE_MESSAGE])
            ->count();
        return $count > 0 ? (string) $count : null;
    }

    public function mount(): void
    {
        abort_unless(static::marketplaceHasMicroservice('live-chat'), 404);

        $adminId = Auth::guard('marketplace_admin')->id();
        $status = ChatOperatorStatus::query()->where('marketplace_admin_id', $adminId)->first();
        $this->presence = $status?->presence ?? ChatOperatorStatus::PRESENCE_OFFLINE;
    }

    // -------- Presence --------

    public function goOnline(): void
    {
        $this->setPresence(ChatOperatorStatus::PRESENCE_ONLINE);
    }

    public function goAway(): void
    {
        $this->setPresence(ChatOperatorStatus::PRESENCE_AWAY);
    }

    public function goOffline(): void
    {
        app(ChatPresenceService::class)->goOffline((int) Auth::guard('marketplace_admin')->id());
        $this->presence = ChatOperatorStatus::PRESENCE_OFFLINE;
    }

    private function setPresence(string $presence): void
    {
        $clientId = static::getMarketplaceClientId();
        $adminId = (int) Auth::guard('marketplace_admin')->id();
        if (!$clientId || !$adminId) {
            return;
        }
        app(ChatPresenceService::class)->heartbeat($clientId, $adminId, $presence);
        $this->presence = $presence;
    }

    /**
     * Called by the view's wire:poll to keep presence warm while the console is
     * open and the operator is online.
     */
    public function heartbeat(): void
    {
        if ($this->presence === ChatOperatorStatus::PRESENCE_ONLINE) {
            $this->setPresence(ChatOperatorStatus::PRESENCE_ONLINE);
        }
    }

    // -------- Conversation actions --------

    public function select(string $reference): void
    {
        $this->activeReference = $reference;
        $this->reply = '';
        // Mark opener messages as read.
        $conversation = $this->findConversation($reference);
        if ($conversation) {
            $conversation->publicMessages()
                ->where('author_type', '!=', 'staff')
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }
    }

    public function claim(string $reference): void
    {
        $conversation = $this->findConversation($reference);
        if (!$conversation) {
            return;
        }
        app(ChatRoutingService::class)->assignTo($conversation, (int) Auth::guard('marketplace_admin')->id());
        $this->activeReference = $reference;
    }

    /**
     * Send an operator reply. The message text comes from the Alpine-managed
     * (wire:ignore) composer as a parameter, so Livewire polling never clobbers
     * the input focus while the operator is typing.
     */
    public function sendReply(string $text, bool $internal = false): void
    {
        $body = trim($text);
        if ($body === '' || !$this->activeReference) {
            return;
        }

        $conversation = $this->findConversation($this->activeReference);
        if (!$conversation || $conversation->isClosed()) {
            return;
        }

        $admin = Auth::guard('marketplace_admin')->user();

        // Auto-claim on first reply if unassigned.
        if (!$conversation->assigned_to_marketplace_admin_id) {
            app(ChatRoutingService::class)->assignTo($conversation, (int) $admin->id);
            $conversation->refresh();
        }

        app(ChatConversationService::class)->postOperatorMessage(
            $conversation,
            (int) $admin->id,
            (string) $admin->name,
            $body,
            $internal,
        );
    }

    public function resolve(string $reference): void
    {
        $conversation = $this->findConversation($reference);
        if (!$conversation) {
            return;
        }
        app(ChatConversationService::class)->resolve($conversation);
        if ($this->activeReference === $reference) {
            $this->activeReference = null;
        }
    }

    /**
     * Transfer the active conversation to another operator.
     */
    public function transfer(string $reference, int $adminId): void
    {
        $conversation = $this->findConversation($reference);
        if (!$conversation || $adminId <= 0) {
            return;
        }
        app(ChatRoutingService::class)->assignTo($conversation, $adminId);
        app(ChatConversationService::class)->postSystemMessage(
            $conversation,
            'Conversație transferată către alt operator.'
        );
    }

    // -------- View data --------

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $clientId = static::getMarketplaceClientId();
        $adminId = (int) Auth::guard('marketplace_admin')->id();

        if (!$clientId) {
            return [
                'queue' => collect(), 'mine' => collect(), 'others' => collect(),
                'offline' => collect(), 'all' => collect(), 'active' => null, 'messages' => collect(),
                'canned' => collect(), 'operators' => collect(), 'stats' => [], 'eventTitle' => null,
            ];
        }

        $base = fn () => ChatConversation::query()->where('marketplace_client_id', $clientId);

        $queue = $base()->where('status', ChatConversation::STATUS_QUEUED)
            ->orderBy('queued_at')->limit(50)->get();

        // Messages left while no operator was available (out of hours / offline).
        $offline = $base()->where('status', ChatConversation::STATUS_OFFLINE_MESSAGE)
            ->orderByDesc('last_activity_at')->limit(50)->get();

        $mine = $base()->where('status', ChatConversation::STATUS_ACTIVE)
            ->where('assigned_to_marketplace_admin_id', $adminId)
            ->orderByDesc('last_activity_at')->get();

        $others = $base()->where('status', ChatConversation::STATUS_ACTIVE)
            ->where(function ($q) use ($adminId) {
                $q->whereNull('assigned_to_marketplace_admin_id')
                    ->orWhere('assigned_to_marketplace_admin_id', '!=', $adminId);
            })
            ->orderByDesc('last_activity_at')->limit(50)->get();

        $active = $this->activeReference ? $this->findConversation($this->activeReference) : null;
        $messages = $active
            ? $active->messages()->get() // operator sees internal notes too
            : collect();

        $canned = ChatCannedResponse::query()
            ->where('marketplace_client_id', $clientId)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('title')
            ->get();

        // Online operators available as transfer targets (excluding self).
        $operators = ChatOperatorStatus::query()
            ->where('marketplace_client_id', $clientId)
            ->where('presence', ChatOperatorStatus::PRESENCE_ONLINE)
            ->where('marketplace_admin_id', '!=', $adminId)
            ->with('operator')
            ->get()
            ->filter(fn ($s) => $s->operator !== null);

        // Personal rating: average of ratings on conversations this operator
        // handled (attributed via the assignee). This is the operator's own score.
        $myRatingQuery = $base()
            ->whereNotNull('rating')
            ->where('assigned_to_marketplace_admin_id', $adminId);
        $myRatingCount = (clone $myRatingQuery)->count();

        $stats = [
            'queued' => $queue->count(),
            'active' => $base()->where('status', ChatConversation::STATUS_ACTIVE)->count(),
            'mine' => $mine->count(),
            'avg_rating' => round((float) $base()->whereNotNull('rating')->avg('rating'), 1),
            'my_avg' => $myRatingCount ? round((float) $myRatingQuery->avg('rating'), 1) : 0,
            'my_ratings' => $myRatingCount,
        ];

        // All chats (recent) with who claimed them — the "Toate" overview.
        $all = $base()->with('assignee:id,name')
            ->orderByDesc('last_activity_at')
            ->limit(100)->get();

        $eventTitle = $active && $active->event_id ? $this->safeEventTitle($active->event_id) : null;

        return compact('queue', 'offline', 'mine', 'others', 'all', 'active', 'messages', 'canned', 'operators', 'stats', 'eventTitle');
    }

    /**
     * Best-effort event title (schema-drift safe): tolerates translatable
     * array/json titles and missing events.
     */
    private function safeEventTitle(int $eventId): ?string
    {
        try {
            $event = \App\Models\Event::query()->find($eventId);
            if (!$event) {
                return null;
            }
            $title = method_exists($event, 'getTranslation')
                ? $event->getTranslation('title', app()->getLocale())
                : ($event->title ?? null);
            if (is_array($title)) {
                $title = $title['ro'] ?? $title['en'] ?? reset($title) ?: null;
            }
            return is_string($title) ? $title : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function findConversation(string $reference): ?ChatConversation
    {
        $clientId = static::getMarketplaceClientId();
        if (!$clientId) {
            return null;
        }
        return ChatConversation::query()
            ->where('marketplace_client_id', $clientId)
            ->where('reference', $reference)
            ->first();
    }
}
