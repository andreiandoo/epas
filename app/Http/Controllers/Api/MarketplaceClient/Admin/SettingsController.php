<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Admin;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SettingsController extends BaseController
{
    /**
     * Get marketplace settings
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('settings.view')) {
            return $this->error('Unauthorized', 403);
        }

        $client = $admin->marketplaceClient;

        return $this->success([
            'marketplace' => [
                'id' => $client->id,
                'name' => $client->name,
                'slug' => $client->slug,
                'domain' => $client->domain,
                'contact_email' => $client->contact_email,
                'contact_phone' => $client->contact_phone,
                'company_name' => $client->company_name,
                'status' => $client->status,
                'commission_rate' => (float) $client->commission_rate,
            ],
            'settings' => $client->settings ?? [],
            'api_stats' => [
                'api_calls_count' => $client->api_calls_count,
                'last_api_call_at' => $client->last_api_call_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update marketplace settings
     */
    public function update(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('settings.manage')) {
            return $this->error('Unauthorized', 403);
        }

        $client = $admin->marketplaceClient;

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'contact_email' => 'sometimes|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:255',
        ]);

        $client->update($validated);

        Log::channel('marketplace')->info('Marketplace settings updated', [
            'client_id' => $client->id,
            'admin_id' => $admin->id,
            'changes' => $validated,
        ]);

        return $this->success([
            'marketplace' => [
                'id' => $client->id,
                'name' => $client->name,
                'contact_email' => $client->contact_email,
                'contact_phone' => $client->contact_phone,
                'company_name' => $client->company_name,
            ],
        ], 'Settings updated');
    }

    /**
     * Update commission rate
     */
    public function updateCommission(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('settings.manage')) {
            return $this->error('Unauthorized', 403);
        }

        $validated = $request->validate([
            'commission_rate' => 'required|numeric|min:0|max:50',
        ]);

        $client = $admin->marketplaceClient;
        $oldRate = $client->commission_rate;

        $client->update(['commission_rate' => $validated['commission_rate']]);

        Log::channel('marketplace')->info('Commission rate updated', [
            'client_id' => $client->id,
            'admin_id' => $admin->id,
            'old_rate' => $oldRate,
            'new_rate' => $validated['commission_rate'],
        ]);

        return $this->success([
            'commission_rate' => (float) $client->commission_rate,
        ], 'Commission rate updated');
    }

    /**
     * Update custom settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('settings.manage')) {
            return $this->error('Unauthorized', 403);
        }

        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.webhook_url' => 'nullable|url|max:500',
            'settings.webhook_secret' => 'nullable|string|max:255',
            'settings.auto_approve_events' => 'nullable|boolean',
            'settings.auto_approve_organizers' => 'nullable|boolean',
            'settings.min_payout_amount' => 'nullable|numeric|min:0',
            'settings.payout_schedule' => 'nullable|string|in:weekly,biweekly,monthly,manual',
            'settings.default_currency' => 'nullable|string|size:3',
            'settings.allowed_payment_methods' => 'nullable|array',
            'settings.terms_url' => 'nullable|url|max:500',
            'settings.privacy_url' => 'nullable|url|max:500',
            'settings.support_email' => 'nullable|email|max:255',
            'settings.branding' => 'nullable|array',
            'settings.branding.primary_color' => 'nullable|string|max:7',
            'settings.branding.logo_url' => 'nullable|url|max:500',
            'settings.email_templates' => 'nullable|array',
        ]);

        $client = $admin->marketplaceClient;
        $currentSettings = $client->settings ?? [];
        $newSettings = array_merge($currentSettings, $validated['settings']);

        $client->update(['settings' => $newSettings]);

        Log::channel('marketplace')->info('Custom settings updated', [
            'client_id' => $client->id,
            'admin_id' => $admin->id,
        ]);

        return $this->success([
            'settings' => $client->settings,
        ], 'Settings updated');
    }

    /**
     * Get webhook configuration
     */
    public function webhooks(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('settings.view')) {
            return $this->error('Unauthorized', 403);
        }

        $client = $admin->marketplaceClient;
        $settings = $client->settings ?? [];

        return $this->success([
            'webhook_url' => $settings['webhook_url'] ?? null,
            'webhook_secret' => $settings['webhook_secret'] ? '********' : null,
            'webhook_events' => $settings['webhook_events'] ?? [
                'order.created',
                'order.completed',
                'order.cancelled',
                'order.refunded',
                'event.published',
                'organizer.registered',
                'payout.completed',
            ],
        ]);
    }

    /**
     * Update webhook configuration
     */
    public function updateWebhooks(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('settings.manage')) {
            return $this->error('Unauthorized', 403);
        }

        $validated = $request->validate([
            'webhook_url' => 'nullable|url|max:500',
            'webhook_secret' => 'nullable|string|min:16|max:255',
            'webhook_events' => 'nullable|array',
            'webhook_events.*' => 'string|in:order.created,order.completed,order.cancelled,order.refunded,event.published,event.cancelled,organizer.registered,organizer.verified,payout.requested,payout.completed',
        ]);

        $client = $admin->marketplaceClient;
        $settings = $client->settings ?? [];

        if (array_key_exists('webhook_url', $validated)) {
            $settings['webhook_url'] = $validated['webhook_url'];
        }
        if (array_key_exists('webhook_secret', $validated)) {
            $settings['webhook_secret'] = $validated['webhook_secret'];
        }
        if (array_key_exists('webhook_events', $validated)) {
            $settings['webhook_events'] = $validated['webhook_events'];
        }

        $client->update(['settings' => $settings]);

        Log::channel('marketplace')->info('Webhook settings updated', [
            'client_id' => $client->id,
            'admin_id' => $admin->id,
        ]);

        return $this->success([
            'webhook_url' => $settings['webhook_url'] ?? null,
            'webhook_configured' => !empty($settings['webhook_url']),
        ], 'Webhook settings updated');
    }

    /**
     * Test webhook
     */
    public function testWebhook(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('settings.manage')) {
            return $this->error('Unauthorized', 403);
        }

        $client = $admin->marketplaceClient;
        $webhookUrl = $client->settings['webhook_url'] ?? null;

        if (!$webhookUrl) {
            return $this->error('No webhook URL configured', 400);
        }

        try {
            $payload = [
                'event' => 'webhook.test',
                'timestamp' => now()->toIso8601String(),
                'data' => [
                    'message' => 'This is a test webhook from your marketplace.',
                ],
            ];

            $signature = hash_hmac(
                'sha256',
                json_encode($payload),
                $client->settings['webhook_secret'] ?? ''
            );

            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Marketplace-Client' => $client->slug,
                ])
                ->post($webhookUrl, $payload);

            Log::channel('marketplace')->info('Webhook test sent', [
                'client_id' => $client->id,
                'admin_id' => $admin->id,
                'status' => $response->status(),
            ]);

            return $this->success([
                'status' => $response->status(),
                'success' => $response->successful(),
                'response' => $response->body(),
            ], $response->successful() ? 'Webhook test successful' : 'Webhook test failed');

        } catch (\Exception $e) {
            Log::channel('marketplace')->error('Webhook test failed', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to send test webhook: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Regenerate API credentials
     */
    public function regenerateApiCredentials(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->isSuperAdmin()) {
            return $this->error('Only super admins can regenerate API credentials', 403);
        }

        $validated = $request->validate([
            'confirm' => 'required|boolean|accepted',
        ]);

        $client = $admin->marketplaceClient;
        $client->regenerateApiCredentials();

        Log::channel('marketplace')->warning('API credentials regenerated', [
            'client_id' => $client->id,
            'admin_id' => $admin->id,
        ]);

        return $this->success([
            'api_key' => $client->api_key,
            'api_secret' => $client->api_secret,
            'warning' => 'Save these credentials now. The API secret will not be shown again.',
        ], 'API credentials regenerated');
    }

    /**
     * Get available permissions for reference
     */
    public function permissions(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        return $this->success([
            'permissions' => MarketplaceAdmin::availablePermissions(),
            'roles' => MarketplaceAdmin::roles(),
        ]);
    }

    /**
     * Require authenticated admin
     */
    protected function requireAdmin(Request $request): MarketplaceAdmin
    {
        $admin = $request->user();

        if (!$admin instanceof MarketplaceAdmin) {
            abort(401, 'Unauthorized');
        }

        return $admin;
    }
}
