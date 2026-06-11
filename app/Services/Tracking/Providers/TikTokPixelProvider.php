<?php

namespace App\Services\Tracking\Providers;

class TikTokPixelProvider implements TrackingProviderInterface
{
    public function getName(): string
    {
        return 'TikTok Pixel';
    }

    public function getConsentCategory(): string
    {
        return 'marketing';
    }

    public function injectHead(array $settings, ?string $nonce = null): string
    {
        $pixelId = $settings['pixel_id'] ?? null;

        if (!$pixelId) {
            return '';
        }

        $nonceAttr = $nonce ? " nonce=\"{$nonce}\"" : '';

        return <<<HTML
<!-- TikTok Pixel Code -->
<script{$nonceAttr}>
!function (w, d, t) {
  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;{$nonceAttr ? "o.setAttribute('nonce','{$nonce}');" : ""}var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};

  ttq.load('{$pixelId}');
  ttq.page();
}(window, document, 'ttq');
</script>
<!-- End TikTok Pixel Code -->
HTML;
    }

    public function injectBodyEnd(array $settings, ?string $nonce = null): string
    {
        // TikTok Pixel is injected in head only
        return '';
    }

    public function getDataLayerAdapter(): string
    {
        return <<<'JS'
// TikTok Pixel Data Layer Adapter
(function() {
  if (typeof ttq === 'undefined') return;

  // Listen to tracking events
  window.addEventListener('tracking:pageview', function(e) {
    ttq.page();
  });

  window.addEventListener('tracking:view_item', function(e) {
    ttq.track('ViewContent', {
      content_id: e.detail.items && e.detail.items[0] ? e.detail.items[0].item_id : '',
      content_type: 'product',
      value: e.detail.value || 0,
      currency: e.detail.currency || 'EUR'
    });
  });

  window.addEventListener('tracking:add_to_cart', function(e) {
    ttq.track('AddToCart', {
      content_id: e.detail.items && e.detail.items[0] ? e.detail.items[0].item_id : '',
      content_type: 'product',
      value: e.detail.value || 0,
      currency: e.detail.currency || 'EUR',
      quantity: e.detail.items ? e.detail.items.length : 1
    });
  });

  window.addEventListener('tracking:begin_checkout', function(e) {
    ttq.track('InitiateCheckout', {
      content_id: e.detail.items && e.detail.items[0] ? e.detail.items[0].item_id : '',
      content_type: 'product',
      value: e.detail.value || 0,
      currency: e.detail.currency || 'EUR',
      quantity: e.detail.items ? e.detail.items.length : 1
    });
  });

  window.addEventListener('tracking:purchase', function(e) {
    ttq.track('CompletePayment', {
      content_id: e.detail.items && e.detail.items[0] ? e.detail.items[0].item_id : '',
      content_type: 'product',
      value: e.detail.value || 0,
      currency: e.detail.currency || 'EUR',
      quantity: e.detail.items ? e.detail.items.length : 1
    });
  });
})();
JS;
    }

    public function validateSettings(array $settings): array
    {
        $errors = [];

        if (empty($settings['pixel_id'])) {
            $errors['pixel_id'] = 'Pixel ID is required';
        } elseif (!preg_match('/^[A-Z0-9]+$/', $settings['pixel_id'])) {
            $errors['pixel_id'] = 'Invalid Pixel ID format (should be alphanumeric)';
        }

        return $errors;
    }
}
