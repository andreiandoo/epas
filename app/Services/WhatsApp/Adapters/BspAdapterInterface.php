<?php

namespace App\Services\WhatsApp\Adapters;

/**
 * BSP (Business Solution Provider) Adapter Interface
 *
 * Provides vendor-agnostic abstraction for WhatsApp messaging via BSPs
 * like 360dialog, Twilio, Meta Cloud API, etc.
 */
interface BspAdapterInterface
{
    /**
     * Authenticate with BSP using provided credentials
     *
     * @param array $credentials API keys, tokens, etc.
     * @return array ['success' => bool, 'message' => string]
     */
    public function authenticate(array $credentials): array;

    /**
     * Send template message to recipient
     *
     * @param string $to E.164 formatted phone number
     * @param string $templateName Template identifier
     * @param array $variables Template variable values
     * @param array $options Additional options (media, buttons, etc.)
     * @return array [
     *   'success' => bool,
     *   'message_id' => string|null,
     *   'status' => string,
     *   'cost' => float|null,
     *   'error_code' => string|null,
     *   'error_message' => string|null
     * ]
     */
    public function sendTemplate(string $to, string $templateName, array $variables = [], array $options = []): array;

    /**
     * Register/submit a new template for approval
     *
     * @param string $name Template name
     * @param string $body Template body with placeholders
     * @param string $language Language code (e.g., en, ro)
     * @param array $variables Variable definitions
     * @param array $options Category, examples, etc.
     * @return array [
     *   'success' => bool,
     *   'template_id' => string|null,
     *   'status' => string (draft|submitted|approved|rejected),
     *   'message' => string
     * ]
     */
    public function registerTemplate(string $name, string $body, string $language, array $variables = [], array $options = []): array;

    /**
     * Check template approval status
     *
     * @param string $templateId BSP template identifier
     * @return array [
     *   'success' => bool,
     *   'status' => string (draft|submitted|approved|rejected),
     *   'rejection_reason' => string|null
     * ]
     */
    public function getTemplateStatus(string $templateId): array;

    /**
     * Handle webhook payload from BSP
     *
     * Processes delivery receipts, status updates, etc.
     *
     * @param array $payload Raw webhook payload
     * @return array [
     *   'type' => string (message_status|message_received|template_status),
     *   'message_id' => string|null,
     *   'status' => string (sent|delivered|read|failed),
     *   'timestamp' => string|null,
     *   'error_code' => string|null,
     *   'error_message' => string|null
     * ]
     */
    public function webhookHandler(array $payload): array;

    /**
     * Verify webhook signature
     *
     * @param string $payload Raw webhook payload
     * @param string $signature Signature header
     * @param string $secret Webhook secret
     * @return bool
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool;

    /**
     * Get account/quota information
     *
     * @return array [
     *   'success' => bool,
     *   'balance' => float|null,
     *   'quota_limit' => int|null,
     *   'quota_used' => int|null,
     *   'tier' => string|null
     * ]
     */
    public function getAccountInfo(): array;

    /**
     * Test connection to BSP
     *
     * @return array ['connected' => bool, 'message' => string]
     */
    public function testConnection(): array;

    /**
     * Get rate limits for this BSP
     *
     * @return array [
     *   'messages_per_second' => int,
     *   'messages_per_minute' => int,
     *   'messages_per_hour' => int
     * ]
     */
    public function getRateLimits(): array;

    /**
     * Get adapter metadata
     *
     * @return array [
     *   'name' => string,
     *   'version' => string,
     *   'supports_media' => bool,
     *   'supports_buttons' => bool,
     *   'supports_delivery_receipts' => bool,
     *   'supports_read_receipts' => bool
     * ]
     */
    public function getMetadata(): array;
}
