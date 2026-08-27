<?php

namespace App\Services\Chat;

use App\Models\MarketplaceClient;

/**
 * Central gate + settings resolver for the `live-chat` microservice.
 *
 * Every entry point (widget bootstrap, API endpoints, admin console) funnels
 * through isActiveFor() so the module is completely inert on any marketplace
 * that has not activated it. Per-marketplace overrides come from the pivot
 * `settings`; global fallbacks come from config/chat.php.
 */
class ChatService
{
    public const SLUG = 'live-chat';

    /**
     * Is the microservice activated for this marketplace?
     */
    public function isActiveFor(?MarketplaceClient $client): bool
    {
        return $client?->hasMicroservice(self::SLUG) ?? false;
    }

    public function isActiveForId(?int $marketplaceClientId): bool
    {
        if (!$marketplaceClientId) {
            return false;
        }
        return $this->isActiveFor(MarketplaceClient::find($marketplaceClientId));
    }

    /**
     * Resolve a setting: per-marketplace pivot value first, then config/chat.php,
     * then the supplied default.
     *
     * @param  string  $dottedKey  e.g. "operator.default_max_concurrent_chats"
     */
    public function setting(?MarketplaceClient $client, string $dottedKey, mixed $default = null): mixed
    {
        $pivotSettings = $client?->getMicroserviceConfig(self::SLUG) ?? [];
        $fromPivot = data_get($pivotSettings, $dottedKey);
        if ($fromPivot !== null) {
            return $fromPivot;
        }

        return config('chat.' . $dottedKey, $default);
    }

    /**
     * Public-facing config the frontend widget needs to bootstrap. Returns null
     * when the microservice is inactive (widget must not render).
     *
     * @return array<string, mixed>|null
     */
    public function widgetBootstrap(?MarketplaceClient $client): ?array
    {
        if (!$this->isActiveFor($client)) {
            return null;
        }

        return [
            'active' => true,
            'transport' => $this->setting($client, 'transport', 'polling'),
            'poll_interval_ms' => (int) $this->setting($client, 'poll_interval_ms', 4000),
            'allow_guests' => (bool) $this->setting($client, 'allow_guests', true),
            'attachments_enabled' => (bool) $this->setting($client, 'attachments.enabled', true),
            'honeypot_field' => $this->setting($client, 'anti_bot.honeypot_field', 'company_website'),
            // Copy shown by the widget; per-marketplace overridable.
            'greeting' => $this->setting($client, 'greeting', 'Bună! Cu ce te putem ajuta?'),
            'offline_message' => $this->setting(
                $client,
                'offline_message',
                'Suntem offline momentan. Lasă-ne un mesaj și revenim pe email.'
            ),
        ];
    }
}
