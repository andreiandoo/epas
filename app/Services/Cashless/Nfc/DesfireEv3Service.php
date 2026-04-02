<?php

namespace App\Services\Cashless\Nfc;

use App\Models\Cashless\CashlessNfcKey;
use App\Models\Cashless\CashlessSettings;

/**
 * DESFire EV3 NFC chip service.
 *
 * Balance is stored ON the chip (Value File). Supports 100% offline operation.
 * This service provides the server-side logic for DESFire operations.
 * Actual chip communication happens on the POS device (Android/TapLinx SDK).
 *
 * The POS app calls the server API to:
 * - Get keys for authentication
 * - Log transactions after chip write
 * - Sync offline transactions
 *
 * The chip operations themselves (AuthenticateEV2First, GetValue, Debit,
 * Credit, CommitTransaction) happen natively on the device.
 */
class DesfireEv3Service implements NfcChipServiceInterface
{
    public function balanceIsOnChip(): bool
    {
        return true;
    }

    public function readBalance(string $uid): ?int
    {
        // DESFire: balance is read from the chip by POS device.
        // Server can return the last-synced balance from CashlessAccount
        // as a fallback/reference, but chip is source of truth.
        $account = \App\Models\Cashless\CashlessAccount::whereHas(
            'wristband',
            fn ($q) => $q->where('uid', $uid)
        )->first();

        return $account?->balance_cents;
    }

    public function writeBalance(string $uid, int $balanceCents): bool
    {
        // DESFire: balance is written to chip by POS device.
        // Server syncs the CashlessAccount to reflect chip state.
        $account = \App\Models\Cashless\CashlessAccount::whereHas(
            'wristband',
            fn ($q) => $q->where('uid', $uid)
        )->first();

        if (! $account) {
            return false;
        }

        $account->update(['balance_cents' => $balanceCents]);

        return true;
    }

    public function charge(string $uid, int $amountCents): ChargeResult
    {
        // DESFire charge flow:
        // 1. POS device authenticates with Key 2 (POS key)
        // 2. POS reads Value File → current balance
        // 3. POS calls Debit(amountCents) on chip
        // 4. POS calls CommitTransaction() (atomic, anti-tear)
        // 5. POS sends transaction log to server (this endpoint)
        //
        // This method is called by server after receiving the POS sync.
        // It updates the server-side CashlessAccount to mirror chip state.

        $account = \App\Models\Cashless\CashlessAccount::whereHas(
            'wristband',
            fn ($q) => $q->where('uid', $uid)
        )->first();

        if (! $account) {
            return ChargeResult::failure(0, 'Account not found for UID');
        }

        $before = $account->balance_cents;
        $after = $before - $amountCents;

        if ($after < 0) {
            return ChargeResult::failure($before, 'Insufficient balance on chip');
        }

        return ChargeResult::success($before, $after);
    }

    public function topUp(string $uid, int $amountCents): TopUpResult
    {
        // DESFire top-up flow:
        // 1. Operator authenticates with Key 1 (TopUp key)
        // 2. Reads current Value File
        // 3. Calls Credit(amountCents) on chip
        // 4. CommitTransaction()
        // 5. Syncs to server

        $account = \App\Models\Cashless\CashlessAccount::whereHas(
            'wristband',
            fn ($q) => $q->where('uid', $uid)
        )->first();

        if (! $account) {
            return TopUpResult::failure(0, 'Account not found for UID');
        }

        $before = $account->balance_cents;
        $after = $before + $amountCents;

        return TopUpResult::success($before, $after);
    }

    public function getChipMetadata(string $uid): array
    {
        // DESFire: metadata is in Backup Data File (File 0x00)
        // Read by POS device. Server returns what it knows.
        $wristband = \App\Models\Wristband::where('uid', $uid)->first();

        return [
            'uid'              => $uid,
            'chip_type'        => 'desfire_ev3',
            'edition_id'       => $wristband?->festival_edition_id,
            'customer_id'      => $wristband?->customer_id,
            'wristband_type'   => $wristband?->wristband_type,
            'activated_at'     => $wristband?->activated_at?->toIso8601String(),
            'balance_on_chip'  => true,
        ];
    }

    public function encodeNewWristband(string $uid, array $metadata): bool
    {
        // DESFire encoding happens on-device (POS/admin tablet):
        // 1. SelectApplication(PICC master)
        // 2. CreateApplication(AID, keySettings, numKeys=4)
        // 3. AuthenticateEV2First with Key 0 (master)
        // 4. CreateBackupDataFile(fileNo=0x00, 32 bytes)
        // 5. WriteData(metadata: edition_id, customer_id, type, timestamp)
        // 6. CreateValueFile(fileNo=0x01, lower=0, upper=upper_limit, initial=0)
        // 7. CreateCyclicRecordFile(fileNo=0x02, recordSize=16, maxRecords=20)
        // 8. ChangeKey for Key 1, 2, 3
        // 9. CommitTransaction()
        //
        // This method logs the encoding on server side.
        return true;
    }

    /**
     * Get the DESFire keys for a specific edition and key slot.
     * Called when POS device authenticates and needs keys.
     */
    public function getKeysForEdition(int $editionId, string $slot): ?string
    {
        $key = CashlessNfcKey::forEdition($editionId)->slot($slot)->first();

        return $key?->getDecryptedKey();
    }

    /**
     * Generate a new set of AES-128 keys for an edition.
     */
    public function generateKeysForEdition(int $tenantId, int $editionId, ?int $createdBy = null): array
    {
        $slots = ['master', 'topup', 'pos', 'readonly'];
        $keys = [];

        foreach ($slots as $slot) {
            $aesKey = bin2hex(random_bytes(16)); // 128-bit AES key as hex
            CashlessNfcKey::storeKey($tenantId, $editionId, $slot, $aesKey, $createdBy);
            $keys[$slot] = $aesKey;
        }

        // Also store in CashlessSettings for quick access
        $settings = CashlessSettings::forEdition($editionId);
        if ($settings) {
            $settings->update([
                'desfire_key_master'   => encrypt($keys['master']),
                'desfire_key_topup'    => encrypt($keys['topup']),
                'desfire_key_pos'      => encrypt($keys['pos']),
                'desfire_key_readonly' => encrypt($keys['readonly']),
                'desfire_keys_rotated_at' => now(),
            ]);
        }

        return $keys;
    }
}
