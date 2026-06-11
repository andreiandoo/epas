<?php

namespace App\Services\Tracking\Providers;

class Ga4Provider implements TrackingProviderInterface
{
    public function getName(): string
    {
        return 'Google Analytics 4';
    }

    public function getConsentCategory(): string
    {
        return 'analytics';
    }

    public function injectHead(array $settings, ?string $nonce = null): string
    {
        $measurementId = $settings['measurement_id'] ?? null;

        if (!$measurementId) {
            return '';
        }

        $nonceAttr = $nonce ? " nonce=\"{$nonce}\"" : '';

        return <<<HTML
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$measurementId}"{$nonceAttr}></script>
<script{$nonceAttr}>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{$measurementId}', {
    'anonymize_ip': true,
    'cookie_flags': 'SameSite=None;Secure'
  });
</script>
HTML;
    }

    public function injectBodyEnd(array $settings, ?string $nonce = null): string
    {
        // GA4 is injected in head only
        return '';
    }

    public function getDataLayerAdapter(): string
    {
        return <<<'JS'
// GA4 Data Layer Adapter
(function() {
  if (typeof gtag === 'undefined') return;

  // Listen to tracking events
  window.addEventListener('tracking:pageview', function(e) {
    gtag('event', 'page_view', e.detail);
  });

  window.addEventListener('tracking:view_item', function(e) {
    gtag('event', 'view_item', {
      currency: e.detail.currency || 'EUR',
      value: e.detail.value || 0,
      items: e.detail.items || []
    });
  });

  window.addEventListener('tracking:add_to_cart', function(e) {
    gtag('event', 'add_to_cart', {
      currency: e.detail.currency || 'EUR',
      value: e.detail.value || 0,
      items: e.detail.items || []
    });
  });

  window.addEventListener('tracking:begin_checkout', function(e) {
    gtag('event', 'begin_checkout', {
      currency: e.detail.currency || 'EUR',
      value: e.detail.value || 0,
      items: e.detail.items || []
    });
  });

  window.addEventListener('tracking:purchase', function(e) {
    gtag('event', 'purchase', {
      transaction_id: e.detail.transaction_id || '',
      value: e.detail.value || 0,
      currency: e.detail.currency || 'EUR',
      tax: e.detail.tax || 0,
      shipping: e.detail.shipping || 0,
      items: e.detail.items || []
    });
  });
})();
JS;
    }

    public function validateSettings(array $settings): array
    {
        $errors = [];

        if (empty($settings['measurement_id'])) {
            $errors['measurement_id'] = 'Measurement ID is required';
        } elseif (!preg_match('/^G-[A-Z0-9]+$/', $settings['measurement_id'])) {
            $errors['measurement_id'] = 'Invalid Measurement ID format (should be G-XXXXXXXXXX)';
        }

        return $errors;
    }
}
