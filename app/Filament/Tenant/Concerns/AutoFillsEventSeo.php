<?php

namespace App\Filament\Tenant\Concerns;

use App\Models\Event;
use Illuminate\Support\Facades\Storage;

/**
 * Auto-generează cheile SEO ale unui eveniment din datele lui, la salvare —
 * echivalentul comportamentului din panoul Marketplace. Cheile auto-generate
 * sunt suprascrise; cheile custom adăugate manual de utilizator sunt păstrate.
 */
trait AutoFillsEventSeo
{
    protected function autoFillSeoKeys(): void
    {
        /** @var Event|null $event */
        $event = $this->record?->fresh(['venue']);
        if (! $event) {
            return;
        }

        $tenant = auth()->user()?->tenant;
        $lang = $tenant?->locale ?? 'ro';

        $title = $event->getTranslation('title', $lang) ?: '';
        $slug = $event->slug ?? '';
        $description = $event->getTranslation('short_description', $lang)
            ?: $event->getTranslation('description', $lang)
            ?: '';
        $shortDesc = trim(strip_tags((string) $description));
        if (mb_strlen($shortDesc) > 160) {
            $shortDesc = mb_substr($shortDesc, 0, 157) . '...';
        }

        $imageUrl = $event->poster_url ?: $event->hero_image_url ?: '';
        $eventDate = $event->event_date?->format('Y-m-d') ?? '';
        $startTime = $event->start_time ?? '';
        $endTime = $event->end_time ?? '';

        $venueName = '';
        $venueAddress = '';
        if ($event->venue) {
            $venueName = $event->venue->getTranslation('name', $lang) ?: ($event->venue->name ?? '');
            $venueAddress = $event->venue->address ?? '';
        }

        // Base URL din domeniul primar activ al tenantului
        $primaryDomain = $tenant?->domains()
            ->where('is_primary', true)
            ->where('is_active', true)
            ->first();
        $baseUrl = $primaryDomain ? 'https://' . $primaryDomain->domain : (string) ($tenant?->website ?? '');
        if ($baseUrl && ! preg_match('#^https?://#', $baseUrl)) {
            $baseUrl = 'https://' . $baseUrl;
        }
        $eventUrl = $baseUrl && $slug ? "{$baseUrl}/spectacol/{$slug}" : '';

        $absoluteImageUrl = '';
        if ($imageUrl) {
            $absoluteImageUrl = preg_match('#^https?://#', $imageUrl)
                ? $imageUrl
                : Storage::disk('public')->url($imageUrl);
        }

        $now = now()->toIso8601String();
        $siteName = $tenant?->public_name ?? $tenant?->name ?? '';
        $currency = $tenant?->currency ?? 'RON';

        // Preț minim din tipurile de bilete active (fără invitații)
        $minPrice = '';
        $ticketTypes = $event->ticketTypes()->where('status', 'active')->get()
            ->filter(fn ($t) => ! ($t->meta['is_invitation'] ?? false));
        if ($ticketTypes->isNotEmpty()) {
            $prices = $ticketTypes->map(fn ($t) => (float) ($t->price ?: $t->price_max))->filter(fn ($p) => $p > 0);
            if ($prices->isNotEmpty()) {
                $minPrice = number_format($prices->min(), 2, '.', '');
            }
        }

        $seo = [
            'meta_title'       => $title,
            'meta_description' => $shortDesc,
            'canonical_url'    => $eventUrl,
            'robots'           => 'index,follow',
            'viewport'         => 'width=device-width, initial-scale=1',
            'referrer'         => 'no-referrer-when-downgrade',

            'og:locale'        => $lang === 'ro' ? 'ro_RO' : 'en_US',
            'og:title'         => $title,
            'og:description'   => $shortDesc,
            'og:type'          => 'event',
            'og:url'           => $eventUrl,
            'og:image'         => $absoluteImageUrl,
            'og:image:alt'     => $title,
            'og:image:width'   => '1200',
            'og:image:height'  => '630',
            'og:site_name'     => $siteName,

            'article:author'         => $siteName,
            'article:section'        => 'Evenimente',
            'article:published_time' => $event->created_at?->toIso8601String() ?? $now,
            'article:modified_time'  => $now,

            'product:price:amount'   => $minPrice,
            'product:price:currency' => $currency,
            'product:availability'   => ($event->is_sold_out ?? false) ? 'oos' : 'instock',

            'twitter:card'        => 'summary_large_image',
            'twitter:title'       => $title,
            'twitter:description' => $shortDesc,
            'twitter:image'       => $absoluteImageUrl,

            'structured_data' => json_encode(array_filter([
                '@context'    => 'https://schema.org',
                '@type'       => 'Event',
                'name'        => $title,
                'description' => $shortDesc,
                'image'       => $absoluteImageUrl,
                'startDate'   => $eventDate && $startTime ? "{$eventDate}T{$startTime}" : $eventDate,
                'endDate'     => $eventDate && $endTime ? "{$eventDate}T{$endTime}" : '',
                'location'    => [
                    '@type'   => 'Place',
                    'name'    => $venueName,
                    'address' => $venueAddress,
                ],
                'organizer' => [
                    '@type' => 'Organization',
                    'name'  => $siteName,
                    'url'   => $baseUrl,
                ],
                'url'    => $eventUrl,
                'offers' => $minPrice ? [
                    '@type'         => 'Offer',
                    'price'         => $minPrice,
                    'priceCurrency' => $currency,
                    'availability'  => ($event->is_sold_out ?? false)
                        ? 'https://schema.org/SoldOut'
                        : 'https://schema.org/InStock',
                    'url' => $eventUrl,
                ] : null,
            ], fn ($v) => $v !== null && $v !== ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),

            'max-snippet'       => '-1',
            'max-image-preview' => 'large',
            'max-video-preview' => '-1',
        ];

        // Păstrează cheile custom adăugate manual, suprascrie-le pe cele auto
        $existingSeo = (array) ($event->seo ?? []);
        $event->update(['seo' => array_merge($existingSeo, $seo)]);
    }
}
