<?php

namespace App\Services\Tracking;

interface ConsentServiceInterface
{
    /**
     * Check if user has given consent for a specific category
     *
     * @param string $category Category name (analytics, marketing, etc.)
     * @return bool True if consent is granted, false otherwise
     */
    public function hasConsent(string $category): bool;

    /**
     * Get all consented categories
     *
     * @return array Array of category names that have consent
     */
    public function getConsentedCategories(): array;

    /**
     * Set consent for a category
     *
     * @param string $category Category name
     * @param bool $granted Whether consent is granted
     * @return void
     */
    public function setConsent(string $category, bool $granted): void;

    /**
     * Revoke all consents
     *
     * @return void
     */
    public function revokeAll(): void;

    /**
     * Get consent mode (opt-in or opt-out)
     *
     * @return string
     */
    public function getMode(): string;
}
