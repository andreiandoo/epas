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
        // Strip protocol and trailing slash from domain to get bare hostname
        $rawDomain = $marketplace->domain ?? 'ambilet.ro';
        $domain = preg_replace('#^https?://#', '', rtrim($rawDomain, '/'));
        $contactEmail = $marketplace->contact_email ?? "contact@{$domain}";
        $logoUrl = "https://{$domain}/assets/images/ambilet_logo.webp";
        $primaryColor = '#A51C30';
        $primaryDark = '#8B1728';

        $templates = [
            // ============================================================
            // CUSTOMER TRANSACTIONAL TEMPLATES
            // ============================================================
            [
                'slug' => 'ticket_purchase',
                'name' => 'Confirmare comandă',
                'subject' => 'Confirmare comandă #{{order_number}} — {{event_name}}',
                'category' => 'transactional',
                'body_html' => $this->wrap($name, $domain, $logoUrl, $primaryColor, $primaryDark, false, <<<CONTENT
<h2 style="color:#1a1a1a;font-size:22px;margin:0 0 16px;">Mulțumim pentru comanda ta!</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 12px;">Salut <strong>{{customer_name}}</strong>,</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;">Comanda ta <strong>#{{order_number}}</strong> a fost procesată cu succes. Mai jos găsești detaliile evenimentului și biletele tale.</p>

<table style="width:100%;border-collapse:collapse;margin:0 0 24px;border-radius:8px;overflow:hidden;">
<tr style="background:{$primaryColor};color:#fff;">
<td colspan="2" style="padding:12px 16px;font-weight:bold;font-size:14px;">Detalii eveniment</td>
</tr>
<tr style="background:#f9fafb;">
<td style="padding:10px 16px;font-size:14px;color:#6b7280;width:140px;">Eveniment</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;font-weight:600;">{{event_name}}</td>
</tr>
<tr>
<td style="padding:10px 16px;font-size:14px;color:#6b7280;border-top:1px solid #f3f4f6;">Data</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;border-top:1px solid #f3f4f6;">{{event_date}}</td>
</tr>
<tr style="background:#f9fafb;">
<td style="padding:10px 16px;font-size:14px;color:#6b7280;">Locație</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;">{{venue_location}}</td>
</tr>
<tr>
<td style="padding:10px 16px;font-size:14px;color:#6b7280;border-top:1px solid #f3f4f6;">Bilete</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;border-top:1px solid #f3f4f6;">{{ticket_count}} bilet(e)</td>
</tr>
<tr style="background:#f9fafb;">
<td style="padding:10px 16px;font-size:14px;color:#6b7280;">Total achitat</td>
<td style="padding:10px 16px;font-size:16px;color:{$primaryColor};font-weight:bold;">{{total_amount}}</td>
</tr>
</table>

<!-- Bilete cu coduri QR -->
{{tickets_list}}

<!-- Buton descărcare bilete -->
<p style="text-align:center;margin:24px 0;">
<a href="{{download_url}}" style="display:inline-block;padding:14px 32px;background:{$primaryColor};color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;font-size:16px;">Descarcă biletele</a>
</p>

<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 8px;">Prezintă codul QR de pe bilet la intrarea în eveniment.</p>
<p style="color:#6b7280;font-size:13px;line-height:1.5;margin:0 0 20px;">Dacă ai întrebări despre comandă sau eveniment, nu ezita să ne contactezi la <a href="mailto:{$contactEmail}" style="color:{$primaryColor};">{$contactEmail}</a>.</p>
CONTENT),
            ],

            [
                'slug' => 'order_confirmation',
                'name' => 'Confirmare comandă (generic)',
                'subject' => 'Comanda ta #{{order_number}} a fost confirmată',
                'category' => 'transactional',
                'body_html' => $this->wrap($name, $domain, $logoUrl, $primaryColor, $primaryDark, false, <<<CONTENT
<h2 style="color:#1a1a1a;font-size:22px;margin:0 0 16px;">Comandă confirmată</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 12px;">Salut <strong>{{customer_name}}</strong>,</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 12px;">Comanda <strong>#{{order_number}}</strong> pentru <strong>{{event_name}}</strong> a fost procesată cu succes.</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;"><strong>Total achitat:</strong> {{total_amount}}</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0;">Biletele vor fi livrate pe email în câteva momente.</p>
CONTENT),
            ],

            [
                'slug' => 'welcome',
                'name' => 'Înregistrare cont',
                'subject' => "Bine ai venit pe {$name}!",
                'category' => 'transactional',
                'body_html' => $this->wrap($name, $domain, $logoUrl, $primaryColor, $primaryDark, false, <<<CONTENT
<h2 style="color:#1a1a1a;font-size:22px;margin:0 0 16px;">Bine ai venit!</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 12px;">Salut <strong>{{customer_name}}</strong>,</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;">Contul tău pe <strong>{$name}</strong> a fost creat cu succes. De acum poți descoperi și cumpăra bilete la cele mai tari evenimente!</p>

<table style="width:100%;margin:0 0 24px;"><tr>
<td style="padding:8px 0;"><span style="display:inline-block;width:24px;text-align:center;margin-right:8px;">🎫</span><span style="color:#4a4a4a;font-size:14px;">Cumpără bilete rapid și simplu</span></td>
</tr><tr>
<td style="padding:8px 0;"><span style="display:inline-block;width:24px;text-align:center;margin-right:8px;">📋</span><span style="color:#4a4a4a;font-size:14px;">Consultă istoricul comenzilor tale</span></td>
</tr><tr>
<td style="padding:8px 0;"><span style="display:inline-block;width:24px;text-align:center;margin-right:8px;">🔔</span><span style="color:#4a4a4a;font-size:14px;">Primește notificări despre evenimentele preferate</span></td>
</tr></table>

<p style="text-align:center;margin:0 0 20px;">
<a href="{{login_url}}" style="display:inline-block;padding:12px 32px;background:{$primaryColor};color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;font-size:15px;">Accesează contul tău</a>
</p>
CONTENT),
            ],

            [
                'slug' => 'password_reset',
                'name' => 'Resetare parolă',
                'subject' => "Resetare parolă — {$name}",
                'category' => 'transactional',
                'body_html' => $this->wrap($name, $domain, $logoUrl, $primaryColor, $primaryDark, false, <<<CONTENT
<h2 style="color:#1a1a1a;font-size:22px;margin:0 0 16px;">Resetare parolă</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 12px;">Salut <strong>{{customer_name}}</strong>,</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;">Am primit o cerere de resetare a parolei pentru contul tău pe <strong>{$name}</strong>. Apasă butonul de mai jos pentru a seta o parolă nouă:</p>

<p style="text-align:center;margin:0 0 24px;">
<a href="{{reset_url}}" style="display:inline-block;padding:12px 32px;background:{$primaryColor};color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;font-size:15px;">Resetează parola</a>
</p>

<p style="color:#6b7280;font-size:13px;line-height:1.5;margin:0 0 8px;">Link-ul este valabil 60 de minute.</p>
<p style="color:#6b7280;font-size:13px;line-height:1.5;margin:0;">Dacă nu ai solicitat resetarea parolei, poți ignora acest email în siguranță.</p>
CONTENT),
            ],

            [
                'slug' => 'ticket_delivery',
                'name' => 'Livrare bilete către beneficiar',
                'subject' => 'Biletul tău pentru {{event_name}}',
                'category' => 'transactional',
                'body_html' => $this->wrap($name, $domain, $logoUrl, $primaryColor, $primaryDark, false, <<<CONTENT
<h2 style="color:#1a1a1a;font-size:22px;margin:0 0 16px;">Ai primit un bilet! 🎉</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 12px;">Salut <strong>{{beneficiary_name}}</strong>,</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;"><strong>{{customer_name}}</strong> ți-a cumpărat un bilet pentru următorul eveniment:</p>

<table style="width:100%;border-collapse:collapse;margin:0 0 24px;border-radius:8px;overflow:hidden;">
<tr style="background:{$primaryColor};color:#fff;">
<td colspan="2" style="padding:12px 16px;font-weight:bold;font-size:14px;">Detalii bilet</td>
</tr>
<tr style="background:#f9fafb;">
<td style="padding:10px 16px;font-size:14px;color:#6b7280;width:120px;">Eveniment</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;font-weight:600;">{{event_name}}</td>
</tr>
<tr>
<td style="padding:10px 16px;font-size:14px;color:#6b7280;border-top:1px solid #f3f4f6;">Data</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;border-top:1px solid #f3f4f6;">{{event_date}}</td>
</tr>
<tr style="background:#f9fafb;">
<td style="padding:10px 16px;font-size:14px;color:#6b7280;">Locație</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;">{{venue_name}}</td>
</tr>
<tr>
<td style="padding:10px 16px;font-size:14px;color:#6b7280;border-top:1px solid #f3f4f6;">Tip bilet</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;border-top:1px solid #f3f4f6;">{{ticket_type}}</td>
</tr>
</table>

<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0;">Prezintă codul QR atașat la intrarea în eveniment. Ne vedem acolo!</p>
CONTENT),
            ],

            [
                'slug' => 'event_reminder',
                'name' => 'Reminder eveniment',
                'subject' => 'Mâine: {{event_name}} 🎶',
                'category' => 'notification',
                'body_html' => $this->wrap($name, $domain, $logoUrl, $primaryColor, $primaryDark, true, <<<CONTENT
<h2 style="color:#1a1a1a;font-size:22px;margin:0 0 16px;">Nu uita de eveniment! 🎉</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 12px;">Salut <strong>{{customer_name}}</strong>,</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;">Evenimentul <strong>{{event_name}}</strong> are loc mâine! Pregătește-ți biletele și ne vedem acolo.</p>

<table style="width:100%;border-collapse:collapse;margin:0 0 24px;border:1px solid #e5e7eb;border-radius:8px;">
<tr style="background:#f9fafb;">
<td style="padding:10px 16px;font-size:14px;color:#6b7280;width:100px;">📅 Data</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;">{{event_date}}</td>
</tr>
<tr>
<td style="padding:10px 16px;font-size:14px;color:#6b7280;border-top:1px solid #f3f4f6;">📍 Locație</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;border-top:1px solid #f3f4f6;">{{venue_name}}, {{venue_address}}</td>
</tr>
</table>
CONTENT),
            ],

            [
                'slug' => 'event_cancelled',
                'name' => 'Eveniment anulat',
                'subject' => 'Evenimentul {{event_name}} a fost anulat',
                'category' => 'transactional',
                'body_html' => $this->wrap($name, $domain, $logoUrl, $primaryColor, $primaryDark, false, <<<CONTENT
<h2 style="color:#dc2626;font-size:22px;margin:0 0 16px;">Eveniment anulat</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 12px;">Salut <strong>{{customer_name}}</strong>,</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;">Din păcate, evenimentul <strong>{{event_name}}</strong> programat pe <strong>{{event_date}}</strong> la <strong>{{venue_name}}</strong> a fost anulat.</p>

<div style="padding:16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;margin:0 0 20px;">
<p style="color:#991b1b;font-size:14px;margin:0 0 8px;font-weight:bold;">Ce se întâmplă cu biletele?</p>
<p style="color:#991b1b;font-size:14px;margin:0;">Vei primi un refund automat în contul din care ai efectuat plata. Procesarea poate dura 5-10 zile lucrătoare.</p>
</div>

<p style="color:#6b7280;font-size:13px;margin:0;">Ne cerem scuze pentru inconvenient. Pentru întrebări, contactează-ne la <a href="mailto:{$contactEmail}" style="color:{$primaryColor};">{$contactEmail}</a>.</p>
CONTENT),
            ],

            [
                'slug' => 'event_updated',
                'name' => 'Eveniment actualizat',
                'subject' => 'Actualizare: {{event_name}}',
                'category' => 'notification',
                'body_html' => $this->wrap($name, $domain, $logoUrl, $primaryColor, $primaryDark, true, <<<CONTENT
<h2 style="color:#1a1a1a;font-size:22px;margin:0 0 16px;">Actualizare eveniment</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 12px;">Salut <strong>{{customer_name}}</strong>,</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;">Au apărut modificări pentru evenimentul <strong>{{event_name}}</strong>. Verifică detaliile actualizate:</p>

<table style="width:100%;border-collapse:collapse;margin:0 0 24px;border:1px solid #e5e7eb;border-radius:8px;">
<tr style="background:#f9fafb;">
<td style="padding:10px 16px;font-size:14px;color:#6b7280;width:100px;">📅 Data</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;">{{event_date}}</td>
</tr>
<tr>
<td style="padding:10px 16px;font-size:14px;color:#6b7280;border-top:1px solid #f3f4f6;">📍 Locație</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;border-top:1px solid #f3f4f6;">{{venue_name}}, {{venue_address}}</td>
</tr>
</table>
<p style="color:#4a4a4a;font-size:14px;margin:0;">Biletele tale rămân valabile.</p>
CONTENT),
            ],

            [
                'slug' => 'refund_approved',
                'name' => 'Refund aprobat',
                'subject' => 'Refund aprobat — Comanda #{{order_number}}',
                'category' => 'transactional',
                'body_html' => $this->wrap($name, $domain, $logoUrl, $primaryColor, $primaryDark, false, <<<CONTENT
<h2 style="color:#059669;font-size:22px;margin:0 0 16px;">Refund aprobat ✓</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 12px;">Salut <strong>{{customer_name}}</strong>,</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;">Cererea ta de refund pentru comanda <strong>#{{order_number}}</strong> a fost aprobată.</p>

<div style="padding:16px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;margin:0 0 20px;text-align:center;">
<p style="color:#065f46;font-size:14px;margin:0 0 4px;">Sumă returnată</p>
<p style="color:#065f46;font-size:24px;font-weight:bold;margin:0;">{{refund_amount}}</p>
</div>

<p style="color:#4a4a4a;font-size:14px;line-height:1.6;margin:0;">Banii vor fi returnați în contul din care ai efectuat plata. Procesarea poate dura 5-10 zile lucrătoare.</p>
CONTENT),
            ],

            [
                'slug' => 'refund_rejected',
                'name' => 'Refund respins',
                'subject' => 'Actualizare cerere refund — Comanda #{{order_number}}',
                'category' => 'transactional',
                'body_html' => $this->wrap($name, $domain, $logoUrl, $primaryColor, $primaryDark, false, <<<CONTENT
<h2 style="color:#1a1a1a;font-size:22px;margin:0 0 16px;">Cerere de refund respinsă</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 12px;">Salut <strong>{{customer_name}}</strong>,</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;">Din păcate, cererea ta de refund pentru comanda <strong>#{{order_number}}</strong> nu a putut fi aprobată.</p>

<div style="padding:16px;background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;margin:0 0 20px;">
<p style="color:#92400e;font-size:14px;margin:0 0 4px;font-weight:bold;">Motiv:</p>
<p style="color:#92400e;font-size:14px;margin:0;">{{rejection_reason}}</p>
</div>

<p style="color:#6b7280;font-size:13px;margin:0;">Dacă ai întrebări, contactează-ne la <a href="mailto:{$contactEmail}" style="color:{$primaryColor};">{$contactEmail}</a>.</p>
CONTENT),
            ],

            [
                'slug' => 'invitation',
                'name' => 'Invitație eveniment',
                'subject' => 'Invitație: {{event_name}} 🎟️',
                'category' => 'transactional',
                'body_html' => $this->wrap($name, $domain, $logoUrl, $primaryColor, $primaryDark, false, <<<CONTENT
<h2 style="color:#1a1a1a;font-size:22px;margin:0 0 16px;">Ai primit o invitație! 🎟️</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 12px;">Salut <strong>{{customer_name}}</strong>,</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;">Ești invitat(ă) la evenimentul <strong>{{event_name}}</strong>!</p>

<table style="width:100%;border-collapse:collapse;margin:0 0 24px;border:1px solid #e5e7eb;border-radius:8px;">
<tr style="background:#f9fafb;">
<td style="padding:10px 16px;font-size:14px;color:#6b7280;width:100px;">📅 Data</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;">{{event_date}}</td>
</tr>
<tr>
<td style="padding:10px 16px;font-size:14px;color:#6b7280;border-top:1px solid #f3f4f6;">📍 Locație</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;border-top:1px solid #f3f4f6;">{{venue_name}}</td>
</tr>
</table>
<p style="color:#4a4a4a;font-size:15px;margin:0;">Prezintă acest email sau codul QR atașat la intrarea în eveniment.</p>
CONTENT),
            ],

            [
                'slug' => 'ticket_cancelled',
                'name' => 'Bilet anulat',
                'subject' => 'Biletul tău pentru {{event_name}} a fost anulat',
                'category' => 'transactional',
                'body_html' => $this->wrap($name, $domain, $logoUrl, $primaryColor, $primaryDark, false, <<<CONTENT
<h2 style="color:#1a1a1a;font-size:22px;margin:0 0 16px;">Bilet anulat</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 12px;">Salut <strong>{{customer_name}}</strong>,</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;">Biletul tău pentru evenimentul <strong>{{event_name}}</strong> din data de <strong>{{event_date}}</strong> a fost anulat.</p>
<p style="color:#4a4a4a;font-size:14px;margin:0;">Dacă ai dreptul la un refund, acesta va fi procesat automat.</p>
CONTENT),
            ],

            // ============================================================
            // ORGANIZER TEMPLATES
            // ============================================================
            [
                'slug' => 'organizer_payout',
                'name' => 'Notificare plată organizator',
                'subject' => 'Plată procesată — {{payout_reference}}',
                'category' => 'transactional',
                'body_html' => $this->wrap($name, $domain, $logoUrl, $primaryColor, $primaryDark, false, <<<CONTENT
<h2 style="color:#059669;font-size:22px;margin:0 0 16px;">Plată procesată ✓</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;">Salut <strong>{{organizer_name}}</strong>,</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;">Plata pentru perioada <strong>{{period}}</strong> a fost procesată cu succes.</p>

<table style="width:100%;border-collapse:collapse;margin:0 0 24px;border:1px solid #e5e7eb;border-radius:8px;">
<tr style="background:#f9fafb;">
<td style="padding:10px 16px;font-size:14px;color:#6b7280;width:120px;">Referință</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;">{{payout_reference}}</td>
</tr>
<tr>
<td style="padding:10px 16px;font-size:14px;color:#6b7280;border-top:1px solid #f3f4f6;">Sumă</td>
<td style="padding:10px 16px;font-size:18px;color:#059669;font-weight:bold;border-top:1px solid #f3f4f6;">{{payout_amount}}</td>
</tr>
</table>
<p style="color:#6b7280;font-size:13px;margin:0;">Banii vor fi virați în contul bancar asociat în 1-3 zile lucrătoare.</p>
CONTENT),
            ],

            [
                'slug' => 'organizer_event_approved',
                'name' => 'Eveniment aprobat',
                'subject' => 'Evenimentul tău "{{event_name}}" a fost aprobat ✓',
                'category' => 'transactional',
                'body_html' => $this->wrap($name, $domain, $logoUrl, $primaryColor, $primaryDark, false, <<<CONTENT
<h2 style="color:#059669;font-size:22px;margin:0 0 16px;">Eveniment aprobat! ✓</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 12px;">Salut <strong>{{organizer_name}}</strong>,</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 12px;">Evenimentul tău <strong>{{event_name}}</strong> din data de <strong>{{event_date}}</strong> a fost aprobat și este acum vizibil pe platformă.</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0;">Biletele pot fi achiziționate de către public de îndată ce sunt configurate și activate.</p>
CONTENT),
            ],

            [
                'slug' => 'organizer_event_rejected',
                'name' => 'Eveniment respins',
                'subject' => 'Evenimentul tău "{{event_name}}" necesită modificări',
                'category' => 'transactional',
                'body_html' => $this->wrap($name, $domain, $logoUrl, $primaryColor, $primaryDark, false, <<<CONTENT
<h2 style="color:#dc2626;font-size:22px;margin:0 0 16px;">Eveniment respins</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 12px;">Salut <strong>{{organizer_name}}</strong>,</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;">Din păcate, evenimentul <strong>{{event_name}}</strong> nu a putut fi aprobat în forma actuală.</p>
<div style="padding:16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;margin:0 0 20px;">
<p style="color:#991b1b;font-size:14px;margin:0 0 4px;font-weight:bold;">Motiv:</p>
<p style="color:#991b1b;font-size:14px;margin:0;">{{rejection_reason}}</p>
</div>
<p style="color:#4a4a4a;font-size:14px;margin:0;">Te rugăm să faci modificările necesare și să retrimiti evenimentul spre aprobare.</p>
CONTENT),
            ],

            [
                'slug' => 'organizer_daily_report',
                'name' => 'Raport zilnic organizator',
                'subject' => 'Raport zilnic vânzări — {{period}}',
                'category' => 'notification',
                'body_html' => $this->wrap($name, $domain, $logoUrl, $primaryColor, $primaryDark, true, $this->reportContent('zilnic', $primaryColor)),
            ],

            [
                'slug' => 'organizer_weekly_report',
                'name' => 'Raport săptămânal organizator',
                'subject' => 'Raport săptămânal vânzări — {{period}}',
                'category' => 'notification',
                'body_html' => $this->wrap($name, $domain, $logoUrl, $primaryColor, $primaryDark, true, $this->reportContent('săptămânal', $primaryColor)),
            ],

            // ============================================================
            // ADMIN NOTIFICATION TEMPLATES
            // ============================================================
            [
                'slug' => 'admin_event_cancelled',
                'name' => 'Admin: Eveniment anulat de organizator',
                'subject' => '⚠ Eveniment ANULAT: {{event_name}} — {{organizer_name}}',
                'category' => 'notification',
                'body_html' => $this->wrap($name, $domain, $logoUrl, $primaryColor, $primaryDark, false, <<<CONTENT
<h2 style="color:#dc2626;font-size:22px;margin:0 0 16px;">⚠ Eveniment anulat de organizator</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;">Organizatorul <strong>{{organizer_name}}</strong> a marcat ca <strong style="color:#dc2626;">ANULAT</strong> următorul eveniment:</p>

<table style="width:100%;border-collapse:collapse;margin:0 0 24px;border:2px solid #fca5a5;border-radius:8px;">
<tr style="background:#fef2f2;">
<td style="padding:10px 16px;font-size:14px;color:#6b7280;width:120px;">Eveniment</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;font-weight:600;">{{event_name}}</td>
</tr>
<tr><td style="padding:10px 16px;font-size:14px;color:#6b7280;border-top:1px solid #fecaca;">Data</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;border-top:1px solid #fecaca;">{{event_date}}</td></tr>
<tr style="background:#fef2f2;">
<td style="padding:10px 16px;font-size:14px;color:#6b7280;">Locație</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;">{{venue_name}}</td>
</tr>
</table>

<p style="color:#4a4a4a;font-size:14px;font-weight:bold;margin:0 0 8px;">Acțiuni necesare:</p>
<ul style="color:#4a4a4a;font-size:14px;line-height:1.8;margin:0 0 20px;padding-left:20px;">
<li>Verifică dacă există bilete vândute care necesită refund</li>
<li>Contactează organizatorul pentru detalii</li>
<li>Actualizează comunicarea pe site</li>
</ul>
<p style="text-align:center;"><a href="{{admin_url}}" style="display:inline-block;padding:10px 24px;background:#dc2626;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">Vezi evenimentul</a></p>
CONTENT),
            ],

            [
                'slug' => 'admin_event_postponed',
                'name' => 'Admin: Eveniment amânat de organizator',
                'subject' => '⚠ Eveniment AMÂNAT: {{event_name}} — {{organizer_name}}',
                'category' => 'notification',
                'body_html' => $this->wrap($name, $domain, $logoUrl, $primaryColor, $primaryDark, false, <<<CONTENT
<h2 style="color:#d97706;font-size:22px;margin:0 0 16px;">⚠ Eveniment amânat de organizator</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;">Organizatorul <strong>{{organizer_name}}</strong> a marcat ca <strong style="color:#d97706;">AMÂNAT</strong> următorul eveniment:</p>

<table style="width:100%;border-collapse:collapse;margin:0 0 24px;border:2px solid #fde68a;border-radius:8px;">
<tr style="background:#fffbeb;">
<td style="padding:10px 16px;font-size:14px;color:#6b7280;width:120px;">Eveniment</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;font-weight:600;">{{event_name}}</td>
</tr>
<tr><td style="padding:10px 16px;font-size:14px;color:#6b7280;border-top:1px solid #fde68a;">Data inițială</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;border-top:1px solid #fde68a;">{{event_date}}</td></tr>
<tr style="background:#fffbeb;">
<td style="padding:10px 16px;font-size:14px;color:#6b7280;">Locație</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;">{{venue_name}}</td>
</tr>
</table>

<p style="color:#4a4a4a;font-size:14px;font-weight:bold;margin:0 0 8px;">Acțiuni necesare:</p>
<ul style="color:#4a4a4a;font-size:14px;line-height:1.8;margin:0 0 20px;padding-left:20px;">
<li>Solicită organizatorului noua dată</li>
<li>Verifică biletele vândute și comunică clienților</li>
<li>Actualizează informațiile pe site</li>
</ul>
<p style="text-align:center;"><a href="{{admin_url}}" style="display:inline-block;padding:10px 24px;background:#d97706;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">Vezi evenimentul</a></p>
CONTENT),
            ],

            // ============================================================
            // STOCK ALERT
            // ============================================================
            [
                'slug' => 'stock_low_alert',
                'name' => 'Alertă stoc redus',
                'subject' => '⚠ Stoc redus: {{ticket_type}} — {{event_name}} ({{event_date}}, {{venue_name}}, {{venue_city}})',
                'category' => 'notification',
                'body_html' => $this->wrap($name, $domain, $logoUrl, $primaryColor, $primaryDark, false, <<<CONTENT
<h2 style="color:#d97706;font-size:22px;margin:0 0 16px;">⚠ Stoc redus de bilete</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 12px;">Salut <strong>{{organizer_name}}</strong>,</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;">Tipul de bilet <strong>{{ticket_type}}</strong> pentru evenimentul <strong>{{event_name}}</strong> a ajuns la un stoc de doar:</p>

<div style="text-align:center;margin:0 0 24px;">
<span style="display:inline-block;padding:16px 32px;background:#fffbeb;border:2px solid #fde68a;border-radius:12px;font-size:28px;font-weight:bold;color:#92400e;">{{remaining_stock}} bilete rămase</span>
</div>

<p style="color:#4a4a4a;font-size:14px;font-weight:bold;margin:0 0 8px;">Ce poți face:</p>
<ul style="color:#4a4a4a;font-size:14px;line-height:1.8;margin:0 0 20px;padding-left:20px;">
<li>Mărește stocul dacă mai sunt locuri disponibile</li>
<li>Adaugă un nou tip de bilet</li>
<li>Lasă să se vândă și ultimele bilete</li>
</ul>
<p style="text-align:center;"><a href="{{admin_url}}" style="display:inline-block;padding:10px 24px;background:#d97706;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">Administrează biletele</a></p>
CONTENT),
            ],
        ];

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

    /**
     * Wrap content in a branded email template
     */
    protected function wrap(string $name, string $domain, string $logoUrl, string $primary, string $primaryDark, bool $showUnsubscribe, string $content): string
    {
        $unsubscribeBlock = $showUnsubscribe ? <<<UNSUB
<p style="margin:8px 0 0;font-size:12px;">
<a href="https://{$domain}/newsletter-unsubscribe?email={{customer_email}}" style="color:#9ca3af;text-decoration:underline;">Dezabonare</a> de la notificările prin email
</p>
UNSUB : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="ro">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
<table style="width:100%;background:#f3f4f6;padding:32px 16px;" cellpadding="0" cellspacing="0">
<tr><td align="center">
<table style="width:100%;max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1);" cellpadding="0" cellspacing="0">

<!-- HEADER -->
<tr>
<td style="background:linear-gradient(135deg,{$primary},{$primaryDark});padding:24px 32px;text-align:center;">
<img src="{$logoUrl}" alt="{$name}" style="height:36px;width:auto;" />
</td>
</tr>

<!-- CONTENT -->
<tr>
<td style="padding:32px;">
{$content}
</td>
</tr>

<!-- FOOTER -->
<tr>
<td style="background:#f9fafb;padding:24px 32px;border-top:1px solid #e5e7eb;">
<p style="margin:0;font-size:13px;color:#9ca3af;text-align:center;">
© {$name} · <a href="https://{$domain}" style="color:#9ca3af;text-decoration:none;">{$domain}</a>
</p>
{$unsubscribeBlock}
</td>
</tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }

    /**
     * Generate report content (daily/weekly)
     */
    protected function reportContent(string $period, string $primary): string
    {
        return <<<CONTENT
<h2 style="color:#1a1a1a;font-size:22px;margin:0 0 16px;">Raport {$period} 📊</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 12px;">Salut <strong>{{organizer_name}}</strong>,</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;">Iată rezumatul vânzărilor pentru perioada <strong>{{period}}</strong>:</p>

<table style="width:100%;border-collapse:collapse;margin:0 0 24px;border-radius:8px;overflow:hidden;">
<tr style="background:{$primary};color:#fff;">
<td colspan="2" style="padding:12px 16px;font-weight:bold;font-size:14px;">Sumar vânzări</td>
</tr>
<tr style="background:#f9fafb;">
<td style="padding:10px 16px;font-size:14px;color:#6b7280;width:160px;">Comenzi</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;font-weight:600;">{{orders_count}}</td>
</tr>
<tr>
<td style="padding:10px 16px;font-size:14px;color:#6b7280;border-top:1px solid #f3f4f6;">Bilete vândute</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;border-top:1px solid #f3f4f6;">{{tickets_count}}</td>
</tr>
<tr style="background:#f9fafb;">
<td style="padding:10px 16px;font-size:14px;color:#6b7280;">Vânzări totale</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;">{{total_sales}}</td>
</tr>
<tr>
<td style="padding:10px 16px;font-size:14px;color:#6b7280;border-top:1px solid #f3f4f6;">Comision</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;border-top:1px solid #f3f4f6;">{{commission}}</td>
</tr>
<tr style="background:#ecfdf5;">
<td style="padding:12px 16px;font-size:14px;color:#065f46;font-weight:bold;">Sumă netă</td>
<td style="padding:12px 16px;font-size:18px;color:#059669;font-weight:bold;">{{net_amount}}</td>
</tr>
</table>
CONTENT;
    }
}
