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
                'featured_event' => $this->renderFeaturedEvent($section, $marketplace),
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

        return $this->wrapInEmailTemplate(
            $contentHtml,
            $marketplace,
            $newsletter->preview_text ?? null,
            $this->deriveEyebrow($sections),
        );
    }

    /**
     * Pick a short uppercase label for the AmBilet card eyebrow ("Concert
     * recomandat", "Săptămâna viitoare", etc.) based on the first
     * substantive section. Header chrome only — does NOT affect what's
     * sent inside the body. Returns "Newsletter" as a safe fallback.
     */
    protected function deriveEyebrow(array $sections): string
    {
        foreach ($sections as $section) {
            $type = $section['type'] ?? null;
            $eyebrow = match ($type) {
                'featured_event' => 'Concert recomandat',
                'events_next_week' => 'Săptămâna viitoare',
                'events_next_month' => 'Luna viitoare',
                'recommended_events' => 'Selecția noastră',
                'hand_picked_events' => 'Recomandările noastre',
                default => null,
            };
            if ($eyebrow !== null) return $eyebrow;
        }
        return 'Newsletter';
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
            ->where('status', 'published')
            ->where('is_public', true)
            ->where('starts_at', '>=', now())
            ->orderByRaw('is_featured DESC, starts_at ASC')
            ->limit($limit)
            ->get();

        if ($events->isEmpty()) return '';

        return $this->renderEventCardsSection('Evenimente recomandate', $events, $marketplace);
    }

    /**
     * Single "featured event" hero block — iabilet-style layout with a
     * 580px-wide image, big title link, venue / city subline, doors / start
     * times, the cheapest live price and a prominent CTA. All driven by the
     * event_id picked in the section schema; admin only needs to supply the
     * event reference (plus an optional intro_text + cta_label override).
     */
    protected function renderFeaturedEvent(array $section, MarketplaceClient $marketplace): string
    {
        $eventId = (int) ($section['event_id'] ?? 0);
        if ($eventId <= 0) return '';

        $event = MarketplaceEvent::where('id', $eventId)
            ->where('status', 'published')
            ->where('is_public', true)
            ->first();
        if (!$event) return '';

        $variant = $section['design_variant'] ?? 'v1';
        return $variant === 'v2'
            ? $this->renderFeaturedEventV2($section, $event, $marketplace)
            : $this->renderFeaturedEventV1($section, $event, $marketplace);
    }

    /**
     * v1 — iabilet-style compact hero (white card on light wrapper).
     */
    protected function renderFeaturedEventV1(array $section, MarketplaceEvent $event, MarketplaceClient $marketplace): string
    {
        $intro = trim((string) ($section['intro_text'] ?? ''));
        if ($intro === '') {
            $intro = ($marketplace->name ?? 'Newsletter') . ' îți recomandă cele mai tari concerte și evenimente';
        }

        $ctaLabel = trim((string) ($section['cta_label'] ?? ''));
        if ($ctaLabel === '') {
            $ctaLabel = 'Vezi programul și Cumpără bilet';
        }

        $name = e($event->name ?? 'Eveniment');
        $venueName = e($event->venue_name ?? '');
        $venueCity = e($event->venue_city ?? '');
        $location = trim($venueName . ($venueName && $venueCity ? ', ' : '') . $venueCity);
        $date = $event->starts_at ? $event->starts_at->format('d M Y') : '';
        $eventUrl = $this->getEventUrl($event, $marketplace);
        $imageUrl = $event->image ? $this->resolveImageUrl($event->image, $marketplace) : '';
        $price = $this->getEventPrice($event, $marketplace);
        $priceLabel = $price ? 'De la ' . e($price) : 'Detalii';

        $imageBlock = '';
        if ($imageUrl) {
            $imageBlock = '<tr><td><a href="' . e($eventUrl) . '" target="_blank" rel="noreferrer" style="text-decoration:none;"><img src="' . e($imageUrl) . '" alt="' . $name . '" style="display:block;border:0;width:580px;max-width:100%;height:auto;border-radius:8px;" width="580" /></a></td></tr>';
        }

        $locationLine = $location !== ''
            ? '<div style="color:#4C4C59;font-size:14px;margin-top:4px;">' . $location . '</div>'
            : '';
        $dateLine = $date !== ''
            ? '<div style="color:#4C4C59;font-size:14px;margin-top:4px;">' . e($date) . '</div>'
            : '';

        return <<<HTML
        <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;margin:24px 0;">
            <tr>
                <td align="center" style="padding:0 0 12px 0;font-size:14px;color:#4C4C59;">
                    {$intro}
                </td>
            </tr>
            <tr>
                <td align="center" style="padding:0 10px;">
                    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:580px;border-collapse:collapse;">
                        {$imageBlock}
                        <tr>
                            <td align="center" style="padding:16px 0 4px 0;">
                                <a href="{$eventUrl}" target="_blank" rel="noreferrer" style="color:#A51C30;text-decoration:none;font-size:22px;font-weight:700;font-family:Arial,Helvetica,sans-serif;line-height:1.3;">{$name}</a>
                                {$locationLine}
                                {$dateLine}
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="padding:16px 0 4px 0;">
                                <a href="{$eventUrl}" target="_blank" rel="noreferrer" style="display:inline-block;padding:14px 28px;background-color:#A51C30;color:#ffffff;text-decoration:none;border-radius:8px;font-size:15px;font-weight:bold;font-family:Arial,Helvetica,sans-serif;">{$ctaLabel} — {$priceLabel}</a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        HTML;
    }

    /**
     * v2 — ambilet single-event hero block. Emits INNER content only: big
     * title + artist subtitle + optional intro + details grid + primary CTA
     * + image. The outer dark wrap and rounded white card are supplied by
     * wrapInEmailTemplate(); double-wrapping (a card inside a card) was the
     * bug that hid the hero in preview.
     */
    protected function renderFeaturedEventV2(array $section, MarketplaceEvent $event, MarketplaceClient $marketplace): string
    {
        $name = e($event->name ?? 'Eveniment');
        $venueName = e($event->venue_name ?: '—');
        $venueCity = e($event->venue_city ?: '—');
        $date = $event->starts_at ? $event->starts_at->format('d M Y') : '—';
        $time = $event->starts_at ? $event->starts_at->format('H:i') : '—';
        $eventUrl = e($this->getEventUrl($event, $marketplace));
        $imageUrl = $event->image ? e($this->resolveImageUrl($event->image, $marketplace)) : '';

        $artist = trim((string) ($section['artist_name'] ?? ''));
        $artistBlock = $artist !== ''
            ? '<div style="font-family:Arial, Helvetica, sans-serif; font-size:20px; line-height:28px; font-weight:700; color:#334155; padding-top:8px;">' . e($artist) . '</div>'
            : '';

        $intro = trim((string) ($section['intro_paragraph'] ?? ''));
        $introBlock = $intro !== ''
            ? '<p style="margin:20px 0 0 0; padding:0; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:25px; color:#4B5563;">' . e($intro) . '</p>'
            : '';

        $ctaLabel = e(trim((string) ($section['cta_label'] ?? '')) ?: 'Cumpără bilete');

        $imageBlock = '';
        if ($imageUrl) {
            $imageBlock = <<<HTML
            <tr>
                <td style="padding:0 6px 8px 6px;">
                    <a href="{$eventUrl}" target="_blank" style="text-decoration:none;">
                        <img src="{$imageUrl}" width="544" alt="{$name}" style="display:block; width:100%; max-width:544px; height:auto; border:0; border-radius:18px; outline:none; text-decoration:none;" />
                    </a>
                </td>
            </tr>
            HTML;
        }

        return <<<HTML
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">

            <tr>
                <td align="center" style="padding:14px 6px 8px 6px;">
                    <h1 style="margin:0; padding:0; font-family:Arial, Helvetica, sans-serif; font-size:32px; line-height:38px; font-weight:800; color:#1e293b; letter-spacing:-0.6px;">
                        {$name}
                    </h1>
                    {$artistBlock}
                    {$introBlock}
                </td>
            </tr>

            <tr>
                <td style="padding:18px 6px 8px 6px;">
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
                        <tr>
                            <td bgcolor="#FFF7ED" style="padding:18px 18px 16px 18px; background-color:#FFF7ED; border:1px solid #FED7AA; border-radius:16px;">
                                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
                                    <tr>
                                        <td width="50%" valign="top" style="font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:18px; color:#9A3412; font-weight:700; padding:0 8px 10px 0;">
                                            Data
                                            <div style="font-size:16px; line-height:23px; color:#1e293b; font-weight:800; padding-top:3px;">{$date}</div>
                                        </td>
                                        <td width="50%" valign="top" style="font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:18px; color:#9A3412; font-weight:700; padding:0 0 10px 8px;">
                                            Ora
                                            <div style="font-size:16px; line-height:23px; color:#1e293b; font-weight:800; padding-top:3px;">{$time}</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td width="50%" valign="top" style="font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:18px; color:#9A3412; font-weight:700; padding:6px 8px 0 0;">
                                            Locație
                                            <div style="font-size:16px; line-height:23px; color:#1e293b; font-weight:800; padding-top:3px;">{$venueName}</div>
                                        </td>
                                        <td width="50%" valign="top" style="font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:18px; color:#9A3412; font-weight:700; padding:6px 0 0 8px;">
                                            Oraș
                                            <div style="font-size:16px; line-height:23px; color:#1e293b; font-weight:800; padding-top:3px;">{$venueCity}</div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr>
                <td align="center" style="padding:18px 6px 22px 6px;">
                    <table role="presentation" border="0" cellspacing="0" cellpadding="0" style="border-collapse:separate;">
                        <tr>
                            <td align="center" bgcolor="#a51c30" style="border-radius:999px; background-color:#a51c30;">
                                <a href="{$eventUrl}" target="_blank" style="display:inline-block; padding:16px 34px; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:20px; font-weight:800; color:#FFFFFF; text-decoration:none; border-radius:999px;">
                                    {$ctaLabel}
                                </a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            {$imageBlock}

        </table>
        HTML;
    }

    protected function renderHandPickedEvents(array $section, MarketplaceClient $marketplace): string
    {
        $eventIds = $section['event_ids'] ?? [];
        if (empty($eventIds)) return '';

        $events = MarketplaceEvent::whereIn('id', $eventIds)
            ->where('status', 'published')
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
            ->where('status', 'published')
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
            ->where('status', 'published')
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

    /**
     * AmBilet wrap — dark #0F172A page, small browser bar above the card,
     * white rounded 600px card with AmBilet wordmark header + "Concert
     * recomandat" eyebrow, content slot, and the new F8FAFC footer band
     * with links + dezabonare line.
     *
     * The eyebrow text comes from `marketplace_newsletters.preview_text`
     * when set (dual-purposed as both the hidden preheader AND the visible
     * uppercase eyebrow), else falls back to a default that hints at the
     * type of digest. Preheader is the first <div> hidden by max-height,
     * shown by Gmail/Outlook in the inbox snippet.
     */
    protected function wrapInEmailTemplate(string $content, MarketplaceClient $marketplace, ?string $previewText = null, ?string $eyebrowText = null): string
    {
        $name = e($marketplace->name ?? 'Newsletter');
        $host = $this->hostOf($marketplace);
        $year = (int) date('Y');

        $eyebrow = e($eyebrowText ?: 'Newsletter');
        $preheader = e($previewText ?: ($marketplace->name ?? 'Newsletter') . ' — cumpără bilete online');
        $privacyUrl = "https://{$host}/privacy";
        $contactUrl = "https://{$host}/contact";

        return <<<HTML
        <!DOCTYPE html>
        <html lang="ro">
        <head>
            <meta charset="UTF-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1.0" />
            <title>{$name}</title>
        </head>
        <body style="margin:0; padding:0; background-color:#0F172A; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">

            <div style="display:none; font-size:1px; color:#0F172A; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
                {$preheader}
            </div>

            <table role="presentation" width="600" border="0" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px; margin:0 auto;">
                <tr>
                    <td align="center" style="padding:14px 30px 0px 30px; font-family:Arial,Helvetica,sans-serif; font-size:12px; line-height:18px; color:#a8a8a8;">
                        {$name} îți recomandă concertele care merită trăite live.
                    </td>
                </tr>
            </table>

            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" bgcolor="#0F172A" style="border-collapse:collapse; background-color:#0F172A; margin:0; padding:0;">
                <tr>
                    <td align="center" style="padding:12px 12px 12px 12px;">

                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="width:600px; max-width:600px; border-collapse:collapse; background-color:#FFFFFF; border-radius:20px; overflow:hidden;">

                            <tr>
                                <td style="padding:20px 28px 12px 28px;">
                                    <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td align="left" style="font-family:Arial,Helvetica,sans-serif;">
                                                <a href="https://{$host}/" target="_blank" style="font-family:Arial, Helvetica, sans-serif; font-size:20px; line-height:18px; font-weight:700; letter-spacing:-0.8px; color:#1e293b; text-decoration:none;">
                                                    Am<span style="color:#a51c30;">Bilet</span><span style="color:#a51c30;">.ro</span>
                                                </a>
                                            </td>
                                            <td align="right" style="font-family:Arial,Helvetica,sans-serif; font-size:12px; line-height:18px; color:#334155; text-transform:uppercase; letter-spacing:1.4px;">
                                                {$eyebrow}
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:0 28px 8px 28px;">
                                    {$content}
                                </td>
                            </tr>

                            <tr>
                                <td align="center" bgcolor="#F8FAFC" style="padding:28px 34px 12px 34px; background-color:#F8FAFC; border-top:1px solid #E5E7EB;">
                                    <div style="font-family:Arial, Helvetica, sans-serif; font-size:18px; line-height:24px; font-weight:800; color:#1e293b;">
                                        Am<span style="color:#a51c30;">Bilet</span><span style="color:#a51c30;">.ro</span>
                                    </div>
                                    <p style="margin:10px 0 0 0; padding:0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:20px; color:#6B7280;">
                                        Platforma ta de încredere pentru bilete la evenimente. Descoperă concerte, festivaluri, spectacole și experiențe în toată România.
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <td align="center" bgcolor="#F8FAFC" style="padding:10px 34px 10px 34px; background-color:#F8FAFC; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:22px; color:#b6b6b6;">
                                    <a href="https://{$host}/" target="_blank" style="color:#a51c30; text-decoration:none;">{$name}</a>
                                    &nbsp;&nbsp;|&nbsp;&nbsp;
                                    <a href="{$privacyUrl}" target="_blank" style="color:#a51c30; text-decoration:none;">Confidențialitate</a>
                                    &nbsp;&nbsp;|&nbsp;&nbsp;
                                    <a href="{$contactUrl}" target="_blank" style="color:#a51c30; text-decoration:none;">Contact</a>
                                </td>
                            </tr>

                            <tr>
                                <td align="center" bgcolor="#F8FAFC" style="padding:8px 34px 26px 34px; background-color:#F8FAFC;">
                                    <p style="margin:0; padding:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:19px; color:#6B7280;">
                                        Primești acest email pentru că te-ai abonat la comunicările {$name}.<br/>
                                        Nu mai vrei să primești newslettere? Te poți dezabona
                                        <a href="{{unsubscribe_url}}" target="_blank" style="color:#a51c30; text-decoration:none;">aici</a>.
                                        <br/><br/>
                                        © {$year} {$name}. Toate drepturile rezervate.
                                    </p>
                                </td>
                            </tr>

                        </table>

                    </td>
                </tr>
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

        $host = $this->hostOf($marketplace);
        return "https://{$host}/storage/{$image}";
    }

    protected function getEventUrl(MarketplaceEvent $event, MarketplaceClient $marketplace): string
    {
        $host = $this->hostOf($marketplace);
        $slug = $event->slug ?? $event->id;
        return "https://{$host}/bilete/{$slug}";
    }

    /**
     * MarketplaceClient->domain often stores "https://ambilet.ro" — strip
     * scheme + trailing slash so URL construction doesn't double the scheme
     * ("https://https://ambilet.ro/...").
     */
    protected function hostOf(MarketplaceClient $marketplace): string
    {
        $raw = trim((string) ($marketplace->domain ?? 'example.com'));
        $raw = preg_replace('#^https?://#i', '', $raw);
        $raw = rtrim((string) $raw, '/');
        return $raw !== '' ? $raw : 'example.com';
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
