<?php

namespace App\Services\AudienceTargeting\Providers;

use App\Models\AudienceExport;
use App\Models\AudienceSegment;
use Illuminate\Support\Collection;

interface AudienceProviderInterface
{
    /**
     * Get the provider name
     */
    public function getName(): string;

    /**
     * Get the platform identifier
     */
    public function getPlatform(): string;

    /**
     * Check if the provider is configured and ready to use
     */
    public function isConfigured(): bool;

    /**
     * Create a custom audience from a segment
     *
     * @param AudienceSegment $segment
     * @param string $audienceName
     * @param string|null $description
     * @return array{external_id: string, name: string, matched_count: ?int}
     */
    public function createCustomAudience(
        AudienceSegment $segment,
        string $audienceName,
        ?string $description = null
    ): array;

    /**
     * Update an existing custom audience with new customers
     *
     * @param string $externalAudienceId
     * @param Collection $customers
     * @return array{matched_count: ?int}
     */
    public function updateCustomAudience(
        string $externalAudienceId,
        Collection $customers
    ): array;

    /**
     * Delete a custom audience
     *
     * @param string $externalAudienceId
     * @return bool
     */
    public function deleteCustomAudience(string $externalAudienceId): bool;

    /**
     * Create a lookalike audience based on a source audience
     *
     * @param string $sourceAudienceId
     * @param string $audienceName
     * @param array $options Platform-specific options (e.g., lookalike ratio)
     * @return array{external_id: string, name: string}
     */
    public function createLookalikeAudience(
        string $sourceAudienceId,
        string $audienceName,
        array $options = []
    ): array;

    /**
     * Get audience details from the platform
     *
     * @param string $externalAudienceId
     * @return array|null
     */
    public function getAudienceDetails(string $externalAudienceId): ?array;

    /**
     * Prepare customer data for upload (hashing, formatting)
     *
     * @param Collection $customers
     * @return array
     */
    public function prepareCustomerData(Collection $customers): array;

    /**
     * Validate provider-specific settings
     *
     * @param array $settings
     * @return array Array of errors, empty if valid
     */
    public function validateSettings(array $settings): array;
}
