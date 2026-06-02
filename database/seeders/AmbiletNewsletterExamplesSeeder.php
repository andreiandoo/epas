<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceNewsletter;
use Illuminate\Database\Seeder;

/**
 * Seeds 3 ready-to-use draft newsletters into the Ambilet marketplace as
 * working models the admin can clone / edit / send.
 *
 * Unlike the "email template" seeders (which only seed a static body_html
 * shell + need the user to manually add a featured_event section), THIS
 * seeder writes fully-populated body_sections JSON so the newsletter
 * renders end-to-end without any extra clicks. The admin just opens it
 * at /marketplace/newsletters/, picks targeting (lists/tags/events) and
 * hits send.
 *
 * The 3 examples:
 *  1. "Exemplu — Eveniment featured (v1 compact)" — text intro + featured_event v1 + CTA button
 *  2. "Exemplu — Eveniment featured (v2 hero)"    — featured_event v2 with artist + intro paragraph
 *  3. "Exemplu — Săptămâna asta la AmBilet"       — text intro + events_next_week + events_next_month + CTA
 *
 * Featured-event slots are pre-filled with the first upcoming approved &
 * public event from the marketplace so the renderer has something to
 * render right away. If no upcoming event exists, event_id is left null
 * and the admin can pick one in the editor.
 *
 *   php artisan db:seed --class="Database\Seeders\AmbiletNewsletterExamplesSeeder"
 */
class AmbiletNewsletterExamplesSeeder extends Seeder
{
    public function run(): void
    {
        $client = MarketplaceClient::query()
            ->where('domain', 'like', '%ambilet.ro%')
            ->orWhere('name', 'like', '%Ambilet%')
            ->first()
            ?? MarketplaceClient::orderBy('id')->first();

        if (!$client) {
            $this->command->warn('No MarketplaceClient found — skipping seeder.');
            return;
        }

        // Pick the soonest live event for the featured slot. Newsletter
        // uses the public catalog Event model (the one /bilete renders),
        // NOT marketplace_events. Filter: is_published, not cancelled,
        // event_date >= today.
        $liveEventsQuery = fn () => Event::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_published', true)
            ->where(fn ($q) => $q->where('is_cancelled', false)->orWhereNull('is_cancelled'));

        $featuredEventId = $liveEventsQuery()
            ->where('event_date', '>=', now()->toDateString())
            ->orderBy('event_date', 'asc')
            ->value('id')
            ?? $liveEventsQuery()
                ->orderBy('event_date', 'desc')
                ->value('id');

        if (!$featuredEventId) {
            $this->command->warn('No live event found for this marketplace — featured_event sections will be created with event_id=null; pick one in the editor.');
        }

        // Pre-populate the digest example with real event_ids so the
        // preview shows actual cards. Same live-only filter.
        $nextWeekEventIds = $liveEventsQuery()
            ->whereBetween('event_date', [now()->toDateString(), now()->addDays(14)->toDateString()])
            ->orderBy('event_date')
            ->limit(4)
            ->pluck('id')
            ->all();

        $nextMonthEventIds = $liveEventsQuery()
            ->whereBetween('event_date', [now()->addDays(14)->toDateString(), now()->addDays(45)->toDateString()])
            ->orderBy('event_date')
            ->limit(6)
            ->pluck('id')
            ->all();

        $host = $this->hostOf($client);
        $name = $client->name ?? 'AmBilet.ro';

        $examples = [
            $this->buildV1FeaturedExample($name, $host, $featuredEventId),
            $this->buildV2FeaturedExample($name, $host, $featuredEventId),
            $this->buildWeeklyDigestExample($name, $host, $nextWeekEventIds, $nextMonthEventIds),
        ];

        foreach ($examples as $payload) {
            $nl = MarketplaceNewsletter::updateOrCreate(
                [
                    'marketplace_client_id' => $client->id,
                    'name' => $payload['name'],
                ],
                array_merge($payload, [
                    'marketplace_client_id' => $client->id,
                    'status' => 'draft',
                    'from_name' => $name,
                    'from_email' => 'newsletter@' . $host,
                    // body_html is NOT NULL in DB; renderer uses body_sections
                    // when present (this column is just the legacy fallback).
                    // Stub it with a 1-line note so the constraint passes.
                    'body_html' => $payload['body_html']
                        ?? '<p>Acest newsletter folosește secțiuni structurate (body_sections). Editează-l în Filament pentru a vedea conținutul complet.</p>',
                ])
            );
            $this->command->info("Seeded newsletter draft: #{$nl->id} — {$nl->name}");
        }
    }

