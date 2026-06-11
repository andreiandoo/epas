/**
 * PromotionPaymentController
 * Handles payment processing endpoints
 */

import { Request, Response, NextFunction } from 'express';
import { PromotionOrderService } from '../services';
import { CheckoutDTO, OrderStatus } from '../types/promotion.types';

// Payment gateway interface
interface PaymentGateway {
  createPaymentIntent(
    amount: number,
    currency: string,
    metadata: Record<string, any>
  ): Promise<{
    paymentIntentId: string;
    clientSecret: string;
    status: string;
  }>;

  confirmPayment(paymentIntentId: string): Promise<{
    status: string;
    paymentMethod: string;
  }>;

  refundPayment(
    paymentIntentId: string,
    amount?: number
  ): Promise<{
    refundId: string;
    status: string;
  }>;
}

export class PromotionPaymentController {
  private orderService: PromotionOrderService;
  private paymentGateway?: PaymentGateway;

  constructor(orderService: PromotionOrderService, paymentGateway?: PaymentGateway) {
    this.orderService = orderService;
    this.paymentGateway = paymentGateway;
  }

  /**
   * POST /api/organizer/promotions/orders/:orderId/checkout
   * Initialize checkout and create payment intent
   */
  initiateCheckout = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;
      const { orderId } = req.params;
      const dto: CheckoutDTO = req.body;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      // Get and validate order
      const order = await this.orderService.getOrderById(parseInt(orderId, 10), organizerId);

      if (!order) {
        res.status(404).json({
          success: false,
          error: 'Order not found',
        });
        return;
      }

      if (order.status !== OrderStatus.DRAFT) {
        res.status(400).json({
          success: false,
          error: 'Order is not in draft status',
        });
        return;
      }

      if (!order.items || order.items.length === 0) {
        res.status(400).json({
          success: false,
          error: 'Order must have at least one item',
        });
        return;
      }

      // Move order to pending payment
      const pendingOrder = await this.orderService.initiateCheckout(
        parseInt(orderId, 10),
        organizerId
      );

      // Create payment intent if payment gateway is configured
      let paymentIntent = null;

      if (this.paymentGateway) {
        paymentIntent = await this.paymentGateway.createPaymentIntent(
          pendingOrder.totalAmount,
          pendingOrder.currency,
          {
            orderId: pendingOrder.id,
            orderNumber: pendingOrder.orderNumber,
            organizerId,
          }
        );
      } else {
        // Mock payment intent for development
        paymentIntent = {
          paymentIntentId: `pi_mock_${Date.now()}`,
          clientSecret: `mock_secret_${Math.random().toString(36).substring(2)}`,
          status: 'requires_payment_method',
        };
      }

