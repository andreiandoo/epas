<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add refund_processed template for each marketplace client
        $clients = DB::table('marketplace_clients')->pluck('id');

        foreach ($clients as $clientId) {
            // Skip if already exists
            $exists = DB::table('marketplace_email_templates')
                ->where('marketplace_client_id', $clientId)
                ->where('slug', 'refund_processed')
                ->exists();

            if ($exists) continue;

            DB::table('marketplace_email_templates')->insert([
                'marketplace_client_id' => $clientId,
                'slug' => 'refund_processed',
                'name' => 'Rambursare procesată',
                'category' => 'transactional',
                'subject' => 'Rambursare procesată — Comanda #{{order_number}}',
                'body_html' => '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#1a1a2e;">Rambursare procesată</h2>
<p>Bună {{customer_name}},</p>
<p>Îți confirmăm că rambursarea pentru comanda <strong>#{{order_number}}</strong> a fost procesată cu succes.</p>
<table style="width:100%;border-collapse:collapse;margin:16px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">Referință rambursare</td><td style="padding:8px;border-bottom:1px solid #eee;font-weight:600;">{{refund_reference}}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">Sumă rambursată</td><td style="padding:8px;border-bottom:1px solid #eee;font-weight:600;color:#10B981;">{{refund_amount}}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">Motiv</td><td style="padding:8px;border-bottom:1px solid #eee;">{{refund_reason}}</td></tr>
</table>
<p style="font-size:14px;color:#666;">Bilete rambursate:</p>
<pre style="background:#f5f5f5;padding:12px;border-radius:6px;font-size:13px;">{{refunded_tickets}}</pre>
<p style="font-size:14px;color:#666;margin-top:16px;">Suma va fi returnată în contul tău în 5-10 zile lucrătoare, în funcție de banca emitentă a cardului.</p>
<p style="font-size:14px;color:#666;">Pentru întrebări, contactează-ne la <a href="mailto:{{marketplace_email}}">{{marketplace_email}}</a>.</p>
<p style="margin-top:24px;">Cu respect,<br><strong>{{marketplace_name}}</strong></p>
</div>',
                'is_active' => true,
                'notify_organizer' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('marketplace_email_templates')->where('slug', 'refund_processed')->delete();
    }
};
