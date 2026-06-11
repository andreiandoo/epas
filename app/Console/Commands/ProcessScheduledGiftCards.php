<?php

namespace App\Console\Commands;

use App\Jobs\SendGiftCardEmailJob;
use App\Models\MarketplaceGiftCard;
use Illuminate\Console\Command;

class ProcessScheduledGiftCards extends Command
{
    protected $signature = 'gift-cards:process-scheduled';
    protected $description = 'Send scheduled gift card emails and process expiry reminders';

    public function handle(): int
    {
        $this->info('Processing scheduled gift card deliveries...');

        // Send scheduled deliveries
        $scheduledCards = MarketplaceGiftCard::where('status', MarketplaceGiftCard::STATUS_ACTIVE)
            ->where('is_delivered', false)
            ->where('delivery_method', 'email')
            ->where('scheduled_delivery_at', '<=', now())
            ->get();

        foreach ($scheduledCards as $giftCard) {
            SendGiftCardEmailJob::dispatch($giftCard, SendGiftCardEmailJob::TYPE_DELIVERY);
            $giftCard->markDelivered();
            $this->line("Sent gift card {$giftCard->code} to {$giftCard->recipient_email}");
        }

        $this->info("Sent {$scheduledCards->count()} scheduled gift cards.");

        // Check for expiring cards (7 days warning)
        $this->info('Checking for expiring gift cards...');

        $expiringCards = MarketplaceGiftCard::where('status', MarketplaceGiftCard::STATUS_ACTIVE)
            ->where('balance', '>', 0)
            ->whereBetween('expires_at', [now()->addDays(6), now()->addDays(8)])
            ->get();

        foreach ($expiringCards as $giftCard) {
            SendGiftCardEmailJob::dispatch($giftCard, SendGiftCardEmailJob::TYPE_EXPIRY_REMINDER);
            $this->line("Sent expiry reminder for gift card {$giftCard->code}");
        }

        $this->info("Sent {$expiringCards->count()} expiry reminders.");

        // Mark expired cards
        $this->info('Marking expired gift cards...');

        $expiredCount = MarketplaceGiftCard::where('status', MarketplaceGiftCard::STATUS_ACTIVE)
            ->where('expires_at', '<', now())
            ->update(['status' => MarketplaceGiftCard::STATUS_EXPIRED]);

        $this->info("Marked {$expiredCount} gift cards as expired.");

        return self::SUCCESS;
    }
}
