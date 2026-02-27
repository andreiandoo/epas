<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceClient;
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

        return response()->json($response, $code);
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
        $fromAddress = $client->getEmailFromAddress();
        $fromName = $client->getEmailFromName();

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

        // Try marketplace-specific mail config first
        if ($client->hasMailConfigured()) {
            $transport = $client->getMailTransport();
            if ($transport) {
                $email = (new \Symfony\Component\Mime\Email())
                    ->from(new \Symfony\Component\Mime\Address($fromAddress, $fromName))
                    ->to(new \Symfony\Component\Mime\Address($toEmail, $toName))
                    ->subject($subject)
                    ->html($html);

                $transport->send($email);
                $log->markSent();
                return;
            }
        }

        // Fallback to Laravel default mailer
        Mail::html($html, function ($message) use ($toEmail, $toName, $subject) {
            $message->to($toEmail, $toName)->subject($subject);
        });
        $log->markSent();
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
