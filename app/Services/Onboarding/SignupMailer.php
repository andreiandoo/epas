<?php

namespace App\Services\Onboarding;

use App\Models\EmailLog;
use App\Models\Setting;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Emailurile înscrierii pleacă prin API-ul Brevo, cu cheia din Setări, ca la /register:
 * pe core mailer-ul implicit e `log`. Fără cheie se folosește mailer-ul Laravel.
 */
class SignupMailer
{
    /**
     * @param  string|string[]  $to
     * @param  string[]  $publicAttachments  căi pe discul `public` (ex. PDF-ul contractului)
     */
    public function send(string|array $to, Mailable $mail, string $type, ?int $tenantId = null, array $publicAttachments = []): bool
    {
        $recipients = array_values(array_filter((array) $to));
        if (! $recipients) {
            return false;
        }

        $settings = Setting::current();
        $subject = (string) $mail->envelope()->subject;
        $html = $mail->render() . ($settings->email_footer ?? '');

        if (empty($settings->brevo_api_key)) {
            Mail::to($recipients)->send($mail);
            $this->log($recipients, $subject, $html, $type, $tenantId, true, null, 'laravel_mail');

            return true;
        }

        $payload = [
            'sender' => [
                'name' => $settings->company_name ?? 'Tixello',
                'email' => $settings->email ?? config('mail.from.address'),
            ],
            'to' => array_map(fn ($email) => ['email' => $email], $recipients),
            'subject' => $subject,
            'htmlContent' => $html,
        ];

        foreach ($publicAttachments as $name => $path) {
            if ($path && Storage::disk('public')->exists($path)) {
                $payload['attachment'][] = [
                    'name' => is_string($name) ? $name : basename($path),
                    'content' => base64_encode(Storage::disk('public')->get($path)),
                ];
            }
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['api-key' => $settings->brevo_api_key, 'Content-Type' => 'application/json'])
                ->post('https://api.brevo.com/v3/smtp/email', $payload);
            $error = $response->successful() ? null : (string) ($response->json('message') ?? $response->status());
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        $this->log($recipients, $subject, $html, $type, $tenantId, $error === null, $error, 'brevo');

        if ($error) {
            Log::error('Signup: Brevo a refuzat emailul', ['type' => $type, 'tenant_id' => $tenantId, 'error' => $error]);
        }

        return $error === null;
    }

    private function log(array $to, string $subject, string $html, string $type, ?int $tenantId, bool $ok, ?string $error, string $provider): void
    {
        try {
            foreach ($to as $email) {
                EmailLog::create([
                    'tenant_id' => $tenantId,
                    'recipient_email' => $email,
                    'subject' => $type === 'signup_code' ? 'Cod de verificare înscriere' : $subject,
                    // Codul de verificare nu rămâne în log
                    'body' => $type === 'signup_code' ? '[codul de verificare nu se păstrează]' : $html,
                    'status' => $ok ? 'sent' : 'failed',
                    'sent_at' => $ok ? now() : null,
                    'failed_at' => $ok ? null : now(),
                    'error_message' => $error,
                    'metadata' => ['type' => $type, 'provider' => $provider],
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Signup: EmailLog nu s-a salvat', ['error' => $e->getMessage()]);
        }
    }
}
