/**
 * Promotion Routes
 * API routes for the Organizer Promotions Feature
 */

import { Router } from 'express';
import {
  PromotionController,
  PromotionOrderController,
  PromotionPaymentController,
} from '../controllers';

// Middleware types (implement based on your auth system)
type AuthMiddleware = (req: any, res: any, next: any) => void;
type OrganizerMiddleware = (req: any, res: any, next: any) => void;

/**
 * Create promotion routes
 */
export function createPromotionRoutes(
  promotionController: PromotionController,
  orderController: PromotionOrderController,
  paymentController: PromotionPaymentController,
  authMiddleware: AuthMiddleware,
  organizerMiddleware: OrganizerMiddleware
): Router {
  const router = Router();

  // ========================================
  // PUBLIC ROUTES (No auth required)
  // ========================================

  // Get all promotion types
  router.get('/types', promotionController.getAllPromotionTypes);

  // Get single promotion type
  router.get('/types/:typeId', promotionController.getPromotionTypeById);

  // Get options for a promotion type
  router.get('/types/:typeId/options', promotionController.getPromotionTypeOptions);

  // Get featured promotions
  router.get('/featured', promotionController.getFeaturedPromotions);

  // Search promotions
  router.get('/search', promotionController.searchPromotions);

  // Get available payment methods
  router.get('/payment-methods', paymentController.getPaymentMethods);

  // ========================================
  // AUTHENTICATED ROUTES (Auth required)
  // ========================================

  // Apply auth middleware to all routes below
  router.use(authMiddleware);
  router.use(organizerMiddleware);

  // ----- PRICING -----

  // Calculate pricing for items
  router.post('/pricing/calculate', promotionController.calculatePricing);

  // ----- EMAIL MARKETING -----

  // Get audience count
  router.get('/email/audience-count', promotionController.getAudienceCount);

  // Preview email cost
  router.post('/email/preview-cost', promotionController.previewEmailCost);

  // ----- ORDERS -----

  // Create new order
  router.post('/orders', orderController.createOrder);

  // Get all orders
  router.get('/orders', orderController.getOrders);

  // Get order statistics
  router.get('/statistics', orderController.getStatistics);

  // Get single order
  router.get('/orders/:orderId', orderController.getOrderById);

  // Update order
  router.patch('/orders/:orderId', orderController.updateOrder);

  // Cancel order
  router.delete('/orders/:orderId', orderController.cancelOrder);

  // Get order items
  router.get('/orders/:orderId/items', orderController.getOrderItems);

  // ----- PAYMENT -----

  // Initiate checkout
  router.post('/orders/:orderId/checkout', paymentController.initiateCheckout);

  // Confirm payment
  router.post('/orders/:orderId/confirm-payment', paymentController.confirmPayment);

  // Get invoice
  router.get('/orders/:orderId/invoice', paymentController.getInvoice);

  // Request refund
  router.post('/orders/:orderId/refund', paymentController.requestRefund);

  // ----- EMAIL CAMPAIGN CONFIGURATION -----

  // Configure email campaign for order item
  router.post(
    '/orders/:orderId/items/:itemId/email-campaign',
    orderController.configureEmailCampaign
  );

  // ----- AD TRACKING -----

  // Get OAuth URL for connecting platform
  router.get('/tracking/connect/:platform', orderController.getAdTrackingOAuthUrl);

  // Complete OAuth connection
  router.post('/tracking/connect', orderController.connectAdPlatform);

  // Get connected platforms
  router.get('/tracking/connections', orderController.getConnectedPlatforms);

  // Get tracked campaigns
  router.get('/tracking/campaigns', orderController.getTrackedCampaigns);

  // Get tracking analytics
  router.get('/tracking/analytics', orderController.getTrackingAnalytics);

  // Sync tracked campaigns
  router.post('/tracking/sync', orderController.syncTrackedCampaigns);

  // ----- AD CAMPAIGN CREATION -----

  // Create ad campaign request
  router.post(
    '/orders/:orderId/items/:itemId/ad-campaign-request',
    orderController.createAdCampaignRequest
  );

  // Get ad campaign requests
  router.get('/ad-campaigns', orderController.getAdCampaignRequests);

  // Get single ad campaign request
  router.get('/ad-campaigns/:requestId', orderController.getAdCampaignRequest);

  // Update ad campaign request
  router.patch('/ad-campaigns/:requestId', orderController.updateAdCampaignRequest);

  return router;
}

/**
 * Create webhook routes (separate router for payment webhooks)
 */
export function createWebhookRoutes(paymentController: PromotionPaymentController): Router {
  const router = Router();

  // Payment webhook (no auth - uses signature verification)
  router.post('/payment', paymentController.handlePaymentWebhook);

  return router;
}

export default createPromotionRoutes;
