<?php

namespace Database\Seeders;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceEmailTemplate;
use Database\Seeders\Concerns\BrandedEmailWrapper;
use Illuminate\Database\Seeder;

/**
 * Seeds the flexible-payment email templates for every marketplace, branded
 * and idempotent. FlexiblePaymentMailer looks these up by slug per marketplace.
 *
 * Run: php artisan db:seed --class=InstallmentEmailTemplatesSeeder
 */
class InstallmentEmailTemplatesSeeder extends Seeder
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

        foreach ($this->templates() as $slug => $tpl) {
            $html = $this->wrap($brand, $tpl['content']);

            MarketplaceEmailTemplate::updateOrCreate(
                ['marketplace_client_id' => $marketplace->id, 'slug' => $slug],
                [
                    'name' => $tpl['name'],
                    'subject' => $tpl['subject'],
                    'body_html' => $html,
                    'body_text' => strip_tags($tpl['content']),
                    'category' => 'transactional',
                    'is_active' => true,
                    'is_default' => true,
                    'variables' => $tpl['variables'] ?? [],
                ]
            );
        }
    }

    protected function block(string $heading, string $body, ?string $cta = null): string
    {
        $button = $cta
            ? '<p style="margin:24px 0;"><a href="' . $cta . '" style="display:inline-block;background:#A51C30;color:#fff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:600;">' . $cta . '</a></p>'
            : '';

        return <<<HTML
<h1 style="font-size:20px;margin:0 0 12px;color:#111827;">{$heading}</h1>
<p style="font-size:15px;line-height:1.6;color:#374151;margin:0 0 12px;">{$body}</p>
{$button}
HTML;
    }

    protected function templates(): array
    {
        $portal = '{{portal_link}}';
        $pay = '{{pay_link}}';

        return [
            'installment_plan_confirmation' => [
                'name' => 'Plan de rate — confirmare + grafic',
                'subject' => 'Confirmare plată în rate — {{event_name}}',
                'content' => $this->block(
                    'Ai activat plata în rate 🎉',
                    'Salut {{customer_name}}, avansul de <strong>{{down_payment}}</strong> a fost încasat. '
                    . 'Vei plăti în total <strong>{{customer_total}}</strong> (față de {{direct_price}} la plata directă), '
                    . 'în {{installments_count}} rate. Următoarea rată: <strong>{{next_due_amount}}</strong> pe {{next_due_date}}.<br><br>'
                    . 'Biletul tău este emis, dar devine valabil după achitarea integrală.<br><br>{{schedule_table}}',
                    $portal
                ),
            ],
            'installment_payment_upcoming' => [
                'name' => 'Rate — reminder înainte de scadență',
                'subject' => 'Reminder: rata {{installment_sequence}} pe {{due_date}}',
                'content' => $this->block(
                    'Se apropie o rată',
                    'Rata {{installment_sequence}} de <strong>{{installment_amount}}</strong> va fi debitată automat pe <strong>{{due_date}}</strong>. '
                    . 'Asigură-te că ai fonduri pe card. Sold rămas: {{remaining_balance}}.',
                    $portal
                ),
            ],
            'installment_payment_due_today' => [
                'name' => 'Rate — scadență azi',
                'subject' => 'Azi debităm rata {{installment_sequence}}',
                'content' => $this->block(
                    'Rata ta se debitează azi',
                    'Astăzi vom debita automat <strong>{{installment_amount}}</strong> pentru rata {{installment_sequence}}.',
                    $portal
                ),
            ],
            'installment_payment_receipt' => [
                'name' => 'Rate — chitanță plată',
                'subject' => 'Plată confirmată — rata {{installment_sequence}}',
                'content' => $this->block(
                    'Plată confirmată ✅',
                    'Am încasat <strong>{{installment_amount}}</strong> pentru rata {{installment_sequence}}. '
                    . 'Sold rămas: <strong>{{remaining_balance}}</strong>. Următoarea scadență: {{next_due_date}}.',
                    $portal
                ),
            ],
            'installment_action_required' => [
                'name' => 'Rate — autentificare necesară (3DS)',
                'subject' => 'Acțiune necesară pentru rata {{installment_sequence}}',
                'content' => $this->block(
                    'Confirmă plata rații',
                    'Banca ta cere autentificare pentru rata de <strong>{{installment_amount}}</strong>. '
                    . 'Te rugăm să finalizezi plata în siguranță prin linkul de mai jos.',
                    $pay
                ),
            ],
            'installment_payment_failed' => [
                'name' => 'Rate — plată eșuată + reîncercare',
                'subject' => 'Plata rații {{installment_sequence}} a eșuat',
                'content' => $this->block(
                    'Nu am putut încasa rata',
                    'Plata de <strong>{{installment_amount}}</strong> nu a reușit. Vom reîncerca în {{retry_days}} zile. '
                    . 'Poți plăti acum manual prin linkul de mai jos ca să eviți întârzierea.',
                    $pay
                ),
            ],
            'installment_overdue' => [
                'name' => 'Rate — restanță',
                'subject' => 'Restanță la plata în rate',
                'content' => $this->block(
                    'Ai o rată restantă',
                    'Rata de <strong>{{installment_amount}}</strong> nu a fost achitată. Te rugăm să plătești pentru a-ți păstra biletul.',
                    $pay
                ),
            ],
            'installment_default_warning' => [
                'name' => 'Rate — avertisment anulare',
                'subject' => 'Ultima șansă: biletul tău poate fi anulat',
                'content' => $this->block(
                    'Planul tău riscă să fie anulat',
                    'Dacă nu achiți soldul de <strong>{{remaining_balance}}</strong>, planul va fi anulat și biletul invalidat înainte de {{event_date}}.',
                    $pay
                ),
            ],
            'installment_defaulted' => [
                'name' => 'Rate — plan anulat',
                'subject' => 'Planul de plată în rate a fost anulat',
                'content' => $this->block(
                    'Plan anulat',
                    'Din cauza neplății, planul a fost anulat și biletul invalidat. Taxele achitate nu sunt returnabile conform termenilor.'
                ),
            ],
            'installment_plan_completed' => [
                'name' => 'Rate — plan finalizat, bilet valid',
                'subject' => 'Felicitări! Biletul tău este acum valabil 🎫',
                'content' => $this->block(
                    'Plată finalizată',
                    'Ai achitat integral! Biletul tău pentru <strong>{{event_name}}</strong> este acum valabil.',
                    $portal
                ),
            ],
            'installment_early_payoff_receipt' => [
                'name' => 'Rate — plată anticipată',
                'subject' => 'Ai achitat integral, în avans',
                'content' => $this->block(
                    'Plată anticipată confirmată',
                    'Ai achitat soldul rămas de <strong>{{remaining_balance}}</strong>. Biletul este acum valabil.',
                    $portal
                ),
            ],
            'installment_refund' => [
                'name' => 'Rate — retur/anulare',
                'subject' => 'Retur procesat',
                'content' => $this->block(
                    'Retur procesat',
                    'Am procesat returul pentru plata ta în rate. Taxele nereturnabile au fost reținute conform termenilor.'
                ),
            ],
            'installment_event_cancelled_refund' => [
                'name' => 'Rate — eveniment anulat, refund',
                'subject' => 'Eveniment anulat — refund integral',
                'content' => $this->block(
                    'Evenimentul a fost anulat',
                    'Îți returnăm integral suma achitată. Ne pare rău pentru inconvenient.'
                ),
            ],
            'installment_event_rescheduled' => [
                'name' => 'Rate — eveniment reprogramat',
                'subject' => 'Eveniment reprogramat — planul tău',
                'content' => $this->block(
                    'Evenimentul a fost reprogramat',
                    'Graficul tău de plată a fost actualizat pentru noua dată. Poți alege să plătești integral acum sau să continui cu planul.',
                    $portal
                ),
            ],
            'delegated_pay_request' => [
                'name' => 'Plată delegată — cerere de plată',
                'subject' => '{{customer_name}} te roagă să plătești pentru {{event_name}}',
                'content' => $this->block(
                    'Ai o plată de făcut',
                    'Cineva a rezervat bilete pentru <strong>{{event_name}}</strong> și te-a rugat să finalizezi plata de <strong>{{customer_total}}</strong>. '
                    . 'Linkul este valabil 24 de ore.',
                    $pay
                ),
            ],
            'delegated_pay_confirmed' => [
                'name' => 'Plată delegată — confirmare',
                'subject' => 'Plata a fost confirmată — biletele sunt valabile',
                'content' => $this->block(
                    'Plată confirmată ✅',
                    'Plata a fost efectuată cu succes și biletele pentru <strong>{{event_name}}</strong> sunt acum valabile.',
                    $portal
                ),
            ],
            'delegated_pay_expired' => [
                'name' => 'Plată delegată — link expirat',
                'subject' => 'Linkul de plată a expirat',
                'content' => $this->block(
                    'Linkul a expirat',
                    'Nimeni nu a finalizat plata în 24 de ore, așa că rezervarea a fost eliberată. Poți relua comanda oricând.',
                    $portal
                ),
            ],
            'organizer_installment_defaulted' => [
                'name' => 'Organizator — plan de rate anulat',
                'subject' => 'Un plan de rate a fost anulat',
                'content' => $this->block(
                    'Plan de rate anulat',
                    'Un client nu a finalizat plata în rate pentru evenimentul <strong>{{event_name}}</strong>. Biletul a fost invalidat și locul eliberat.'
                ),
            ],
        ];
    }
}
