<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceClient;
use App\Support\EmailRouting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

abstract class BaseController extends Controller
{
    /**
     * Get marketplace client from request
     */
    protected function getClient(Request $request): ?MarketplaceClient
    {
        return $request->attributes->get('marketplace_client');
    }

    /**
     * Require authenticated marketplace client
     */
    protected function requireClient(Request $request): MarketplaceClient
    {
        $client = $this->getClient($request);

        if (!$client) {
            abort(401, 'Marketplace client authentication required');
        }

        if (!$client->isActive()) {
            abort(403, 'Marketplace client account is not active');
        }

        return $client;
    }

    /**
     * Standard success response
     */
    protected function success(mixed $data = null, string $message = null, int $code = 200): JsonResponse
    {
        $response = ['success' => true];

        if ($message) {
            $response['message'] = $message;
        }

        if (!is_null($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $code)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Standard error response
     */
    protected function error(string $message, int $code = 400, array $errors = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Send email via marketplace mail transport with fallback to Laravel default mailer.
     * This ensures emails are sent from the marketplace's configured SMTP/provider,
     * matching the behavior of order confirmation emails.
     */
    protected function sendMarketplaceEmail(MarketplaceClient $client, string $toEmail, string $toName, string $subject, string $html, array $logExtra = []): void
    {
        static::sendViaMarketplace($client, $toEmail, $toName, $subject, $html, $logExtra);
    }

    /**
     * Static helper to send email via marketplace transport.
     * Can be called from closures, jobs, and other contexts outside controller instances.
     */
    public static function sendViaMarketplace(MarketplaceClient $client, string $toEmail, string $toName, string $subject, string $html, array $logExtra = []): void
    {
        // Auto-route by template_slug: transactional templates use the platform-owned
        // (transactional) provider; everything else stays on the primary provider.
        // Both transactional accessors fall back to the primary when not configured,
        // so behavior for marketplaces that haven't set a second provider is unchanged.
        $slug = $logExtra['template_slug'] ?? null;
        $useTransactional = EmailRouting::isTransactional($slug);

        $fromAddress = $useTransactional
            ? $client->getTransactionalEmailFromAddress()
            : $client->getEmailFromAddress();
        $fromName = $useTransactional
            ? $client->getTransactionalEmailFromName()
            : $client->getEmailFromName();

        $hasPrimaryConfig = $client->hasMailConfigured();
        $hasTransactionalConfig = $client->hasTransactionalMailConfigured();
        $marketplaceConfigured = $useTransactional
            ? ($hasTransactionalConfig || $hasPrimaryConfig)
            : $hasPrimaryConfig;

        // Log the email
        $log = \App\Models\MarketplaceEmailLog::create(array_merge([
            'marketplace_client_id' => $client->id,
            'to_email' => $toEmail,
            'to_name' => $toName,
            'from_email' => $fromAddress,
            'from_name' => $fromName,
            'subject' => $subject,
            'body_html' => $html,
            'status' => 'pending',
        ], $logExtra));

        try {
            if ($marketplaceConfigured) {
                $transport = $useTransactional
                    ? $client->getTransactionalMailTransport()
                    : $client->getMailTransport();
                if ($transport) {
                    $email = (new \Symfony\Component\Mime\Email())
                        ->from(new \Symfony\Component\Mime\Address($fromAddress, $fromName))
                        ->to(new \Symfony\Component\Mime\Address($toEmail, $toName))
                        ->subject($subject)
                        ->html($html);

                    // Optional reply-to. Used by e.g. the venue contact form
                    // so the venue owner can hit "Reply" and reach the
                    // visitor that actually wrote the message.
                    if (!empty($logExtra['reply_to_email'])) {
                        $email->replyTo(new \Symfony\Component\Mime\Address(
                            $logExtra['reply_to_email'],
                            $logExtra['reply_to_name'] ?? ''
                        ));
                    }

                    $sentMessage = $transport->send($email);
                    $messageId = $sentMessage?->getMessageId();
                    $log->markSent($messageId);

                    // Audit log is best-effort. If the channel write fails (e.g. log
                    // file perms), the email already went out — never propagate.
                    try {
                        $settingsForLog = $useTransactional
                            ? $client->getTransactionalMailSettings()
                            : $client->getMailSettings();
                        Log::channel('marketplace')->info('Email sent via marketplace transport', [
                            'marketplace_client_id' => $client->id,
                            'to' => $toEmail,
                            'subject' => $subject,
                            'message_id' => $messageId,
                            'driver' => $settingsForLog['driver'] ?? 'unknown',
                            'transport' => $useTransactional
                                ? ($hasTransactionalConfig ? 'transactional' : 'primary (transactional fallback)')
                                : 'primary',
                            'template_slug' => $slug,
                        ]);
                    } catch (\Throwable $logEx) {
                        // swallow — DB row already marked sent with message_id
                    }
                    return;
                }

                Log::channel('marketplace')->warning('Marketplace mail configured but transport creation failed, falling back to Laravel mailer', [
                    'marketplace_client_id' => $client->id,
                    'transport' => $useTransactional ? 'transactional' : 'primary',
                ]);
            }

            // Fallback to Laravel default mailer
            Mail::html($html, function ($message) use ($toEmail, $toName, $subject) {
                $message->to($toEmail, $toName)->subject($subject);
            });
            $log->markSent();

            try {
                Log::channel('marketplace')->info('Email sent via Laravel default mailer (fallback)', [
                    'marketplace_client_id' => $client->id,
                    'to' => $toEmail,
                    'subject' => $subject,
                    'template_slug' => $slug,
                ]);
            } catch (\Throwable $logEx) {
                // swallow — DB row already marked sent
            }
        } catch (\Throwable $e) {
            try {
                Log::channel('marketplace')->error('Failed to send marketplace email', [
                    'marketplace_client_id' => $client->id,
                    'to' => $toEmail,
                    'subject' => $subject,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                ]);
            } catch (\Throwable $logEx) {
                // swallow — preserve original exception
            }
            $log->markFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate a signed preview token for test orders on unpublished events.
     * Token is HMAC-signed, event-specific, and expires after 24 hours.
     */
    public static function generatePreviewToken(int $eventId, int $adminId, int $hoursValid = 24): string
    {
        $payload = json_encode([
            'event_id' => $eventId,
            'admin_id' => $adminId,
            'expires' => now()->addHours($hoursValid)->timestamp,
        ]);

        $encoded = base64_encode($payload);
        $signature = hash_hmac('sha256', $encoded, config('app.key'));

        return $encoded . '.' . $signature;
    }

    /**
     * Validate a preview token. Returns the decoded payload or null if invalid.
     */
    protected function validatePreviewToken(?string $token, int $eventId): ?array
    {
        if (!$token || !str_contains($token, '.')) {
            return null;
        }

        [$encoded, $signature] = explode('.', $token, 2);

        $expectedSignature = hash_hmac('sha256', $encoded, config('app.key'));
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payload = json_decode(base64_decode($encoded), true);
        if (!$payload) {
            return null;
        }

        if (($payload['expires'] ?? 0) < now()->timestamp) {
            return null;
        }

        if (($payload['event_id'] ?? null) !== $eventId) {
            return null;
        }

        return $payload;
    }

    /**
     * Paginated response
     *
     * @param mixed $paginator Laravel paginator instance
     * @param callable|array $callbackOrMeta Either a callback to transform items, or meta array
     * @param array $meta Additional meta data (only used when callback is provided)
     */
    protected function paginated($paginator, callable|array $callbackOrMeta = [], array $meta = []): JsonResponse
    {
        // Determine if second param is callback or meta array
        if (is_callable($callbackOrMeta)) {
            $items = array_map($callbackOrMeta, $paginator->items());
        } else {
            $items = $paginator->items();
            $meta = $callbackOrMeta;
        }

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => array_merge([
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ], $meta),
        ]);
    }
}
