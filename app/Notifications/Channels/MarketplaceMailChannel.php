<?php

namespace App\Notifications\Channels;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceClient;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Notification channel that routes mail through the marketplace's
 * configured SMTP transport instead of Laravel's default mailer.
 *
 * A notification opts in by:
 *   1. Returning ['marketplace-mail'] from via().
 *   2. Implementing one of:
 *        - toMarketplaceMail($notifiable): MailMessage
 *        - toMail($notifiable): MailMessage   (used as fallback)
 *
 * The notifiable must expose a MarketplaceClient via either:
 *   - getMarketplaceClient(): MarketplaceClient
 *   - $notifiable->marketplaceClient (relation)
 *   - $notifiable->marketplace_client_id (we'll fetch the client)
 *
 * If no marketplace context can be resolved, the channel logs a warning
 * and silently drops the message rather than fall back to localhost.
 */
class MarketplaceMailChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $client = $this->resolveMarketplaceClient($notifiable);
        if (!$client) {
            Log::channel('marketplace')->warning('MarketplaceMailChannel: no marketplace client resolved — dropping notification', [
                'notification' => get_class($notification),
                'notifiable' => get_class($notifiable),
            ]);
            return;
        }

        $email = $this->resolveRecipientEmail($notifiable);
        if (!$email) {
            Log::channel('marketplace')->warning('MarketplaceMailChannel: notifiable has no routeable email', [
                'notification' => get_class($notification),
                'notifiable' => get_class($notifiable),
            ]);
            return;
        }

        $name = $this->resolveRecipientName($notifiable);

        // 1) Try DB-backed marketplace template (per-marketplace branding via
        //    /marketplace/email-templates). The notification opts in by
        //    implementing marketplaceTemplateSlug($notifiable) +
        //    marketplaceTemplateData($notifiable). If the template exists for
        //    this marketplace, we render and dispatch. If not, we silently
        //    fall through to the Laravel MailMessage branch below.
        $rendered = $this->renderFromMarketplaceTemplate($notifiable, $notification, $client);
        if ($rendered) {
            $this->dispatch($client, $email, $name, $rendered['subject'], $rendered['body_html'], [
                'template_slug' => $rendered['slug'],
            ] + $this->extrasFromNotification($notifiable, $notification));
            return;
        }

        // 2) Fallback to MailMessage rendering (matches the pre-template flow).
        $message = $this->buildMailMessage($notifiable, $notification);
        if (!$message) return;

        $subject = $message->subject ?: 'Notification';
        $html = $this->renderMailMessage($message);
        $extras = ['template_slug' => method_exists($notification, 'templateSlug')
            ? $notification->templateSlug()
            : $this->slugFromClassName($notification)
        ] + $this->extrasFromNotification($notifiable, $notification);

        $this->dispatch($client, $email, $name, $subject, $html, $extras);
    }

    /**
     * Try to render via a MarketplaceEmailTemplate stored for $client.
     * Returns ['subject' => ..., 'body_html' => ..., 'slug' => ...] on
     * success; null when the notification doesn't expose a template hook,
     * the template doesn't exist for the marketplace, or rendering throws.
     */
    protected function renderFromMarketplaceTemplate(object $notifiable, Notification $notification, MarketplaceClient $client): ?array
    {
        if (!method_exists($notification, 'marketplaceTemplateSlug')) return null;

        $slug = (string) $notification->marketplaceTemplateSlug($notifiable);
        if ($slug === '') return null;

        $template = \App\Models\MarketplaceEmailTemplate::query()
            ->where('marketplace_client_id', $client->id)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
        if (!$template) return null;

        $data = method_exists($notification, 'marketplaceTemplateData')
            ? (array) $notification->marketplaceTemplateData($notifiable)
            : [];

        // Always inject some marketplace-level defaults so a template can
        // reference {{marketplace_name}} / {{customer_name}} without the
        // notification needing to pass them every time.
        $data = array_merge([
            'marketplace_name' => $client->public_name ?? $client->name ?? 'Marketplace',
            'marketplace_domain' => preg_replace('#^https?://#', '', rtrim((string) ($client->domain ?? ''), '/')),
            'customer_name' => $this->resolveRecipientName($notifiable),
            'customer_email' => $this->resolveRecipientEmail($notifiable) ?? '',
        ], $data);

        try {
            $r = $template->render($data);
        } catch (\Throwable $e) {
            Log::channel('marketplace')->warning('MarketplaceMailChannel: template render failed', [
                'slug' => $slug,
                'marketplace_client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        return [
            'slug' => $slug,
            'subject' => $r['subject'] ?? 'Notification',
            'body_html' => $r['body_html'] ?? '',
        ];
    }

    protected function extrasFromNotification(object $notifiable, Notification $notification): array
    {
        if (method_exists($notification, 'logExtra')) {
            return (array) $notification->logExtra($notifiable);
        }
        return [];
    }

    protected function dispatch(MarketplaceClient $client, string $email, string $name, string $subject, string $html, array $extras): void
    {
        try {
            BaseController::sendViaMarketplace($client, $email, $name, $subject, $html, $extras);
        } catch (\Throwable $e) {
            Log::channel('marketplace')->error('MarketplaceMailChannel: send failed', [
                'to' => $email,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function resolveMarketplaceClient(object $notifiable): ?MarketplaceClient
    {
        if (method_exists($notifiable, 'getMarketplaceClient')) {
            $c = $notifiable->getMarketplaceClient();
            if ($c instanceof MarketplaceClient) return $c;
        }
        if (isset($notifiable->marketplaceClient) && $notifiable->marketplaceClient instanceof MarketplaceClient) {
            return $notifiable->marketplaceClient;
        }
        if (!empty($notifiable->marketplace_client_id)) {
            return MarketplaceClient::find($notifiable->marketplace_client_id);
        }
        return null;
    }

    protected function buildMailMessage(object $notifiable, Notification $notification): ?MailMessage
    {
        if (method_exists($notification, 'toMarketplaceMail')) {
            return $notification->toMarketplaceMail($notifiable);
        }
        if (method_exists($notification, 'toMail')) {
            return $notification->toMail($notifiable);
        }
        return null;
    }

    protected function resolveRecipientEmail(object $notifiable): ?string
    {
        if (method_exists($notifiable, 'routeNotificationFor')) {
            $route = $notifiable->routeNotificationFor('mail');
            if (is_string($route) && $route !== '') return $route;
            if (is_array($route)) return array_key_first($route);
        }
        return $notifiable->email ?? null;
    }

    protected function resolveRecipientName(object $notifiable): string
    {
        $first = $notifiable->first_name ?? null;
        $last = $notifiable->last_name ?? null;
        $full = trim(($first ?? '') . ' ' . ($last ?? ''));
        return $full ?: ($notifiable->name ?? '');
    }

    /**
     * Render a Laravel MailMessage to HTML using the same notifications
     * blade view Laravel itself uses, so the body looks identical to what
     * default-channel sends would produce.
     */
    protected function renderMailMessage(MailMessage $message): string
    {
        $view = $message->markdown ?? 'notifications::email';
        try {
            if ($message->markdown) {
                return app(\Illuminate\Mail\Markdown::class)
                    ->render($message->markdown, $message->data());
            }
            return view($view, $message->data())->render();
        } catch (\Throwable $e) {
            // Fallback: plain assembly of intro + outro lines
            $lines = array_merge(
                $message->introLines ?? [],
                $message->outroLines ?? []
            );
            return '<div style="font-family:sans-serif;line-height:1.5;">'
                . implode('', array_map(fn ($l) => '<p>' . e($l) . '</p>', $lines))
                . '</div>';
        }
    }

    protected function slugFromClassName(Notification $notification): string
    {
        $base = class_basename($notification);
        $base = preg_replace('/Notification$/', '', $base);
        return \Illuminate\Support\Str::snake($base);
    }
}
