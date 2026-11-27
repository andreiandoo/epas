<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Chat\ChatCannedResponse;
use App\Models\Chat\ChatConversation;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatOperatorStatus;
use App\Models\MarketplaceAdmin;
use App\Services\Chat\ChatConversationService;
use App\Services\Chat\ChatRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Dock-ul de chat din colțul panoului: ferestre lipite jos, ca pe Facebook.
 * Folosește aceleași servicii ca ChatConsole (preluare, răspuns, transfer, rezolvare).
 */
class ChatDockController extends Controller
{
    private const MAX_WINDOWS = 6;

    public function state(): JsonResponse
    {
        $admin = $this->admin();
        if (! $admin) {
            return response()->json(['ok' => false], 403);
        }

        $clientId = (int) $admin->marketplace_client_id;
        $windows = collect((array) data_get($admin->settings, 'chat_dock', []))
            ->filter(fn ($w) => is_array($w) && ! empty($w['ref']))
            ->map(fn ($w) => ['ref' => (string) $w['ref'], 'min' => (bool) ($w['min'] ?? false)])
            ->values();

        // Doar conversațiile care încă există.
        $existing = ChatConversation::query()
            ->where('marketplace_client_id', $clientId)
            ->whereIn('reference', $windows->pluck('ref'))
            ->pluck('reference')
            ->all();

        return response()->json([
            'ok' => true,
            'me' => ['id' => (int) $admin->id, 'name' => $this->cleanName($admin->name)],
            'windows' => $windows->filter(fn ($w) => in_array($w['ref'], $existing, true))->values(),
            'canned' => ChatCannedResponse::query()
                ->where('marketplace_client_id', $clientId)
                ->where('is_active', true)
                ->orderBy('sort_order')->orderBy('title')
                ->get(['title', 'body'])
                ->map(fn ($c) => ['title' => (string) $c->title, 'body' => (string) $c->body]),
        ]);
    }

