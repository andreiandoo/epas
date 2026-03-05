<?php

namespace Database\Seeders;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceEmailTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds default email templates for all marketplace clients.
 *
 * Usage: php artisan db:seed --class=MarketplaceEmailTemplatesSeeder
 */
class MarketplaceEmailTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $marketplaces = MarketplaceClient::all();

        foreach ($marketplaces as $marketplace) {
            $this->seedForMarketplace($marketplace);
        }
    }

    protected function seedForMarketplace(MarketplaceClient $marketplace): void
    {
        $name = $marketplace->public_name ?? $marketplace->name ?? 'Marketplace';

        $templates = [
            // 1. Order / Ticket Purchase Confirmation
            [
                'slug' => 'ticket_purchase',
                'name' => 'Confirmare comandă',
                'subject' => 'Confirmare comandă #{{order_number}} — {{event_name}}',
                'category' => 'transactional',
                'body_html' => <<<HTML
<h2>Mulțumim pentru comanda ta!</h2>
<p>Salut {{customer_name}},</p>
<p>Comanda ta <strong>#{{order_number}}</strong> a fost confirmată cu succes.</p>

<table style="width:100%;border-collapse:collapse;margin:16px 0;">
<tr style="background:#f3f4f6;">
<td style="padding:8px;font-weight:bold;">Eveniment</td>
<td style="padding:8px;">{{event_name}}</td>
</tr>
<tr>
<td style="padding:8px;font-weight:bold;">Data</td>
<td style="padding:8px;">{{event_date}}</td>
</tr>
<tr style="background:#f3f4f6;">
<td style="padding:8px;font-weight:bold;">Locație</td>
<td style="padding:8px;">{{event_venue}}</td>
</tr>
<tr>
<td style="padding:8px;font-weight:bold;">Bilete</td>
<td style="padding:8px;">{{tickets_count}} bilet(e)</td>
</tr>
<tr style="background:#f3f4f6;">
<td style="padding:8px;font-weight:bold;">Total</td>
<td style="padding:8px;"><strong>{{total_amount}}</strong></td>
</tr>
</table>

<p>Biletele tale au fost trimise și pe adresa de email asociată contului. Prezintă codul QR de pe bilet la intrarea în eveniment.</p>

<p>Dacă ai întrebări, nu ezita să ne contactezi.</p>
<p>Echipa {$name}</p>
HTML,
            ],

            // 2. Order Confirmation (generic, without ticket details)
            [
                'slug' => 'order_confirmation',
                'name' => 'Confirmare comandă (generic)',
                'subject' => 'Comanda ta #{{order_number}} a fost confirmată',
                'category' => 'transactional',
                'body_html' => <<<HTML
<h2>Comandă confirmată</h2>
<p>Salut {{customer_name}},</p>
<p>Comanda ta <strong>#{{order_number}}</strong> pentru <strong>{{event_name}}</strong> a fost procesată cu succes.</p>
<p><strong>Total:</strong> {{total_amount}}</p>
<p>Vei primi biletele pe email în scurt timp.</p>
<p>Echipa {$name}</p>
HTML,
            ],

            // 3. Welcome / Registration
            [
                'slug' => 'welcome',
                'name' => 'Înregistrare cont',
                'subject' => 'Bine ai venit pe ' . $name . '!',
                'category' => 'transactional',
                'body_html' => <<<HTML
<h2>Bine ai venit!</h2>
<p>Salut {{customer_name}},</p>
<p>Contul tău pe <strong>{$name}</strong> a fost creat cu succes.</p>
<p>De acum poți:</p>
<ul>
<li>Cumpăra bilete rapid și simplu</li>
<li>Vedea istoricul comenzilor tale</li>
<li>Primi notificări despre evenimentele care te interesează</li>
</ul>
<p><a href="{{login_url}}" style="display:inline-block;padding:10px 24px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;">Accesează contul tău</a></p>
<p>Echipa {$name}</p>
HTML,
            ],

            // 4. Password Reset
            [
                'slug' => 'password_reset',
                'name' => 'Resetare parolă',
                'subject' => 'Resetare parolă — ' . $name,
                'category' => 'transactional',
                'body_html' => <<<HTML
<h2>Resetare parolă</h2>
<p>Salut {{customer_name}},</p>
<p>Am primit o cerere de resetare a parolei pentru contul tău pe <strong>{$name}</strong>.</p>
<p>Apasă butonul de mai jos pentru a-ți seta o parolă nouă:</p>
<p><a href="{{reset_url}}" style="display:inline-block;padding:10px 24px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;">Resetează parola</a></p>
<p>Link-ul este valabil 60 de minute. Dacă nu ai solicitat resetarea parolei, poți ignora acest email.</p>
<p>Echipa {$name}</p>
HTML,
            ],

            // 5. Ticket Delivery to Beneficiary
            [
                'slug' => 'ticket_delivery',
                'name' => 'Livrare bilete către beneficiar',
                'subject' => 'Biletul tău pentru {{event_name}}',
                'category' => 'transactional',
                'body_html' => <<<HTML
<h2>Ai primit un bilet!</h2>
<p>Salut {{beneficiary_name}},</p>
<p><strong>{{customer_name}}</strong> ți-a cumpărat un bilet pentru:</p>

<table style="width:100%;border-collapse:collapse;margin:16px 0;">
<tr style="background:#f3f4f6;">
<td style="padding:8px;font-weight:bold;">Eveniment</td>
<td style="padding:8px;">{{event_name}}</td>
</tr>
<tr>
<td style="padding:8px;font-weight:bold;">Data</td>
<td style="padding:8px;">{{event_date}}</td>
</tr>
<tr style="background:#f3f4f6;">
<td style="padding:8px;font-weight:bold;">Locație</td>
<td style="padding:8px;">{{venue_name}}</td>
</tr>
<tr>
<td style="padding:8px;font-weight:bold;">Tip bilet</td>
<td style="padding:8px;">{{ticket_type}}</td>
</tr>
</table>

<p>Prezintă codul QR atașat la intrarea în eveniment.</p>
<p>Echipa {$name}</p>
HTML,
            ],

            // 6. Event Reminder
            [
                'slug' => 'event_reminder',
                'name' => 'Reminder eveniment',
                'subject' => 'Mâine: {{event_name}}',
                'category' => 'notification',
                'body_html' => <<<HTML
<h2>Nu uita de eveniment!</h2>
<p>Salut {{customer_name}},</p>
<p>Evenimentul <strong>{{event_name}}</strong> are loc mâine!</p>

<table style="width:100%;border-collapse:collapse;margin:16px 0;">
<tr style="background:#f3f4f6;">
<td style="padding:8px;font-weight:bold;">Data</td>
<td style="padding:8px;">{{event_date}}</td>
</tr>
<tr>
<td style="padding:8px;font-weight:bold;">Locație</td>
<td style="padding:8px;">{{venue_name}}, {{venue_address}}</td>
</tr>
</table>

<p>Pregătește-ți biletele și ne vedem acolo!</p>
<p>Echipa {$name}</p>
HTML,
            ],

            // 7. Event Cancelled
            [
                'slug' => 'event_cancelled',
                'name' => 'Eveniment anulat',
                'subject' => 'Evenimentul {{event_name}} a fost anulat',
                'category' => 'transactional',
                'body_html' => <<<HTML
<h2>Eveniment anulat</h2>
<p>Salut {{customer_name}},</p>
<p>Din păcate, evenimentul <strong>{{event_name}}</strong> programat pe <strong>{{event_date}}</strong> la <strong>{{venue_name}}</strong> a fost anulat.</p>
<p>Vei primi un refund automat în contul din care ai efectuat plata. Procesarea poate dura 5-10 zile lucrătoare.</p>
<p>Ne cerem scuze pentru inconvenient.</p>
<p>Echipa {$name}</p>
HTML,
            ],

            // 8. Event Updated
            [
                'slug' => 'event_updated',
                'name' => 'Eveniment actualizat',
                'subject' => 'Actualizare: {{event_name}}',
                'category' => 'notification',
                'body_html' => <<<HTML
<h2>Actualizare eveniment</h2>
<p>Salut {{customer_name}},</p>
<p>Au apărut modificări pentru evenimentul <strong>{{event_name}}</strong>:</p>

<table style="width:100%;border-collapse:collapse;margin:16px 0;">
<tr style="background:#f3f4f6;">
<td style="padding:8px;font-weight:bold;">Data</td>
<td style="padding:8px;">{{event_date}}</td>
</tr>
<tr>
<td style="padding:8px;font-weight:bold;">Locație</td>
<td style="padding:8px;">{{venue_name}}, {{venue_address}}</td>
</tr>
</table>

<p>Biletele tale rămân valabile. Verifică detaliile actualizate pe site.</p>
<p>Echipa {$name}</p>
HTML,
            ],

            // 9. Refund Approved
            [
                'slug' => 'refund_approved',
                'name' => 'Refund aprobat',
                'subject' => 'Refund aprobat — Comanda #{{order_number}}',
                'category' => 'transactional',
                'body_html' => <<<HTML
<h2>Refund aprobat</h2>
<p>Salut {{customer_name}},</p>
<p>Cererea ta de refund pentru comanda <strong>#{{order_number}}</strong> a fost aprobată.</p>
<p><strong>Sumă returnată:</strong> {{refund_amount}}</p>
<p>Banii vor fi returnați în contul din care ai efectuat plata. Procesarea poate dura 5-10 zile lucrătoare.</p>
<p>Echipa {$name}</p>
HTML,
            ],

            // 10. Refund Rejected
            [
                'slug' => 'refund_rejected',
                'name' => 'Refund respins',
                'subject' => 'Actualizare cerere refund — Comanda #{{order_number}}',
                'category' => 'transactional',
                'body_html' => <<<HTML
<h2>Cerere de refund respinsă</h2>
<p>Salut {{customer_name}},</p>
<p>Din păcate, cererea ta de refund pentru comanda <strong>#{{order_number}}</strong> nu a putut fi aprobată.</p>
<p><strong>Motiv:</strong> {{rejection_reason}}</p>
<p>Dacă ai întrebări, te rugăm să ne contactezi.</p>
<p>Echipa {$name}</p>
HTML,
            ],

            // 11. Invitation
            [
                'slug' => 'invitation',
                'name' => 'Invitație eveniment',
                'subject' => 'Invitație: {{event_name}}',
                'category' => 'transactional',
                'body_html' => <<<HTML
<h2>Ai primit o invitație!</h2>
<p>Salut {{customer_name}},</p>
<p>Ești invitat(ă) la evenimentul <strong>{{event_name}}</strong>!</p>

<table style="width:100%;border-collapse:collapse;margin:16px 0;">
<tr style="background:#f3f4f6;">
<td style="padding:8px;font-weight:bold;">Data</td>
<td style="padding:8px;">{{event_date}}</td>
</tr>
<tr>
<td style="padding:8px;font-weight:bold;">Locație</td>
<td style="padding:8px;">{{venue_name}}</td>
</tr>
</table>

<p>Prezintă acest email sau codul QR atașat la intrarea în eveniment.</p>
<p>Echipa {$name}</p>
HTML,
            ],

            // 12. Ticket Cancelled
            [
                'slug' => 'ticket_cancelled',
                'name' => 'Bilet anulat',
                'subject' => 'Biletul tău pentru {{event_name}} a fost anulat',
                'category' => 'transactional',
                'body_html' => <<<HTML
<h2>Bilet anulat</h2>
<p>Salut {{customer_name}},</p>
<p>Biletul tău pentru evenimentul <strong>{{event_name}}</strong> din data de <strong>{{event_date}}</strong> a fost anulat.</p>
<p>Dacă ai dreptul la un refund, acesta va fi procesat automat.</p>
<p>Echipa {$name}</p>
HTML,
            ],

            // ============================================================
            // ORGANIZER TEMPLATES
            // ============================================================

            // 13. Organizer Payout
            [
                'slug' => 'organizer_payout',
                'name' => 'Notificare plată organizator',
                'subject' => 'Plată procesată — {{payout_reference}}',
                'category' => 'transactional',
                'body_html' => <<<HTML
<h2>Plată procesată</h2>
<p>Salut {{organizer_name}},</p>
<p>Plata pentru perioada <strong>{{period}}</strong> a fost procesată cu succes.</p>

<table style="width:100%;border-collapse:collapse;margin:16px 0;">
<tr style="background:#f3f4f6;">
<td style="padding:8px;font-weight:bold;">Referință</td>
<td style="padding:8px;">{{payout_reference}}</td>
</tr>
<tr>
<td style="padding:8px;font-weight:bold;">Sumă</td>
<td style="padding:8px;"><strong>{{payout_amount}}</strong></td>
</tr>
</table>

<p>Banii vor fi virați în contul bancar asociat în 1-3 zile lucrătoare.</p>
<p>Echipa {$name}</p>
HTML,
            ],

            // 14. Organizer Event Approved
            [
                'slug' => 'organizer_event_approved',
                'name' => 'Eveniment aprobat',
                'subject' => 'Evenimentul tău "{{event_name}}" a fost aprobat',
                'category' => 'transactional',
                'body_html' => <<<HTML
<h2>Eveniment aprobat!</h2>
<p>Salut {{organizer_name}},</p>
<p>Evenimentul tău <strong>{{event_name}}</strong> din data de <strong>{{event_date}}</strong> a fost aprobat și este acum vizibil pe platformă.</p>
<p>Biletele pot fi achiziționate de către public de îndată ce sunt configurate și activate.</p>
<p>Echipa {$name}</p>
HTML,
            ],

            // 15. Organizer Event Rejected
            [
                'slug' => 'organizer_event_rejected',
                'name' => 'Eveniment respins',
                'subject' => 'Evenimentul tău "{{event_name}}" necesită modificări',
                'category' => 'transactional',
                'body_html' => <<<HTML
<h2>Eveniment respins</h2>
<p>Salut {{organizer_name}},</p>
<p>Din păcate, evenimentul <strong>{{event_name}}</strong> nu a putut fi aprobat în forma actuală.</p>
<p><strong>Motiv:</strong> {{rejection_reason}}</p>
<p>Te rugăm să faci modificările necesare și să retrimiti evenimentul spre aprobare.</p>
<p>Echipa {$name}</p>
HTML,
            ],

            // 16. Organizer Daily Report
            [
                'slug' => 'organizer_daily_report',
                'name' => 'Raport zilnic organizator',
                'subject' => 'Raport zilnic vânzări — {{period}}',
                'category' => 'notification',
                'body_html' => <<<HTML
<h2>Raport zilnic</h2>
<p>Salut {{organizer_name}},</p>
<p>Iată rezumatul vânzărilor pentru <strong>{{period}}</strong>:</p>

<table style="width:100%;border-collapse:collapse;margin:16px 0;">
<tr style="background:#f3f4f6;">
<td style="padding:8px;font-weight:bold;">Comenzi</td>
<td style="padding:8px;">{{orders_count}}</td>
</tr>
<tr>
<td style="padding:8px;font-weight:bold;">Bilete vândute</td>
<td style="padding:8px;">{{tickets_count}}</td>
</tr>
<tr style="background:#f3f4f6;">
<td style="padding:8px;font-weight:bold;">Vânzări totale</td>
<td style="padding:8px;">{{total_sales}}</td>
</tr>
<tr>
<td style="padding:8px;font-weight:bold;">Comision</td>
<td style="padding:8px;">{{commission}}</td>
</tr>
<tr style="background:#f3f4f6;">
<td style="padding:8px;font-weight:bold;">Sumă netă</td>
<td style="padding:8px;"><strong>{{net_amount}}</strong></td>
</tr>
</table>

<p>Echipa {$name}</p>
HTML,
            ],

            // 17. Organizer Weekly Report
            [
                'slug' => 'organizer_weekly_report',
                'name' => 'Raport săptămânal organizator',
                'subject' => 'Raport săptămânal vânzări — {{period}}',
                'category' => 'notification',
                'body_html' => <<<HTML
<h2>Raport săptămânal</h2>
<p>Salut {{organizer_name}},</p>
<p>Iată rezumatul vânzărilor pentru săptămâna <strong>{{period}}</strong>:</p>

<table style="width:100%;border-collapse:collapse;margin:16px 0;">
<tr style="background:#f3f4f6;">
<td style="padding:8px;font-weight:bold;">Comenzi</td>
<td style="padding:8px;">{{orders_count}}</td>
</tr>
<tr>
<td style="padding:8px;font-weight:bold;">Bilete vândute</td>
<td style="padding:8px;">{{tickets_count}}</td>
</tr>
<tr style="background:#f3f4f6;">
<td style="padding:8px;font-weight:bold;">Vânzări totale</td>
<td style="padding:8px;">{{total_sales}}</td>
</tr>
<tr>
<td style="padding:8px;font-weight:bold;">Comision</td>
<td style="padding:8px;">{{commission}}</td>
</tr>
<tr style="background:#f3f4f6;">
<td style="padding:8px;font-weight:bold;">Sumă netă</td>
<td style="padding:8px;"><strong>{{net_amount}}</strong></td>
</tr>
</table>

<p>Echipa {$name}</p>
HTML,
            ],

            // ============================================================
            // ADMIN NOTIFICATION TEMPLATES
            // ============================================================

            // 18. Admin: Event Cancelled by Organizer
            [
                'slug' => 'admin_event_cancelled',
                'name' => 'Admin: Eveniment anulat de organizator',
                'subject' => '⚠ Eveniment ANULAT: {{event_name}} — {{organizer_name}}',
                'category' => 'notification',
                'body_html' => <<<HTML
<h2 style="color:#dc2626;">Eveniment anulat de organizator</h2>
<p>Organizatorul <strong>{{organizer_name}}</strong> a marcat ca <strong>ANULAT</strong> următorul eveniment:</p>

<table style="width:100%;border-collapse:collapse;margin:16px 0;border:1px solid #fca5a5;">
<tr style="background:#fef2f2;">
<td style="padding:8px;font-weight:bold;">Eveniment</td>
<td style="padding:8px;">{{event_name}}</td>
</tr>
<tr>
<td style="padding:8px;font-weight:bold;">Data</td>
<td style="padding:8px;">{{event_date}}</td>
</tr>
<tr style="background:#fef2f2;">
<td style="padding:8px;font-weight:bold;">Locație</td>
<td style="padding:8px;">{{venue_name}}</td>
</tr>
<tr>
<td style="padding:8px;font-weight:bold;">Organizator</td>
<td style="padding:8px;">{{organizer_name}}</td>
</tr>
</table>

<p><strong>Acțiuni necesare:</strong></p>
<ul>
<li>Verifică dacă există bilete vândute care necesită refund</li>
<li>Contactează organizatorul pentru detalii</li>
<li>Actualizează comunicarea pe site</li>
</ul>

<p><a href="{{admin_url}}" style="display:inline-block;padding:10px 24px;background:#dc2626;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;">Vezi evenimentul</a></p>
HTML,
            ],

            // 19. Admin: Event Postponed by Organizer
            [
                'slug' => 'admin_event_postponed',
                'name' => 'Admin: Eveniment amânat de organizator',
                'subject' => '⚠ Eveniment AMÂNAT: {{event_name}} — {{organizer_name}}',
                'category' => 'notification',
                'body_html' => <<<HTML
<h2 style="color:#d97706;">Eveniment amânat de organizator</h2>
<p>Organizatorul <strong>{{organizer_name}}</strong> a marcat ca <strong>AMÂNAT</strong> următorul eveniment:</p>

<table style="width:100%;border-collapse:collapse;margin:16px 0;border:1px solid #fde68a;">
<tr style="background:#fffbeb;">
<td style="padding:8px;font-weight:bold;">Eveniment</td>
<td style="padding:8px;">{{event_name}}</td>
</tr>
<tr>
<td style="padding:8px;font-weight:bold;">Data inițială</td>
<td style="padding:8px;">{{event_date}}</td>
</tr>
<tr style="background:#fffbeb;">
<td style="padding:8px;font-weight:bold;">Locație</td>
<td style="padding:8px;">{{venue_name}}</td>
</tr>
<tr>
<td style="padding:8px;font-weight:bold;">Organizator</td>
<td style="padding:8px;">{{organizer_name}}</td>
</tr>
</table>

<p><strong>Acțiuni necesare:</strong></p>
<ul>
<li>Solicită organizatorului noua dată</li>
<li>Verifică biletele vândute și comunică clienților</li>
<li>Actualizează informațiile pe site</li>
</ul>

<p><a href="{{admin_url}}" style="display:inline-block;padding:10px 24px;background:#d97706;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;">Vezi evenimentul</a></p>
HTML,
            ],

            // ============================================================
            // STOCK ALERT TEMPLATES
            // ============================================================

            // 20. Low Stock Alert (sent to organizer)
            [
                'slug' => 'stock_low_alert',
                'name' => 'Alertă stoc redus',
                'subject' => '⚠ Stoc redus: {{ticket_type}} — {{event_name}}',
                'category' => 'notification',
                'body_html' => <<<HTML
<h2 style="color:#d97706;">Stoc redus de bilete</h2>
<p>Salut {{organizer_name}},</p>
<p>Tipul de bilet <strong>{{ticket_type}}</strong> pentru evenimentul <strong>{{event_name}}</strong> a ajuns la un stoc de doar <strong>{{remaining_stock}} bilete</strong>.</p>

<div style="padding:12px 16px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;margin:16px 0;">
<strong>Ce poți face:</strong>
<ul style="margin:8px 0 0 0;">
<li>Mărește stocul dacă mai sunt locuri disponibile</li>
<li>Adaugă un nou tip de bilet</li>
<li>Lasă să se vândă și ultimele bilete</li>
</ul>
</div>

<p><a href="{{admin_url}}" style="display:inline-block;padding:10px 24px;background:#d97706;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;">Administrează biletele</a></p>
<p>Echipa {$name}</p>
HTML,
            ],
        ];

        // Add ticket_delivery slug to TEMPLATE_SLUGS if not already there
        // (it's used in the seeder but may not be in the const yet)

        foreach ($templates as $template) {
            MarketplaceEmailTemplate::updateOrCreate(
                [
                    'marketplace_client_id' => $marketplace->id,
                    'slug' => $template['slug'],
                ],
                [
                    'name' => $template['name'],
                    'subject' => $template['subject'],
                    'body_html' => $template['body_html'],
                    'category' => $template['category'] ?? 'transactional',
                    'is_active' => true,
                    'is_default' => true,
                ]
            );
        }
    }
}
