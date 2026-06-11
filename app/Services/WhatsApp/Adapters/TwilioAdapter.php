<?php

namespace App\Services\WhatsApp\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Twilio WhatsApp BSP Adapter
 *
 * Implements WhatsApp messaging via Twilio's Content API
 * @see https://www.twilio.com/docs/whatsapp/api
 */
class TwilioAdapter implements BspAdapterInterface
{
    protected bool $authenticated = false;
    protected array $credentials = [];
    protected string $accountSid = '';
    protected string $authToken = '';
    protected string $fromNumber = ''; // Twilio WhatsApp number (e.g., whatsapp:+14155238886)
    protected string $baseUrl = 'https://api.twilio.com/2010-04-01';
    protected string $contentBaseUrl = 'https://content.twilio.com/v1';

    /**
     * {@inheritdoc}
     */
    public function authenticate(array $credentials): array
    {
        if (empty($credentials['account_sid']) || empty($credentials['auth_token'])) {
            return [
                'success' => false,
                'message' => 'Missing Twilio Account SID or Auth Token',
            ];
        }

        $this->accountSid = $credentials['account_sid'];
        $this->authToken = $credentials['auth_token'];
        $this->fromNumber = $credentials['from_number'] ?? '';

        // Validate credentials by fetching account info
        try {
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->get("{$this->baseUrl}/Accounts/{$this->accountSid}.json");

            if ($response->successful()) {
                $this->authenticated = true;
                $this->credentials = $credentials;

                return [
                    'success' => true,
                    'message' => 'Twilio authentication successful',
                ];
            }

            return [
                'success' => false,
                'message' => 'Invalid Twilio credentials: ' . $response->json('message', 'Unknown error'),
            ];

        } catch (\Exception $e) {
            Log::error('Twilio authentication failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Twilio authentication failed: ' . $e->getMessage(),
            ];
        }
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

        try {
            // Format phone numbers for WhatsApp
            $from = 'whatsapp:' . ltrim($this->fromNumber, 'whatsapp:');
            $toFormatted = 'whatsapp:' . $to;

            // Build Content SID from template name or use directly if it's a SID
            $contentSid = Str::startsWith($templateName, 'HX')
                ? $templateName
                : $options['content_sid'] ?? null;

            if (!$contentSid) {
                return [
                    'success' => false,
                    'message_id' => null,
                    'status' => 'failed',
                    'cost' => null,
                    'error_code' => 'MISSING_CONTENT_SID',
                    'error_message' => 'Content SID is required for template messages',
                ];
            }

            // Build content variables in Twilio format
            $contentVariables = [];
            foreach ($variables as $key => $value) {
                $contentVariables[(string) ($key + 1)] = $value; // Twilio uses 1-indexed variables
            }

            $payload = [
                'From' => $from,
                'To' => $toFormatted,
                'ContentSid' => $contentSid,
                'ContentVariables' => json_encode($contentVariables),
            ];

            // Add media if provided
            if (isset($options['media_url'])) {
                $payload['MediaUrl'] = $options['media_url'];
            }

            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->asForm()
                ->post("{$this->baseUrl}/Accounts/{$this->accountSid}/Messages.json", $payload);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'message_id' => $data['sid'] ?? null,
                    'status' => $this->mapTwilioStatus($data['status'] ?? 'queued'),
                    'cost' => isset($data['price']) ? (float) abs($data['price']) : null,
                    'error_code' => null,
                    'error_message' => null,
                ];
            }

            $errorData = $response->json();
            Log::error('Twilio send template failed', [
                'error_code' => $errorData['code'] ?? null,
                'error_message' => $errorData['message'] ?? null,
                'to' => $to,
            ]);

