<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceAdmin;
use App\Models\TeamChat\TeamMessage;
use App\Models\TeamChat\TeamMessageAttachment;
use App\Models\TeamChat\TeamThread;
use App\Services\OperatorCockpit\AttentionService;
use App\Services\TeamChat\TeamChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class TeamChatController extends Controller
{
    public function __construct(private TeamChatService $chat)
    {
    }

    public function threads(): JsonResponse
    {
        $admin = $this->admin();
        if (! $admin) {
            return response()->json(['ok' => false], 403);
        }

        return response()->json([
            'ok' => true,
            'me' => ['id' => (int) $admin->id, 'name' => $this->chat->cleanName($admin->name)],
            'threads' => $this->chat->threadsFor($admin),
            'colleagues' => $this->chat->colleagues($admin)
                ->reject(fn ($c) => (int) $c->id === (int) $admin->id)
                ->map(fn ($c) => ['id' => (int) $c->id, 'name' => $this->chat->cleanName($c->name)])
                ->values(),
        ]);
    }

    public function show(Request $request, int $thread): JsonResponse
    {
        [$admin, $t] = $this->resolve($thread);
        if (! $t) {
            return response()->json(['ok' => false, 'message' => 'Conversația nu există.'], 404);
        }

        $messages = $this->chat->messages($admin, $t, (int) $request->query('after', 0), (int) $request->query('before', 0));

        if ($request->boolean('read') && $messages) {
            $this->chat->markRead($admin, $t, (int) max(array_column($messages, 'id')));
        }

        $members = $t->admins()->get(['marketplace_admins.id', 'marketplace_admins.name'])
            ->map(fn ($a) => ['id' => (int) $a->id, 'name' => $this->chat->cleanName($a->name)])
            ->values();

        return response()->json([
            'ok' => true,
            'thread' => [
                'id' => (int) $t->id,
                'type' => $t->type,
                'name' => $t->type === TeamThread::TYPE_DM
                    ? ($members->firstWhere('id', '!=', (int) $admin->id)['name'] ?? 'Conversație')
                    : (string) $t->name,
                'is_system' => (bool) $t->is_system,
                'members' => $members,
                'chat_ref' => $t->chat_conversation_id
                    ? \App\Models\Chat\ChatConversation::query()->whereKey($t->chat_conversation_id)->value('reference')
                    : null,
            ],
            'messages' => $messages,
        ]);
    }

    public function post(Request $request, int $thread): JsonResponse
    {
        [$admin, $t] = $this->resolve($thread);
        if (! $t) {
            return response()->json(['ok' => false, 'message' => 'Conversația nu există.'], 404);
        }

        return $this->attempt(function () use ($admin, $t, $request) {
            $m = $this->chat->post($admin, $t, (string) $request->input('body', ''), $request->file('file'));
            return ['message' => $this->chat->present($admin, $m->fresh(['author', 'attachments']))];
        });
    }

    public function edit(Request $request, int $message): JsonResponse
    {
        $admin = $this->admin();
        $m = $admin ? $this->ownMessage($admin, $message) : null;
        if (! $m) {
            return response()->json(['ok' => false], 404);
        }

        return $this->attempt(fn () => $this->chat->edit($admin, $m, (string) $request->input('body', '')));
    }

    public function destroy(int $message): JsonResponse
    {
        $admin = $this->admin();
        $m = $admin ? $this->ownMessage($admin, $message) : null;
        if (! $m) {
            return response()->json(['ok' => false], 404);
        }

        return $this->attempt(fn () => $this->chat->delete($admin, $m));
    }

    public function read(Request $request, int $thread): JsonResponse
    {
        [$admin, $t] = $this->resolve($thread);
        if (! $t) {
            return response()->json(['ok' => false], 404);
        }
        $this->chat->markRead($admin, $t, (int) $request->input('message_id'));

        return response()->json(['ok' => true]);
    }

    public function typing(int $thread): JsonResponse
    {
        [$admin, $t] = $this->resolve($thread);
        if ($t) {
            $this->chat->typing($admin, $t);
        }

        return response()->json(['ok' => (bool) $t]);
    }

    public function dm(Request $request): JsonResponse
    {
        $admin = $this->admin();
        if (! $admin) {
            return response()->json(['ok' => false], 403);
        }

        return $this->attempt(fn () => ['thread_id' => (int) $this->chat->openDm($admin, (int) $request->input('admin_id'))->id]);
    }

    public function group(Request $request): JsonResponse
    {
        $admin = $this->admin();
        if (! $admin) {
            return response()->json(['ok' => false], 403);
        }

        return $this->attempt(fn () => ['thread_id' => (int) $this->chat->createGroup($admin, (string) $request->input('name'), (array) $request->input('member_ids', []))->id]);
    }

    public function help(Request $request): JsonResponse
    {
        $admin = $this->admin();
        if (! $admin) {
            return response()->json(['ok' => false], 403);
        }

        return $this->attempt(fn () => ['thread_id' => (int) $this->chat->askForHelp(
            $admin,
            (string) $request->input('conversation_ref'),
            (array) $request->input('member_ids', []),
            $request->input('note')
        )->id]);
    }

    public function addMembers(Request $request, int $thread): JsonResponse
    {
        [$admin, $t] = $this->resolve($thread);
        if (! $t) {
            return response()->json(['ok' => false], 404);
        }

        return $this->attempt(fn () => $this->chat->addMembersBy($admin, $t, (array) $request->input('member_ids', [])));
    }

    public function leave(int $thread): JsonResponse
    {
        [$admin, $t] = $this->resolve($thread);
        if (! $t) {
            return response()->json(['ok' => false], 404);
        }

        return $this->attempt(fn () => $this->chat->leave($admin, $t));
    }

    public function mute(Request $request, int $thread): JsonResponse
    {
        [$admin, $t] = $this->resolve($thread);
        if (! $t) {
            return response()->json(['ok' => false], 404);
        }
        $minutes = $request->input('minutes');
        $this->chat->mute($admin, $t, $minutes ? (int) $minutes : null);

        return response()->json(['ok' => true]);
    }

    public function attachment(int $attachment): Response
    {
        $admin = $this->admin();
        $a = $admin ? $this->visibleAttachment($admin, $attachment) : null;
        if (! $a || $a->purged_at || ! Storage::disk('local')->exists($a->path)) {
            abort(404);
        }

        return Storage::disk('local')->response($a->path, $a->name, [
            'Content-Type' => $a->mime,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ], str_starts_with($a->mime, 'image/') || $a->mime === 'application/pdf' ? 'inline' : 'attachment');
    }

    public function keep(Request $request, int $attachment): JsonResponse
    {
        $admin = $this->admin();
        $a = $admin ? $this->visibleAttachment($admin, $attachment) : null;
        if (! $a || $a->purged_at) {
            return response()->json(['ok' => false], 404);
        }
        $this->chat->keepAttachment($admin, $a, $request->boolean('keep', true));

        return response()->json(['ok' => true, 'expires' => optional($a->fresh()->expiresAt())->format('d.m.Y')]);
    }

    // ------------------------------------------------------------------

    private function admin(): ?MarketplaceAdmin
    {
        $admin = Auth::guard('marketplace_admin')->user();
        if (! $admin instanceof MarketplaceAdmin || ! TeamChatService::isAvailable()
            || ! AttentionService::isEnabledFor($admin->marketplaceClient)) {
            return null;
        }

        return $admin;
    }

    /** @return array{0: ?MarketplaceAdmin, 1: ?TeamThread} */
    private function resolve(int $threadId): array
    {
        $admin = $this->admin();

        return [$admin, $admin ? $this->chat->threadFor($admin, $threadId) : null];
    }

    private function ownMessage(MarketplaceAdmin $admin, int $id): ?TeamMessage
    {
        $m = TeamMessage::query()->with('thread')->find($id);
        if (! $m || ! $m->thread || ! $this->chat->threadFor($admin, (int) $m->team_thread_id)) {
            return null;
        }

        return $m;
    }

    private function visibleAttachment(MarketplaceAdmin $admin, int $id): ?TeamMessageAttachment
    {
        $a = TeamMessageAttachment::query()->with('message')->find($id);
        if (! $a || ! $a->message || ! $this->chat->threadFor($admin, (int) $a->message->team_thread_id)) {
            return null;
        }

        return $a;
    }

    private function attempt(callable $fn): JsonResponse
    {
        try {
            $result = $fn();
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['ok' => false, 'message' => 'Nu am găsit ce ai cerut.'], 404);
        }

        return response()->json(array_merge(['ok' => true], is_array($result) ? $result : []));
    }
}
