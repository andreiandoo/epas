<?php

namespace App\Services\Tracking;

use App\Models\CookieConsent;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing consent renewal notifications
 *
 * Handles identifying expiring consents and triggering renewal
 * notifications through various channels (email, in-app banners, etc.)
 */
class ConsentRenewalService
{
    /**
     * Default days before expiry to send first notification
     */
    public const DEFAULT_FIRST_NOTIFICATION_DAYS = 30;

    /**
     * Default days before expiry to send reminder notification
     */
    public const DEFAULT_REMINDER_DAYS = 7;

    /**
     * Process consent renewals for all tenants
     */
    public function processAllTenants(): array
    {
        $results = [];

        $tenants = Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            $result = $this->processRenewalsForTenant($tenant);
            $results[$tenant->id] = $result;
        }

        return $results;
    }

    /**
     * Process consent renewals for a specific tenant
     */
    public function processRenewalsForTenant(Tenant $tenant): array
    {
        $settings = $this->getTenantSettings($tenant);

        if (!$settings['renewal_notifications_enabled']) {
            return [
                'tenant_id' => $tenant->id,
                'skipped' => true,
                'reason' => 'Renewal notifications disabled',
            ];
        }

        $firstNotificationConsents = $this->getConsentsNeedingFirstNotification(
            $tenant->id,
            $settings['first_notification_days']
        );

        $reminderConsents = $this->getConsentsNeedingReminder(
            $tenant->id,
            $settings['reminder_days']
        );

        $results = [
            'tenant_id' => $tenant->id,
            'first_notifications' => [],
            'reminders' => [],
        ];

        // Process first notifications
        foreach ($firstNotificationConsents as $consent) {
            $notified = $this->sendRenewalNotification($consent, 'first', $tenant, $settings);
            if ($notified) {
                $results['first_notifications'][] = $consent->id;
            }
        }

        // Process reminders
        foreach ($reminderConsents as $consent) {
            $notified = $this->sendRenewalNotification($consent, 'reminder', $tenant, $settings);
            if ($notified) {
                $results['reminders'][] = $consent->id;
            }
        }

        Log::info('Processed consent renewals for tenant', [
            'tenant_id' => $tenant->id,
            'first_notifications_sent' => count($results['first_notifications']),
            'reminders_sent' => count($results['reminders']),
        ]);

        return $results;
    }

    /**
     * Get consents that need first renewal notification
     */
    public function getConsentsNeedingFirstNotification(int $tenantId, int $daysBeforeExpiry = null): Collection
    {
        $daysBeforeExpiry = $daysBeforeExpiry ?? self::DEFAULT_FIRST_NOTIFICATION_DAYS;

        $targetDate = now()->addDays($daysBeforeExpiry);
        $startDate = $targetDate->copy()->startOfDay();
        $endDate = $targetDate->copy()->endOfDay();

        return CookieConsent::forTenant($tenantId)
            ->active()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$startDate, $endDate])
            ->whereNull('renewal_first_notified_at')
            ->with('customer')
            ->get();
    }

    /**
     * Get consents that need reminder notification
     */
    public function getConsentsNeedingReminder(int $tenantId, int $daysBeforeExpiry = null): Collection
    {
        $daysBeforeExpiry = $daysBeforeExpiry ?? self::DEFAULT_REMINDER_DAYS;

        $targetDate = now()->addDays($daysBeforeExpiry);
        $startDate = $targetDate->copy()->startOfDay();
        $endDate = $targetDate->copy()->endOfDay();

        return CookieConsent::forTenant($tenantId)
            ->active()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$startDate, $endDate])
            ->whereNotNull('renewal_first_notified_at')
            ->whereNull('renewal_reminder_notified_at')
            ->with('customer')
            ->get();
    }

    /**
     * Send renewal notification for a consent
     */
    protected function sendRenewalNotification(
        CookieConsent $consent,
        string $type,
        Tenant $tenant,
        array $settings
    ): bool {
        // Check if consent has associated customer with email
        if (!$consent->customer || !$consent->customer->email) {
            // Log for visitor-only consents - these will show renewal banner on site
            Log::debug('Consent renewal skipped - no customer email', [
                'consent_id' => $consent->id,
                'visitor_id' => $consent->visitor_id,
            ]);

            // Still mark as notified to track that we attempted
            $this->markAsNotified($consent, $type);
            return false;
        }

        try {
            // Generate renewal URL
            $renewalUrl = $this->generateRenewalUrl($consent, $tenant);

            // Send notification based on tenant settings
            if ($settings['email_notifications']) {
                $this->sendEmailNotification($consent, $type, $tenant, $renewalUrl);
            }

            // Mark consent as notified
            $this->markAsNotified($consent, $type);

            Log::info('Consent renewal notification sent', [
                'consent_id' => $consent->id,
                'customer_id' => $consent->customer_id,
                'type' => $type,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send consent renewal notification', [
                'consent_id' => $consent->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate renewal URL for the consent
     */
    protected function generateRenewalUrl(CookieConsent $consent, Tenant $tenant): string
    {
        // Get primary domain for tenant
        $domain = $tenant->primaryDomain?->domain ?? $tenant->slug . '.example.com';

        // Generate signed URL for consent renewal
        $token = hash('sha256', $consent->id . $consent->visitor_id . config('app.key'));

        return "https://{$domain}/cookie-preferences?renewal=1&token=" . substr($token, 0, 32);
    }

    /**
     * Send email notification for consent renewal
     */
    protected function sendEmailNotification(
        CookieConsent $consent,
        string $type,
        Tenant $tenant,
        string $renewalUrl
    ): void {
        // Use tenant mail service if available
        $mailService = app(\App\Services\TenantMailService::class);

        $daysUntilExpiry = now()->diffInDays($consent->expires_at, false);

        $subject = $type === 'first'
            ? "Your cookie preferences will expire in {$daysUntilExpiry} days"
            : "Reminder: Your cookie preferences expire soon";

        $mailService->sendTemplatedEmail(
            $tenant,
            $consent->customer->email,
            'cookie_consent_renewal',
            [
                'customer_name' => $consent->customer->name ?? 'Valued Customer',
                'expires_at' => $consent->expires_at->format('F j, Y'),
                'days_until_expiry' => $daysUntilExpiry,
                'renewal_url' => $renewalUrl,
                'notification_type' => $type,
                'current_preferences' => [
                    'analytics' => $consent->analytics,
                    'marketing' => $consent->marketing,
                    'preferences' => $consent->preferences,
                ],
            ],
            $subject
        );
    }

    /**
     * Mark consent as notified
     */
    protected function markAsNotified(CookieConsent $consent, string $type): void
    {
        $field = $type === 'first' ? 'renewal_first_notified_at' : 'renewal_reminder_notified_at';
        $consent->update([$field => now()]);
    }

    /**
     * Get tenant settings for renewal notifications
     */
    protected function getTenantSettings(Tenant $tenant): array
    {
        $settings = $tenant->settings ?? [];
        $cookieSettings = $settings['cookie_consent'] ?? [];

        return [
            'renewal_notifications_enabled' => $cookieSettings['renewal_notifications_enabled'] ?? true,
            'first_notification_days' => $cookieSettings['first_notification_days'] ?? self::DEFAULT_FIRST_NOTIFICATION_DAYS,
            'reminder_days' => $cookieSettings['reminder_days'] ?? self::DEFAULT_REMINDER_DAYS,
            'email_notifications' => $cookieSettings['email_notifications'] ?? true,
        ];
    }

    /**
     * Get renewal status for frontend banner display
     */
    public function getRenewalStatus(CookieConsent $consent): array
    {
        if (!$consent->expires_at) {
            return [
                'needs_renewal' => false,
                'expires_at' => null,
            ];
        }

        $daysUntilExpiry = now()->diffInDays($consent->expires_at, false);

        return [
            'needs_renewal' => $daysUntilExpiry <= self::DEFAULT_FIRST_NOTIFICATION_DAYS,
            'is_urgent' => $daysUntilExpiry <= self::DEFAULT_REMINDER_DAYS,
            'expires_at' => $consent->expires_at->toIso8601String(),
            'days_until_expiry' => max(0, $daysUntilExpiry),
        ];
    }

    /**
     * Renew consent (extend expiration)
     */
    public function renewConsent(CookieConsent $consent): CookieConsent
    {
        $consent->update([
            'expires_at' => now()->addDays(CookieConsent::DEFAULT_EXPIRY_DAYS),
            'renewal_first_notified_at' => null,
            'renewal_reminder_notified_at' => null,
        ]);

        Log::info('Consent renewed', [
            'consent_id' => $consent->id,
            'new_expires_at' => $consent->expires_at,
        ]);

        return $consent->fresh();
    }
}