      res.json({
        success: true,
        data: {
          order: pendingOrder.toJSON(),
          payment: {
            paymentIntentId: paymentIntent.paymentIntentId,
            clientSecret: paymentIntent.clientSecret,
            amount: pendingOrder.totalAmount,
            currency: pendingOrder.currency,
            status: paymentIntent.status,
          },
        },
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * POST /api/organizer/promotions/orders/:orderId/confirm-payment
   * Confirm payment completion
   */
  confirmPayment = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;
      const { orderId } = req.params;
      const { paymentIntentId, paymentMethod } = req.body;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      if (!paymentIntentId) {
        res.status(400).json({
          success: false,
          error: 'paymentIntentId is required',
        });
        return;
      }

      // Get order
      const order = await this.orderService.getOrderById(parseInt(orderId, 10), organizerId);

      if (!order) {
        res.status(404).json({
          success: false,
          error: 'Order not found',
        });
        return;
      }

      if (order.status !== OrderStatus.PENDING_PAYMENT) {
        res.status(400).json({
          success: false,
          error: 'Order is not pending payment',
        });
        return;
      }

      // Verify payment with gateway
      let paymentStatus = 'succeeded';
      let confirmedPaymentMethod = paymentMethod || 'card';

      if (this.paymentGateway) {
        const confirmation = await this.paymentGateway.confirmPayment(paymentIntentId);
        paymentStatus = confirmation.status;
        confirmedPaymentMethod = confirmation.paymentMethod;

        if (paymentStatus !== 'succeeded') {
          res.status(400).json({
            success: false,
            error: 'Payment not completed',
            data: {
              status: paymentStatus,
            },
          });
          return;
        }
      }

      // Mark order as paid
      const paidOrder = await this.orderService.markOrderAsPaid(parseInt(orderId, 10), {
        paymentId: paymentIntentId,
        paymentProvider: this.paymentGateway ? 'stripe' : 'mock',
        paymentMethod: confirmedPaymentMethod,
      });

      res.json({
        success: true,
        data: paidOrder.toJSON(),
        message: 'Payment successful! Your promotions are being processed.',
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * POST /api/organizer/promotions/orders/:orderId/webhook
   * Handle payment webhook (for async payment confirmation)
   */
  handlePaymentWebhook = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const { type, data } = req.body;

      // Verify webhook signature (implementation depends on payment provider)
      // const signature = req.headers['stripe-signature'];

      switch (type) {
        case 'payment_intent.succeeded': {
          const { orderId } = data.metadata;
          const order = await this.orderService.getOrderById(parseInt(orderId, 10));

          if (order && order.status === OrderStatus.PENDING_PAYMENT) {
            await this.orderService.markOrderAsPaid(parseInt(orderId, 10), {
              paymentId: data.id,
              paymentProvider: 'stripe',
              paymentMethod: data.payment_method_types?.[0] || 'card',
            });
          }
          break;
        }

        case 'payment_intent.payment_failed': {
          // Handle failed payment - could notify user, etc.
          console.error('Payment failed:', data.id);
          break;
        }

        default:
          console.log('Unhandled webhook event:', type);
      }

      res.json({ received: true });
    } catch (error) {
      next(error);
    }
  };

  /**
   * GET /api/organizer/promotions/orders/:orderId/invoice
   * Get invoice for a paid order
   */
  getInvoice = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;
      const { orderId } = req.params;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      const order = await this.orderService.getOrderById(parseInt(orderId, 10), organizerId);

      if (!order) {
        res.status(404).json({
          success: false,
          error: 'Order not found',
        });
        return;
      }

      if (!order.isPaid()) {
        res.status(400).json({
          success: false,
          error: 'Invoice is only available for paid orders',
        });
        return;
      }

      // Generate invoice data
      const invoice = {
        invoiceNumber: `INV-${order.orderNumber}`,
        orderNumber: order.orderNumber,
        issuedAt: order.paidAt,
        items: order.items?.map(item => ({
          description: item.getDisplayName(),
          quantity: item.quantity,
          unitPrice: item.unitPrice,
          totalPrice: item.totalPrice,
        })),
        subtotal: order.subtotal,
        discountAmount: order.discountAmount,
        discountCode: order.discountCode,
        taxRate: order.taxRate,
        taxAmount: order.taxAmount,
        totalAmount: order.totalAmount,
        currency: order.currency,
        paymentMethod: order.paymentMethod,
        paymentId: order.paymentId,
      };

      res.json({
        success: true,
        data: invoice,
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * POST /api/organizer/promotions/orders/:orderId/refund
   * Request a refund for a paid order
   */
  requestRefund = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;
      const { orderId } = req.params;
      const { reason } = req.body;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      const order = await this.orderService.getOrderById(parseInt(orderId, 10), organizerId);

      if (!order) {
        res.status(404).json({
          success: false,
          error: 'Order not found',
        });
        return;
      }

      if (!order.isPaid()) {
        res.status(400).json({
          success: false,
          error: 'Only paid orders can be refunded',
        });
        return;
      }

      // Check if any promotions have been used
      const hasActivePromotions = order.items?.some(item => item.status === 'active' || item.status === 'completed');

      if (hasActivePromotions) {
        res.status(400).json({
          success: false,
          error: 'Cannot refund orders with active or completed promotions. Please contact support for partial refunds.',
        });
        return;
      }

      // Process refund
      if (this.paymentGateway && order.paymentId) {
        await this.paymentGateway.refundPayment(order.paymentId);
      }

      // For now, just return success - in production, would update order status
      res.json({
        success: true,
        message: 'Refund request submitted. You will be notified once processed.',
        data: {
          orderId: order.id,
          orderNumber: order.orderNumber,
          refundAmount: order.totalAmount,
          reason,
        },
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * GET /api/organizer/promotions/payment-methods
   * Get available payment methods
   */
  getPaymentMethods = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      // Return available payment methods
      const paymentMethods = [
        {
          id: 'card',
          name: 'Credit/Debit Card',
          description: 'Pay with Visa, Mastercard, or other cards',
          icon: 'credit-card',
          enabled: true,
        },
        {
          id: 'bank_transfer',
          name: 'Bank Transfer',
          description: 'Direct bank transfer (may take 1-3 business days)',
          icon: 'bank',
          enabled: true,
        },
        // Add more payment methods as needed
      ];

      res.json({
        success: true,
        data: paymentMethods,
      });
    } catch (error) {
      next(error);
    }
  };
}

export default PromotionPaymentController;
