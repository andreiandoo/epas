<?php

namespace App\Services\Integrations\Twilio;

use App\Models\Integrations\Twilio\TwilioConnection;
use App\Models\Integrations\Twilio\TwilioMessage;
use App\Models\Integrations\Twilio\TwilioCall;
use Illuminate\Support\Facades\Http;

class TwilioService
{
    protected string $apiUrl = 'https://api.twilio.com/2010-04-01';

    public function connect(int $tenantId, array $credentials): TwilioConnection
    {
        // Validate credentials by making a test API call
        $response = Http::withBasicAuth($credentials['account_sid'], $credentials['auth_token'])
            ->get("{$this->apiUrl}/Accounts/{$credentials['account_sid']}.json");

        if (!$response->successful()) {
            throw new \Exception('Invalid Twilio credentials');
        }

        return TwilioConnection::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'account_sid' => $credentials['account_sid'],
                'auth_token' => $credentials['auth_token'],
                'phone_number' => $credentials['phone_number'] ?? null,
                'messaging_service_sid' => $credentials['messaging_service_sid'] ?? null,
                'enabled_channels' => $credentials['channels'] ?? ['sms'],
                'status' => 'active',
                'connected_at' => now(),
            ]
        );
    }

    public function disconnect(TwilioConnection $connection): bool
    {
        $connection->update(['status' => 'disconnected']);
        return true;
    }

    public function testConnection(TwilioConnection $connection): bool
    {
        $response = Http::withBasicAuth($connection->account_sid, $connection->auth_token)
            ->get("{$this->apiUrl}/Accounts/{$connection->account_sid}.json");

        return $response->successful();
    }

    // SMS Operations
    public function sendSms(TwilioConnection $connection, string $to, string $body, array $options = []): TwilioMessage
    {
        return $this->sendMessage($connection, $to, $body, 'sms', $options);
    }

    // WhatsApp Operations
    public function sendWhatsApp(TwilioConnection $connection, string $to, string $body, array $options = []): TwilioMessage
    {
        $to = str_starts_with($to, 'whatsapp:') ? $to : "whatsapp:{$to}";
        $from = "whatsapp:{$connection->phone_number}";

        return $this->sendMessage($connection, $to, $body, 'whatsapp', array_merge($options, ['from' => $from]));
    }

    protected function sendMessage(TwilioConnection $connection, string $to, string $body, string $channel, array $options = []): TwilioMessage
    {
        $from = $options['from'] ?? $connection->phone_number;

        $message = TwilioMessage::create([
            'connection_id' => $connection->id,
            'channel' => $channel,
            'direction' => 'outbound',
            'from_number' => $from,
            'to_number' => $to,
            'body' => $body,
            'media_urls' => $options['media_urls'] ?? null,
            'status' => 'pending',
            'correlation_ref' => $options['correlation_ref'] ?? null,
        ]);

        $payload = [
            'From' => $from,
            'To' => $to,
            'Body' => $body,
        ];

        if (!empty($options['media_urls'])) {
            foreach ($options['media_urls'] as $i => $url) {
                $payload["MediaUrl{$i}"] = $url;
            }
        }

        if ($connection->messaging_service_sid && $channel === 'sms') {
            $payload['MessagingServiceSid'] = $connection->messaging_service_sid;
            unset($payload['From']);
        }

        $response = Http::withBasicAuth($connection->account_sid, $connection->auth_token)
            ->asForm()
            ->post("{$this->apiUrl}/Accounts/{$connection->account_sid}/Messages.json", $payload);

        if ($response->successful()) {
            $data = $response->json();
            $message->update([
                'message_sid' => $data['sid'],
                'status' => $data['status'],
                'sent_at' => now(),
                'price' => $data['price'] ?? null,
                'price_unit' => $data['price_unit'] ?? null,
            ]);
        } else {
            $message->update([
                'status' => 'failed',
                'error_details' => $response->json(),
            ]);
        }

        $connection->update(['last_used_at' => now()]);

        return $message->fresh();
    }

    // Voice Operations
    public function makeCall(TwilioConnection $connection, string $to, string $twiml, array $options = []): TwilioCall
    {
        $call = TwilioCall::create([
            'connection_id' => $connection->id,
            'direction' => 'outbound',
            'from_number' => $connection->phone_number,
            'to_number' => $to,
            'status' => 'pending',
            'twiml' => $twiml,
            'correlation_ref' => $options['correlation_ref'] ?? null,
        ]);

        $payload = [
            'From' => $connection->phone_number,
            'To' => $to,
            'Twiml' => $twiml,
        ];

        if (isset($options['url'])) {
            unset($payload['Twiml']);
            $payload['Url'] = $options['url'];
        }

        $response = Http::withBasicAuth($connection->account_sid, $connection->auth_token)
            ->asForm()
            ->post("{$this->apiUrl}/Accounts/{$connection->account_sid}/Calls.json", $payload);

        if ($response->successful()) {
            $data = $response->json();
            $call->update([
                'call_sid' => $data['sid'],
                'status' => $data['status'],
                'started_at' => now(),
            ]);
        } else {
            $call->update([
                'status' => 'failed',
                'error_details' => $response->json(),
            ]);
        }

        return $call->fresh();
    }

    // Webhook handling
    public function handleStatusCallback(array $payload): void
    {
        $messageSid = $payload['MessageSid'] ?? null;
        $callSid = $payload['CallSid'] ?? null;

        if ($messageSid) {
            $message = TwilioMessage::where('message_sid', $messageSid)->first();
            if ($message) {
                $message->update([
                    'status' => $payload['MessageStatus'] ?? $payload['Status'],
                    'delivered_at' => in_array($payload['MessageStatus'] ?? '', ['delivered', 'read']) ? now() : null,
                    'price' => $payload['Price'] ?? null,
                    'price_unit' => $payload['PriceUnit'] ?? null,
                ]);
            }
        }

        if ($callSid) {
            $call = TwilioCall::where('call_sid', $callSid)->first();
            if ($call) {
                $call->update([
                    'status' => $payload['CallStatus'],
                    'duration' => $payload['CallDuration'] ?? null,
                    'ended_at' => in_array($payload['CallStatus'], ['completed', 'failed', 'busy', 'no-answer']) ? now() : null,
                    'price' => $payload['Price'] ?? null,
                    'price_unit' => $payload['PriceUnit'] ?? null,
                ]);
            }
        }
    }

    public function getMessages(TwilioConnection $connection, array $filters = []): array
    {
        $params = array_filter([
            'To' => $filters['to'] ?? null,
            'From' => $filters['from'] ?? null,
            'DateSent>' => $filters['date_from'] ?? null,
            'DateSent<' => $filters['date_to'] ?? null,
            'PageSize' => $filters['limit'] ?? 50,
        ]);

        $response = Http::withBasicAuth($connection->account_sid, $connection->auth_token)
            ->get("{$this->apiUrl}/Accounts/{$connection->account_sid}/Messages.json", $params);

        return $response->json('messages') ?? [];
    }

    public function getConnection(int $tenantId): ?TwilioConnection
    {
        return TwilioConnection::where('tenant_id', $tenantId)->where('status', 'active')->first();
    }
}
