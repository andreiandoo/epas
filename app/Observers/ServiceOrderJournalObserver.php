<?php

namespace App\Observers;

use App\Models\MarketplaceNotification;
use App\Models\ServiceOrder;
use App\Services\OperatorCockpit\AttentionService;

/**
 * Comenzile de servicii ajung și în jurnalul operatorilor: comandă nouă prin transfer
 * (plata trebuie confirmată) și comandă plătită. Până acum nu le anunța nimic.
 */
class ServiceOrderJournalObserver
{
    public function saved(ServiceOrder $o): void
    {
        try {
            if (! AttentionService::isEnabledForClientId((int) $o->marketplace_client_id)) {
                return;
            }

            $paidNow = $o->wasChanged('payment_status') && $o->payment_status === ServiceOrder::PAYMENT_PAID;
            $newTransfer = $o->wasRecentlyCreated && $o->payment_method === 'transfer';
            if (! $paidNow && ! $newTransfer) {
                return;
            }

            $organizer = $o->marketplaceOrganizer?->name ?: 'Organizator';
            $total = $o->total !== null ? number_format((float) $o->total, 2, ',', '.') . ' ' . ($o->currency ?? 'RON') : '';
            $title = ($paidNow ? 'Comandă servicii plătită: ' : 'Comandă servicii prin transfer: ') . $o->service_type_label;
            $message = trim($organizer . ' · ' . $o->order_number . ($total ? ' · ' . $total : '')
                . ($paidNow && $o->status === ServiceOrder::STATUS_PROCESSING ? ' · de activat' : '')
                . (! $paidNow ? ' · plata trebuie confirmată' : ''));

            $url = null;
            try {
                $url = \App\Filament\Marketplace\Resources\ServiceOrderResource::getUrl('view', ['record' => $o->id], isAbsolute: false, panel: 'marketplace');
            } catch (\Throwable) {
            }

            MarketplaceNotification::create([
                'marketplace_client_id' => $o->marketplace_client_id,
                'type' => MarketplaceNotification::TYPE_SERVICE_ORDER,
                'title' => mb_strimwidth($title, 0, 250, '…'),
                'message' => $message,
                'icon' => MarketplaceNotification::getTypeIcons()[MarketplaceNotification::TYPE_SERVICE_ORDER] ?? 'heroicon-o-shopping-cart',
                'color' => 'success',
                'actionable_type' => ServiceOrder::class,
                'actionable_id' => $o->id,
                'action_url' => $url,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
