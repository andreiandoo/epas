<?php

namespace App\Services\Tracking\Providers;

class GtmProvider implements TrackingProviderInterface
{
    public function getName(): string
    {
        return 'Google Tag Manager';
    }

    public function getConsentCategory(): string
    {
        return 'analytics';
    }

    public function injectHead(array $settings, ?string $nonce = null): string
    {
        $containerId = $settings['container_id'] ?? null;

        if (!$containerId) {
            return '';
        }

        $nonceAttr = $nonce ? " nonce=\"{$nonce}\"" : '';

        return <<<HTML
<!-- Google Tag Manager -->
<script{$nonceAttr}>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;{$nonceAttr ? "j.setAttribute('nonce','{$nonce}');" : ""}f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{$containerId}');</script>
<!-- End Google Tag Manager -->
HTML;
    }

    public function injectBodyEnd(array $settings, ?string $nonce = null): string
    {
        $containerId = $settings['container_id'] ?? null;

        if (!$containerId) {
            return '';
        }

        return <<<HTML
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={$containerId}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
HTML;
    }

    public function getDataLayerAdapter(): string
    {
        return <<<'JS'
// GTM Data Layer Adapter
(function() {
  window.dataLayer = window.dataLayer || [];

  // Listen to tracking events and push to GTM dataLayer
  window.addEventListener('tracking:pageview', function(e) {
    dataLayer.push({
      'event': 'pageview',
      ...e.detail
    });
  });

  window.addEventListener('tracking:view_item', function(e) {
    dataLayer.push({
      'event': 'view_item',
      'ecommerce': {
        'currency': e.detail.currency || 'EUR',
        'value': e.detail.value || 0,
        'items': e.detail.items || []
      }
    });
  });

  window.addEventListener('tracking:add_to_cart', function(e) {
    dataLayer.push({
      'event': 'add_to_cart',
      'ecommerce': {
        'currency': e.detail.currency || 'EUR',
        'value': e.detail.value || 0,
        'items': e.detail.items || []
      }
    });
  });

  window.addEventListener('tracking:begin_checkout', function(e) {
    dataLayer.push({
      'event': 'begin_checkout',
      'ecommerce': {
        'currency': e.detail.currency || 'EUR',
        'value': e.detail.value || 0,
        'items': e.detail.items || []
      }
    });
  });

  window.addEventListener('tracking:purchase', function(e) {
    dataLayer.push({
      'event': 'purchase',
      'ecommerce': {
        'transaction_id': e.detail.transaction_id || '',
        'value': e.detail.value || 0,
        'currency': e.detail.currency || 'EUR',
        'tax': e.detail.tax || 0,
        'shipping': e.detail.shipping || 0,
        'items': e.detail.items || []
      }
    });
  });
})();
JS;
    }

    public function validateSettings(array $settings): array
    {
        $errors = [];

        if (empty($settings['container_id'])) {
            $errors['container_id'] = 'Container ID is required';
        } elseif (!preg_match('/^GTM-[A-Z0-9]+$/', $settings['container_id'])) {
            $errors['container_id'] = 'Invalid Container ID format (should be GTM-XXXXXX)';
        }

        return $errors;
    }
}