    /**
     * Normalize the client's stored domain (often `https://ambilet.ro`)
     * down to a bare host suitable for both `from_email` and CTA URLs.
     */
    private function hostOf(MarketplaceClient $client): string
    {
        $raw = trim((string) ($client->domain ?? 'ambilet.ro'));
        $raw = preg_replace('#^https?://#i', '', $raw);
        $raw = rtrim((string) $raw, '/');
        return $raw !== '' ? $raw : 'ambilet.ro';
    }

    /**
     * Example 1 — minimal featured event (just the hero, no artist /
     * intro paragraph). Subject + preview_text drive the inbox snippet.
     */
    private function buildV1FeaturedExample(string $name, string $host, ?int $eventId): array
    {
        return [
            'name' => 'Exemplu — Eveniment featured (minimal)',
            'subject' => "{$name} îți recomandă: nu rata acest eveniment!",
            'preview_text' => 'Un eveniment ales pe sprânceană, doar pentru tine.',
            'body_sections' => [
                [
                    'type' => 'featured_event',
                    'event_id' => $eventId,
                    'cta_label' => 'Cumpără bilete',
                ],
            ],
            'body_text' => "Salut,\n\n{$name} îți recomandă un eveniment de neratat. Vezi detaliile în versiunea HTML a acestui email.\n\nDezabonare: {{unsubscribe_url}}\n",
        ];
    }

    /**
     * Example 2 — featured event with artist subtitle + intro paragraph
     * so the admin sees what those optional fields look like in the
     * rendered hero.
     */
    private function buildV2FeaturedExample(string $name, string $host, ?int $eventId): array
    {
        return [
            'name' => 'Exemplu — Eveniment featured (cu artist + intro)',
            'subject' => 'Eveniment de neratat — ' . $name,
            'preview_text' => 'Concertul săptămânii — energie, scenă, vibe. Vezi detaliile.',
            'body_sections' => [
                [
                    'type' => 'featured_event',
                    'event_id' => $eventId,
                    'artist_name' => 'Trupa pe care nu vrei să o ratezi',
                    'intro_paragraph' => 'Un show intens, lumini, vibrații puternice și o seară pe care o vei ține minte. Bilete limitate — rezervă-ți locul acum, la prețul cel mai bun.',
                    'cta_label' => 'Cumpără bilete',
                ],
            ],
            'body_text' => "{$name} îți recomandă acest eveniment. Vezi detaliile în versiunea HTML.\n\nDezabonare: {{unsubscribe_url}}\n",
        ];
    }

    /**
     * Example 3 — săptămânal: text intro + events_next_week +
     * events_next_month + footer CTA. event_ids are pre-populated from
     * the seeder so the preview shows real cards; admin can swap them
     * in the editor.
     */
    private function buildWeeklyDigestExample(string $name, string $host, array $nextWeekIds, array $nextMonthIds): array
    {
        return [
            'name' => 'Exemplu — Săptămâna asta la ' . $name,
            'subject' => 'Săptămâna asta pe ' . $name . ' — concerte, festivaluri, evenimente',
            'preview_text' => 'Top recomandări pentru următoarele 7 zile și pentru luna viitoare.',
            'body_sections' => [
                [
                    'type' => 'text',
                    'content' => '<h2 style="font-size:22px;line-height:28px;color:#1f2937;margin:0 0 12px 0;">Săptămâna asta pe ' . e($name) . '</h2>'
                        . '<p style="font-size:15px;line-height:22px;color:#374151;margin:0 0 12px 0;">Salut {{customer_name}}, mai jos ai recomandările noastre pentru zilele care urmează. Bilete online, direct de la organizator.</p>',
                ],
                [
                    'type' => 'events_next_week',
                    'event_ids' => $nextWeekIds,
                    'section_title' => 'Săptămâna viitoare',
                ],
                [
                    'type' => 'spacer',
                    'height' => 24,
                ],
                [
                    'type' => 'text',
                    'content' => '<h3 style="font-size:18px;line-height:24px;color:#1f2937;margin:0 0 8px 0;">Plănuiește mai departe — luna viitoare</h3>',
                ],
                [
                    'type' => 'events_next_month',
                    'event_ids' => $nextMonthIds,
                    'section_title' => 'Luna viitoare',
                ],
                [
                    'type' => 'spacer',
                    'height' => 16,
                ],
                [
                    'type' => 'button',
                    'button_text' => 'Vezi calendarul complet',
                    'button_url' => "https://{$host}/bilete",
                    'button_color' => '#A51C30',
                ],
            ],
            'body_text' => "Salut,\n\nMai jos sunt recomandările {$name} pentru săptămâna și luna care vine. Vezi versiunea HTML pentru poze și prețuri.\n\nCalendar complet: https://{$host}/bilete\n\nDezabonare: {{unsubscribe_url}}\n",
        ];
    }
}
