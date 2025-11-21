<?php

namespace App\Filament\Resources\EmailTemplates\Pages;

use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use App\Models\EmailLog;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTestEmail')
                ->label('Send Test Email')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->form([
                    TextInput::make('test_email')
                        ->label('Recipient Email')
                        ->email()
                        ->required()
                        ->placeholder('test@example.com')
                        ->helperText('Enter the email address to send the test email to'),
                ])
                ->action(function (array $data) {
                    $this->sendTestEmail($data['test_email']);
                }),

            DeleteAction::make(),
        ];
    }

    protected function sendTestEmail(string $email): void
    {
        $template = $this->record;

        // Sample variables for testing
        $testVariables = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'full_name' => 'Test User',
            'email' => $email,
            'company_name' => 'Tixello',
            'public_name' => 'Tixello',
            'plan' => 'Premium',
            'website_url' => config('app.url'),
            'verification_link' => config('app.url') . '/verify/test-token',
            'reset_password_link' => config('app.url') . '/reset-password/test-token',
            'invoice_number' => 'INV-2024-001',
            'invoice_amount' => '€99.00',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'invoice_url' => config('app.url') . '/admin/invoices/1',
            'payment_amount' => '€99.00',
            'payment_date' => now()->format('Y-m-d'),
            'failure_reason' => 'Card declined',
            'payment_url' => config('app.url') . '/admin/payments',
            'domain_name' => 'test.tixello.com',
            'suspension_reason' => 'Billing issue',
            'microservice_name' => 'Analytics',
            'days_remaining' => '7',
            'upgrade_url' => config('app.url') . '/admin/upgrade',
            'next_billing_date' => now()->addMonth()->format('Y-m-d'),
            'renewal_amount' => '€99.00',
            'access_end_date' => now()->addMonth()->format('Y-m-d'),
            'resubscribe_url' => config('app.url') . '/admin/subscribe',
        ];

        // Process template with test variables
        $processed = $template->processTemplate($testVariables);

        // Get settings for Brevo
        $settings = Setting::current();

        if (!empty($settings->brevo_api_key)) {
            // Send via Brevo API
            try {
                $response = Http::withHeaders([
                    'api-key' => $settings->brevo_api_key,
                    'Content-Type' => 'application/json',
                ])->post('https://api.brevo.com/v3/smtp/email', [
                    'sender' => [
                        'name' => $settings->company_name ?? 'Tixello',
                        'email' => $settings->email ?? 'noreply@tixello.com',
                    ],
                    'to' => [
                        ['email' => $email]
                    ],
                    'subject' => '[TEST] ' . $processed['subject'],
                    'htmlContent' => $processed['body'] . ($settings->email_footer ?? ''),
                ]);

                if ($response->successful()) {
                    // Log successful email
                    EmailLog::create([
                        'email_template_id' => $template->id,
                        'recipient_email' => $email,
                        'recipient_name' => 'Test User',
                        'subject' => '[TEST] ' . $processed['subject'],
                        'body' => $processed['body'] . ($settings->email_footer ?? ''),
                        'status' => 'sent',
                        'sent_at' => now(),
                        'metadata' => [
                            'type' => 'test',
                            'sender_email' => $settings->email ?? 'noreply@tixello.com',
                            'sender_name' => $settings->company_name ?? 'Tixello',
                            'provider' => 'brevo',
                        ],
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Test email sent!')
                        ->body("Email sent to {$email} via Brevo")
                        ->send();
                } else {
                    // Log failed email
                    EmailLog::create([
                        'email_template_id' => $template->id,
                        'recipient_email' => $email,
                        'recipient_name' => 'Test User',
                        'subject' => '[TEST] ' . $processed['subject'],
                        'body' => $processed['body'] . ($settings->email_footer ?? ''),
                        'status' => 'failed',
                        'failed_at' => now(),
                        'error_message' => $response->json('message') ?? 'Unknown error',
                        'metadata' => [
                            'type' => 'test',
                            'sender_email' => $settings->email ?? 'noreply@tixello.com',
                            'sender_name' => $settings->company_name ?? 'Tixello',
                            'provider' => 'brevo',
                        ],
                    ]);

                    Notification::make()
                        ->danger()
                        ->title('Failed to send email')
                        ->body($response->json('message') ?? 'Unknown error')
                        ->send();
                }
            } catch (\Exception $e) {
                // Log exception
                EmailLog::create([
                    'email_template_id' => $template->id,
                    'recipient_email' => $email,
                    'recipient_name' => 'Test User',
                    'subject' => '[TEST] ' . $processed['subject'],
                    'body' => $processed['body'] . ($settings->email_footer ?? ''),
                    'status' => 'failed',
                    'failed_at' => now(),
                    'error_message' => $e->getMessage(),
                    'metadata' => [
                        'type' => 'test',
                        'sender_email' => $settings->email ?? 'noreply@tixello.com',
                        'sender_name' => $settings->company_name ?? 'Tixello',
                        'provider' => 'brevo',
                    ],
                ]);

                Notification::make()
                    ->danger()
                    ->title('Error sending email')
                    ->body($e->getMessage())
                    ->send();
            }
        } else {
            // Fallback to Laravel mail
            try {
                Mail::html($processed['body'] . ($settings->email_footer ?? ''), function ($message) use ($email, $processed) {
                    $message->to($email)
                        ->subject('[TEST] ' . $processed['subject']);
                });

                // Log successful email
                EmailLog::create([
                    'email_template_id' => $template->id,
                    'recipient_email' => $email,
                    'recipient_name' => 'Test User',
                    'subject' => '[TEST] ' . $processed['subject'],
                    'body' => $processed['body'] . ($settings->email_footer ?? ''),
                    'status' => 'sent',
                    'sent_at' => now(),
                    'metadata' => [
                        'type' => 'test',
                        'sender_email' => config('mail.from.address'),
                        'sender_name' => config('mail.from.name'),
                        'provider' => 'laravel_mail',
                    ],
                ]);

                Notification::make()
                    ->success()
                    ->title('Test email sent!')
                    ->body("Email sent to {$email} via Laravel Mail")
                    ->send();
            } catch (\Exception $e) {
                // Log failed email
                EmailLog::create([
                    'email_template_id' => $template->id,
                    'recipient_email' => $email,
                    'recipient_name' => 'Test User',
                    'subject' => '[TEST] ' . $processed['subject'],
                    'body' => $processed['body'] . ($settings->email_footer ?? ''),
                    'status' => 'failed',
                    'failed_at' => now(),
                    'error_message' => $e->getMessage(),
                    'metadata' => [
                        'type' => 'test',
                        'sender_email' => config('mail.from.address'),
                        'sender_name' => config('mail.from.name'),
                        'provider' => 'laravel_mail',
                    ],
                ]);

                Notification::make()
                    ->danger()
                    ->title('Error sending email')
                    ->body($e->getMessage())
                    ->send();
            }
        }
    }
}
