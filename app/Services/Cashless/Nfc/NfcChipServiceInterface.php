<?php

namespace App\Services\Cashless\Nfc;

/**
 * Chip-agnostic interface for NFC wristband operations.
 *
 * Abstracts the difference between DESFire EV3 (balance on chip)
 * and NTAG213 (balance server-side). All business logic services
 * (SaleService, TopUpService, etc.) call this interface without
 * knowing which chip type is in use.
 */
interface NfcChipServiceInterface
{
    /**
     * Read the current balance from the chip or local cache.
     * Returns null if balance cannot be determined (NTAG213 offline with no cache).
     */
    public function readBalance(string $uid): ?int;

    /**
     * Write/sync balance to chip (DESFire) or acknowledge server balance (NTAG213).
     */
    public function writeBalance(string $uid, int $balanceCents): bool;

    /**
     * Charge (debit) an amount from the wristband.
     * DESFire: atomic debit on chip via Debit + CommitTransaction.
     * NTAG213: server/cache-side debit, just reads UID.
     */
    public function charge(string $uid, int $amountCents): ChargeResult;

    /**
     * Top-up (credit) an amount to the wristband.
     * DESFire: atomic credit on chip via Credit + CommitTransaction.
     * NTAG213: server-side credit, just reads UID.
     */
    public function topUp(string $uid, int $amountCents): TopUpResult;

    /**
     * Read chip metadata (edition_id, customer_id, activation timestamp, etc.)
     * DESFire: reads Backup Data File (File 0x00).
     * NTAG213: reads NDEF record or returns minimal {uid} info.
     */
    public function getChipMetadata(string $uid): array;

    /**
     * Encode a new wristband with initial metadata.
     * DESFire: creates application, sets up keys, writes metadata file, initializes Value File.
     * NTAG213: writes NDEF URL record and optionally sets password.
     */
    public function encodeNewWristband(string $uid, array $metadata): bool;

    /**
     * Whether this chip type stores balance on the physical chip.
     * true = DESFire (chip is source of truth for balance).
     * false = NTAG213 (server/CashlessAccount is source of truth).
     */
    public function balanceIsOnChip(): bool;
}
