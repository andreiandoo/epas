<?php

namespace App\Services\Seating\Pricing\Contracts;

use App\Services\Seating\Pricing\DTO\PriceDecision;

/**
 * DynamicPricingEngine Interface
 *
 * Defines the contract for dynamic pricing implementations
 */
interface DynamicPricingEngine
{
    /**
     * Compute effective price for a specific seat
     *
     * @param int $eventSeatingId
     * @param string $seatUid
     * @return PriceDecision
     */
    public function computeEffectivePrice(int $eventSeatingId, string $seatUid): PriceDecision;

    /**
     * Compute effective prices for multiple seats (batch operation)
     *
     * @param int $eventSeatingId
     * @param array $seatUids
     * @return array<string, PriceDecision> Map of seat_uid => PriceDecision
     */
    public function computeBulkPrices(int $eventSeatingId, array $seatUids): array;

    /**
     * Apply dynamic repricing based on rules
     *
     * @param int $eventSeatingId
     * @param string $scope Scope: 'event', 'section', 'row', 'seat'
     * @param string|null $scopeRef Reference ID for scope
     * @return int Number of seats repriced
     */
    public function bulkReprice(int $eventSeatingId, string $scope, ?string $scopeRef = null): int;

    /**
     * Preview pricing changes without applying them
     *
     * @param int $eventSeatingId
     * @param string $scope
     * @param string|null $scopeRef
     * @return array Preview of changes
     */
    public function previewRepricing(int $eventSeatingId, string $scope, ?string $scopeRef = null): array;
}
