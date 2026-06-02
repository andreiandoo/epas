<?php

namespace Database\Seeders;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceEvent;
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

        $featuredEventId = MarketplaceEvent::query()
            ->where('marketplace_client_id', $client->id)
            ->where('status', 'approved')
            ->where('is_public', true)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at', 'asc')
            ->value('id');

        if (!$featuredEventId) {
            $this->command->warn('No upcoming approved+public event found — featured_event sections will be created with event_id=null; pick one in the editor.');
        }

        $examples = [
            $this->buildV1FeaturedExample($client, $featuredEventId),
            $this->buildV2FeaturedExample($client, $featuredEventId),
            $this->buildWeeklyDigestExample($client),
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
                    'from_name' => $client->name ?? 'AmBilet.ro',
                    'from_email' => 'newsletter@' . ($client->domain ?? 'ambilet.ro'),
                ])
            );
            $this->command->info("Seeded newsletter draft: #{$nl->id} — {$nl->name}");
        }
    }

    /**
     * Example 1 — text intro + featured event (v1 compact) + footer CTA.
     */
    private function buildV1FeaturedExample(MarketplaceClient $client, ?int $eventId): array
    {
        $name = $client->name ?? 'AmBilet.ro';
        $domain = $client->domain ?? 'ambilet.ro';

        return [
            'name' => 'Exemplu — Eveniment featured (v1 compact)',
            'subject' => "{$name} îți recomandă: nu rata acest eveniment!",
            'preview_text' => 'Un eveniment ales pe sprânceană, doar pentru tine.',
            'body_sections' => [
                [
                    'type' => 'text',
                    'content' => '<p style="font-size:15px;line-height:22px;color:#1f2937;margin:0 0 12px 0;">Salut {{customer_name}},</p>'
                        . '<p style="font-size:15px;line-height:22px;color:#1f2937;margin:0 0 12px 0;">Echipa <strong>' . e($name) . '</strong> îți pregătește o recomandare specială pentru zilele următoare. Mai jos ai toate detaliile + linkul direct la bilete.</p>',
                ],
                [
                    'type' => 'featured_event',
                    'event_id' => $eventId,
                    'design_variant' => 'v1',
                    'intro_text' => $name . ' îți recomandă',
                    'cta_label' => 'Vezi programul și Cumpără bilet',
                ],
                [
                    'type' => 'spacer',
                    'height' => 16,
                ],
                [
                    'type' => 'button',
                    'button_text' => 'Vezi toate evenimentele',
                    'button_url' => "https://{$domain}/bilete",
                    'button_color' => '#A51C30',
                ],
            ],
            'body_text' => "Salut,\n\n{$name} îți recomandă un eveniment de neratat. Vezi detaliile în versiunea HTML a acestui email.\n\nDezabonare: {{unsubscribe_url}}\n",
        ];
    }

    /**
     * Example 2 — featured event v2 hero (dark wrapper, big title,
     * artist subtitle, intro paragraph, details grid).
     */
    private function buildV2FeaturedExample(MarketplaceClient $client, ?int $eventId): array
    {
        $name = $client->name ?? 'AmBilet.ro';

        return [
            'name' => 'Exemplu — Eveniment featured (v2 hero cu detalii)',
            'subject' => 'Eveniment de neratat — ' . $name,
            'preview_text' => 'Concertul săptămânii — energie, scenă, vibe. Vezi detaliile.',
            'body_sections' => [
                [
                    'type' => 'featured_event',
                    'event_id' => $eventId,
                    'design_variant' => 'v2',
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
     * events_next_month + footer CTA. Auto-populated from the upcoming
     * calendar; no specific event picked.
     */
    private function buildWeeklyDigestExample(MarketplaceClient $client): array
    {
        $name = $client->name ?? 'AmBilet.ro';
        $domain = $client->domain ?? 'ambilet.ro';

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
                    'limit' => 4,
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
                    'limit' => 6,
                ],
                [
                    'type' => 'spacer',
                    'height' => 16,
                ],
                [
                    'type' => 'button',
                    'button_text' => 'Vezi calendarul complet',
                    'button_url' => "https://{$domain}/bilete",
                    'button_color' => '#A51C30',
                ],
            ],
            'body_text' => "Salut,\n\nMai jos sunt recomandările {$name} pentru săptămâna și luna care vine. Vezi versiunea HTML pentru poze și prețuri.\n\nCalendar complet: https://{$domain}/bilete\n\nDezabonare: {{unsubscribe_url}}\n",
        ];
    }
}
