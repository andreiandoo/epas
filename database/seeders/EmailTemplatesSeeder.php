<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;

class EmailTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Registration Confirmation Email',
                'event_trigger' => 'registration_confirmation',
                'subject' => 'Welcome to EventPilot ePas - Verify Your Email',
                'body' => '<h2>Welcome {{first_name}}!</h2>
<p>Thank you for registering your organization <strong>{{public_name}}</strong> with EventPilot ePas.</p>
<p>To complete your registration, please verify your email address by clicking the button below:</p>
<p style="text-align: center; margin: 30px 0;">
    <a href="{{verification_link}}" style="background-color: #3b82f6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;">Verify Email Address</a>
</p>
<p>Or copy and paste this link into your browser:</p>
<p style="background-color: #f3f4f6; padding: 10px; border-radius: 4px; word-break: break-all;">{{verification_link}}</p>
<p>If you did not create this account, please ignore this email.</p>
<p>Best regards,<br>The EventPilot Team</p>',
                'description' => 'Sent immediately after tenant registration to verify email address',
                'available_variables' => ['first_name', 'last_name', 'full_name', 'email', 'company_name', 'public_name', 'verification_link'],
                'is_active' => true,
            ],
            [
                'name' => 'Welcome Email After Verification',
                'event_trigger' => 'welcome_email',
                'subject' => 'Welcome to {{public_name}} - Let\'s Get Started!',
                'body' => '<h2>Welcome Aboard, {{first_name}}!</h2>
<p>Your email has been verified successfully. We\'re excited to have <strong>{{public_name}}</strong> on board!</p>
<h3>Your Account Details:</h3>
<ul>
    <li><strong>Company:</strong> {{company_name}}</li>
    <li><strong>Plan:</strong> {{plan}}</li>
    <li><strong>Primary Website:</strong> <a href="{{website_url}}">{{website_url}}</a></li>
</ul>
<h3>Next Steps:</h3>
<ol>
    <li>Complete your profile settings</li>
    <li>Configure your domain settings</li>
    <li>Create your first event</li>
    <li>Set up payment methods</li>
</ol>
<p style="text-align: center; margin: 30px 0;">
    <a href="{{website_url}}/admin" style="background-color: #10b981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;">Go to Dashboard</a>
</p>
<p>If you have any questions, feel free to reach out to our support team.</p>
<p>Best regards,<br>The EventPilot Team</p>',
                'description' => 'Sent after email verification to welcome new tenant and guide next steps',
                'available_variables' => ['first_name', 'last_name', 'full_name', 'email', 'company_name', 'public_name', 'plan', 'website_url'],
                'is_active' => true,
            ],
            [
                'name' => 'Password Reset Request',
                'event_trigger' => 'password_reset',
                'subject' => 'Reset Your Password - EventPilot ePas',
                'body' => '<h2>Password Reset Request</h2>
<p>Hello {{first_name}},</p>
<p>We received a request to reset the password for your account associated with <strong>{{email}}</strong>.</p>
<p>Click the button below to reset your password:</p>
<p style="text-align: center; margin: 30px 0;">
    <a href="{{reset_password_link}}" style="background-color: #ef4444; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;">Reset Password</a>
</p>
<p>Or copy and paste this link into your browser:</p>
<p style="background-color: #f3f4f6; padding: 10px; border-radius: 4px; word-break: break-all;">{{reset_password_link}}</p>
<p><strong>This link will expire in 60 minutes.</strong></p>
<p>If you did not request a password reset, please ignore this email. Your password will remain unchanged.</p>
<p>For security reasons, we recommend:</p>
<ul>
    <li>Using a strong, unique password</li>
    <li>Enabling two-factor authentication</li>
    <li>Never sharing your password with anyone</li>
</ul>
<p>Best regards,<br>The EventPilot Security Team</p>',
                'description' => 'Sent when user requests password reset with time-limited reset link',
                'available_variables' => ['first_name', 'last_name', 'full_name', 'email', 'reset_password_link'],
                'is_active' => true,
            ],
            // ----------------------------------------------------------------
            // Extended Artist (Faza 1 - placeholder bodies; populated by team)
            // ----------------------------------------------------------------
            [
                'name' => 'Extended Artist - Trial Started',
                'event_trigger' => 'extended_artist_trial_started',
                'subject' => 'Trial-ul tău Extended Artist a început',
                'body' => '<h2>Salut {{first_name}}!</h2>
<p>Ai pornit un trial gratuit de <strong>{{trial_days}} zile</strong> pentru pachetul Extended Artist.</p>
<p>Ai acces complet la cele 4 module: Fan CRM, Booking Marketplace, Smart EPK și Tour Optimizer.</p>
<p>Trial-ul expiră pe <strong>{{trial_ends_at}}</strong>. După această dată, dacă nu activezi abonamentul plătit, accesul la module va fi suspendat.</p>',
                'description' => 'Notificare către artist după pornirea trial-ului Extended Artist (30 zile gratuit).',
                'available_variables' => ['first_name', 'last_name', 'full_name', 'email', 'trial_days', 'trial_ends_at', 'portal_url'],
                'is_active' => true,
            ],
            [
                'name' => 'Extended Artist - Trial Ending Soon',
                'event_trigger' => 'extended_artist_trial_ending',
                'subject' => 'Trial-ul Extended Artist expiră în curând',
                'body' => '<h2>Salut {{first_name}}!</h2>
<p>Trial-ul tău Extended Artist expiră pe <strong>{{trial_ends_at}}</strong>.</p>
<p>Pentru a păstra accesul la Fan CRM, Booking Marketplace, Smart EPK și Tour Optimizer, activează abonamentul lunar.</p>',
                'description' => 'Reminder cu 3 zile înainte ca trial-ul Extended Artist să expire.',
                'available_variables' => ['first_name', 'last_name', 'full_name', 'email', 'trial_ends_at', 'subscribe_url'],
                'is_active' => true,
            ],
            [
                'name' => 'Extended Artist - Trial Expired',
                'event_trigger' => 'extended_artist_trial_expired',
                'subject' => 'Trial-ul Extended Artist a expirat',
                'body' => '<h2>Salut {{first_name}}!</h2>
<p>Trial-ul tău Extended Artist a expirat. Accesul la cele 4 module a fost suspendat.</p>
<p>Reactivează oricând prin abonamentul lunar — datele tale rămân salvate.</p>',
                'description' => 'Notificare la expirarea trial-ului Extended Artist.',
                'available_variables' => ['first_name', 'last_name', 'full_name', 'email', 'subscribe_url'],
                'is_active' => true,
            ],
            [
                'name' => 'Extended Artist - Subscription Renewed',
                'event_trigger' => 'extended_artist_subscription_renewed',
                'subject' => 'Abonamentul Extended Artist s-a reînnoit',
                'body' => '<h2>Salut {{first_name}}!</h2>
<p>Plata pentru abonamentul tău Extended Artist a fost procesată cu succes.</p>
<p>Acces valabil până la <strong>{{expires_at}}</strong>. Suma facturată: <strong>{{amount}} {{currency}}</strong>.</p>',
                'description' => 'Confirmare după re-charge automat sau manual lunar pentru Extended Artist.',
                'available_variables' => ['first_name', 'last_name', 'full_name', 'email', 'expires_at', 'amount', 'currency', 'invoice_url'],
                'is_active' => true,
            ],
            [
                'name' => 'Extended Artist - Subscription Failed',
                'event_trigger' => 'extended_artist_subscription_failed',
                'subject' => 'Plata pentru Extended Artist a eșuat',
                'body' => '<h2>Salut {{first_name}}!</h2>
<p>Nu am putut procesa plata pentru abonamentul tău Extended Artist.</p>
<p>Pentru a păstra accesul, te rugăm să actualizezi metoda de plată sau să retrimiți plata.</p>',
                'description' => 'Notificare la eșec de re-charge pentru abonamentul Extended Artist.',
                'available_variables' => ['first_name', 'last_name', 'full_name', 'email', 'retry_url'],
                'is_active' => true,
            ],
            [
                'name' => 'Extended Artist - Activated by Admin',
                'event_trigger' => 'extended_artist_admin_activated',
                'subject' => 'Ai primit acces gratuit la Extended Artist',
                'body' => '<h2>Salut {{first_name}}!</h2>
<p>Echipa marketplace ți-a activat accesul la pachetul Extended Artist (Fan CRM, Booking Marketplace, Smart EPK, Tour Optimizer).</p>
<p>Accesul este nelimitat și nu îți va fi facturat. Intră în portal pentru a începe.</p>',
                'description' => 'Notificare către artist când marketplace admin face admin override pe contul lui.',
                'available_variables' => ['first_name', 'last_name', 'full_name', 'email', 'portal_url'],
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['event_trigger' => $template['event_trigger']],
                $template
            );
        }

        $this->command->info('Email templates seeded successfully!');
    }
}
