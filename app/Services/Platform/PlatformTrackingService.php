<?php

namespace App\Services\Platform;

use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use App\Models\Platform\PlatformAdAccount;
use App\Models\Platform\PlatformConversion;
use App\Models\Platform\PlatformAudience;
use App\Models\Platform\PlatformAudienceMember;
use App\Models\Tenant;
use App\Models\Order;
use App\Services\Integrations\GoogleAds\GoogleAdsService;
use App\Services\Integrations\FacebookCapi\FacebookCapiService;
use App\Services\Integrations\TikTokAds\TikTokAdsService;
use App\Services\Integrations\LinkedInAds\LinkedInAdsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class PlatformTrackingService
{
    protected array $platformServices = [];

    public function __construct()
    {
        // Services will be initialized on demand
    }

    /**
     * Track a page view event
     */
    public function trackPageView(array $data): CoreCustomerEvent
    {
        $session = $this->getOrCreateSession($data);
        $customer = $this->getOrCreateCustomer($data, $session);

        $event = $this->createEvent([
            'core_customer_id' => $customer?->id,
            'tenant_id' => $data['tenant_id'] ?? null,
            'session_id' => $session->id,
            'event_type' => CoreCustomerEvent::TYPE_PAGE_VIEW,
            'event_category' => CoreCustomerEvent::CATEGORY_NAVIGATION,
            'page_url' => $data['page_url'] ?? null,
            'page_title' => $data['page_title'] ?? null,
            'referrer' => $data['referrer'] ?? null,
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'utm_term' => $data['utm_term'] ?? null,
            'utm_content' => $data['utm_content'] ?? null,
            'gclid' => $data['gclid'] ?? null,
            'fbclid' => $data['fbclid'] ?? null,
            'ttclid' => $data['ttclid'] ?? null,
            'li_fat_id' => $data['li_fat_id'] ?? null,
            'device_type' => $data['device_type'] ?? null,
            'browser' => $data['browser'] ?? null,
            'os' => $data['os'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'region' => $data['region'] ?? null,
            'city' => $data['city'] ?? null,
            'event_data' => $data['event_data'] ?? [],
        ]);

        // Update session
        $session->recordPageView($data['page_url'] ?? '');

        // Update customer visit tracking
        if ($customer) {
            $customer->recordVisit([
                'referrer' => $data['referrer'] ?? null,
                'utm_source' => $data['utm_source'] ?? null,
                'utm_medium' => $data['utm_medium'] ?? null,
                'utm_campaign' => $data['utm_campaign'] ?? null,
            ]);
        }

        return $event;
    }

    /**
     * Track a purchase/conversion event - DUAL TRACKING
     */
    public function trackPurchase(array $data, ?Order $order = null): CoreCustomerEvent
    {
        $session = $this->getOrCreateSession($data);
        $customer = $this->getOrCreateCustomer($data, $session);

        // Create the event
        $event = $this->createEvent([
            'core_customer_id' => $customer?->id,
            'tenant_id' => $data['tenant_id'] ?? null,
            'session_id' => $session->id,
            'event_type' => CoreCustomerEvent::TYPE_PURCHASE,
            'event_category' => CoreCustomerEvent::CATEGORY_ECOMMERCE,
            'page_url' => $data['page_url'] ?? null,
            'referrer' => $data['referrer'] ?? null,
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'gclid' => $data['gclid'] ?? null,
            'fbclid' => $data['fbclid'] ?? null,
            'ttclid' => $data['ttclid'] ?? null,
            'li_fat_id' => $data['li_fat_id'] ?? null,
            'order_id' => $order?->id ?? $data['order_id'] ?? null,
            'order_total' => $order?->total ?? $data['order_total'] ?? 0,
            'currency' => $order?->currency ?? $data['currency'] ?? 'USD',
            'ticket_count' => $data['ticket_count'] ?? null,
            'event_id' => $data['event_id'] ?? null,
            'device_type' => $data['device_type'] ?? null,
            'browser' => $data['browser'] ?? null,
            'os' => $data['os'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'is_converted' => true,
            'conversion_value' => $order?->total ?? $data['order_total'] ?? 0,
            'event_data' => array_merge($data['event_data'] ?? [], [
                'order_items' => $data['order_items'] ?? [],
                'user_agent' => $data['user_agent'] ?? null,
            ]),
        ]);

        // Mark session as converted
        $session->markConverted($event->conversion_value);

        // Update customer purchase data
        if ($customer) {
            $customer->recordPurchase(
                $event->conversion_value,
                $event->ticket_count ?? 1
            );
        }

        // DUAL TRACKING: Send to tenant's ad platforms
        $this->sendToTenantPlatforms($event, $customer, $data);

        // DUAL TRACKING: Send to platform (core admin) ad accounts
        $this->sendToPlatformAccounts($event, $customer);

        return $event;
    }

    /**
     * Track add to cart event
     */
    public function trackAddToCart(array $data): CoreCustomerEvent
    {
        $session = $this->getOrCreateSession($data);
        $customer = $this->getOrCreateCustomer($data, $session);

        $event = $this->createEvent([
            'core_customer_id' => $customer?->id,
            'tenant_id' => $data['tenant_id'] ?? null,
            'session_id' => $session->id,
            'event_type' => CoreCustomerEvent::TYPE_ADD_TO_CART,
            'event_category' => CoreCustomerEvent::CATEGORY_ECOMMERCE,
            'page_url' => $data['page_url'] ?? null,
            'gclid' => $data['gclid'] ?? null,
            'fbclid' => $data['fbclid'] ?? null,
            'ttclid' => $data['ttclid'] ?? null,
            'li_fat_id' => $data['li_fat_id'] ?? null,
            'conversion_value' => $data['value'] ?? 0,
            'currency' => $data['currency'] ?? 'USD',
            'event_data' => $data['event_data'] ?? [],
            'ip_address' => $data['ip_address'] ?? null,
        ]);

        // Update customer cart abandonment tracking
        if ($customer) {
            $customer->update([
                'has_cart_abandoned' => true,
                'last_cart_abandoned_at' => now(),
            ]);
        }

        // Dual tracking for add to cart
        $this->sendToTenantPlatforms($event, $customer, $data, 'add_to_cart');
        $this->sendToPlatformAccounts($event, $customer, 'add_to_cart');

        return $event;
    }

    /**
     * Track begin checkout event
     */
    public function trackBeginCheckout(array $data): CoreCustomerEvent
    {
        $session = $this->getOrCreateSession($data);
        $customer = $this->getOrCreateCustomer($data, $session);

        $event = $this->createEvent([
            'core_customer_id' => $customer?->id,
            'tenant_id' => $data['tenant_id'] ?? null,
            'session_id' => $session->id,
            'event_type' => CoreCustomerEvent::TYPE_BEGIN_CHECKOUT,
            'event_category' => CoreCustomerEvent::CATEGORY_ECOMMERCE,
            'page_url' => $data['page_url'] ?? null,
            'gclid' => $data['gclid'] ?? null,
            'fbclid' => $data['fbclid'] ?? null,
            'ttclid' => $data['ttclid'] ?? null,
            'li_fat_id' => $data['li_fat_id'] ?? null,
            'conversion_value' => $data['value'] ?? 0,
            'currency' => $data['currency'] ?? 'USD',
            'event_data' => $data['event_data'] ?? [],
            'ip_address' => $data['ip_address'] ?? null,
        ]);

        // Dual tracking
        $this->sendToTenantPlatforms($event, $customer, $data, 'begin_checkout');
        $this->sendToPlatformAccounts($event, $customer, 'begin_checkout');

        return $event;
    }

    /**
     * Track sign up event
     */
    public function trackSignUp(array $data): CoreCustomerEvent
    {
        $session = $this->getOrCreateSession($data);
        $customer = $this->getOrCreateCustomer($data, $session);

        $event = $this->createEvent([
            'core_customer_id' => $customer?->id,
            'tenant_id' => $data['tenant_id'] ?? null,
            'session_id' => $session->id,
            'event_type' => CoreCustomerEvent::TYPE_SIGN_UP,
            'event_category' => CoreCustomerEvent::CATEGORY_USER,
            'page_url' => $data['page_url'] ?? null,
            'gclid' => $data['gclid'] ?? null,
            'fbclid' => $data['fbclid'] ?? null,
            'ttclid' => $data['ttclid'] ?? null,
            'li_fat_id' => $data['li_fat_id'] ?? null,
            'is_converted' => true,
            'event_data' => $data['event_data'] ?? [],
            'ip_address' => $data['ip_address'] ?? null,
        ]);

        // Dual tracking
        $this->sendToTenantPlatforms($event, $customer, $data, 'sign_up');
        $this->sendToPlatformAccounts($event, $customer, 'sign_up');

        return $event;
    }

    /**
     * Track custom event
     */
    public function trackEvent(string $eventType, array $data): CoreCustomerEvent
    {
        $session = $this->getOrCreateSession($data);
        $customer = $this->getOrCreateCustomer($data, $session);

        return $this->createEvent([
            'core_customer_id' => $customer?->id,
            'tenant_id' => $data['tenant_id'] ?? null,
            'session_id' => $session->id,
            'event_type' => $eventType,
            'event_category' => $data['event_category'] ?? CoreCustomerEvent::CATEGORY_CUSTOM,
            'page_url' => $data['page_url'] ?? null,
            'gclid' => $data['gclid'] ?? null,
            'fbclid' => $data['fbclid'] ?? null,
            'ttclid' => $data['ttclid'] ?? null,
            'li_fat_id' => $data['li_fat_id'] ?? null,
            'conversion_value' => $data['value'] ?? null,
            'event_data' => $data['event_data'] ?? [],
            'ip_address' => $data['ip_address'] ?? null,
            'time_on_page' => $data['time_on_page'] ?? null,
            'scroll_depth' => $data['scroll_depth'] ?? null,
        ]);
    }

    /**
     * Send conversions to tenant's connected ad platforms
     */
    protected function sendToTenantPlatforms(
        CoreCustomerEvent $event,
        ?CoreCustomer $customer,
        array $data,
        string $conversionType = 'purchase'
    ): void {
        if (!$event->tenant_id) {
            return;
        }

        try {
            $tenant = Tenant::find($event->tenant_id);
            if (!$tenant) {
                return;
            }

            // Check for Google Ads connection
            if ($event->gclid && class_exists(GoogleAdsService::class)) {
                $this->sendToTenantGoogleAds($tenant, $event, $customer, $conversionType);
            }

            // Check for Facebook CAPI connection
            if ($event->fbclid && class_exists(FacebookCapiService::class)) {
                $this->sendToTenantFacebook($tenant, $event, $customer, $conversionType);
            }

            // Check for TikTok connection
            if ($event->ttclid && class_exists(TikTokAdsService::class)) {
                $this->sendToTenantTikTok($tenant, $event, $customer, $conversionType);
            }

            // Check for LinkedIn connection
            if ($event->li_fat_id && class_exists(LinkedInAdsService::class)) {
                $this->sendToTenantLinkedIn($tenant, $event, $customer, $conversionType);
            }

        } catch (Exception $e) {
            Log::error('Failed to send to tenant platforms', [
                'event_id' => $event->id,
                'tenant_id' => $event->tenant_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send conversions to platform (core admin) ad accounts - DUAL TRACKING
     */
    protected function sendToPlatformAccounts(
        CoreCustomerEvent $event,
        ?CoreCustomer $customer,
        string $conversionType = 'purchase'
    ): void {
        $platformAccounts = PlatformAdAccount::active()->get();

        foreach ($platformAccounts as $account) {
            try {
                // Create pending conversion record
                $conversion = PlatformConversion::createFromEvent(
                    $account,
                    $event,
                    $customer,
                    $conversionType
                );

                // Send based on platform type
                switch ($account->platform) {
                    case PlatformAdAccount::PLATFORM_GOOGLE_ADS:
                        $this->sendToPlatformGoogle($account, $conversion, $event, $customer);
                        break;

                    case PlatformAdAccount::PLATFORM_FACEBOOK:
                        $this->sendToPlatformFacebook($account, $conversion, $event, $customer);
                        break;

                    case PlatformAdAccount::PLATFORM_TIKTOK:
                        $this->sendToPlatformTikTok($account, $conversion, $event, $customer);
                        break;

                    case PlatformAdAccount::PLATFORM_LINKEDIN:
                        $this->sendToPlatformLinkedIn($account, $conversion, $event, $customer);
                        break;
                }

            } catch (Exception $e) {
                Log::error('Failed to send to platform account', [
                    'account_id' => $account->id,
                    'platform' => $account->platform,
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Send conversion to platform's Google Ads account
     */
    protected function sendToPlatformGoogle(
        PlatformAdAccount $account,
        PlatformConversion $conversion,
        CoreCustomerEvent $event,
        ?CoreCustomer $customer
    ): void {
        // Build enhanced conversion data
        $conversionData = [
            'conversion_action' => $account->conversion_action_ids[0] ?? null,
            'conversion_date_time' => now()->format('Y-m-d H:i:sP'),
            'conversion_value' => $conversion->value,
            'currency_code' => $conversion->currency,
            'order_id' => $event->order_id,
            'gclid' => $event->gclid,
            'user_identifiers' => [],
        ];

        // Add hashed user data for enhanced conversions
        if ($customer) {
            if ($customer->email_hash) {
                $conversionData['user_identifiers'][] = [
                    'hashed_email' => $customer->email_hash,
                ];
            }
            if ($customer->phone_hash) {
                $conversionData['user_identifiers'][] = [
                    'hashed_phone_number' => $customer->phone_hash,
                ];
            }
        }

        // Queue for batch sending (Google Ads prefers batching)
        $conversion->update([
            'event_data' => $conversionData,
            'status' => PlatformConversion::STATUS_PENDING,
        ]);

        // In production, this would be handled by a job
        // For now, mark as sent
        $conversion->markSent(['queued' => true]);

        Log::info('Queued conversion for platform Google Ads', [
            'account_id' => $account->id,
            'conversion_id' => $conversion->id,
        ]);
    }

    /**
     * Send conversion to platform's Facebook account
     */
    protected function sendToPlatformFacebook(
        PlatformAdAccount $account,
        PlatformConversion $conversion,
        CoreCustomerEvent $event,
        ?CoreCustomer $customer
    ): void {
        $eventData = [
            'event_name' => $this->mapToFacebookEvent($conversion->conversion_type),
            'event_time' => now()->timestamp,
            'event_id' => 'platform_' . $conversion->id,
            'event_source_url' => $event->page_url,
            'action_source' => 'website',
            'user_data' => [
                'client_ip_address' => $event->ip_address,
                'client_user_agent' => $event->event_data['user_agent'] ?? null,
                'fbc' => $event->fbclid ? 'fb.1.' . time() . '.' . $event->fbclid : null,
            ],
            'custom_data' => [
                'currency' => $conversion->currency,
                'value' => (float) $conversion->value,
                'order_id' => $event->order_id,
            ],
        ];

        if ($customer) {
            if ($customer->email_hash) {
                $eventData['user_data']['em'] = [$customer->email_hash];
            }
            if ($customer->phone_hash) {
                $eventData['user_data']['ph'] = [$customer->phone_hash];
            }
        }

        $conversion->update([
            'event_data' => $eventData,
        ]);

        $conversion->markSent(['queued' => true]);

        Log::info('Queued conversion for platform Facebook', [
            'account_id' => $account->id,
            'conversion_id' => $conversion->id,
        ]);
    }

    /**
     * Send conversion to platform's TikTok account
     */
    protected function sendToPlatformTikTok(
        PlatformAdAccount $account,
        PlatformConversion $conversion,
        CoreCustomerEvent $event,
        ?CoreCustomer $customer
    ): void {
        $eventData = [
            'event' => $this->mapToTikTokEvent($conversion->conversion_type),
            'event_time' => now()->timestamp,
            'event_id' => 'platform_' . $conversion->id,
            'page' => [
                'url' => $event->page_url,
            ],
            'user' => [
                'ip' => $event->ip_address,
                'user_agent' => $event->event_data['user_agent'] ?? null,
                'ttclid' => $event->ttclid,
            ],
            'properties' => [
                'currency' => $conversion->currency,
                'value' => (float) $conversion->value,
                'order_id' => $event->order_id,
            ],
        ];

        if ($customer) {
            if ($customer->email_hash) {
                $eventData['user']['email'] = $customer->email_hash;
            }
            if ($customer->phone_hash) {
                $eventData['user']['phone'] = $customer->phone_hash;
            }
        }

        $conversion->update([
            'event_data' => $eventData,
        ]);

        $conversion->markSent(['queued' => true]);

        Log::info('Queued conversion for platform TikTok', [
            'account_id' => $account->id,
            'conversion_id' => $conversion->id,
        ]);
    }

    /**
     * Send conversion to platform's LinkedIn account
     */
    protected function sendToPlatformLinkedIn(
        PlatformAdAccount $account,
        PlatformConversion $conversion,
        CoreCustomerEvent $event,
        ?CoreCustomer $customer
    ): void {
        $eventData = [
            'conversion' => $this->mapToLinkedInConversion($conversion->conversion_type),
            'conversionHappenedAt' => now()->timestamp * 1000,
            'conversionValue' => [
                'currencyCode' => $conversion->currency,
                'amount' => (string) ($conversion->value * 100), // LinkedIn uses cents
            ],
            'eventId' => 'platform_' . $conversion->id,
            'user' => [
                'userIds' => [],
                'userInfo' => [
                    'firstName' => $customer?->getHashedDataForAds()['fn'] ?? null,
                    'lastName' => $customer?->getHashedDataForAds()['ln'] ?? null,
                ],
            ],
        ];

        if ($customer?->email_hash) {
            $eventData['user']['userIds'][] = [
                'idType' => 'SHA256_EMAIL',
                'idValue' => $customer->email_hash,
            ];
        }

        if ($event->li_fat_id) {
            $eventData['user']['userIds'][] = [
                'idType' => 'LINKEDIN_FIRST_PARTY_ADS_TRACKING_UUID',
                'idValue' => $event->li_fat_id,
            ];
        }

        $conversion->update([
            'event_data' => $eventData,
        ]);

        $conversion->markSent(['queued' => true]);

        Log::info('Queued conversion for platform LinkedIn', [
            'account_id' => $account->id,
            'conversion_id' => $conversion->id,
        ]);
    }

    /**
     * Tenant-specific platform sending methods
     */
    protected function sendToTenantGoogleAds(Tenant $tenant, CoreCustomerEvent $event, ?CoreCustomer $customer, string $type): void
    {
        // Implementation would use tenant's GoogleAdsConnection
        Log::info('Would send to tenant Google Ads', ['tenant_id' => $tenant->id, 'type' => $type]);
    }

    protected function sendToTenantFacebook(Tenant $tenant, CoreCustomerEvent $event, ?CoreCustomer $customer, string $type): void
    {
        Log::info('Would send to tenant Facebook', ['tenant_id' => $tenant->id, 'type' => $type]);
    }

    protected function sendToTenantTikTok(Tenant $tenant, CoreCustomerEvent $event, ?CoreCustomer $customer, string $type): void
    {
        Log::info('Would send to tenant TikTok', ['tenant_id' => $tenant->id, 'type' => $type]);
    }

    protected function sendToTenantLinkedIn(Tenant $tenant, CoreCustomerEvent $event, ?CoreCustomer $customer, string $type): void
    {
        Log::info('Would send to tenant LinkedIn', ['tenant_id' => $tenant->id, 'type' => $type]);
    }

    /**
     * Event type mapping helpers
     */
    protected function mapToFacebookEvent(string $type): string
    {
        return match ($type) {
            'purchase' => 'Purchase',
            'add_to_cart' => 'AddToCart',
            'begin_checkout' => 'InitiateCheckout',
            'sign_up' => 'CompleteRegistration',
            'lead' => 'Lead',
            'view_content' => 'ViewContent',
            default => 'CustomEvent',
        };
    }

    protected function mapToTikTokEvent(string $type): string
    {
        return match ($type) {
            'purchase' => 'CompletePayment',
            'add_to_cart' => 'AddToCart',
            'begin_checkout' => 'InitiateCheckout',
            'sign_up' => 'CompleteRegistration',
            'lead' => 'SubmitForm',
            'view_content' => 'ViewContent',
            default => 'CustomEvent',
        };
    }

    protected function mapToLinkedInConversion(string $type): string
    {
        return match ($type) {
            'purchase' => 'PURCHASE',
            'add_to_cart' => 'ADD_TO_CART',
            'begin_checkout' => 'START_CHECKOUT',
            'sign_up' => 'SIGN_UP',
            'lead' => 'LEAD',
            default => 'OTHER',
        };
    }

    /**
     * Session management
     */
    protected function getOrCreateSession(array $data): CoreSession
    {
        $sessionToken = $data['session_token'] ?? null;

        if ($sessionToken) {
            $session = CoreSession::where('session_token', $sessionToken)->first();
            if ($session && $session->isActive()) {
                $session->recordActivity();
                return $session;
            }
        }

        // Create new session
        $sessionId = $sessionToken ?? Str::uuid()->toString();

        return CoreSession::create([
            'session_id' => $sessionId,
            'tenant_id' => $data['tenant_id'] ?? null,
            'visitor_id' => $data['visitor_id'] ?? null,
            'started_at' => now(),
            'landing_page' => $data['page_url'] ?? null,
            'landing_page_type' => $data['page_type'] ?? null,
            'source' => $data['source'] ?? null,
            'medium' => $data['medium'] ?? null,
            'campaign' => $data['campaign'] ?? null,
            'referrer' => $data['referrer'] ?? null,
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'gclid' => $data['gclid'] ?? null,
            'fbclid' => $data['fbclid'] ?? null,
            'ttclid' => $data['ttclid'] ?? null,
            'device_type' => $data['device_type'] ?? null,
            'browser' => $data['browser'] ?? null,
            'os' => $data['os'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'city' => $data['city'] ?? null,
            'pageviews' => 0,
            'events' => 0,
        ]);
    }

    /**
     * Customer management
     */
    protected function getOrCreateCustomer(array $data, CoreSession $session): ?CoreCustomer
    {
        $email = $data['email'] ?? null;
        $visitorId = $data['visitor_id'] ?? $session->visitor_id;

        if (!$email && !$visitorId) {
            return null;
        }

        // Try to find by email first
        if ($email) {
            $customer = CoreCustomer::findByEmail($email);
            if ($customer) {
                // Update with new data
                $this->updateCustomerData($customer, $data);
                return $customer;
            }
        }

        // Try to find by visitor_id
        if ($visitorId) {
            $customer = CoreCustomer::where('visitor_id', $visitorId)->first();
            if ($customer) {
                // If we now have email, update it
                if ($email && !$customer->email) {
                    $customer->email = $email;
                    $customer->email_hash = hash('sha256', strtolower(trim($email)));
                    $customer->save();
                }
                $this->updateCustomerData($customer, $data);
                return $customer;
            }
        }

        // Create new customer
        return $this->createCustomer($data, $session);
    }

    protected function createCustomer(array $data, CoreSession $session): CoreCustomer
    {
        $email = $data['email'] ?? null;

        $customerData = [
            'visitor_id' => $data['visitor_id'] ?? $session->visitor_id ?? Str::uuid()->toString(),
            'tenant_id' => $data['tenant_id'] ?? null,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'first_referrer' => $data['referrer'] ?? null,
            'first_utm_source' => $data['utm_source'] ?? null,
            'first_utm_medium' => $data['utm_medium'] ?? null,
            'first_utm_campaign' => $data['utm_campaign'] ?? null,
            'first_landing_page' => $data['page_url'] ?? null,
            'device_type' => $data['device_type'] ?? null,
            'browser' => $data['browser'] ?? null,
            'os' => $data['os'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'region' => $data['region'] ?? null,
            'city' => $data['city'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'total_visits' => 1,
            'total_pageviews' => 1,
        ];

        if ($email) {
            $customerData['email'] = $email;
            $customerData['email_hash'] = hash('sha256', strtolower(trim($email)));
        }

        if (!empty($data['phone'])) {
            $customerData['phone'] = $data['phone'];
            $customerData['phone_hash'] = hash('sha256', preg_replace('/[^0-9]/', '', $data['phone']));
        }

        if (!empty($data['first_name'])) {
            $customerData['first_name'] = $data['first_name'];
        }

        if (!empty($data['last_name'])) {
            $customerData['last_name'] = $data['last_name'];
        }

        // Store click IDs for attribution
        if (!empty($data['gclid'])) {
            $customerData['first_gclid'] = $data['gclid'];
            $customerData['last_gclid'] = $data['gclid'];
        }

        if (!empty($data['fbclid'])) {
            $customerData['first_fbclid'] = $data['fbclid'];
            $customerData['last_fbclid'] = $data['fbclid'];
        }

        if (!empty($data['ttclid'])) {
            $customerData['first_ttclid'] = $data['ttclid'];
            $customerData['last_ttclid'] = $data['ttclid'];
        }

        if (!empty($data['li_fat_id'])) {
            $customerData['first_li_fat_id'] = $data['li_fat_id'];
            $customerData['last_li_fat_id'] = $data['li_fat_id'];
        }

        return CoreCustomer::create($customerData);
    }

    protected function updateCustomerData(CoreCustomer $customer, array $data): void
    {
        $updates = [
            'last_seen_at' => now(),
        ];

        // Update last click IDs
        if (!empty($data['gclid'])) {
            $updates['last_gclid'] = $data['gclid'];
            if (!$customer->first_gclid) {
                $updates['first_gclid'] = $data['gclid'];
            }
        }

        if (!empty($data['fbclid'])) {
            $updates['last_fbclid'] = $data['fbclid'];
            if (!$customer->first_fbclid) {
                $updates['first_fbclid'] = $data['fbclid'];
            }
        }

        if (!empty($data['ttclid'])) {
            $updates['last_ttclid'] = $data['ttclid'];
            if (!$customer->first_ttclid) {
                $updates['first_ttclid'] = $data['ttclid'];
            }
        }

        if (!empty($data['li_fat_id'])) {
            $updates['last_li_fat_id'] = $data['li_fat_id'];
            if (!$customer->first_li_fat_id) {
                $updates['first_li_fat_id'] = $data['li_fat_id'];
            }
        }

        // Update personal info if provided and not already set
        if (!empty($data['first_name']) && !$customer->first_name) {
            $updates['first_name'] = $data['first_name'];
        }

        if (!empty($data['last_name']) && !$customer->last_name) {
            $updates['last_name'] = $data['last_name'];
        }

        if (!empty($data['phone']) && !$customer->phone) {
            $updates['phone'] = $data['phone'];
            $updates['phone_hash'] = hash('sha256', preg_replace('/[^0-9]/', '', $data['phone']));
        }

        $customer->update($updates);
    }

    /**
     * Create event record
     */
    protected function createEvent(array $data): CoreCustomerEvent
    {
        $data['created_at'] = now();
        return CoreCustomerEvent::create($data);
    }

    /**
     * Get real-time analytics data
     */
    public function getRealTimeStats(?int $tenantId = null): array
    {
        $baseQuery = CoreSession::query();
        $eventQuery = CoreCustomerEvent::query();

        if ($tenantId) {
            $baseQuery->forTenant($tenantId);
            $eventQuery->forTenant($tenantId);
        }

        // Active visitors (sessions active in last 30 minutes)
        $activeVisitors = (clone $baseQuery)->active()->count();

        // Today's stats
        $todayPageViews = (clone $eventQuery)->today()->pageViews()->count();
        $todaySessions = (clone $baseQuery)->today()->count();
        $todayPurchases = (clone $eventQuery)->today()->purchases()->count();
        $todayRevenue = (clone $eventQuery)->today()->purchases()->sum('conversion_value');

        // Conversion rate
        $conversionRate = $todaySessions > 0
            ? round(($todayPurchases / $todaySessions) * 100, 2)
            : 0;

        // Traffic sources (last 24 hours)
        $trafficSources = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->lastHours(24)
            ->selectRaw("
                CASE
                    WHEN gclid IS NOT NULL THEN 'Google Ads'
                    WHEN fbclid IS NOT NULL THEN 'Facebook Ads'
                    WHEN ttclid IS NOT NULL THEN 'TikTok Ads'
                    WHEN utm_source IS NOT NULL THEN CONCAT(UPPER(SUBSTRING(utm_source, 1, 1)), LOWER(SUBSTRING(utm_source, 2)))
                    WHEN referrer IS NOT NULL AND referrer != '' THEN 'Referral'
                    ELSE 'Direct'
                END as source,
                COUNT(*) as count
            ")
            ->groupBy('source')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'source')
            ->toArray();

        // Device breakdown
        $devices = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->today()
            ->selectRaw('device_type, COUNT(*) as count')
            ->groupBy('device_type')
            ->pluck('count', 'device_type')
            ->toArray();

        // Recent events (last 50)
        $recentEvents = CoreCustomerEvent::with('coreCustomer')
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($event) => [
                'id' => $event->id,
                'type' => $event->event_type,
                'category' => $event->event_category,
                'page_url' => $event->page_url,
                'value' => $event->conversion_value,
                'source' => $event->getAttributionSource(),
                'location' => implode(', ', array_filter([$event->city, $event->country_code])),
                'created_at' => $event->created_at->toIso8601String(),
                'time_ago' => $event->created_at->diffForHumans(),
            ]);

        // Top pages today
        $topPages = CoreCustomerEvent::pageViews()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->today()
            ->selectRaw('page_url, page_title, COUNT(*) as views')
            ->groupBy('page_url', 'page_title')
            ->orderByDesc('views')
            ->limit(10)
            ->get();

        // Geographic distribution
        $geoDistribution = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->today()
            ->whereNotNull('country_code')
            ->selectRaw('country_code, COUNT(*) as visitors')
            ->groupBy('country_code')
            ->orderByDesc('visitors')
            ->limit(10)
            ->get();

        return [
            'active_visitors' => $activeVisitors,
            'today' => [
                'page_views' => $todayPageViews,
                'sessions' => $todaySessions,
                'purchases' => $todayPurchases,
                'revenue' => $todayRevenue,
                'conversion_rate' => $conversionRate,
            ],
            'traffic_sources' => $trafficSources,
            'devices' => $devices,
            'recent_events' => $recentEvents,
            'top_pages' => $topPages,
            'geo_distribution' => $geoDistribution,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Sync audience to ad platform
     */
    public function syncAudience(PlatformAudience $audience): array
    {
        $audience->markSyncing();

        try {
            $customers = $audience->getCustomersQuery()->get();
            $memberCount = $customers->count();
            $matchedCount = 0;

            // Clear existing members
            PlatformAudienceMember::forAudience($audience->id)->delete();

            // Add all matching customers
            foreach ($customers as $customer) {
                $member = PlatformAudienceMember::createFromCustomer($audience, $customer);

                // In production, we'd check with the platform if the user was matched
                // For now, assume users with email are matchable
                if ($customer->email_hash) {
                    $member->markMatched();
                    $matchedCount++;
                }
            }

            $audience->markSynced($memberCount, $matchedCount);

            return [
                'success' => true,
                'member_count' => $memberCount,
                'matched_count' => $matchedCount,
                'match_rate' => $memberCount > 0 ? round(($matchedCount / $memberCount) * 100, 1) : 0,
            ];

        } catch (Exception $e) {
            $audience->markError($e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process pending conversions (for batch sending)
     */
    public function processPendingConversions(): array
    {
        $pending = PlatformConversion::pending()
            ->with(['platformAdAccount', 'coreCustomer'])
            ->limit(1000)
            ->get();

        $results = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'by_platform' => [],
        ];

        foreach ($pending as $conversion) {
            try {
                // In production, this would actually send to the platform API
                // For now, just mark as sent
                $conversion->markSent(['batch_processed' => true]);
                $results['success']++;

                $platform = $conversion->platformAdAccount->platform ?? 'unknown';
                $results['by_platform'][$platform] = ($results['by_platform'][$platform] ?? 0) + 1;

            } catch (Exception $e) {
                $conversion->markFailed($e->getMessage());
                $results['failed']++;
            }

            $results['processed']++;
        }

        return $results;
    }
}
