<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Chat\ChatConversation;
use App\Models\Chat\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ChatPollController extends Controller
{
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
