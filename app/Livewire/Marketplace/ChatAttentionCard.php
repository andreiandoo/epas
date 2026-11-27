<?php

namespace App\Livewire\Marketplace;

use App\Models\Chat\ChatConversation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Cardul "Chat-uri nepreluate" din zona "Cer atenție" a dashboardului
 * (aceleași numere ca ChatStatsWidget; se reîmprospătează la 30s).
 */
class ChatAttentionCard extends Component
{
    public function render()
    {
        $admin = Auth::guard('marketplace_admin')->user();
        $clientId = $admin?->marketplace_client_id;

        if ($clientId === null || ! $admin?->marketplaceClient?->hasMicroservice('live-chat')) {
            return <<<'HTML'
            <div></div>
            HTML;
        }

        $base = fn () => ChatConversation::query()->where('marketplace_client_id', $clientId);

        return view('livewire.marketplace.chat-attention-card', [
            'active' => $base()->where('status', 'active')->count(),
            'unclaimed' => $base()->whereIn('status', ['queued', 'offline_message'])->count(),
            'today' => $base()->whereDate('created_at', Carbon::today())->count(),
        ]);
    }
}
