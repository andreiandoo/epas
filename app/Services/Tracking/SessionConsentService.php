<?php

namespace App\Services\Tracking;

use Illuminate\Support\Facades\Session;

/**
 * Default consent service implementation using session storage
 *
 * This is a GDPR-compliant "deny all" default implementation.
 * Consent must be explicitly granted before any tracking scripts are loaded.
 */
class SessionConsentService implements ConsentServiceInterface
{
    private const SESSION_KEY = 'tracking_consent';
    private const MODE_OPT_IN = 'opt-in'; // Default: deny all until user explicitly consents

    /**
     * Check if user has given consent for a specific category
     */
    public function hasConsent(string $category): bool
    {
        $consents = $this->getConsents();

        return $consents[$category] ?? false; // Default to false (deny all)
    }

    /**
     * Get all consented categories
     */
    public function getConsentedCategories(): array
    {
        $consents = $this->getConsents();

        return array_keys(array_filter($consents, fn($value) => $value === true));
    }

    /**
     * Set consent for a category
     */
    public function setConsent(string $category, bool $granted): void
    {
        $consents = $this->getConsents();
        $consents[$category] = $granted;

        Session::put(self::SESSION_KEY, $consents);
    }

    /**
     * Revoke all consents
     */
    public function revokeAll(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Get consent mode
     */
    public function getMode(): string
    {
        return self::MODE_OPT_IN;
    }

    /**
     * Get all consents from session
     */
    private function getConsents(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    /**
     * Grant consent for all standard categories (for testing/admin purposes)
     */
    public function grantAll(): void
    {
        $this->setConsent('analytics', true);
        $this->setConsent('marketing', true);
        $this->setConsent('necessary', true);
        $this->setConsent('preferences', true);
    }

    /**
     * Helper: Check if any consent has been recorded
     */
    public function hasAnyConsent(): bool
    {
        return !empty($this->getConsents());
    }
}
