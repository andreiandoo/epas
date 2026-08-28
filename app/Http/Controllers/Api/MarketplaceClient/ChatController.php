<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Chat\ChatConversation;
use App\Models\MarketplaceClient;
use App\Services\Chat\ChatAntiAbuseService;
use App\Services\Chat\ChatConversationService;
use App\Services\Chat\ChatOpenerResolver;
use App\Services\Chat\ChatRoutingService;
use App\Services\Chat\ChatScheduleService;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public widget API for the `live-chat` microservice. Runs under the
 * marketplace-client route group (X-API-Key resolves the marketplace). Opener
 * identity is derived from the Sanctum bearer when present, else the visitor is
 * an anonymous guest identified by the pre-chat form.
 *
 * Ownership of a conversation across polling requests is proven with a per-
 * conversation session token minted at open time (stored server-side in
 * context.session_token). This blocks reference enumeration by third parties.
 */
class ChatController extends BaseController
{
    public function __construct(
        private ChatService $chat,
        private ChatConversationService $conversations,
        private ChatScheduleService $schedule,
        private ChatAntiAbuseService $antiAbuse,
        private ChatOpenerResolver $openerResolver,
        private ChatRoutingService $routing,
    ) {
    }

    /**
     * Widget bootstrap: config + current availability. Always 200 so the widget
     * script can decide whether to render at all.
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $client = $this->getClient($request);
        $config = $this->chat->widgetBootstrap($client);

        if (!$config) {
            return $this->success(['active' => false]);
        }

        $config['availability'] = $this->schedule->availabilityState($client->id);

        return $this->success($config);
    }

    /**
     * Open a new conversation.
     */
    public function open(Request $request): JsonResponse
    {
        $client = $this->requireActiveChat($request);

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:255'],
            'event_id' => ['nullable', 'integer'],
            'support_department_id' => ['nullable', 'integer'],
            'guest_name' => ['nullable', 'string', 'max:191'],
            'guest_email' => ['nullable', 'email', 'max:191'],
            'context' => ['nullable', 'array'],
            'elapsed_seconds' => ['nullable', 'integer'],
        ]);

        $opener = $this->openerResolver->resolve($request);

        // Guest identity comes from the pre-chat form.
        $guestName = $opener['name'] ?? ($validated['guest_name'] ?? null);
        $guestEmail = $opener['email'] ?? ($validated['guest_email'] ?? null);

        // Anti-abuse: honeypot / time-trap / rate-limit / blocklist.
        $honeypotField = (string) $this->chat->setting($client, 'anti_bot.honeypot_field', 'company_website');
        $reason = $this->antiAbuse->guardConversationOpen(
            $client->id,
            (string) $request->ip(),
            $guestEmail,
            [
                $honeypotField => $request->input($honeypotField),
                'elapsed_seconds' => $validated['elapsed_seconds'] ?? null,
            ]
        );
        if ($reason) {
            return $this->error('Cererea nu a putut fi procesată.', 429, ['reason' => $reason]);
        }

        // Guests must provide a contact when guests are allowed; otherwise deny.
        if ($opener['visitor_type'] === ChatConversation::VISITOR_GUEST) {
            if (!$this->chat->setting($client, 'allow_guests', true)) {
                return $this->error('Trebuie să fii autentificat pentru a începe o conversație.', 403);
            }
            $request->validate([
                'guest_name' => ['required', 'string', 'max:191'],
                'guest_email' => ['required', 'email', 'max:191'],
            ]);
            $guestName = $validated['guest_name'];
            $guestEmail = $validated['guest_email'];
        }

        $sessionToken = Str::random(40);
        [$browser, $os, $device] = $this->deviceInfo((string) $request->userAgent());
        $context = array_merge($validated['context'] ?? [], [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'browser' => $browser,
            'os' => $os,
            'device' => $device,
            'session_token' => $sessionToken,
            'opened_url' => $request->input('context.url'),
        ]);

        $conversation = $this->conversations->open($client->id, [
            'visitor_type' => $opener['visitor_type'],
            'opener_type' => $opener['opener_type'],
            'opener_id' => $opener['opener_id'],
            'guest_name' => $guestName,
            'guest_email' => $guestEmail,
            'event_id' => $validated['event_id'] ?? null,
            'support_department_id' => $validated['support_department_id'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'context' => $context,
        ], $validated['message'] ?? null);

        return $this->success([
            'reference' => $conversation->reference,
            'session_token' => $sessionToken,
            'status' => $conversation->status,
            'visitor_type' => $conversation->visitor_type,
            'queue_position' => $this->queuePosition($conversation),
            'operator' => $this->operatorFirstName($conversation),
            'started_at' => optional($conversation->created_at)->toIso8601String(),
            'messages' => $this->serializeMessages($conversation),
        ], null, 201);
    }

    /**
     * Poll a conversation: status + new public messages (after a message id).
     */
    public function show(Request $request, string $reference): JsonResponse
    {
        $client = $this->requireActiveChat($request);
        $conversation = $this->findOwned($request, $client, $reference);

        $after = (int) $request->query('after', 0);

        return $this->success([
            'reference' => $conversation->reference,
            'status' => $conversation->status,
            'queue_position' => $this->queuePosition($conversation),
            'operator' => $this->operatorFirstName($conversation),
            'rated' => $conversation->rating !== null,
            'started_at' => optional($conversation->created_at)->toIso8601String(),
            'messages' => $this->serializeMessages($conversation, $after),
        ]);
    }

    /**
     * Opener posts a message.
     */
    public function message(Request $request, string $reference): JsonResponse
    {
        $client = $this->requireActiveChat($request);
        $conversation = $this->findOwned($request, $client, $reference);

        if ($conversation->isClosed()) {
            return $this->error('Conversația este închisă.', 422);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        if ($reason = $this->antiAbuse->guardMessage($conversation->id)) {
            return $this->error('Prea multe mesaje. Încearcă din nou în scurt timp.', 429, ['reason' => $reason]);
        }

        $message = $this->conversations->postOpenerMessage($conversation, $validated['message']);

        // Optional auto-assign on a follow-up message (off by default — the chat
        // stays queued until an operator claims it manually).
        if (config('chat.operator.auto_assign', false)
            && $conversation->status === ChatConversation::STATUS_QUEUED
            && $this->schedule->availabilityState($client->id) === 'online') {
            $this->routing->assign($conversation->refresh());
        }

        return $this->success([
            'message' => $this->serializeMessage($message),
            'status' => $conversation->refresh()->status,
        ], null, 201);
    }

    /**
     * Visitor-initiated close (e.g. the idle countdown expired). Resolves the
     * conversation so the operator slot is freed and the rating flow can start.
     */
    public function close(Request $request, string $reference): JsonResponse
    {
        $client = $this->requireActiveChat($request);
        $conversation = $this->findOwned($request, $client, $reference);

        if (!$conversation->isClosed()) {
            $this->conversations->resolve($conversation);
        }

        return $this->success(['status' => $conversation->refresh()->status]);
    }

    /**
     * Post-chat rating.
     */
    public function rating(Request $request, string $reference): JsonResponse
    {
        $client = $this->requireActiveChat($request);
        $conversation = $this->findOwned($request, $client, $reference);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $conversation->forceFill([
            'rating' => $validated['rating'],
            'rating_comment' => $validated['comment'] ?? null,
        ])->save();

        return $this->success(null, 'Îți mulțumim pentru feedback!');
    }

    // -------- Internals --------

    private function requireActiveChat(Request $request): MarketplaceClient
    {
        $client = $this->requireClient($request);
        if (!$this->chat->isActiveFor($client)) {
            abort(404);
        }
        return $client;
    }

    private function findOwned(Request $request, MarketplaceClient $client, string $reference): ChatConversation
    {
        $conversation = ChatConversation::query()
            ->where('marketplace_client_id', $client->id)
            ->where('reference', $reference)
            ->first();

        if (!$conversation) {
            abort(404);
        }

        $provided = $request->header('X-Chat-Token') ?: $request->input('session_token', $request->query('session_token'));
        $expected = data_get($conversation->context, 'session_token');
        if (!$expected || !is_string($provided) || !hash_equals($expected, $provided)) {
            abort(403, 'Token de sesiune invalid.');
        }

        return $conversation;
    }

    /**
     * Lightweight User-Agent parse → [browser, os, device]. Mirrors the manual
     * parsing used elsewhere (Customer/AuthController) — no extra dependency.
     *
     * @return array{0:string,1:string,2:string}
     */
    private function deviceInfo(string $ua): array
    {
        $browser = 'Necunoscut';
        if (str_contains($ua, 'Edg/')) $browser = 'Edge';
        elseif (str_contains($ua, 'OPR/') || str_contains($ua, 'Opera')) $browser = 'Opera';
        elseif (str_contains($ua, 'Chrome/') && !str_contains($ua, 'Edg/')) $browser = 'Chrome';
        elseif (str_contains($ua, 'Firefox/')) $browser = 'Firefox';
        elseif (str_contains($ua, 'Safari/') && !str_contains($ua, 'Chrome/')) $browser = 'Safari';

        $os = 'Necunoscut';
        if (str_contains($ua, 'Windows')) $os = 'Windows';
        elseif (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') || str_contains($ua, 'iOS')) $os = 'iOS';
        elseif (str_contains($ua, 'Mac OS')) $os = 'macOS';
        elseif (str_contains($ua, 'Android')) $os = 'Android';
        elseif (str_contains($ua, 'Linux')) $os = 'Linux';

        $device = 'Desktop';
        if (str_contains($ua, 'iPad') || (str_contains($ua, 'Tablet'))) $device = 'Tabletă';
        elseif (str_contains($ua, 'Mobile') || str_contains($ua, 'iPhone') || str_contains($ua, 'Android')) $device = 'Telefon';

        return [$browser, $os, $device];
    }

    /**
     * The assigned operator's first name (for the "Conectat cu X" label).
     */
    private function operatorFirstName(ChatConversation $conversation): ?string
    {
        $name = $conversation->assignee?->name;
        if (!$name) {
            return null;
        }
        return trim(explode(' ', trim($name))[0]) ?: null;
    }

    private function queuePosition(ChatConversation $conversation): ?int
    {
        if ($conversation->status !== ChatConversation::STATUS_QUEUED) {
            return null;
        }

        return 1 + ChatConversation::query()
            ->where('marketplace_client_id', $conversation->marketplace_client_id)
            ->where('status', ChatConversation::STATUS_QUEUED)
            ->where('id', '<', $conversation->id)
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeMessages(ChatConversation $conversation, int $after = 0): array
    {
        return $conversation->publicMessages()
            ->when($after > 0, fn ($q) => $q->where('id', '>', $after))
            ->get()
            ->map(fn ($m) => $this->serializeMessage($m))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage($message): array
    {
        return [
            'id' => $message->id,
            'type' => $message->type,
            'from' => $message->isFromStaff() ? 'operator' : ($message->isSystem() ? 'system' : 'me'),
            'author' => $message->author_label,
            'body' => $message->body,
            'attachments' => $message->attachments ?? [],
            'created_at' => optional($message->created_at)->toIso8601String(),
        ];
    }
}
