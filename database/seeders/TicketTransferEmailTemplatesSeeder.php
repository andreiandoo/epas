<?php

namespace Database\Seeders;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceEmailTemplate;
use Database\Seeders\Concerns\BrandedEmailWrapper;
use Illuminate\Database\Seeder;

/**
 * Seeds the 5 customer-facing ticket-transfer templates per marketplace:
 *   ticket_transfer_initiated  / ticket_transfer_received
 *   ticket_transfer_accepted   / ticket_transfer_rejected
 *   ticket_transfer_cancelled
 *
 * MarketplaceTicketTransferNotification looks these up by slug per
 * marketplace; missing rows fall back to the hardcoded MailMessage body.
 *
 * Run:
 *   php artisan db:seed --class=TicketTransferEmailTemplatesSeeder
 */
class TicketTransferEmailTemplatesSeeder extends Seeder
{
    use BrandedEmailWrapper;

    public function run(): void
    {
        foreach (MarketplaceClient::all() as $marketplace) {
            $this->seedForMarketplace($marketplace);
        }
    }

    protected function seedForMarketplace(MarketplaceClient $marketplace): void
    {
        $brand = $this->brand($marketplace);
        $primary = $brand['primary'];

        $coreRows = [
            ['Eveniment', '{{event_name}}'],
            ['Bilet', '{{ticket_type}}'],
            ['Data eveniment', '{{event_date}}'],
        ];

        $templates = [
            // --------------------------------------------------------
            // Sent to the original ticket holder when they fire a transfer
            'ticket_transfer_initiated' => [
                'name' => 'Transfer bilet — inițiat (expeditor)',
                'subject' => 'Transfer bilet inițiat — {{event_name}}',
                'category' => 'transactional',
                'content' => $this->intro(
                    'Transfer bilet inițiat',
                    'Ai inițiat un transfer pentru biletul tău.',
                    array_merge($coreRows, [
                        ['Către', '{{to_name}} ({{to_email}})'],
                        ['Expiră la', '{{expires_at}}'],
                    ]),
                    'Destinatarul va primi un email cu instrucțiuni pentru a accepta transferul. Poți anula transferul oricând înainte ca acesta să fie acceptat.',
                    null,
                    null,
                    $brand
                ),
            ],

            // --------------------------------------------------------
            // Sent to the recipient — the only one with a CTA button
            'ticket_transfer_received' => [
                'name' => 'Transfer bilet — primit (destinatar)',
                'subject' => 'Ai primit un transfer de bilet — {{event_name}}',
                'category' => 'transactional',
                'content' => $this->intro(
                    'Ai primit un transfer de bilet 🎟️',
                    '<strong>{{from_name}}</strong> dorește să-ți transfere un bilet.',
                    array_merge($coreRows, [
                        ['De la', '{{from_name}}'],
                        ['Mesaj', '{{transfer_message}}'],
                        ['Expiră la', '{{expires_at}}'],
                    ]),
                    'Apasă butonul de mai jos pentru a accepta transferul. Dacă nu îți dorești biletul, poți pur și simplu să ignori acest email.',
                    '{{accept_url}}',
                    'Acceptă transferul',
                    $brand
                ),
            ],

            // --------------------------------------------------------
            // Back to the original holder — recipient accepted
            'ticket_transfer_accepted' => [
                'name' => 'Transfer bilet — acceptat',
                'subject' => 'Transfer bilet acceptat — {{event_name}}',
                'category' => 'transactional',
                'content' => $this->intro(
                    'Transferul a fost acceptat ✅',
                    'Veste bună! <strong>{{to_name}}</strong> a acceptat transferul biletului.',
                    $coreRows,
                    'Biletul este acum în posesia destinatarului. Mulțumim pentru folosirea serviciului de transfer.',
                    null,
                    null,
                    $brand
                ),
            ],

            // --------------------------------------------------------
            // Back to the original holder — recipient declined
            'ticket_transfer_rejected' => [
                'name' => 'Transfer bilet — refuzat',
                'subject' => 'Transfer bilet refuzat — {{event_name}}',
                'category' => 'transactional',
                'content' => $this->intro(
                    'Transferul a fost refuzat',
                    '<strong>{{to_name}}</strong> a refuzat transferul biletului tău.',
                    $coreRows,
                    'Biletul rămâne în posesia ta. Poți să-l transferi altcuiva sau să-l folosești tu la eveniment.',
                    null,
                    null,
                    $brand
                ),
            ],

            // --------------------------------------------------------
            // To the recipient — original holder cancelled mid-flight
            'ticket_transfer_cancelled' => [
                'name' => 'Transfer bilet — anulat (destinatar)',
                'subject' => 'Transfer bilet anulat — {{event_name}}',
                'category' => 'transactional',
                'content' => $this->intro(
                    'Transferul a fost anulat',
                    'Transferul biletului de la <strong>{{from_name}}</strong> a fost anulat.',
                    $coreRows,
                    'Dacă încă vrei să participi la eveniment, poți cumpăra bilete direct de pe site.',
                    'https://{{marketplace_domain}}/bilete/{{event_slug}}',
                    'Vezi biletele disponibile',
                    $brand
                ),
            ],
        ];

        foreach ($templates as $slug => $tpl) {
            MarketplaceEmailTemplate::updateOrCreate(
                [
                    'marketplace_client_id' => $marketplace->id,
                    'slug' => $slug,
                ],
                [
                    'name' => $tpl['name'],
                    'subject' => $tpl['subject'],
                    'body_html' => $this->wrap($brand, $tpl['content']),
                    'category' => $tpl['category'] ?? 'transactional',
                    'is_active' => true,
                    'is_default' => true,
                ]
            );
        }
    }

    protected function intro(
        string $heading,
        string $intro,
        array $rows,
        ?string $outro,
        ?string $ctaUrl,
        ?string $ctaLabel,
        array $brand
    ): string {
        $primary = $brand['primary'];
        $details = $this->detailsTable($brand, 'Detalii bilet', $rows);
        $cta = ($ctaUrl && $ctaLabel)
            ? "<p style=\"text-align:center;margin:24px 0;\"><a href=\"{$ctaUrl}\" style=\"display:inline-block;padding:12px 28px;background:{$primary};color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;\">{$ctaLabel}</a></p>"
            : '';

        return <<<CONTENT
<h2 style="color:#1a1a1a;font-size:22px;margin:0 0 16px;">{$heading}</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 12px;">Salut <strong>{{customer_name}}</strong>,</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;">{$intro}</p>
{$details}
<p style="color:#4a4a4a;font-size:14px;line-height:1.6;margin:0 0 16px;">{$outro}</p>
{$cta}
CONTENT;
    }
}
