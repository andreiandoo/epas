<?php

namespace App\Services\Cashless\Nfc;

use App\Enums\NfcChipType;
use App\Models\FestivalEdition;

class NfcChipServiceFactory
{
    /**
     * Create the appropriate NFC chip service for an edition.
     */
    public function make(FestivalEdition $edition): NfcChipServiceInterface
    {
        return match ($edition->nfc_chip_type) {
            NfcChipType::DesfireEv3 => app(DesfireEv3Service::class),
            NfcChipType::Ntag213 => app(Ntag213Service::class),
            default => app(DesfireEv3Service::class),
        };
    }

    /**
     * Create from chip type enum directly.
     */
    public function makeFromType(NfcChipType $type): NfcChipServiceInterface
    {
        return match ($type) {
            NfcChipType::DesfireEv3 => app(DesfireEv3Service::class),
            NfcChipType::Ntag213 => app(Ntag213Service::class),
        };
    }
}
