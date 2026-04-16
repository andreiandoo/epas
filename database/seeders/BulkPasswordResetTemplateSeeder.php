<?php

namespace Database\Seeders;

use App\Models\MarketplaceEmailTemplate;
use Illuminate\Database\Seeder;

class BulkPasswordResetTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $clientId = 1; // AmBilet

        // Customer template
        MarketplaceEmailTemplate::updateOrCreate(
            ['marketplace_client_id' => $clientId, 'slug' => 'bulk_password_reset_customer'],
            [
                'name' => 'Resetare parolă — Migrare cont',
                'subject' => 'Activează-ți contul pe noul AmBilet',
                'body_html' => $this->customerHtml(),
                'body_text' => '',
                'variables' => json_encode(['first_name', 'email', 'reset_link', 'site_name', 'expire_days']),
                'category' => 'transactional',
                'is_active' => true,
                'is_default' => false,
            ]
        );

        // Organizer template
        MarketplaceEmailTemplate::updateOrCreate(
            ['marketplace_client_id' => $clientId, 'slug' => 'bulk_password_reset_organizer'],
            [
                'name' => 'Resetare parolă organizator — Migrare cont',
                'subject' => 'Activează-ți contul de organizator pe noul AmBilet',
                'body_html' => $this->organizerHtml(),
                'body_text' => '',
                'variables' => json_encode(['first_name', 'email', 'reset_link', 'site_name', 'expire_days']),
                'category' => 'transactional',
                'is_active' => true,
                'is_default' => false,
            ]
        );

        $this->command->info('Bulk password reset templates created/updated.');
    }

    private function customerHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f8fafc">
<div style="max-width:600px;margin:0 auto;padding:40px 20px">
<div style="background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">
<div style="background:linear-gradient(135deg,#A51C30 0%,#8B1728 100%);padding:32px;text-align:center">
<h1 style="color:white;margin:0;font-size:24px">{{site_name}}</h1>
<p style="color:rgba(255,255,255,0.8);margin:8px 0 0;font-size:14px">Contul tău te așteaptă</p>
</div>
<div style="padding:32px">
<p style="font-size:16px;color:#1e293b;margin:0 0 16px">Salut {{first_name}},</p>
<p style="font-size:15px;color:#475569;margin:0 0 16px">Am migrat platforma AmBilet pe un sistem nou, mai rapid și mai sigur. Contul tău cu adresa <strong>{{email}}</strong> a fost transferat cu succes.</p>
<p style="font-size:15px;color:#475569;margin:0 0 16px">Pentru a-ți accesa contul pe noua platformă, trebuie să îți setezi o parolă nouă. Apasă butonul de mai jos:</p>
<div style="text-align:center;margin:24px 0">
<a href="{{reset_link}}" style="display:inline-block;background:#A51C30;color:white;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:16px">Setează parola nouă</a>
</div>
<p style="font-size:13px;color:#94a3b8;margin:16px 0 0;text-align:center">Linkul este valabil {{expire_days}} zile.</p>
<p style="font-size:13px;color:#94a3b8;margin:8px 0 0;text-align:center">Dacă nu ai un cont AmBilet, poți ignora acest email.</p>
</div>
<div style="padding:16px 32px;background:#f8fafc;text-align:center;border-top:1px solid #e2e8f0">
<p style="font-size:13px;color:#94a3b8;margin:0">Echipa {{site_name}}</p>
</div>
</div></div></body></html>
HTML;
    }

    private function organizerHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f8fafc">
<div style="max-width:600px;margin:0 auto;padding:40px 20px">
<div style="background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">
<div style="background:linear-gradient(135deg,#1E293B 0%,#0F172A 100%);padding:32px;text-align:center">
<h1 style="color:white;margin:0;font-size:24px">{{site_name}} — Organizatori</h1>
<p style="color:rgba(255,255,255,0.8);margin:8px 0 0;font-size:14px">Contul tău de organizator te așteaptă</p>
</div>
<div style="padding:32px">
<p style="font-size:16px;color:#1e293b;margin:0 0 16px">Salut {{first_name}},</p>
<p style="font-size:15px;color:#475569;margin:0 0 16px">Am migrat platforma AmBilet pe un sistem nou cu dashboard complet pentru organizatori: statistici vânzări, export participanți, management evenimente și multe altele.</p>
<p style="font-size:15px;color:#475569;margin:0 0 16px">Contul tău de organizator (<strong>{{email}}</strong>) a fost transferat. Pentru a accesa noul dashboard, setează-ți o parolă nouă:</p>
<div style="text-align:center;margin:24px 0">
<a href="{{reset_link}}" style="display:inline-block;background:#1E293B;color:white;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:16px">Setează parola nouă</a>
</div>
<p style="font-size:13px;color:#94a3b8;margin:16px 0 0;text-align:center">Linkul este valabil {{expire_days}} zile.</p>
</div>
<div style="padding:16px 32px;background:#f8fafc;text-align:center;border-top:1px solid #e2e8f0">
<p style="font-size:13px;color:#94a3b8;margin:0">Echipa {{site_name}}</p>
</div>
</div></div></body></html>
HTML;
    }
}
