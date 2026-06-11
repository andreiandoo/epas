<?php

namespace App\Services\Tracking;

/**
 * Tracking Event Bus
 *
 * Helper service to generate JavaScript code for emitting tracking events
 * These events are then caught by provider adapters (GA4, GTM, Meta, TikTok)
 */
class TrackingEventBus
{
    /**
     * Generate JavaScript to emit a pageview event
     */
    public static function pageview(array $data = []): string
    {
        $json = json_encode($data);
        return "window.dispatchEvent(new CustomEvent('tracking:pageview', { detail: {$json} }));";
    }

    /**
     * Generate JavaScript to emit a view_item event
     *
     * @param array $data Should include: value, currency, items[]
     */
    public static function viewItem(array $data): string
    {
        $json = json_encode($data);
        return "window.dispatchEvent(new CustomEvent('tracking:view_item', { detail: {$json} }));";
    }

    /**
     * Generate JavaScript to emit an add_to_cart event
     *
     * @param array $data Should include: value, currency, items[]
     */
    public static function addToCart(array $data): string
    {
        $json = json_encode($data);
        return "window.dispatchEvent(new CustomEvent('tracking:add_to_cart', { detail: {$json} }));";
    }

    /**
     * Generate JavaScript to emit a begin_checkout event
     *
     * @param array $data Should include: value, currency, items[]
     */
    public static function beginCheckout(array $data): string
    {
        $json = json_encode($data);
        return "window.dispatchEvent(new CustomEvent('tracking:begin_checkout', { detail: {$json} }));";
    }

    /**
     * Generate JavaScript to emit a purchase event
     *
     * @param array $data Should include: transaction_id, value, currency, tax, shipping, items[]
     */
    public static function purchase(array $data): string
    {
        $json = json_encode($data);
        return "window.dispatchEvent(new CustomEvent('tracking:purchase', { detail: {$json} }));";
    }

    /**
     * Get JavaScript helper library for emitting events from frontend
     */
    public static function getHelperLibrary(): string
    {
        return <<<'JS'
// Tracking Event Bus Helper Library
window.TrackingEvents = {
  pageview: function(data) {
    window.dispatchEvent(new CustomEvent('tracking:pageview', { detail: data || {} }));
  },

  viewItem: function(data) {
    if (!data.items || !data.value) {
      console.warn('TrackingEvents.viewItem: items and value are required');
      return;
    }
    window.dispatchEvent(new CustomEvent('tracking:view_item', { detail: data }));
  },

  addToCart: function(data) {
    if (!data.items || !data.value) {
      console.warn('TrackingEvents.addToCart: items and value are required');
      return;
    }
    window.dispatchEvent(new CustomEvent('tracking:add_to_cart', { detail: data }));
  },

  beginCheckout: function(data) {
    if (!data.items || !data.value) {
      console.warn('TrackingEvents.beginCheckout: items and value are required');
      return;
    }
    window.dispatchEvent(new CustomEvent('tracking:begin_checkout', { detail: data }));
  },

  purchase: function(data) {
    if (!data.transaction_id || !data.value) {
      console.warn('TrackingEvents.purchase: transaction_id and value are required');
      return;
    }
    window.dispatchEvent(new CustomEvent('tracking:purchase', { detail: data }));
  }
};
JS;
    }

    /**
     * Format item for tracking (ecommerce item format)
     *
     * @param array $item Item data
     * @return array Formatted item
     */
    public static function formatItem(array $item): array
    {
        return [
            'item_id' => $item['id'] ?? $item['item_id'] ?? '',
            'item_name' => $item['name'] ?? $item['item_name'] ?? '',
            'price' => $item['price'] ?? 0,
            'quantity' => $item['quantity'] ?? 1,
            'item_category' => $item['category'] ?? $item['item_category'] ?? '',
            'item_variant' => $item['variant'] ?? $item['item_variant'] ?? '',
        ];
    }
}
