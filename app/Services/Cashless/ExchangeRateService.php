<?php

namespace App\Services\Cashless;

use App\Models\Cashless\CashlessExchangeRate;
use App\Models\Cashless\CashlessSettings;

class ExchangeRateService
{
    /**
     * Convert an amount from one currency to the base currency of an edition.
     */
    public function convert(int $editionId, int $amountCents, string $fromCurrency): array
    {
        $settings = CashlessSettings::forEdition($editionId);
        $baseCurrency = $settings?->base_currency ?? 'RON';

        if ($fromCurrency === $baseCurrency) {
            return [
                'original_cents'   => $amountCents,
                'original_currency' => $fromCurrency,
                'converted_cents'  => $amountCents,
                'target_currency'  => $baseCurrency,
                'rate'             => 1.0,
                'markup_rate'      => 1.0,
            ];
        }

        $exchangeRate = CashlessExchangeRate::getCurrentRate($editionId, $fromCurrency, $baseCurrency);

        if (! $exchangeRate) {
            throw new \InvalidArgumentException(
                "No exchange rate found for {$fromCurrency} → {$baseCurrency} in edition {$editionId}"
            );
        }

        $convertedCents = $exchangeRate->convert($amountCents);

        return [
            'original_cents'    => $amountCents,
            'original_currency' => $fromCurrency,
            'converted_cents'   => $convertedCents,
            'target_currency'   => $baseCurrency,
            'rate'              => (float) $exchangeRate->rate,
            'markup_rate'       => (float) $exchangeRate->markup_rate,
        ];
    }

    /**
     * Set a manual exchange rate.
     */
    public function setRate(
        int $tenantId,
        int $editionId,
        string $from,
        string $to,
        float $rate,
        ?float $markupPercentage = null,
    ): CashlessExchangeRate {
        if ($markupPercentage === null) {
            $settings = CashlessSettings::forEdition($editionId);
            $markupPercentage = (float) ($settings?->exchange_markup_percentage ?? 2.00);
        }

        $markupRate = $rate * (1 + $markupPercentage / 100);

        // Invalidate previous rate
        CashlessExchangeRate::where('festival_edition_id', $editionId)
            ->where('from_currency', $from)
            ->where('to_currency', $to)
            ->whereNull('valid_until')
            ->update(['valid_until' => now()]);

        return CashlessExchangeRate::create([
            'tenant_id'           => $tenantId,
            'festival_edition_id' => $editionId,
            'from_currency'       => $from,
            'to_currency'         => $to,
            'rate'                => $rate,
            'markup_rate'         => $markupRate,
            'valid_from'          => now(),
            'source'              => 'manual',
        ]);
    }

    /**
     * Get all active rates for an edition.
     */
    public function getActiveRates(int $editionId): array
    {
        return CashlessExchangeRate::where('festival_edition_id', $editionId)
            ->where('valid_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
            ->orderBy('from_currency')
            ->get()
            ->toArray();
    }
}
