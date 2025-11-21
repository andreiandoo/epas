<?php

namespace App\Services\Wallet\Adapters;

use App\Models\Ticket;
use App\Models\WalletPass;

/**
 * Interface for wallet pass generation adapters
 */
interface WalletAdapterInterface
{
    /**
     * Generate a wallet pass for a ticket
     *
     * @param Ticket $ticket
     * @param array $options Additional options (logo, colors, etc.)
     * @return array {success: bool, pass_url: string|null, pass_data: mixed}
     */
    public function generatePass(Ticket $ticket, array $options = []): array;

    /**
     * Update an existing pass
     *
     * @param WalletPass $pass
     * @param array $changes
     * @return array {success: bool, message: string}
     */
    public function updatePass(WalletPass $pass, array $changes = []): array;

    /**
     * Void/invalidate a pass
     *
     * @param WalletPass $pass
     * @return array {success: bool, message: string}
     */
    public function voidPass(WalletPass $pass): array;

    /**
     * Send push notification to registered devices
     *
     * @param WalletPass $pass
     * @return array {success: bool, devices_notified: int}
     */
    public function pushUpdate(WalletPass $pass): array;

    /**
     * Get the platform name
     *
     * @return string
     */
    public function getPlatform(): string;

    /**
     * Check if adapter is properly configured
     *
     * @return bool
     */
    public function isConfigured(): bool;
}
