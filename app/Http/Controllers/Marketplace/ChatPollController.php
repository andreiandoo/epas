<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Chat\ChatConversation;
use App\Models\Chat\ChatMessage;
use App\Services\Chat\ChatAttachmentService;
use App\Services\Chat\ChatConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ChatPollController extends Controller
{
    /**
     * Serve a stored chat image attachment to the operator (session auth,
     * scoped to their marketplace). Streams from the private disk.
     */
    public function attachment(int $conversation, string $token): Response
    {
        $admin = Auth::guard('marketplace_admin')->user();
        if (!$admin) {
            abort(401);
        }

        $conv = ChatConversation::withoutGlobalScopes()
            ->where('id', $conversation)
            ->where('marketplace_client_id', $admin->marketplace_client_id)
            ->first();
        if (!$conv) {
            abort(404);
        }

        $file = app(ChatAttachmentService::class)->read($conv, $token);
        if (!$file) {
            abort(404);
        }

        return response($file[0], 200, [
            'Content-Type' => $file[1],
            'Content-Length' => (string) strlen($file[0]),
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Operator uploads an image into a conversation (base64 JSON). Re-encoded +
     * stored privately, then posted as an operator message.
     */
    public function upload(Request $request, int $conversation): JsonResponse
    {
        $admin = Auth::guard('marketplace_admin')->user();
        if (!$admin) {
            return response()->json(['ok' => false], 401);
        }
        if (!$admin->marketplaceClient?->hasMicroservice('live-chat')) {
            return response()->json(['ok' => false], 403);
        }

        $conv = ChatConversation::withoutGlobalScopes()
            ->where('id', $conversation)
            ->where('marketplace_client_id', $admin->marketplace_client_id)
            ->first();
        if (!$conv || $conv->isClosed()) {
            return response()->json(['ok' => false, 'message' => 'Conversație indisponibilă.'], 422);
        }

        $data = (string) $request->input('data', '');
        $name = $request->input('name');
        try {
            $descriptor = app(ChatAttachmentService::class)->storeBase64($conv, $data, $name);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        app(ChatConversationService::class)->postOperatorMessage(
            $conv,
            (int) $admin->id,
            (string) $admin->name,
            '',
            false,
            [$descriptor]
        );

        return response()->json(['ok' => true]);
    }

    public function poll(): JsonResponse
    {
        $admin = Auth::guard('marketplace_admin')->user();
        if (! $admin) {
            return response()->json(['ok' => false], 401);
        }

        $clientId = $admin->marketplace_client_id;

        if (! $admin->marketplaceClient?->hasMicroservice('live-chat')) {
            return response()->json([
                'ok' => true,
                'active' => 0,
                'unclaimed' => 0,
                'latest_incoming_id' => 0,
                'my_latest_incoming_id' => 0,
            ]);
        }

        $unclaimed = ChatConversation::query()
            ->where('marketplace_client_id', $clientId)
            ->whereIn('status', ['queued', 'offline_message'])
            ->count();

        $active = ChatConversation::query()
            ->where('marketplace_client_id', $clientId)
            ->where('status', 'active')
            ->count();

        $latestIncomingId = ChatMessage::query()
            ->where('marketplace_client_id', $clientId)
            ->whereRaw("(author_type IS NULL OR author_type <> 'staff')")
            ->max('id') ?? 0;

        $myLatestIncomingId = ChatMessage::query()
            ->where('marketplace_client_id', $clientId)
            ->whereRaw("(author_type IS NULL OR author_type <> 'staff')")
            ->whereIn('chat_conversation_id', ChatConversation::query()
                ->where('marketplace_client_id', $clientId)
                ->where('assigned_to_marketplace_admin_id', $admin->id)
                ->select('id'))
            ->max('id') ?? 0;

        return response()->json([
            'ok' => true,
            'active' => $active,
            'unclaimed' => $unclaimed,
            'latest_incoming_id' => (int) $latestIncomingId,
            'my_latest_incoming_id' => (int) $myLatestIncomingId,
        ]);
    }
}
