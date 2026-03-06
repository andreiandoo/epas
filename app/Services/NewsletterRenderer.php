<?php

namespace App\Services;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceNewsletter;
use Illuminate\Support\Collection;

class NewsletterRenderer
{
    /**
     * Render newsletter sections to full HTML email.
     */
    public function render(MarketplaceNewsletter $newsletter, ?MarketplaceCustomer $customer = null): string
    {
        $sections = $newsletter->body_sections ?? [];
        $marketplace = $newsletter->marketplaceClient;
        $marketplaceId = $marketplace->id;

        if (empty($sections)) {
            // Backward compat: use body_html directly
            return $newsletter->body_html ?? '';
        }

        $contentHtml = '';

        foreach ($sections as $section) {
            $type = $section['type'] ?? 'text';
            $contentHtml .= match ($type) {
                'text' => $this->renderTextSection($section),
                'html' => $this->renderHtmlSection($section),
                'recommended_events' => $this->renderRecommendedEvents($section, $marketplaceId, $marketplace),
                'hand_picked_events' => $this->renderHandPickedEvents($section, $marketplace),
                'events_next_week' => $this->renderEventsNextWeek($section, $marketplaceId, $marketplace),
                'events_next_month' => $this->renderEventsNextMonth($section, $marketplaceId, $marketplace),
                'button' => $this->renderButton($section),
                'spacer' => $this->renderSpacer($section),
                'image' => $this->renderImage($section),
                default => '',
            };
        }

        // Replace event variables {{event:ID:field}}
        $contentHtml = $this->replaceEventVariables($contentHtml, $marketplaceId, $marketplace);

        return $this->wrapInEmailTemplate($contentHtml, $marketplace);
    }

    /**
     * Render for preview (no customer-specific variables replaced).
     */
    public function renderPreview(MarketplaceNewsletter $newsletter): string
    {
        return $this->render($newsletter);
    }

    // =========================================
    // Section Renderers
    // =========================================

    protected function renderTextSection(array $section): string
    {
        $content = $section['content'] ?? '';
        if (empty($content)) return '';

        return '<div style="margin-bottom: 20px;">' . $content . '</div>';
    }

    protected function renderHtmlSection(array $section): string
    {
        return $section['html_content'] ?? '';
    }

    protected function renderRecommendedEvents(array $section, int $marketplaceId, MarketplaceClient $marketplace): string
    {
        $limit = (int) ($section['limit'] ?? 4);

        $events = MarketplaceEvent::where('marketplace_client_id', $marketplaceId)
            ->where('status', 'approved')
            ->where('is_public', true)
            ->where('starts_at', '>=', now())
            ->orderByRaw('is_featured DESC, starts_at ASC')
            ->limit($limit)
            ->get();

        if ($events->isEmpty()) return '';

        return $this->renderEventCardsSection('Evenimente recomandate', $events, $marketplace);
    }

    protected function renderHandPickedEvents(array $section, MarketplaceClient $marketplace): string
    {
        $eventIds = $section['event_ids'] ?? [];
        if (empty($eventIds)) return '';

        $events = MarketplaceEvent::whereIn('id', $eventIds)
            ->where('status', 'approved')
            ->where('is_public', true)
            ->get()
            ->sortBy(function ($event) use ($eventIds) {
                return array_search($event->id, $eventIds);
            });

        if ($events->isEmpty()) return '';

        return $this->renderEventCardsSection('Evenimente selectate', $events, $marketplace);
    }

    protected function renderEventsNextWeek(array $section, int $marketplaceId, MarketplaceClient $marketplace): string
    {
        $limit = (int) ($section['limit'] ?? 6);

        $events = MarketplaceEvent::where('marketplace_client_id', $marketplaceId)
            ->where('status', 'approved')
            ->where('is_public', true)
            ->whereBetween('starts_at', [now(), now()->addDays(7)])
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();

        if ($events->isEmpty()) return '';

        return $this->renderEventCardsSection('Evenimente săptămâna viitoare', $events, $marketplace);
    }

    protected function renderEventsNextMonth(array $section, int $marketplaceId, MarketplaceClient $marketplace): string
    {
        $limit = (int) ($section['limit'] ?? 8);

        $events = MarketplaceEvent::where('marketplace_client_id', $marketplaceId)
            ->where('status', 'approved')
            ->where('is_public', true)
            ->whereBetween('starts_at', [now(), now()->addDays(30)])
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();

        if ($events->isEmpty()) return '';

        return $this->renderEventCardsSection('Evenimente luna viitoare', $events, $marketplace);
    }

