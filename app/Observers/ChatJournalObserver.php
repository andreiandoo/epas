<?php

namespace App\Observers;

use App\Models\Chat\ChatConversation;
use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceNotification;
use App\Services\OperatorCockpit\AttentionService;
use Illuminate\Support\Facades\Auth;

/**
 * Pune în jurnalul operatorilor momentele unui chat cu un client: a început, a fost preluat, s-a închis.
 * Doar pe marketplace-urile cu bara de atenție, ca să nu umple clopoțelul celorlalți.
 */
class ChatJournalObserver
{
    public function saved(ChatConversation $c): void
    {
        try {
            if (! AttentionService::isEnabledForClientId((int) $c->marketplace_client_id)) {
                return;
            }

            $who = $c->guest_name ?: (data_get($c->context, 'visitor_name') ?: 'vizitator');
            $label = $who . ($c->subject ? ' · ' . $c->subject : '');

            if ($c->wasRecentlyCreated) {
                $this->note($c, MarketplaceNotification::TYPE_CHAT_STARTED, 'Chat nou: ' . $label, $this->visitorType($c->visitor_type) . ' · ' . $c->reference);
                return;
            }

            if ($c->wasChanged('assigned_to_marketplace_admin_id') && $c->assigned_to_marketplace_admin_id) {
                $name = $this->adminName($c->assigned_to_marketplace_admin_id);
                $previous = $c->getOriginal('assigned_to_marketplace_admin_id');
                $title = $previous
                    ? 'Chat transferat către ' . $name . ': ' . $label
                    : 'Chat preluat de ' . $name . ': ' . $label;
                $this->note($c, MarketplaceNotification::TYPE_CHAT_CLAIMED, $title, $c->reference);
            }

            $closed = [ChatConversation::STATUS_RESOLVED, ChatConversation::STATUS_CLOSED];
            if ($c->wasChanged('status') && in_array($c->status, $closed, true) && ! in_array($c->getOriginal('status'), $closed, true)) {
                $actor = Auth::guard('marketplace_admin')->user();
                $by = $actor ? 'închis de ' . $this->clean($actor->name) : 'închis de client sau automat, după inactivitate';
                $owner = $c->assigned_to_marketplace_admin_id ? ' · lucrat de ' . $this->adminName($c->assigned_to_marketplace_admin_id) : ' · nepreluat';
                $this->note($c, MarketplaceNotification::TYPE_CHAT_CLOSED, 'Chat închis: ' . $label, ucfirst($by) . $owner . ' · ' . $c->reference);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function note(ChatConversation $c, string $type, string $title, string $message): void
    {
        MarketplaceNotification::create([
            'marketplace_client_id' => $c->marketplace_client_id,
            'type' => $type,
            'title' => mb_strimwidth($title, 0, 250, '…'),
            'message' => $message,
            'icon' => MarketplaceNotification::getTypeIcons()[$type] ?? 'heroicon-o-bell',
            'color' => MarketplaceNotification::getTypeColors()[$type] ?? 'primary',
            'actionable_type' => ChatConversation::class,
            'actionable_id' => $c->id,
            'action_url' => '/marketplace/chat-console?c=' . urlencode((string) $c->reference),
        ]);
    }

    private function adminName($id): string
    {
        return $this->clean(MarketplaceAdmin::query()->whereKey($id)->value('name')) ?: 'un operator';
    }

    private function clean(?string $name): string
    {
        return trim(preg_replace('/\s*\(System\)$/', '', (string) $name));
    }

    private function visitorType(?string $type): string
    {
        return match ($type) {
            ChatConversation::VISITOR_ORGANIZER => 'Organizator',
            ChatConversation::VISITOR_ARTIST => 'Artist',
            ChatConversation::VISITOR_CUSTOMER => 'Client',
            default => 'Vizitator',
        };
    }
}
