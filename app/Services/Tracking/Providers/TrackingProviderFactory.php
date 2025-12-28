<?php

namespace App\Services\Tracking\Providers;

class TrackingProviderFactory
{
    /**
     * Create a tracking provider instance
     *
     * @param string $provider Provider type (ga4, gtm, meta, tiktok)
     * @return TrackingProviderInterface
     * @throws \Exception
     */
    public static function make(string $provider): TrackingProviderInterface
    {
        return match ($provider) {
            'ga4' => new Ga4Provider(),
            'gtm' => new GtmProvider(),
            'meta' => new MetaPixelProvider(),
            'tiktok' => new TikTokPixelProvider(),
            default => throw new \Exception("Unsupported tracking provider: {$provider}"),
        };
    }

    /**
     * Get all available providers
     *
     * @return array
     */
    public static function getAvailableProviders(): array
    {
        return [
            'ga4' => [
                'name' => 'Google Analytics 4',
                'description' => 'Track website traffic and user behavior with GA4',
                'consent_category' => 'analytics',
                'fields' => [
                    'measurement_id' => [
                        'label' => 'Measurement ID',
                        'type' => 'text',
                        'placeholder' => 'G-XXXXXXXXXX',
                        'required' => true,
                        'help' => 'Your GA4 Measurement ID (found in GA4 Admin → Data Streams)',
                    ],
                ],
            ],
            'gtm' => [
                'name' => 'Google Tag Manager',
                'description' => 'Manage all your marketing tags in one place',
                'consent_category' => 'analytics',
                'fields' => [
                    'container_id' => [
                        'label' => 'Container ID',
                        'type' => 'text',
                        'placeholder' => 'GTM-XXXXXX',
                        'required' => true,
                        'help' => 'Your GTM Container ID (found in GTM Admin → Container Settings)',
                    ],
                ],
            ],
            'meta' => [
                'name' => 'Meta Pixel (Facebook)',
                'description' => 'Track conversions and build audiences for Facebook Ads',
                'consent_category' => 'marketing',
                'fields' => [
                    'pixel_id' => [
                        'label' => 'Pixel ID',
                        'type' => 'text',
                        'placeholder' => '1234567890123456',
                        'required' => true,
                        'help' => 'Your Meta Pixel ID (found in Facebook Events Manager)',
                    ],
                ],
            ],
            'tiktok' => [
                'name' => 'TikTok Pixel',
                'description' => 'Track conversions and optimize TikTok Ads campaigns',
                'consent_category' => 'marketing',
                'fields' => [
                    'pixel_id' => [
                        'label' => 'Pixel ID',
                        'type' => 'text',
                        'placeholder' => 'C12ABC34DEF56GHI7JKL',
                        'required' => true,
                        'help' => 'Your TikTok Pixel ID (found in TikTok Ads Manager → Assets → Events)',
                    ],
                ],
            ],
        ];
    }

    /**
     * Validate provider settings
     *
     * @param string $provider Provider type
     * @param array $settings Settings to validate
     * @return array Validation errors
     */
    public static function validateSettings(string $provider, array $settings): array
    {
        try {
            $instance = self::make($provider);
            return $instance->validateSettings($settings);
        } catch (\Exception $e) {
            return ['provider' => $e->getMessage()];
        }
    }
}
