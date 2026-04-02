<?php

namespace App\Services\Cashless\Nfc;

/**
 * NTAG213 NFC chip service.
 *
 * Balance is stored SERVER-SIDE (CashlessAccount). The chip only holds
 * the UID which is used to look up the account. POS devices maintain
 * a local SQLite cache of {uid → balance} for offline operation.
 *
 * Limitations vs DESFire:
 * - No anti-tear (interrupted write = corrupted data)
 * - Password is only 4 bytes (trivially brute-forced)
 * - Offline mode uses stale cache (risk of negative balance)
 * - No on-chip transaction log
 */
class Ntag213Service implements NfcChipServiceInterface
{
    public function balanceIsOnChip(): bool
    {
        return false;
    }

    public function readBalance(string $uid): ?int
    {
        // NTAG213: balance is always server-side (CashlessAccount)
        $account = \App\Models\Cashless\CashlessAccount::whereHas(
            'wristband',
            fn ($q) => $q->where('uid', $uid)
        )->first();

        return $account?->balance_cents;
    }

    public function writeBalance(string $uid, int $balanceCents): bool
    {
        // NTAG213: no balance on chip, update server record
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
        // NTAG213 charge:
        // 1. POS reads UID from chip
        // 2. Looks up balance in local cache (SQLite) or server
        // 3. If sufficient: decrements local cache + logs transaction
        // 4. Syncs to server when online
        //
        // Server-side: validate and update CashlessAccount

        $account = \App\Models\Cashless\CashlessAccount::whereHas(
            'wristband',
            fn ($q) => $q->where('uid', $uid)
        )->first();

        if (! $account) {
            return ChargeResult::failure(0, 'Account not found for UID');
        }

        $before = $account->balance_cents;

        if ($before < $amountCents) {
            return ChargeResult::failure($before, 'Insufficient balance');
        }

        $after = $before - $amountCents;

        return ChargeResult::success($before, $after);
    }

    public function topUp(string $uid, int $amountCents): TopUpResult
    {
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
        // NTAG213: minimal metadata from server
        $wristband = \App\Models\Wristband::where('uid', $uid)->first();

        return [
            'uid'              => $uid,
            'chip_type'        => 'ntag213',
            'edition_id'       => $wristband?->festival_edition_id,
            'customer_id'      => $wristband?->customer_id,
            'wristband_type'   => $wristband?->wristband_type,
            'activated_at'     => $wristband?->activated_at?->toIso8601String(),
            'balance_on_chip'  => false,
        ];
    }

    public function encodeNewWristband(string $uid, array $metadata): bool
    {
        // NTAG213 encoding:
        // 1. Write NDEF URL record (e.g. https://festival.app/w/{uid})
        // 2. Optionally set 4-byte password to prevent overwriting
        // 3. Lock CC (Capability Container) bytes
        //
        // This can be done from any NFC-enabled phone.
        return true;
    }
}
