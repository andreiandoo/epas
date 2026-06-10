<?php

namespace App\Services;

use App\Http\Controllers\NewsletterTrackingController;
use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceNewsletter;
use App\Models\MarketplaceNewsletterRecipient;
use Illuminate\Support\Collection;

class NewsletterRenderer
{
    /**
     * Set per-call by render() so instrumentLinks() + wrap pixel can
     * stamp per-recipient tokens without threading the recipient
     * through every helper signature. Null in preview / test send.
     */
    protected ?MarketplaceNewsletterRecipient $currentRecipient = null;

    /**
     * Render newsletter sections to full HTML email.
     */
    public function render(MarketplaceNewsletter $newsletter, ?MarketplaceCustomer $customer = null, ?MarketplaceNewsletterRecipient $recipient = null): string
    {
        $this->currentRecipient = $recipient;
        $sections = $newsletter->body_sections ?? [];
        $marketplace = $newsletter->marketplaceClient;
        $marketplaceId = $marketplace->id;

        if (empty($sections)) {
            // Backward compat: use body_html directly
            return $newsletter->body_html ?? '';
        }

        $contentHtml = '';

        foreach ($sections as $idx => $section) {
            $type = $section['type'] ?? 'text';
            try {
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
                    'image' => $this->renderImage($section, $marketplace),
                    default => '',
                };
            } catch (\Throwable $e) {
                // One broken section shouldn't 500 the whole preview /
                // outgoing email. Log + replace with a visible placeholder
                // so the admin can spot the problem.
                \Log::error('NewsletterRenderer: section #' . $idx . ' (' . $type . ') failed', [
                    'newsletter_id' => $newsletter->id ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $contentHtml .= '<div style="margin:16px 0;padding:12px 14px;border:1px solid #fca5a5;background:#fef2f2;color:#991b1b;font-family:Arial,Helvetica,sans-serif;font-size:13px;border-radius:8px;">'
                    . 'Secțiunea #' . ($idx + 1) . ' (' . e($type) . ') nu a putut fi randată: ' . e($e->getMessage())
                    . '</div>';
            }
        }

        // Replace event variables {{event:ID:field}}
        $contentHtml = $this->replaceEventVariables($contentHtml, $marketplaceId, $marketplace);

        // Rewrite outgoing <a href> through the tracking redirect AFTER
        // event variables are resolved (so the dest URL is final). The
        // wrap then appends the open-tracking pixel at the end of the
        // email body.
        $contentHtml = $this->instrumentLinks($contentHtml, $newsletter, $customer);

        return $this->wrapInEmailTemplate(
            $contentHtml,
            $marketplace,
            $newsletter->preview_text ?? null,
            $this->deriveEyebrow($sections),
            $newsletter,
            $customer,
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

        // Match the typography used by the predefined sections
        // (featured_event hero, event cards, footer, etc.) so the
        // RichEditor output doesn't visually clash with system-rendered
        // blocks. Body copy + line-height pegged to AmBilet's defaults.
        return '<div style="margin-bottom:20px;font-family:Arial, Helvetica, sans-serif;font-size:15px;line-height:22px;color:#1f2937;">' . $content . '</div>';
    }

    protected function renderHtmlSection(array $section): string
    {
        return $section['html_content'] ?? '';
    }

    protected function renderRecommendedEvents(array $section, int $marketplaceId, MarketplaceClient $marketplace): string
    {
        return $this->renderManualEventList(
            $section,
            $marketplace,
            $section['section_title'] ?? 'Evenimente recomandate'
        );
    }

    /**
     * Featured event hero block — AmBilet design (was "v2" before the
     * v1 iabilet variant was dropped). Emits INNER content only: big
     * title, optional artist subtitle, optional intro paragraph, details
     * grid (data/ora/locație/oraș), primary pill CTA, and a poster
     * image. The outer dark wrap + rounded white card are supplied by
     * wrapInEmailTemplate() — never wrap here, or the hero ends up
     * nested in a card-within-a-card and disappears in preview.
     */
    protected function renderFeaturedEvent(array $section, MarketplaceClient $marketplace): string
    {
        $eventId = (int) ($section['event_id'] ?? 0);
        if ($eventId <= 0) return '';

        $event = Event::with('venue')->find($eventId);
        if (!$event) return '';

        return $this->renderFeaturedEventHero($section, $event, $marketplace);
    }

    protected function renderFeaturedEventHero(array $section, Event $event, MarketplaceClient $marketplace): string
    {
        $name = e($this->eventTitle($event));
        $venueName = e($this->eventVenueName($event) ?: '—');
        $venueCity = e($this->eventVenueCity($event) ?: '—');
        // Mode-aware label so range (festival) + multi_day events render
        // "18 - 21 Iun 2026" instead of '-' (which is what we got when the
        // legacy single-day path read $event_date on a range row).
        $date = e($event->displayDateLabel() ?: '—');
        $time = $this->eventStartTime($event) ?: '—';
        $eventUrl = e($this->getEventUrl($event, $marketplace));
        // Always prefer poster format for newsletter heroes — admin
        // request. Falls back to featured / hero columns when poster_url
        // is blank (common for events imported from older WP feeds where
        // only image_url → hero_image_url was populated).
        $imageRaw = $this->eventImage($event, 'card');
        $imageUrl = $imageRaw !== '' ? e($this->resolveImageUrl($imageRaw, $marketplace)) : '';

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
        return $this->renderManualEventList(
            $section,
            $marketplace,
            $section['section_title'] ?? 'Evenimente alese'
        );
    }

    protected function renderEventsNextWeek(array $section, int $marketplaceId, MarketplaceClient $marketplace): string
    {
        return $this->renderManualEventList(
            $section,
            $marketplace,
            $section['section_title'] ?? 'Săptămâna viitoare'
        );
    }

    protected function renderEventsNextMonth(array $section, int $marketplaceId, MarketplaceClient $marketplace): string
    {
        return $this->renderManualEventList(
            $section,
            $marketplace,
            $section['section_title'] ?? 'Luna viitoare'
        );
    }

    /**
     * Shared body for the 4 manual-pick event list sections (recommended,
     * hand_picked, next_week, next_month). Renderer no longer auto-queries
     * by date/featured — admin pre-selects in the Filament picker (which
     * applies its own dropdown pre-filter for the next_week / next_month
     * variants). Order in $eventIds is preserved.
     */
    protected function renderManualEventList(array $section, MarketplaceClient $marketplace, string $title): string
    {
        $eventIds = $section['event_ids'] ?? [];
        if (empty($eventIds)) return '';

        // Sort the picked events chronologically (closest first) regardless of
        // the order the admin checked them in. `start_date` is a mode-aware
        // accessor on Event that returns `event_date` for single_day,
        // `range_start_date` for range, the first slot date for multi_day,
        // and `recurring_start_date` for recurring. Carbon-castable, so
        // sortBy/Carbon parse keeps mixed types working.
        $events = Event::with('venue')
            ->whereIn('id', $eventIds)
            ->get()
            ->sortBy(function ($event) {
                $d = $event->start_date;
                if (!$d) return PHP_INT_MAX;
                if ($d instanceof \DateTimeInterface) return $d->getTimestamp();
                $ts = strtotime((string) $d);
                return $ts === false ? PHP_INT_MAX : $ts;
            })
            ->values();

        if ($events->isEmpty()) return '';

        $layout = $section['display_layout'] ?? '2_cols';
        $heroEventId = isset($section['hero_event_id']) && $section['hero_event_id'] !== '' && $section['hero_event_id'] !== null
            ? (int) $section['hero_event_id']
            : null;
        return $this->renderEventCardsSection($title, $events, $marketplace, $layout, $heroEventId);
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

    protected function renderImage(array $section, MarketplaceClient $marketplace): string
    {
        // Accept either a `file` (relative path stored by FileUpload) or
        // a legacy `image_url` (free-text URL). FileUpload payloads come
        // through as an array with one element when single().
        $raw = $section['file'] ?? $section['image_url'] ?? '';
        if (is_array($raw)) {
            $raw = reset($raw) ?: '';
        }
        $raw = (string) $raw;
        if ($raw === '') return '';

        $url = e($this->resolveImageUrl($raw, $marketplace));
        $link = $section['image_link'] ?? '';
        $alt = e($section['alt_text'] ?? '');

        $img = "<img src=\"{$url}\" alt=\"{$alt}\" width=\"100%\" style=\"display:block;border-radius:8px;max-width:100%;height:auto;\" />";

        if (!empty($link)) {
            $linkE = e($link);
            $img = "<a href=\"{$linkE}\" target=\"_blank\" style=\"text-decoration:none;\">{$img}</a>";
        }

        return '<div style="margin-bottom:20px;">' . $img . '</div>';
    }

    // =========================================
    // Event Card Rendering
    // =========================================

    /**
     * @param  string    $layout       one of: 2_cols | 3_cols | 2_cols_first_hero | 3_cols_first_hero
     * @param  int|null  $heroEventId  optional override — when set on a *_first_hero
     *                                 layout, this event is promoted to hero instead
     *                                 of the chronologically-first one. It is also
     *                                 removed from the column grid to avoid showing
     *                                 the same event twice.
     */
    protected function renderEventCardsSection(string $title, Collection $events, MarketplaceClient $marketplace, string $layout = '2_cols', ?int $heroEventId = null): string
    {
        $titleHtml = '<h2 style="font-size: 22px; font-weight: 700; color: #1f2937; margin: 24px 0 16px 0; font-family: Arial, sans-serif;">' . e($title) . '</h2>';

        $events = $events->values();
        $cardsHtml = '';

        // Hero-first variants: a single event is a 100% landscape card, the
        // remainder flows into a 2- or 3-column grid below.
        $heroFirst = in_array($layout, ['2_cols_first_hero', '3_cols_first_hero'], true);
        $remainderCols = in_array($layout, ['3_cols', '3_cols_first_hero'], true) ? 3 : 2;

        if ($heroFirst && $events->isNotEmpty()) {
            // Resolve which event becomes the hero. Admin override wins when
            // the picked id is actually in the selected pool; otherwise we
            // fall back to the chronologically-first event (already sorted
            // by renderManualEventList).
            $hero = $heroEventId
                ? $events->firstWhere('id', $heroEventId)
                : null;
            if (!$hero) {
                $hero = $events->first();
            }

            $cardsHtml .= $this->renderHeroEventRow($hero, $marketplace);
            // Strip the hero out of the remainder — comparing by id keeps
            // this safe whether the hero was the first event or any other.
            $remaining = $events->reject(fn ($e) => (int) $e->id === (int) $hero->id)->values();
        } else {
            $remaining = $events;
        }

        if ($remaining->isNotEmpty()) {
            if ($remainderCols === 3) {
                $cardsHtml .= $this->renderEventGridThreeCols($remaining, $marketplace);
            } else {
                $cardsHtml .= $this->renderEventGridTwoCols($remaining, $marketplace);
            }
        }

        return $titleHtml . $cardsHtml;
    }

    protected function renderEventGridTwoCols(Collection $events, MarketplaceClient $marketplace): string
    {
        $html = '';
        foreach ($events->chunk(2) as $pair) {
            $row = $pair->values();
            $c1 = $this->renderSingleEventCard($row[0], $marketplace, '2_cols');
            $c2 = isset($row[1]) ? $this->renderSingleEventCard($row[1], $marketplace, '2_cols') : '<td width="48%" valign="top" style="padding: 8px;"></td>';
            $html .= <<<HTML
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 4px;">
                <tr>
                    {$c1}
                    <td width="4%"></td>
                    {$c2}
                </tr>
            </table>
            HTML;
        }
        return $html;
    }

    protected function renderEventGridThreeCols(Collection $events, MarketplaceClient $marketplace): string
    {
        $html = '';
        foreach ($events->chunk(3) as $trio) {
            $row = $trio->values();
            $c1 = $this->renderSingleEventCard($row[0], $marketplace, '3_cols');
            $c2 = isset($row[1]) ? $this->renderSingleEventCard($row[1], $marketplace, '3_cols') : '<td width="32%" valign="top" style="padding: 6px;"></td>';
            $c3 = isset($row[2]) ? $this->renderSingleEventCard($row[2], $marketplace, '3_cols') : '<td width="32%" valign="top" style="padding: 6px;"></td>';
            $html .= <<<HTML
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 4px;">
                <tr>
                    {$c1}
                    <td width="2%"></td>
                    {$c2}
                    <td width="2%"></td>
                    {$c3}
                </tr>
            </table>
            HTML;
        }
        return $html;
    }

    /**
     * Hero variant: full-width landscape (hero_image preferred), date strip
     * over the bottom of the image, title/venue/CTA below. Email-safe — no
     * position:absolute; the "overlay" is a real <td> with brand-red bg
     * sitting on the next row, painted to look attached to the image.
     */
    protected function renderHeroEventRow(Event $event, MarketplaceClient $marketplace): string
    {
        $name = e($this->eventTitle($event));
        $time = $this->eventStartTime($event);
        $label = $event->displayDateLabel();
        $isSingleDay = ($event->duration_mode ?? 'single_day') === 'single_day' || $event->duration_mode === null;
        $dateStr = $label ? $label . ($isSingleDay && $time ? ", {$time}" : '') : '';
        $date = e($dateStr);

        $venueName = $this->eventVenueName($event);
        $venueCity = $this->eventVenueCity($event);
        $venue = e(trim($venueName . ($venueName && $venueCity ? ', ' : '') . $venueCity));

        // Hero variant prefers the landscape hero_image. Falls back through
        // homepage/featured/poster — same order eventImage('hero') already
        // uses internally.
        $imageRaw = $this->eventImage($event, 'hero');
        $image = $imageRaw !== '' ? $this->resolveImageUrl($imageRaw, $marketplace) : '';
        $eventUrl = e($this->getEventUrl($event, $marketplace));

        $priceLabel = $this->getEventPrice($event, $marketplace);
        $ctaText = $priceLabel !== '' ? 'De la ' . e($priceLabel) : 'Detalii';

        $imageHtml = '';
        if ($image) {
            // Landscape — taller than the column cards so it acts as a real
            // hero. 280px keeps the email bounded; object-fit:cover crops
            // safely for arbitrary aspect ratios.
            $imageHtml = '<tr><td style="font-size:0;line-height:0;"><img src="' . e($image) . '" width="100%" style="display:block;width:100%;height:280px;object-fit:cover;border-radius:10px 10px 0 0;" alt="' . $name . '" /></td></tr>';
        }

        $dateStrip = $date !== ''
            ? '<tr><td style="background:#A51C30;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;padding:8px 14px;letter-spacing:0.3px;">' . $date . '</td></tr>'
            : '';

        return <<<HTML
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 6px;">
            <tr>
                <td valign="top" style="padding: 8px;">
                    <a href="{$eventUrl}" target="_blank" style="display:block;color:inherit;text-decoration:none;">
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;background:#ffffff;">
                            {$imageHtml}
                            {$dateStrip}
                            <tr><td style="padding:14px 16px;font-family:Arial,Helvetica,sans-serif;">
                                <p style="font-size:18px;font-weight:bold;margin:0 0 6px;color:#1f2937;font-family:Arial,Helvetica,sans-serif;">{$name}</p>
                                <p style="font-size:13px;color:#6b7280;margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;">{$venue}</p>
                                <span style="display:inline-block;padding:9px 18px;background:#A51C30;color:#fff;border-radius:4px;font-size:13px;font-family:Arial,Helvetica,sans-serif;font-weight:600;">{$ctaText}</span>
                            </td></tr>
                        </table>
                    </a>
                </td>
            </tr>
        </table>
        HTML;
    }

    /**
     * Grid card (2-col or 3-col). Date is rendered as a strip directly over
     * the bottom edge of the poster — variant A, email-safe (no
     * position:absolute). Outlook + Gmail + Apple Mail all paint this
     * correctly. The poster height differs by column count:
     *   2_cols → 355px (full vertical poster on a wide column)
     *   3_cols → 255px (still poster aspect, narrower column)
     */
    protected function renderSingleEventCard(Event $event, MarketplaceClient $marketplace, string $variant = '2_cols'): string
    {
        $name = e($this->eventTitle($event));
        $time = $this->eventStartTime($event);
        $label = $event->displayDateLabel();
        $isSingleDay = ($event->duration_mode ?? 'single_day') === 'single_day' || $event->duration_mode === null;
        $dateStr = $label ? $label . ($isSingleDay && $time ? ", {$time}" : '') : '';
        $venueName = $this->eventVenueName($event);
        $venueCity = $this->eventVenueCity($event);
        $venue = e(trim($venueName . ($venueName && $venueCity ? ', ' : '') . $venueCity));
        $date = e($dateStr);
        $imageRaw = $this->eventImage($event, 'card');
        $image = $imageRaw !== '' ? $this->resolveImageUrl($imageRaw, $marketplace) : '';
        $eventUrl = e($this->getEventUrl($event, $marketplace));

        $priceLabel = $this->getEventPrice($event, $marketplace);
        $ctaText = $priceLabel !== '' ? 'De la ' . e($priceLabel) : 'Detalii';

        // Variant geometry. The outer <td> width controls grid placement;
        // the inner img height + font sizes scale with the column count.
        $variantCfg = $variant === '3_cols'
            ? ['cell_width' => '32%', 'cell_padding' => '6px', 'poster_h' => 255, 'title_size' => 14, 'meta_size' => 12, 'cta_size' => 12]
            : ['cell_width' => '48%', 'cell_padding' => '8px', 'poster_h' => 355, 'title_size' => 15, 'meta_size' => 13, 'cta_size' => 13];

        $cellWidth = $variantCfg['cell_width'];
        $cellPadding = $variantCfg['cell_padding'];
        $posterH = $variantCfg['poster_h'];
        $titleSize = $variantCfg['title_size'];
        $metaSize = $variantCfg['meta_size'];
        $ctaSize = $variantCfg['cta_size'];

        $imageHtml = '';
        if ($image) {
            // font-size:0 / line-height:0 on the wrapping td kills the rogue
            // 3-4px gap some clients add under inline-block images.
            $imageHtml = '<tr><td style="font-size:0;line-height:0;"><img src="' . e($image) . '" width="100%" style="display:block;width:100%;height:' . $posterH . 'px;object-fit:cover;border-radius:8px 8px 0 0;" alt="' . $name . '" /></td></tr>';
        }

        // Date strip — sits directly under the poster, painted in brand red
        // with white text. Reads as if it were overlaid on the image.
        $dateStrip = $date !== ''
            ? '<tr><td style="background:#A51C30;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;padding:6px 12px;letter-spacing:0.3px;">' . $date . '</td></tr>'
            : '';

        return <<<HTML
        <td width="{$cellWidth}" valign="top" style="padding: {$cellPadding};">
            <a href="{$eventUrl}" target="_blank" style="display:block;color:inherit;text-decoration:none;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;background:#ffffff;">
                    {$imageHtml}
                    {$dateStrip}
                    <tr><td style="padding:12px;font-family:Arial, Helvetica, sans-serif;">
                        <p style="font-size:{$titleSize}px;font-weight:bold;margin:0 0 4px;color:#1f2937;font-family:Arial, Helvetica, sans-serif;">{$name}</p>
                        <p style="font-size:{$metaSize}px;color:#6b7280;margin:0 0 10px;font-family:Arial, Helvetica, sans-serif;">{$venue}</p>
                        <span style="display:inline-block;padding:8px 16px;background:#A51C30;color:#fff;border-radius:4px;font-size:{$ctaSize}px;font-family:Arial, Helvetica, sans-serif;font-weight:600;">{$ctaText}</span>
                    </td></tr>
                </table>
            </a>
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

        // Batch load events with venue eager-loaded so the translatable
        // venue name + city accessors don't fan out to per-event queries.
        $events = Event::with('venue')->whereIn('id', $eventIds)->get()->keyBy('id');

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

            $time = $this->eventStartTime($event);
            $imageRaw = $this->eventImage($event, 'card');
            $label = $event->displayDateLabel();
            $isSingleDay = ($event->duration_mode ?? 'single_day') === 'single_day' || $event->duration_mode === null;

            $value = match ($field) {
                'name' => $this->eventTitle($event),
                'date' => $label ? $label . ($isSingleDay && $time ? ", {$time}" : '') : '',
                'venue' => $this->eventVenueName($event),
                'city' => $this->eventVenueCity($event),
                'image' => $imageRaw !== '' ? $this->resolveImageUrl($imageRaw, $marketplace) : '',
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
    protected function wrapInEmailTemplate(string $content, MarketplaceClient $marketplace, ?string $previewText = null, ?string $eyebrowText = null, ?MarketplaceNewsletter $newsletter = null, ?MarketplaceCustomer $customer = null): string
    {
        $name = e($marketplace->name ?? 'Newsletter');
        $host = $this->hostOf($marketplace);
        $year = (int) date('Y');

        $eyebrow = e($eyebrowText ?: 'Newsletter');
        $preheader = e($previewText ?: ($marketplace->name ?? 'Newsletter') . ' — cumpără bilete online');
        $privacyUrl = "https://{$host}/privacy";
        $contactUrl = "https://{$host}/contact";

        // Open-tracking pixel — empty src when there's no newsletter
        // (shouldn't happen at send time but guards against direct
        // template-preview calls). Recipient id is left null in preview;
        // populated only by per-recipient send loops.
        $pixelTag = '';
        if ($newsletter) {
            $token = NewsletterTrackingController::buildToken(
                $newsletter->id,
                null,
                $this->currentRecipient?->id
            );
            $pixelUrl = e(route('newsletter.open', ['token' => $token]));
            $pixelTag = '<img src="' . $pixelUrl . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;" />';
        }

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
                        {$name} îți recomandă evenimentele care merită trăite live.
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

            {$pixelTag}

        </body>
        </html>
        HTML;
    }

    // =========================================
    // Link Instrumentation
    // =========================================

    /**
     * Rewrite every external <a href="..."> in the rendered body so it
     * goes through /newsletter/click/{token}. Skips:
     *   - mailto: / tel: / sms: schemes
     *   - in-page anchors (#fragment)
     *   - {{placeholder}} tokens that still need replacement (e.g.
     *     {{unsubscribe_url}} — handled per-recipient at send time)
     *
     * The tracking endpoint logs the click + 302s to the dest URL with
     * utm_source=newsletter & utm_campaign=nl_{id} appended, so any
     * existing analytics on the destination domain attributes the
     * traffic to this newsletter.
     */
    protected function instrumentLinks(string $html, MarketplaceNewsletter $newsletter, ?MarketplaceCustomer $customer): string
    {
        $recipientId = $this->currentRecipient?->id;

        return preg_replace_callback(
            '#href\s*=\s*"([^"]+)"#i',
            function ($m) use ($newsletter, $recipientId) {
                $dest = $m[1];

                if ($dest === '' || $dest[0] === '#') return $m[0];
                if (str_starts_with($dest, 'mailto:') || str_starts_with($dest, 'tel:') || str_starts_with($dest, 'sms:')) {
                    return $m[0];
                }
                if (str_contains($dest, '{{') || str_contains($dest, '}}')) return $m[0];
                if (!preg_match('#^https?://#i', $dest)) return $m[0];

                $token = NewsletterTrackingController::buildToken($newsletter->id, $dest, $recipientId);
                $tracked = route('newsletter.click', ['token' => $token]);
                return 'href="' . e($tracked) . '"';
            },
            $html
        ) ?? $html;
    }

    // =========================================
    // Helpers
    // =========================================

    /**
     * Resolve a stored image reference to an absolute URL.
     *
     * Images live on the platform host (config('app.url') →
     * core.tixello.com), NOT on the marketplace consumer host
     * (ambilet.ro). This matches the EventsController::formatImageUrl
     * convention used by the public API + the /bilete catalog. Pass-
     * through if the column already holds a fully qualified URL.
     */
    protected function resolveImageUrl(string $image, MarketplaceClient $marketplace): string
    {
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }
        return rtrim(config('app.url'), '/') . '/storage/' . ltrim($image, '/');
    }

    protected function getEventUrl(Event $event, MarketplaceClient $marketplace): string
    {
        $host = $this->hostOf($marketplace);
        $slug = $event->slug ?? $event->id;
        return "https://{$host}/bilete/{$slug}";
    }

    /**
     * Pick the first non-empty value from a translatable JSON column
     * (e.g. Event.title, Venue.name) — ro first, en next, then any locale.
     * Falls back to the raw string when the column isn't JSON-cast.
     */
    protected function localized($value): string
    {
        if (is_array($value)) {
            return (string) ($value['ro'] ?? $value['en'] ?? reset($value) ?? '');
        }
        return (string) ($value ?? '');
    }

    protected function eventTitle(Event $e): string
    {
        return $this->localized($e->title) ?: 'Eveniment';
    }

    protected function eventVenueName(Event $e): string
    {
        return $this->localized($e->venue?->name) ?: (string) ($e->suggested_venue_name ?? '');
    }

    protected function eventVenueCity(Event $e): string
    {
        return (string) ($e->venue?->city ?? '');
    }

    /** Returns a Carbon date (event_date) or null. */
    protected function eventDate(Event $e): ?\Carbon\Carbon
    {
        return $e->event_date ? \Carbon\Carbon::parse($e->event_date) : null;
    }

    /** Earliest start time as HH:mm, or empty string. */
    protected function eventStartTime(Event $e): string
    {
        $t = $e->start_time ?? $e->range_start_time ?? null;
        if (!$t) return '';
        try { return \Carbon\Carbon::parse((string) $t)->format('H:i'); }
        catch (\Throwable $ex) { return ''; }
    }

    /**
     * Pick the best image column for newsletter use. $variant='hero' prefers
     * wide cover assets (used by v2 hero); $variant='card' prefers poster
     * thumbnails (used by event cards + v1 hero). Returns empty string
     * when no image is on file.
     */
    protected function eventImage(Event $e, string $variant = 'card'): string
    {
        $candidates = $variant === 'hero'
            ? [$e->hero_image_url ?? null, $e->homepage_featured_image ?? null, $e->featured_image ?? null, $e->poster_url ?? null]
            : [$e->poster_url ?? null, $e->featured_image ?? null, $e->homepage_featured_image ?? null, $e->hero_image_url ?? null];
        foreach ($candidates as $img) {
            if (!empty($img)) return (string) $img;
        }
        return '';
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

    /**
     * Cheapest active ticket-type price for the event, formatted as
     * "{N} RON". Uses the denormalized cheapest_price_cents column when
     * available, falls back to a live ticket_types lookup otherwise.
     */
    protected function getEventPrice(Event $event, MarketplaceClient $marketplace): string
    {
        $currency = $marketplace->currency ?? 'RON';

        $cents = (int) ($event->cheapest_price_cents ?? 0);
        if ($cents > 0) {
            return number_format($cents / 100, 0) . ' ' . $currency;
        }

        $tt = $event->ticketTypes()
            ->where('status', 'active')
            ->orderByRaw('COALESCE(NULLIF(sale_price_cents, 0), price_cents) ASC')
            ->first();
        if (!$tt) return '';

        $best = (int) ($tt->sale_price_cents ?? 0) > 0
            ? (int) $tt->sale_price_cents
            : (int) ($tt->price_cents ?? 0);
        if ($best <= 0) return '';

        return number_format($best / 100, 0) . ' ' . $currency;
    }
}
