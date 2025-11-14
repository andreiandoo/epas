<?php

namespace App\Services\Seating\Pricing\DTO;

/**
 * PriceDecision Data Transfer Object
 *
 * Represents a pricing decision with base price, effective price, and metadata
 */
class PriceDecision
{
    public function __construct(
        public int $basePriceCents,
        public int $effectivePriceCents,
        public ?int $sourceRuleId = null,
        public ?string $strategy = null,
        public array $metadata = []
    ) {}

    /**
     * Calculate price difference
     */
    public function getDifferenceCents(): int
    {
        return $this->effectivePriceCents - $this->basePriceCents;
    }

    /**
     * Calculate percentage change
     */
    public function getChangePercentage(): float
    {
        if ($this->basePriceCents === 0) {
            return 0;
        }

        return (($this->effectivePriceCents - $this->basePriceCents) / $this->basePriceCents) * 100;
    }

    /**
     * Check if price was changed
     */
    public function wasChanged(): bool
    {
        return $this->effectivePriceCents !== $this->basePriceCents;
    }

    /**
     * To array representation
     */
    public function toArray(): array
    {
        return [
            'base_price_cents' => $this->basePriceCents,
            'effective_price_cents' => $this->effectivePriceCents,
            'difference_cents' => $this->getDifferenceCents(),
            'change_percentage' => round($this->getChangePercentage(), 2),
            'was_changed' => $this->wasChanged(),
            'source_rule_id' => $this->sourceRuleId,
            'strategy' => $this->strategy,
            'metadata' => $this->metadata,
        ];
    }
}
