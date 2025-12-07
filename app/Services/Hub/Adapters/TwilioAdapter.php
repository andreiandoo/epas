<?php

namespace App\Services\Hub\Adapters;

/**
 * Twilio Integration Adapter
 *
 * Supports:
 * - SMS sending
 * - Voice calls
 * - WhatsApp messaging
 * - Webhook handling
 */
class TwilioAdapter extends BaseConnectorAdapter
{
    protected string $slug = 'twilio';
    protected string $baseUrl = 'https://api.twilio.com/2010-04-01';

    public function getAuthorizationUrl(string $connectionId, array $config): string
    {
        // Twilio uses API keys, not OAuth
        return config('app.url') . '/hub/twilio/setup?connection=' . $connectionId;
    }

    public function exchangeCodeForTokens(string $code, array $config): array
    {
        // For Twilio, we expect API credentials in the 'code' as JSON
        $credentials = json_decode($code, true);

        return [
            'access_token' => 'api_key',
            'account_sid' => $credentials['account_sid'] ?? null,
            'auth_token' => $credentials['auth_token'] ?? null,
            'phone_number' => $credentials['phone_number'] ?? null,
        ];
    }

    public function refreshTokens(string $refreshToken, array $config): array
    {
        // API keys don't refresh
        return ['access_token' => 'api_key'];
    }

    protected function getAuthHeaders(array $credentials): array
    {
        $accountSid = $credentials['account_sid'];
        $authToken = $credentials['auth_token'];

        return [
            'Authorization' => 'Basic ' . base64_encode("{$accountSid}:{$authToken}"),
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
    }

    public function testConnection(array $credentials): array
    {
        try {
            $accountSid = $credentials['account_sid'];
            $response = \Illuminate\Support\Facades\Http::withBasicAuth(
                $accountSid,
                $credentials['auth_token']
            )->get("{$this->baseUrl}/Accounts/{$accountSid}.json");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'account_name' => $data['friendly_name'] ?? null,
                    'status' => $data['status'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => 'Invalid credentials',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function executeAction(string $action, array $data, array $credentials): array
    {
        return match ($action) {
            'send_sms' => $this->sendSms($data, $credentials),
            'send_whatsapp' => $this->sendWhatsApp($data, $credentials),
            'make_call' => $this->makeCall($data, $credentials),
            'list_messages' => $this->listMessages($data, $credentials),
            'get_message' => $this->getMessage($data, $credentials),
            default => throw new \Exception("Unsupported action: {$action}"),
        };
    }

    protected function sendSms(array $data, array $credentials): array
    {
        $accountSid = $credentials['account_sid'];

        $response = \Illuminate\Support\Facades\Http::asForm()
            ->withBasicAuth($accountSid, $credentials['auth_token'])
            ->post("{$this->baseUrl}/Accounts/{$accountSid}/Messages.json", [
                'To' => $data['to'],
                'From' => $data['from'] ?? $credentials['phone_number'],
                'Body' => $data['body'],
                'StatusCallback' => $data['status_callback'] ?? null,
            ]);

        return $response->json();
    }

    protected function sendWhatsApp(array $data, array $credentials): array
    {
        $accountSid = $credentials['account_sid'];

        // WhatsApp numbers need 'whatsapp:' prefix
        $to = str_starts_with($data['to'], 'whatsapp:') ? $data['to'] : "whatsapp:{$data['to']}";
        $from = $data['from'] ?? "whatsapp:{$credentials['phone_number']}";

        $payload = [
            'To' => $to,
            'From' => $from,
        ];

        // Use template or regular message
        if (isset($data['template_sid'])) {
            $payload['ContentSid'] = $data['template_sid'];
            if (isset($data['content_variables'])) {
                $payload['ContentVariables'] = json_encode($data['content_variables']);
            }
        } else {
            $payload['Body'] = $data['body'];
        }

        $response = \Illuminate\Support\Facades\Http::asForm()
            ->withBasicAuth($accountSid, $credentials['auth_token'])
            ->post("{$this->baseUrl}/Accounts/{$accountSid}/Messages.json", $payload);

        return $response->json();
    }

    protected function makeCall(array $data, array $credentials): array
    {
        $accountSid = $credentials['account_sid'];

        $response = \Illuminate\Support\Facades\Http::asForm()
            ->withBasicAuth($accountSid, $credentials['auth_token'])
            ->post("{$this->baseUrl}/Accounts/{$accountSid}/Calls.json", [
                'To' => $data['to'],
                'From' => $data['from'] ?? $credentials['phone_number'],
                'Url' => $data['twiml_url'], // URL returning TwiML instructions
                'StatusCallback' => $data['status_callback'] ?? null,
            ]);

        return $response->json();
    }

    protected function listMessages(array $data, array $credentials): array
    {
        $accountSid = $credentials['account_sid'];

        $params = array_filter([
            'To' => $data['to'] ?? null,
            'From' => $data['from'] ?? null,
            'PageSize' => $data['limit'] ?? 50,
        ]);

        $response = \Illuminate\Support\Facades\Http::withBasicAuth($accountSid, $credentials['auth_token'])
            ->get("{$this->baseUrl}/Accounts/{$accountSid}/Messages.json", $params);

        return $response->json();
    }

    protected function getMessage(array $data, array $credentials): array
    {
        $accountSid = $credentials['account_sid'];
        $messageSid = $data['message_sid'];

        $response = \Illuminate\Support\Facades\Http::withBasicAuth($accountSid, $credentials['auth_token'])
            ->get("{$this->baseUrl}/Accounts/{$accountSid}/Messages/{$messageSid}.json");

        return $response->json();
    }

    public function parseWebhookEventType(array $payload): string
    {
        // Twilio webhooks send status in different ways
        if (isset($payload['MessageStatus'])) {
            return 'message.' . strtolower($payload['MessageStatus']);
        }
        if (isset($payload['CallStatus'])) {
            return 'call.' . strtolower($payload['CallStatus']);
        }
        return 'unknown';
    }

    public function getSupportedActions(): array
    {
        return [
            'send_sms' => 'Send an SMS message',
            'send_whatsapp' => 'Send a WhatsApp message',
            'make_call' => 'Initiate a voice call',
            'list_messages' => 'List sent/received messages',
            'get_message' => 'Get message details',
        ];
    }

    public function getSupportedEvents(): array
    {
        return [
            'message.delivered' => 'Message was delivered',
            'message.sent' => 'Message was sent',
            'message.failed' => 'Message delivery failed',
            'message.received' => 'Incoming message received',
            'call.completed' => 'Call was completed',
            'call.failed' => 'Call failed',
        ];
    }
}
