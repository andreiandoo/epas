<?php

namespace App\Services\Tracking\Providers;

class MetaPixelProvider implements TrackingProviderInterface
{
    public function getName(): string
    {
        return 'Meta Pixel (Facebook)';
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
<!-- Meta Pixel Code -->
<script{$nonceAttr}>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;{$nonceAttr ? "t.setAttribute('nonce','{$nonce}');" : ""}s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '{$pixelId}');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id={$pixelId}&ev=PageView&noscript=1"
/></noscript>
<!-- End Meta Pixel Code -->
HTML;
    }

    public function injectBodyEnd(array $settings, ?string $nonce = null): string
    {
        // Meta Pixel is injected in head only
        return '';
    }

    public function getDataLayerAdapter(): string
    {
        return <<<'JS'
// Meta Pixel Data Layer Adapter
(function() {
  if (typeof fbq === 'undefined') return;

  // Listen to tracking events
  window.addEventListener('tracking:pageview', function(e) {
    fbq('track', 'PageView');
  });

  window.addEventListener('tracking:view_item', function(e) {
    fbq('track', 'ViewContent', {
      content_ids: e.detail.items ? e.detail.items.map(i => i.item_id) : [],
      content_type: 'product',
      value: e.detail.value || 0,
      currency: e.detail.currency || 'EUR'
    });
  });

  window.addEventListener('tracking:add_to_cart', function(e) {
    fbq('track', 'AddToCart', {
      content_ids: e.detail.items ? e.detail.items.map(i => i.item_id) : [],
      content_type: 'product',
      value: e.detail.value || 0,
      currency: e.detail.currency || 'EUR'
    });
  });

  window.addEventListener('tracking:begin_checkout', function(e) {
    fbq('track', 'InitiateCheckout', {
      content_ids: e.detail.items ? e.detail.items.map(i => i.item_id) : [],
      content_type: 'product',
      value: e.detail.value || 0,
      currency: e.detail.currency || 'EUR',
      num_items: e.detail.items ? e.detail.items.length : 0
    });
  });

  window.addEventListener('tracking:purchase', function(e) {
    fbq('track', 'Purchase', {
      content_ids: e.detail.items ? e.detail.items.map(i => i.item_id) : [],
      content_type: 'product',
      value: e.detail.value || 0,
      currency: e.detail.currency || 'EUR',
      transaction_id: e.detail.transaction_id || ''
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
        } elseif (!preg_match('/^\d+$/', $settings['pixel_id'])) {
            $errors['pixel_id'] = 'Invalid Pixel ID format (should be numeric)';
        }

        return $errors;
    }
}