    public function saveState(Request $request): JsonResponse
    {
        $admin = $this->admin();
        if (! $admin) {
            return response()->json(['ok' => false], 403);
        }

        $windows = collect((array) $request->input('windows', []))
            ->filter(fn ($w) => is_array($w) && is_string($w['ref'] ?? null) && strlen($w['ref']) <= 64)
            ->map(fn ($w) => ['ref' => $w['ref'], 'min' => (bool) ($w['min'] ?? false)])
            ->unique('ref')
            ->take(self::MAX_WINDOWS)
            ->values()
            ->all();

        $settings = (array) ($admin->settings ?? []);
        $settings['chat_dock'] = $windows;
        $admin->forceFill(['settings' => $settings])->saveQuietly();

        return response()->json(['ok' => true]);
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        $admin = $this->admin();
        $conversation = $admin ? $this->find($admin, $reference) : null;
        if (! $conversation) {
            return response()->json(['ok' => false, 'message' => 'Conversația nu mai există.'], 404);
        }

        $isMine = (int) $conversation->assigned_to_marketplace_admin_id === (int) $admin->id;

        // Marcăm citit doar când fereastra e deschisă și conversația e a operatorului.
        if ($request->boolean('read') && $isMine) {
            $conversation->publicMessages()
                ->where('author_type', '!=', 'staff')
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        $after = (int) $request->query('after', 0);
        $query = $conversation->messages()->orderBy('id');
        if ($after > 0) {
            $query->where('id', '>', $after);
            $messages = $query->limit(200)->get();
        } else {
            $messages = $conversation->messages()->orderByDesc('id')->limit(60)->get()->reverse()->values();
        }

        $owner = $conversation->assigned_to_marketplace_admin_id
            ? MarketplaceAdmin::query()->whereKey($conversation->assigned_to_marketplace_admin_id)->value('name')
            : null;

        return response()->json([
            'ok' => true,
            'conversation' => [
                'id' => (int) $conversation->id,
                'ref' => (string) $conversation->reference,
                'status' => (string) $conversation->status,
                'closed' => $conversation->isClosed(),
                'name' => $conversation->guest_name ?: (data_get($conversation->context, 'visitor_name') ?: 'Vizitator'),
                'type' => $this->visitorLabel($conversation->visitor_type),
                'subject' => $conversation->subject,
                'owner_id' => $conversation->assigned_to_marketplace_admin_id ? (int) $conversation->assigned_to_marketplace_admin_id : null,
                'owner_name' => $owner ? $this->cleanName($owner) : null,
                'mine' => $isMine,
                'console_url' => '/marketplace/chat-console?c=' . urlencode((string) $conversation->reference),
                'attach_url' => route('marketplace.chat.attach', ['conversation' => $conversation->id], false),
                'unread' => (int) $conversation->publicMessages()
                    ->where('author_type', '!=', 'staff')
                    ->whereNull('read_at')
                    ->count(),
            ],
            'messages' => $messages->map(fn (ChatMessage $m) => $this->presentMessage($conversation, $m))->values(),
            'operators' => $this->operators($admin),
        ]);
    }

    public function reply(Request $request, string $reference): JsonResponse
    {
        $admin = $this->admin();
        $conversation = $admin ? $this->find($admin, $reference) : null;
        if (! $conversation || $conversation->isClosed()) {
            return response()->json(['ok' => false, 'message' => 'Conversația e închisă.'], 422);
        }

        $body = trim((string) $request->input('text', ''));
        if ($body === '') {
            return response()->json(['ok' => false, 'message' => 'Mesajul e gol.'], 422);
        }
        $internal = $request->boolean('internal');

        // Ca în consolă: primul răspuns preia conversația dacă e nealocată.
        if (! $conversation->assigned_to_marketplace_admin_id) {
            app(ChatRoutingService::class)->assignTo($conversation, (int) $admin->id);
            $conversation->refresh();
        }

        app(ChatConversationService::class)->postOperatorMessage(
            $conversation,
            (int) $admin->id,
            (string) $admin->name,
            mb_substr($body, 0, 5000),
            $internal,
        );

        return response()->json(['ok' => true]);
    }

    public function claim(string $reference): JsonResponse
    {
        $admin = $this->admin();
        $conversation = $admin ? $this->find($admin, $reference) : null;
        if (! $conversation) {
            return response()->json(['ok' => false, 'message' => 'Conversația nu mai există.'], 404);
        }

        $owner = $conversation->assigned_to_marketplace_admin_id;
        if ($owner && (int) $owner !== (int) $admin->id) {
            $name = MarketplaceAdmin::query()->whereKey($owner)->value('name');
            return response()->json(['ok' => false, 'message' => 'A preluat-o deja ' . $this->cleanName($name ?: 'alt operator') . '.'], 409);
        }

        app(ChatRoutingService::class)->assignTo($conversation, (int) $admin->id);

        return response()->json(['ok' => true]);
    }

    public function transfer(Request $request, string $reference): JsonResponse
    {
        $admin = $this->admin();
        $conversation = $admin ? $this->find($admin, $reference) : null;
        $targetId = (int) $request->input('admin_id');
        if (! $conversation || $targetId <= 0) {
            return response()->json(['ok' => false], 422);
        }

        $target = MarketplaceAdmin::query()
            ->whereKey($targetId)
            ->where('marketplace_client_id', $admin->marketplace_client_id)
            ->first();
        if (! $target) {
            return response()->json(['ok' => false, 'message' => 'Operatorul nu există.'], 422);
        }

        app(ChatRoutingService::class)->assignTo($conversation, $targetId);
        app(ChatConversationService::class)->postSystemMessage($conversation, 'Conversație transferată către alt operator.');

        return response()->json(['ok' => true]);
    }

    public function resolve(string $reference): JsonResponse
    {
        $admin = $this->admin();
        $conversation = $admin ? $this->find($admin, $reference) : null;
        if (! $conversation || $conversation->isClosed()) {
            return response()->json(['ok' => false], 422);
        }

        // Același mesaj de încheiere ca în consolă.
        $first = $admin->name ? trim(explode(' ', trim($admin->name))[0]) : 'operator';
        app(ChatConversationService::class)->postSystemMessage(
            $conversation,
            'Conversația a fost marcată ca rezolvată de ' . $first . '. Îți mulțumim că ne-ai scris! '
            . 'Dacă mai ai nevoie de ceva, deschide oricând o conversație nouă. O zi frumoasă! 🙌'
        );
        app(ChatConversationService::class)->resolve($conversation);

        return response()->json(['ok' => true]);
    }

    // ------------------------------------------------------------------

    private function admin(): ?MarketplaceAdmin
    {
        $admin = Auth::guard('marketplace_admin')->user();
        if (! $admin instanceof MarketplaceAdmin || ! $admin->marketplaceClient?->hasMicroservice('live-chat')) {
            return null;
        }

        return $admin;
    }

    private function find(MarketplaceAdmin $admin, string $reference): ?ChatConversation
    {
        return ChatConversation::query()
            ->where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('reference', $reference)
            ->first();
    }

    private function presentMessage(ChatConversation $conversation, ChatMessage $m): array
    {
        $kind = $m->type === ChatMessage::TYPE_SYSTEM ? 'system'
            : ($m->is_internal ? 'note' : ($m->isFromStaff() ? 'out' : 'in'));

        return [
            'id' => (int) $m->id,
            'kind' => $kind,
            'author' => $kind === 'in' ? null : $this->cleanName((string) $m->author_label),
            'body' => (string) $m->body,
            'time' => optional($m->created_at)->format('H:i'),
            'attachments' => collect((array) ($m->attachments ?? []))
                ->filter(fn ($a) => ! empty($a['token']))
                ->map(fn ($a) => [
                    'url' => route('marketplace.chat.attachment', ['conversation' => $conversation->id, 'token' => $a['token']], false),
                    'name' => (string) ($a['name'] ?? 'fișier'),
                    'image' => str_starts_with((string) ($a['mime'] ?? ''), 'image/'),
                ])
                ->values(),
        ];
    }

    private function operators(MarketplaceAdmin $admin): array
    {
        return ChatOperatorStatus::query()
            ->where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('presence', ChatOperatorStatus::PRESENCE_ONLINE)
            ->where('marketplace_admin_id', '!=', $admin->id)
            ->with('operator:id,name')
            ->get()
            ->filter(fn ($s) => $s->operator !== null)
            ->map(fn ($s) => ['id' => (int) $s->operator->id, 'name' => $this->cleanName($s->operator->name)])
            ->values()
            ->all();
    }

    private function visitorLabel(?string $type): string
    {
        return match ($type) {
            ChatConversation::VISITOR_ORGANIZER => 'organizator',
            ChatConversation::VISITOR_ARTIST => 'artist',
            ChatConversation::VISITOR_CUSTOMER => 'client',
            default => 'vizitator',
        };
    }

    private function cleanName(?string $name): string
    {
        return trim(preg_replace('/\s*\(System\)$/', '', (string) $name));
    }
}
