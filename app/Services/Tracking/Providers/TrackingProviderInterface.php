<?php

namespace App\Services\Tracking\Providers;

interface TrackingProviderInterface
{
    /**
     * Get provider name
     */
    public function getName(): string;

    /**
     * Get consent category required for this provider
     */
    public function getConsentCategory(): string;

    /**
     * Generate head injection code
     *
     * @param array $settings Provider-specific settings
     * @param string|null $nonce CSP nonce if available
     * @return string HTML/JavaScript code to inject in <head>
     */
    public function injectHead(array $settings, ?string $nonce = null): string;

    /**
     * Generate body end injection code
     *
     * @param array $settings Provider-specific settings
     * @param string|null $nonce CSP nonce if available
     * @return string HTML/JavaScript code to inject before </body>
     */
    public function injectBodyEnd(array $settings, ?string $nonce = null): string;

    /**
     * Get Data Layer adapter JavaScript
     *
     * Returns JS code that listens to tracking events and forwards them
     * to this provider's API
     *
     * @return string JavaScript code
     */
    public function getDataLayerAdapter(): string;

    /**
     * Validate provider settings
     *
     * @param array $settings
     * @return array Validation errors (empty if valid)
     */
    public function validateSettings(array $settings): array;
}
