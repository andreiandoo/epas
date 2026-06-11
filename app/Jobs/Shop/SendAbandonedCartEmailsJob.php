<?php

namespace App\Jobs\Shop;

use App\Models\Shop\ShopCart;
use App\Models\Tenant;
use App\Notifications\Shop\ShopAbandonedCartNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendAbandonedCartEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?int $tenantId = null
    ) {}

    public function handle(): void
    {
        $query = Tenant::whereHas('microservices', function ($q) {
            $q->where('slug', 'shop')
                ->wherePivot('is_active', true);
        });

        if ($this->tenantId) {
            $query->where('id', $this->tenantId);
        }

        $tenants = $query->get();

        foreach ($tenants as $tenant) {
            $this->processAbandonedCartsForTenant($tenant);
        }
    }

    protected function processAbandonedCartsForTenant(Tenant $tenant): void
    {
        $config = $tenant->microservices()
            ->where('slug', 'shop')
            ->first()
            ?->pivot
            ?->configuration ?? [];

        // Check if abandoned cart recovery is enabled
        if (!($config['abandoned_cart_enabled'] ?? false)) {
            return;
        }

        $hoursBeforeFirst = $config['abandoned_cart_hours'] ?? 24;
        $maxEmails = $config['abandoned_cart_max_emails'] ?? 3;

        // Find abandoned carts that need recovery emails
        $carts = ShopCart::where('tenant_id', $tenant->id)
            ->where('status', 'abandoned')
            ->whereNotNull('email')
            ->where('recovery_emails_sent', '<', $maxEmails)
            ->whereHas('items')
            ->with(['items.product', 'items.variant'])
            ->get();

        foreach ($carts as $cart) {
            if ($this->shouldSendRecoveryEmail($cart, $hoursBeforeFirst)) {
                $this->sendRecoveryEmail($cart);
            }
        }

        // Also mark active carts as abandoned if they're old enough
        $this->markOldCartsAsAbandoned($tenant, $hoursBeforeFirst);
    }

    protected function shouldSendRecoveryEmail(ShopCart $cart, int $hoursBeforeFirst): bool
    {
        if (!$cart->canSendRecoveryEmail()) {
            return false;
        }

        $emailNumber = $cart->recovery_emails_sent + 1;

        // Calculate when this email should be sent based on cart update time
        $hoursForThisEmail = match($emailNumber) {
            1 => $hoursBeforeFirst,           // First email: after X hours
            2 => $hoursBeforeFirst + 24,      // Second email: 24 hours after first
            3 => $hoursBeforeFirst + 72,      // Third email: 72 hours after first
            default => null,
        };

        if ($hoursForThisEmail === null) {
            return false;
        }

        return $cart->updated_at->diffInHours(now()) >= $hoursForThisEmail;
    }

    protected function sendRecoveryEmail(ShopCart $cart): void
    {
        try {
            $emailNumber = $cart->recovery_emails_sent + 1;

            Notification::route('mail', $cart->email)
                ->notify(new ShopAbandonedCartNotification($cart, $emailNumber));

            $cart->recordRecoveryEmailSent();

            Log::info('Sent abandoned cart recovery email', [
                'cart_id' => $cart->id,
                'email' => $cart->email,
                'email_number' => $emailNumber,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send abandoned cart email', [
                'cart_id' => $cart->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function markOldCartsAsAbandoned(Tenant $tenant, int $hours): void
    {
        ShopCart::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->whereNotNull('email')
            ->where('updated_at', '<', now()->subHours($hours))
            ->whereHas('items')
            ->update(['status' => 'abandoned']);
    }
}