            return [
                'success' => false,
                'message_id' => null,
                'status' => 'failed',
                'cost' => null,
                'error_code' => (string) ($errorData['code'] ?? 'UNKNOWN'),
                'error_message' => $errorData['message'] ?? 'Unknown error',
            ];

        } catch (\Exception $e) {
            Log::error('Twilio sendTemplate exception', [
                'error' => $e->getMessage(),
                'to' => $to,
            ]);

            return [
                'success' => false,
                'message_id' => null,
                'status' => 'failed',
                'cost' => null,
                'error_code' => 'EXCEPTION',
                'error_message' => $e->getMessage(),
            ];
        }
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

        try {
            // Create Content Template via Twilio Content API
            $payload = [
                'friendly_name' => $name,
                'language' => $language,
                'types' => [
                    'twilio/text' => [
                        'body' => $body,
                    ],
                ],
            ];

            // Add variables if provided
            if (!empty($variables)) {
                $payload['variables'] = $variables;
            }

            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->post("{$this->contentBaseUrl}/Content", $payload);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'template_id' => $data['sid'] ?? null,
                    'status' => 'submitted', // Twilio templates need approval
                    'message' => 'Template submitted for approval',
                ];
            }

            $errorData = $response->json();

            return [
                'success' => false,
                'template_id' => null,
                'status' => 'draft',
                'message' => $errorData['message'] ?? 'Failed to create template',
            ];

        } catch (\Exception $e) {
            Log::error('Twilio registerTemplate exception', [
                'error' => $e->getMessage(),
                'name' => $name,
            ]);

            return [
                'success' => false,
                'template_id' => null,
                'status' => 'draft',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplateStatus(string $templateId): array
    {
        if (!$this->authenticated) {
            return [
                'success' => false,
                'status' => 'not_found',
                'rejection_reason' => null,
            ];
        }

        try {
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->get("{$this->contentBaseUrl}/Content/{$templateId}");

            if ($response->successful()) {
                $data = $response->json();
                $approvalStatus = $data['approval_requests']['whatsapp']['status'] ?? 'unsubmitted';

                return [
                    'success' => true,
                    'status' => $this->mapTwilioTemplateStatus($approvalStatus),
                    'rejection_reason' => $approvalStatus === 'rejected'
                        ? ($data['approval_requests']['whatsapp']['rejection_reason'] ?? 'Unknown')
                        : null,
                ];
            }

            return [
                'success' => false,
                'status' => 'not_found',
                'rejection_reason' => null,
            ];

        } catch (\Exception $e) {
            Log::error('Twilio getTemplateStatus exception', [
                'error' => $e->getMessage(),
                'template_id' => $templateId,
            ]);

            return [
                'success' => false,
                'status' => 'not_found',
                'rejection_reason' => null,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function webhookHandler(array $payload): array
    {
        try {
            $type = 'message_status';
            $messageId = $payload['MessageSid'] ?? $payload['SmsSid'] ?? null;
            $status = $payload['MessageStatus'] ?? $payload['SmsStatus'] ?? null;

            if (!$messageId || !$status) {
                return [
                    'type' => 'unknown',
                    'message_id' => null,
                    'status' => null,
                    'timestamp' => null,
                    'error_code' => null,
                    'error_message' => null,
                ];
            }

            $errorCode = $payload['ErrorCode'] ?? null;
            $errorMessage = $payload['ErrorMessage'] ?? null;

            return [
                'type' => $type,
                'message_id' => $messageId,
                'status' => $this->mapTwilioStatus($status),
                'timestamp' => now()->toIso8601String(),
                'error_code' => $errorCode ? (string) $errorCode : null,
                'error_message' => $errorMessage,
            ];

        } catch (\Exception $e) {
            Log::error('Twilio webhookHandler exception', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return [
                'type' => 'unknown',
                'message_id' => null,
                'status' => null,
                'timestamp' => null,
                'error_code' => 'EXCEPTION',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        // Twilio uses X-Twilio-Signature header with SHA1-HMAC
        // Format: URL + sorted POST params, then HMAC-SHA1
        // For simplicity, we'll use the auth token as secret
        $expectedSignature = base64_encode(hash_hmac('sha1', $payload, $secret ?: $this->authToken, true));

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

        try {
            // Get account balance
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->get("{$this->baseUrl}/Accounts/{$this->accountSid}/Balance.json");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'balance' => isset($data['balance']) ? (float) $data['balance'] : null,
                    'quota_limit' => null, // Twilio doesn't have hard quotas
                    'quota_used' => null,
                    'tier' => $data['account_status'] ?? 'active',
                ];
            }

            return [
                'success' => false,
                'balance' => null,
                'quota_limit' => null,
                'quota_used' => null,
                'tier' => null,
            ];

        } catch (\Exception $e) {
            Log::error('Twilio getAccountInfo exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'balance' => null,
                'quota_limit' => null,
                'quota_used' => null,
                'tier' => null,
            ];
        }
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

        try {
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->get("{$this->baseUrl}/Accounts/{$this->accountSid}.json");

            if ($response->successful()) {
                return [
                    'connected' => true,
                    'message' => 'Twilio connection successful',
                ];
            }

            return [
                'connected' => false,
                'message' => 'Twilio connection failed: ' . $response->json('message', 'Unknown error'),
            ];

        } catch (\Exception $e) {
            return [
                'connected' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRateLimits(): array
    {
        // Twilio WhatsApp rate limits (as of 2024)
        return [
            'messages_per_second' => 80,
            'messages_per_minute' => 4800,
            'messages_per_hour' => 288000,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata(): array
    {
        return [
            'name' => 'Twilio WhatsApp BSP Adapter',
            'version' => '1.0.0',
            'supports_media' => true,
            'supports_buttons' => true,
            'supports_delivery_receipts' => true,
            'supports_read_receipts' => true,
        ];
    }

    /**
     * Map Twilio message status to standard status
     */
    protected function mapTwilioStatus(string $twilioStatus): string
    {
        return match ($twilioStatus) {
            'queued', 'accepted' => 'queued',
            'sending' => 'sent',
            'sent', 'delivered' => 'delivered',
            'read' => 'read',
            'failed', 'undelivered' => 'failed',
            default => 'unknown',
        };
    }

    /**
     * Map Twilio template approval status to standard status
     */
    protected function mapTwilioTemplateStatus(string $twilioStatus): string
    {
        return match ($twilioStatus) {
            'unsubmitted' => 'draft',
            'pending' => 'submitted',
            'approved' => 'approved',
            'rejected' => 'rejected',
            default => 'draft',
        };
    }
}
