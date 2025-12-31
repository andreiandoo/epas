<?php

namespace App\Services\WhatsApp\Adapters;

use Illuminate\Support\Str;

/**
 * Mock BSP Adapter for testing and development
 *
 * Simulates WhatsApp BSP behavior without making real API calls.
 */
class MockBspAdapter implements BspAdapterInterface
{
    protected bool $authenticated = false;
    protected array $credentials = [];
    protected array $sentMessages = [];
    protected array $templates = [];

    /**
     * {@inheritdoc}
     */
    public function authenticate(array $credentials): array
    {
        if (empty($credentials['api_key']) && empty($credentials['access_token'])) {
            return [
                'success' => false,
                'message' => 'Missing API key or access token',
            ];
        }

        $this->authenticated = true;
        $this->credentials = $credentials;

        return [
            'success' => true,
            'message' => 'Mock authentication successful',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function sendTemplate(string $to, string $templateName, array $variables = [], array $options = []): array
    {
        if (!$this->authenticated) {
            return [
                'success' => false,
                'message_id' => null,
                'status' => 'failed',
                'cost' => null,
                'error_code' => 'AUTH_ERROR',
                'error_message' => 'Not authenticated',
            ];
        }

        // Validate E.164 phone format
        if (!preg_match('/^\+\d{10,15}$/', $to)) {
            return [
                'success' => false,
                'message_id' => null,
                'status' => 'failed',
                'cost' => null,
                'error_code' => 'INVALID_PHONE',
                'error_message' => 'Phone number must be in E.164 format',
            ];
        }

        // Simulate 95% success rate
        $random = rand(1, 100);
        if ($random > 95) {
            return [
                'success' => false,
                'message_id' => null,
                'status' => 'failed',
                'cost' => null,
                'error_code' => 'RATE_LIMIT',
                'error_message' => 'Rate limit exceeded',
            ];
        }

        // Generate mock message ID
        $messageId = 'wamid.mock_' . strtoupper(Str::random(32));

        // Store sent message for webhook simulation
        $this->sentMessages[$messageId] = [
            'to' => $to,
            'template_name' => $templateName,
            'variables' => $variables,
            'sent_at' => now()->toIso8601String(),
        ];

        return [
            'success' => true,
            'message_id' => $messageId,
            'status' => 'sent',
            'cost' => 0.005, // Mock cost: 0.5 cents
            'error_code' => null,
            'error_message' => null,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function registerTemplate(string $name, string $body, string $language, array $variables = [], array $options = []): array
    {
        if (!$this->authenticated) {
            return [
                'success' => false,
                'template_id' => null,
                'status' => 'draft',
                'message' => 'Not authenticated',
            ];
        }

        // Generate mock template ID
        $templateId = 'tmpl_mock_' . strtoupper(Str::random(16));

        // Simulate approval (90% approval rate)
        $random = rand(1, 100);
        $status = $random <= 90 ? 'approved' : 'rejected';

        $this->templates[$templateId] = [
            'name' => $name,
            'body' => $body,
            'language' => $language,
            'variables' => $variables,
            'status' => $status,
            'created_at' => now()->toIso8601String(),
        ];

        return [
            'success' => true,
            'template_id' => $templateId,
            'status' => $status,
            'message' => $status === 'approved'
                ? 'Template approved automatically (mock)'
                : 'Template rejected: Invalid variable format (mock)',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplateStatus(string $templateId): array
    {
        if (!isset($this->templates[$templateId])) {
            return [
                'success' => false,
                'status' => 'not_found',
                'rejection_reason' => null,
            ];
        }

        $template = $this->templates[$templateId];

        return [
            'success' => true,
            'status' => $template['status'],
            'rejection_reason' => $template['status'] === 'rejected'
                ? 'Invalid variable format (mock rejection)'
                : null,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function webhookHandler(array $payload): array
    {
        // Simulate webhook payload parsing
        $type = $payload['type'] ?? 'message_status';
        $messageId = $payload['message_id'] ?? null;

        if ($type === 'message_status' && $messageId) {
            // Simulate delivery progression
            $statuses = ['sent', 'delivered', 'read'];
            $randomStatus = $statuses[array_rand($statuses)];

            return [
                'type' => 'message_status',
                'message_id' => $messageId,
                'status' => $randomStatus,
                'timestamp' => now()->toIso8601String(),
                'error_code' => null,
                'error_message' => null,
            ];
        }

        return [
            'type' => 'unknown',
            'message_id' => null,
            'status' => null,
            'timestamp' => null,
            'error_code' => null,
            'error_message' => null,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        // Mock signature verification (always passes for mock)
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * {@inheritdoc}
     */
    public function getAccountInfo(): array
    {
        if (!$this->authenticated) {
            return [
                'success' => false,
                'balance' => null,
                'quota_limit' => null,
                'quota_used' => null,
                'tier' => null,
            ];
        }

        return [
            'success' => true,
            'balance' => 100.00,
            'quota_limit' => 10000,
            'quota_used' => 1234,
            'tier' => 'standard',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function testConnection(): array
    {
        if (!$this->authenticated) {
            return [
                'connected' => false,
                'message' => 'Not authenticated',
            ];
        }

        return [
            'connected' => true,
            'message' => 'Mock connection successful',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getRateLimits(): array
    {
        return [
            'messages_per_second' => 10,
            'messages_per_minute' => 600,
            'messages_per_hour' => 36000,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata(): array
    {
        return [
            'name' => 'Mock BSP Adapter',
            'version' => '1.0.0',
            'supports_media' => true,
            'supports_buttons' => true,
            'supports_delivery_receipts' => true,
            'supports_read_receipts' => true,
        ];
    }

    /**
     * Get sent messages (for testing)
     */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    /**
     * Get registered templates (for testing)
     */
    public function getTemplates(): array
    {
        return $this->templates;
    }

    /**
     * Reset adapter state (for testing)
     */
    public function reset(): void
    {
        $this->authenticated = false;
        $this->credentials = [];
        $this->sentMessages = [];
        $this->templates = [];
    }
}
