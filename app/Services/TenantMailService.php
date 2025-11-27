<?php

namespace App\Services;

use App\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TenantMailService
{
    /**
     * Send email using tenant's mail configuration if available,
     * otherwise fallback to core mail configuration (Brevo)
     */
    public function send(Tenant $tenant, Closure $callback): void
    {
        if ($this->hasTenantMailConfig($tenant)) {
            $this->sendWithTenantConfig($tenant, $callback);
        } else {
            $this->sendWithCoreConfig($callback);
        }
    }

    /**
     * Check if tenant has custom mail configuration
     */
    private function hasTenantMailConfig(Tenant $tenant): bool
    {
        $settings = $tenant->settings ?? [];

        return isset($settings['mail'])
            && isset($settings['mail']['host'])
            && isset($settings['mail']['username'])
            && isset($settings['mail']['password']);
    }

    /**
     * Send email using tenant's custom mail configuration
     */
    private function sendWithTenantConfig(Tenant $tenant, Closure $callback): void
    {
        try {
            $mailConfig = $tenant->settings['mail'];

            // Configure tenant mailer dynamically
            Config::set('mail.mailers.tenant', [
                'transport' => $mailConfig['driver'] ?? 'smtp',
                'host' => $mailConfig['host'],
                'port' => $mailConfig['port'] ?? 587,
                'username' => $mailConfig['username'],
                'password' => $this->decryptPassword($mailConfig['password']),
                'encryption' => $mailConfig['encryption'] ?? 'tls',
                'timeout' => 30,
            ]);

            // Set from address
            if (isset($mailConfig['from_address']) && isset($mailConfig['from_name'])) {
                Config::set('mail.from', [
                    'address' => $mailConfig['from_address'],
                    'name' => $mailConfig['from_name'],
                ]);
            }

            // Send using tenant mailer
            Mail::mailer('tenant')->send([], [], $callback);

            Log::info('Email sent using tenant mail configuration', [
                'tenant_id' => $tenant->id,
                'mail_host' => $mailConfig['host'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send email with tenant configuration, falling back to core', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback to core configuration
            $this->sendWithCoreConfig($callback);
        }
    }

    /**
     * Send email using core mail configuration (Brevo from .env)
     */
    private function sendWithCoreConfig(Closure $callback): void
    {
        try {
            Mail::send([], [], $callback);

            Log::info('Email sent using core mail configuration (Brevo)');
        } catch (\Exception $e) {
            Log::error('Failed to send email with core configuration', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't re-throw - let the caller decide how to handle failure
            // Registration should succeed even if email fails
        }
    }

    /**
     * Decrypt password (if encrypted in DB)
     */
    private function decryptPassword(string $password): string
    {
        // Check if password looks encrypted (base64 encoded Laravel encryption)
        if (str_starts_with($password, 'eyJpdiI6')) {
            try {
                return decrypt($password);
            } catch (\Exception $e) {
                // If decryption fails, assume it's plain text
                return $password;
            }
        }

        return $password;
    }

    /**
     * Test tenant mail configuration
     *
     * @return array{success: bool, message: string, details?: array}
     */
    public function testTenantMailConfig(Tenant $tenant, string $testEmail): array
    {
        if (!$this->hasTenantMailConfig($tenant)) {
            return [
                'success' => false,
                'message' => 'Tenant does not have mail configuration set up',
            ];
        }

        try {
            $this->send($tenant, function ($message) use ($testEmail, $tenant) {
                $message->to($testEmail)
                    ->subject('Test Email - ' . ($tenant->public_name ?? $tenant->name))
                    ->html("
                        <h2>Test Email</h2>
                        <p>This is a test email to verify your mail configuration.</p>
                        <p>Tenant: {$tenant->name}</p>
                        <p>If you received this email, your mail configuration is working correctly!</p>
                    ");
            });

            return [
                'success' => true,
                'message' => 'Test email sent successfully',
                'details' => [
                    'sent_to' => $testEmail,
                    'using_config' => $this->hasTenantMailConfig($tenant) ? 'tenant' : 'core',
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to send test email: ' . $e->getMessage(),
                'details' => [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ],
            ];
        }
    }
}