    protected function renderButton(array $section): string
    {
        $text = e($section['button_text'] ?? 'Click aici');
        $url = e($section['button_url'] ?? '#');
        $color = $section['button_color'] ?? '#A51C30';

        return <<<HTML
        <div style="margin: 24px 0; text-align: center;">
            <a href="{$url}" style="display: inline-block; padding: 14px 32px; background-color: {$color}; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 16px; font-weight: 600; font-family: Arial, sans-serif;">{$text}</a>
        </div>
        HTML;
    }

    protected function renderSpacer(array $section): string
    {
        $height = (int) ($section['height'] ?? 20);
        return "<div style=\"height: {$height}px;\"></div>";
    }

    protected function renderImage(array $section): string
    {
        $url = e($section['image_url'] ?? '');
        $link = $section['image_link'] ?? '';
        $alt = e($section['alt_text'] ?? '');

        if (empty($url)) return '';

        $img = "<img src=\"{$url}\" alt=\"{$alt}\" width=\"100%\" style=\"display: block; border-radius: 8px; max-width: 100%;\" />";

        if (!empty($link)) {
            $linkE = e($link);
            $img = "<a href=\"{$linkE}\" style=\"text-decoration: none;\">{$img}</a>";
        }

        return '<div style="margin-bottom: 20px;">' . $img . '</div>';
    }

    // =========================================
    // Event Card Rendering
    // =========================================

    protected function renderEventCardsSection(string $title, Collection $events, MarketplaceClient $marketplace): string
    {
        $titleHtml = '<h2 style="font-size: 22px; font-weight: 700; color: #1f2937; margin: 24px 0 16px 0; font-family: Arial, sans-serif;">' . e($title) . '</h2>';

        $cardsHtml = '';
        $chunks = $events->chunk(2);

        foreach ($chunks as $pair) {
            $cards = $pair->values();
            $card1 = $this->renderSingleEventCard($cards[0], $marketplace);
            $card2 = isset($cards[1]) ? $this->renderSingleEventCard($cards[1], $marketplace) : '<td width="48%" valign="top" style="padding: 8px;"></td>';

            $cardsHtml .= <<<HTML
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 4px;">
                <tr>
                    {$card1}
                    <td width="4%"></td>
                    {$card2}
                </tr>
            </table>
            HTML;
        }

        return $titleHtml . $cardsHtml;
    }

    protected function renderSingleEventCard(MarketplaceEvent $event, MarketplaceClient $marketplace): string
    {
        $name = e($event->name ?? 'Eveniment');
        $date = $event->starts_at ? $event->starts_at->format('d M Y, H:i') : '';
        $venue = e($event->venue_name ?? '');
        $image = $event->image ? $this->resolveImageUrl($event->image, $marketplace) : '';
        $eventUrl = $this->getEventUrl($event, $marketplace);

        // Get cheapest ticket price
        $cheapest = $event->ticketTypes()
            ->where('is_active', true)
            ->where('is_entry', false)
            ->orderBy('price')
            ->first();
        $price = $cheapest ? number_format($cheapest->price, 0) . ' ' . ($marketplace->currency ?? 'RON') : '';

        $imageHtml = '';
        if ($image) {
            $imageHtml = '<tr><td><img src="' . e($image) . '" width="100%" style="display:block;border-radius:8px 8px 0 0;height:140px;object-fit:cover;" alt="' . $name . '" /></td></tr>';
        }

        $priceBtn = '';
        if ($price) {
            $priceBtn = '<a href="' . e($eventUrl) . '" style="display:inline-block;padding:8px 16px;background:#A51C30;color:#fff;text-decoration:none;border-radius:4px;font-size:13px;font-family:Arial,sans-serif;">De la ' . e($price) . '</a>';
        } else {
            $priceBtn = '<a href="' . e($eventUrl) . '" style="display:inline-block;padding:8px 16px;background:#A51C30;color:#fff;text-decoration:none;border-radius:4px;font-size:13px;font-family:Arial,sans-serif;">Detalii</a>';
        }

        return <<<HTML
        <td width="48%" valign="top" style="padding: 8px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                {$imageHtml}
                <tr><td style="padding:12px;">
                    <p style="font-size:15px;font-weight:bold;margin:0 0 4px;color:#1f2937;font-family:Arial,sans-serif;">{$name}</p>
                    <p style="font-size:13px;color:#6b7280;margin:0 0 4px;font-family:Arial,sans-serif;">{$date}</p>
                    <p style="font-size:13px;color:#6b7280;margin:0 0 10px;font-family:Arial,sans-serif;">{$venue}</p>
                    {$priceBtn}
                </td></tr>
            </table>
        </td>
        HTML;
    }

