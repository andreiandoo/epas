<?php

namespace App\Services\Wallet;

use App\Models\Ticket;
use App\Models\WalletPass;
use App\Models\WalletPassUpdate;
use App\Services\Wallet\Adapters\WalletAdapterInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Wallet Service
 *
 * Manages mobile wallet passes for Apple Wallet and Google Pay
 */
class WalletService
{
    protected array $adapters = [];

    /**
     * Register a wallet adapter
     */
    public function registerAdapter(string $platform, WalletAdapterInterface $adapter): void
    {
        $this->adapters[$platform] = $adapter;
    }

    /**
     * Get adapter for platform
     */
    protected function getAdapter(string $platform): WalletAdapterInterface
    {
        if (!isset($this->adapters[$platform])) {
            throw new \InvalidArgumentException("No adapter registered for platform: {$platform}");
        }

        return $this->adapters[$platform];
    }

    /**
     * Generate wallet pass for a ticket
     */
    public function generatePass(Ticket $ticket, string $platform, array $options = []): array
    {
        // Check if pass already exists
        if (WalletPass::existsForTicket($ticket->id, $platform)) {
            $existingPass = WalletPass::where('ticket_id', $ticket->id)
                ->where('platform', $platform)
                ->whereNull('voided_at')
                ->first();

            return [
                'success' => true,
                'pass' => $existingPass,
                'pass_url' => $this->getPassUrl($existingPass),
                'already_exists' => true,
            ];
        }

        $adapter = $this->getAdapter($platform);

        if (!$adapter->isConfigured()) {
            return [
                'success' => false,
                'error' => "Wallet adapter for {$platform} is not configured",
            ];
        }

        // Generate pass via adapter
        $result = $adapter->generatePass($ticket, $options);

        if (!$result['success']) {
            return $result;
        }

        // Store pass record
        $pass = WalletPass::create([
            'tenant_id' => $ticket->tenant_id ?? $ticket->order->tenant_id,
            'ticket_id' => $ticket->id,
            'order_id' => $ticket->order_id,
            'platform' => $platform,
            'pass_identifier' => "{$platform}_{$ticket->id}",
            'serial_number' => $result['pass_data']['serial_number'],
            'auth_token' => $result['pass_data']['auth_token'],
            'last_updated_at' => now(),
        ]);

        Log::info('Wallet pass generated', [
            'ticket_id' => $ticket->id,
            'platform' => $platform,
            'pass_id' => $pass->id,
        ]);

        return [
            'success' => true,
            'pass' => $pass,
            'pass_url' => $result['pass_url'],
            'already_exists' => false,
        ];
    }

    /**
     * Generate passes for both platforms
     */
    public function generateAllPasses(Ticket $ticket, array $options = []): array
    {
        $results = [];

        foreach (['apple', 'google'] as $platform) {
            if (isset($this->adapters[$platform])) {
                $results[$platform] = $this->generatePass($ticket, $platform, $options);
            }
        }

        return $results;
    }

    /**
     * Update a pass (e.g., when event details change)
     */
    public function updatePass(WalletPass $pass, string $updateType, array $changes = []): array
    {
        $adapter = $this->getAdapter($pass->platform);

        $result = $adapter->updatePass($pass, $changes);

        if ($result['success']) {
            // Log the update
            WalletPassUpdate::create([
                'pass_id' => $pass->id,
                'update_type' => $updateType,
                'changes' => $changes,
            ]);

            $pass->markUpdated();
        }

        return $result;
    }

    /**
     * Update all passes for an event (when event details change)
     */
    public function updatePassesForEvent(int $eventId, string $updateType, array $changes = []): array
    {
        $passes = WalletPass::whereHas('ticket', function ($query) use ($eventId) {
            $query->where('event_id', $eventId);
        })->active()->get();

        $results = [
            'total' => $passes->count(),
            'success' => 0,
            'failed' => 0,
        ];

        foreach ($passes as $pass) {
            $result = $this->updatePass($pass, $updateType, $changes);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        Log::info('Updated wallet passes for event', [
            'event_id' => $eventId,
            'update_type' => $updateType,
            'results' => $results,
        ]);

        return $results;
    }

    /**
     * Void a pass (e.g., when ticket is cancelled)
     */
    public function voidPass(WalletPass $pass): array
    {
        $adapter = $this->getAdapter($pass->platform);

        $result = $adapter->voidPass($pass);

        if ($result['success']) {
            $pass->void();

            Log::info('Wallet pass voided', [
                'pass_id' => $pass->id,
                'ticket_id' => $pass->ticket_id,
            ]);
        }

        return $result;
    }

    /**
     * Void all passes for a ticket
     */
    public function voidPassesForTicket(int $ticketId): array
    {
        $passes = WalletPass::where('ticket_id', $ticketId)->active()->get();

        $results = [
            'voided' => 0,
            'failed' => 0,
        ];

        foreach ($passes as $pass) {
            $result = $this->voidPass($pass);

            if ($result['success']) {
                $results['voided']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Get pass URL for downloading
     */
    public function getPassUrl(WalletPass $pass): string
    {
        if ($pass->isApple()) {
            return url("/api/wallet/download/{$pass->id}/apple");
        }

        // Google uses the JWT URL directly
        return url("/api/wallet/download/{$pass->id}/google");
    }

    /**
     * Register device for push notifications (Apple)
     */
    public function registerDevice(
        string $deviceLibraryId,
        string $passTypeId,
        string $serialNumber,
        string $pushToken
    ): array {
        $pass = WalletPass::where('serial_number', $serialNumber)->first();

        if (!$pass) {
            return [
                'success' => false,
                'error' => 'Pass not found',
            ];
        }

        $pass->pushRegistrations()->updateOrCreate(
            ['device_library_id' => $deviceLibraryId],
            ['push_token' => $pushToken]
        );

        return [
            'success' => true,
        ];
    }

    /**
     * Unregister device
     */
    public function unregisterDevice(string $deviceLibraryId, string $serialNumber): array
    {
        $pass = WalletPass::where('serial_number', $serialNumber)->first();

        if (!$pass) {
            return [
                'success' => false,
                'error' => 'Pass not found',
            ];
        }

        $pass->pushRegistrations()
            ->where('device_library_id', $deviceLibraryId)
            ->delete();

        return [
            'success' => true,
        ];
    }

    /**
     * Get passes registered for a device
     */
    public function getPassesForDevice(string $deviceLibraryId, string $passTypeId): array
    {
        $passes = WalletPass::whereHas('pushRegistrations', function ($query) use ($deviceLibraryId) {
            $query->where('device_library_id', $deviceLibraryId);
        })->active()->get();

        return $passes->pluck('serial_number')->toArray();
    }

    /**
     * Get statistics for a tenant
     */
    public function getStatistics(string $tenantId): array
    {
        return [
            'total_passes' => WalletPass::forTenant($tenantId)->count(),
            'active_passes' => WalletPass::forTenant($tenantId)->active()->count(),
            'voided_passes' => WalletPass::forTenant($tenantId)->voided()->count(),
            'by_platform' => [
                'apple' => WalletPass::forTenant($tenantId)->forPlatform('apple')->active()->count(),
                'google' => WalletPass::forTenant($tenantId)->forPlatform('google')->active()->count(),
            ],
        ];
    }
}
