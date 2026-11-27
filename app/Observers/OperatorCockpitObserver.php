<?php

namespace App\Observers;

use App\Models\Chat\ChatConversation;
use App\Models\Chat\ChatMessage;
use App\Models\Event;
use App\Models\MarketplaceArtistAccount;
use App\Models\MarketplaceTodo;
use App\Models\SupportTicket;
use App\Services\OperatorCockpit\CockpitSignal;
use Illuminate\Database\Eloquent\Model;

/**
 * Anunță bara de atenție când se schimbă ceva ce o privește. Doar câmpurile care
 * contează pentru bară declanșează semnalul.
 */
class OperatorCockpitObserver
{
    private const WATCH = [
        SupportTicket::class => ['ticket', ['status', 'assigned_to_marketplace_admin_id', 'support_department_id', 'subject', 'last_activity_at']],
        MarketplaceTodo::class => ['todo', ['status', 'assigned_to_marketplace_admin_id', 'priority', 'title']],
        MarketplaceArtistAccount::class => ['artist', ['status', 'assigned_to_marketplace_admin_id', 'artist_id']],
        Event::class => ['event', ['submitted_at', 'is_published', 'rejected_at', 'pending_changes_status', 'assigned_to_marketplace_admin_id', 'is_cancelled', 'event_date', 'range_end_date']],
        ChatConversation::class => ['chat', ['status', 'assigned_to_marketplace_admin_id']],
    ];

    public function saved(Model $model): void
    {
        if ($model instanceof \App\Models\MarketplaceNotification) {
            if ($model->wasRecentlyCreated) {
                CockpitSignal::changed((int) $model->marketplace_client_id, 'journal');
            }
            return;
        }

        if ($model instanceof ChatMessage) {
            if ($model->wasRecentlyCreated && $model->author_type !== 'staff') {
                CockpitSignal::changed((int) $model->marketplace_client_id, 'chat');
            }
            return;
        }

        $watch = self::WATCH[get_class($model)] ?? null;
        if (! $watch) {
            return;
        }

        [$category, $fields] = $watch;

        if ($category === 'event' && $model->wasRecentlyCreated && ! $model->submitted_at) {
            return;
        }

        if ($model->wasRecentlyCreated || $model->wasChanged($fields)) {
            CockpitSignal::changed((int) $model->marketplace_client_id, $category);
        }
    }

    public function deleted(Model $model): void
    {
        $watch = self::WATCH[get_class($model)] ?? null;
        if ($watch) {
            CockpitSignal::changed((int) $model->marketplace_client_id, $watch[0]);
        }
    }
}