    // =========================================
    // Event Variables
    // =========================================

    protected function replaceEventVariables(string $html, int $marketplaceId, MarketplaceClient $marketplace): string
    {
        // Find all {{event:ID:field}} patterns
        if (!preg_match_all('/\{\{event:(\d+):(\w+)\}\}/', $html, $matches, PREG_SET_ORDER)) {
            return $html;
        }

        // Collect unique event IDs
        $eventIds = array_unique(array_map(fn ($m) => (int) $m[1], $matches));

        // Batch load events
        $events = MarketplaceEvent::whereIn('id', $eventIds)->get()->keyBy('id');

        // Replace each occurrence
        foreach ($matches as $match) {
            $fullMatch = $match[0];
            $eventId = (int) $match[1];
            $field = $match[2];

            $event = $events->get($eventId);
            if (!$event) {
                $html = str_replace($fullMatch, '', $html);
                continue;
            }

            $value = match ($field) {
                'name' => $event->name ?? '',
                'date' => $event->starts_at ? $event->starts_at->format('d M Y, H:i') : '',
                'venue' => $event->venue_name ?? '',
                'image' => $event->image ? $this->resolveImageUrl($event->image, $marketplace) : '',
                'url' => $this->getEventUrl($event, $marketplace),
                'price' => $this->getEventPrice($event, $marketplace),
                default => '',
            };

            $html = str_replace($fullMatch, $value, $html);
        }

        return $html;
    }

    // =========================================
    // Email Wrapper
    // =========================================

    protected function wrapInEmailTemplate(string $content, MarketplaceClient $marketplace): string
    {
        $name = e($marketplace->name ?? 'Newsletter');
        $domain = $marketplace->domain ?? 'example.com';
        $logoUrl = "https://{$domain}/assets/images/ambilet_logo.webp";

        return <<<HTML
        <!DOCTYPE html>
        <html lang="ro">
        <head>
            <meta charset="UTF-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1.0" />
            <title>{$name}</title>
        </head>
        <body style="margin:0;padding:0;background-color:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6;">
                <tr><td align="center" style="padding:24px 16px;">
                    <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">

                        <!-- Header -->
                        <tr><td style="background:linear-gradient(135deg, #A51C30 0%, #8B1728 100%);padding:24px;text-align:center;border-radius:12px 12px 0 0;">
                            <img src="{$logoUrl}" alt="{$name}" height="40" style="height:40px;max-height:40px;" />
                        </td></tr>

                        <!-- Content -->
                        <tr><td style="background-color:#ffffff;padding:32px 28px;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;">
                            {$content}
                        </td></tr>

                        <!-- Footer -->
                        <tr><td style="background-color:#1f2937;padding:24px 28px;border-radius:0 0 12px 12px;text-align:center;">
                            <p style="color:#9ca3af;font-size:13px;margin:0 0 8px;font-family:Arial,sans-serif;">{$name}</p>
                            <p style="color:#6b7280;font-size:12px;margin:0 0 12px;font-family:Arial,sans-serif;">
                                <a href="https://{$domain}" style="color:#9ca3af;text-decoration:underline;">{$domain}</a>
                            </p>
                            <p style="color:#6b7280;font-size:11px;margin:0;font-family:Arial,sans-serif;">
                                <a href="{{unsubscribe_url}}" style="color:#9ca3af;text-decoration:underline;">Dezabonare</a>
                            </p>
                        </td></tr>

                    </table>
                </td></tr>
            </table>
        </body>
        </html>
        HTML;
    }

    // =========================================
    // Helpers
    // =========================================

    protected function resolveImageUrl(string $image, MarketplaceClient $marketplace): string
    {
        if (str_starts_with($image, 'http')) {
            return $image;
        }

        $domain = $marketplace->domain ?? 'example.com';
        return "https://{$domain}/storage/{$image}";
    }

    protected function getEventUrl(MarketplaceEvent $event, MarketplaceClient $marketplace): string
    {
        $domain = $marketplace->domain ?? 'example.com';
        $slug = $event->slug ?? $event->id;
        return "https://{$domain}/e/{$slug}";
    }

    protected function getEventPrice(MarketplaceEvent $event, MarketplaceClient $marketplace): string
    {
        $cheapest = $event->ticketTypes()
            ->where('is_active', true)
            ->where('is_entry', false)
            ->orderBy('price')
            ->first();

        if (!$cheapest) return '';
        return number_format($cheapest->price, 0) . ' ' . ($marketplace->currency ?? 'RON');
    }
}
