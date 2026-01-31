/**
 * StripeIntegration
 * Stripe payment gateway implementation
 */

import {
  PaymentGateway,
  PaymentIntentResult,
  PaymentConfirmation,
  RefundResult,
  WebhookEvent,
} from './PaymentGateway';

// Stripe SDK types (would be imported from 'stripe' in production)
interface Stripe {
  paymentIntents: {
    create(params: any): Promise<any>;
    retrieve(id: string): Promise<any>;
  };
  refunds: {
    create(params: any): Promise<any>;
  };
  webhooks: {
    constructEvent(payload: string | Buffer, signature: string, secret: string): any;
  };
}

interface StripeConfig {
  secretKey: string;
  webhookSecret: string;
  apiVersion?: string;
}

export class StripeIntegration implements PaymentGateway {
  private stripe: Stripe;
  private webhookSecret: string;

  constructor(config: StripeConfig) {
    // In production, would use: const Stripe = require('stripe');
    // this.stripe = new Stripe(config.secretKey, { apiVersion: config.apiVersion || '2023-10-16' });

    // Mock implementation for development
    this.stripe = this.createMockStripe();
    this.webhookSecret = config.webhookSecret;
  }

  /**
   * Create a payment intent
   */
  async createPaymentIntent(
    amount: number,
    currency: string,
    metadata: Record<string, any>
  ): Promise<PaymentIntentResult> {
    try {
      // Convert amount to smallest currency unit (e.g., cents for USD, bani for RON)
      const amountInSmallestUnit = Math.round(amount * 100);

      const paymentIntent = await this.stripe.paymentIntents.create({
        amount: amountInSmallestUnit,
        currency: currency.toLowerCase(),
        automatic_payment_methods: {
          enabled: true,
        },
        metadata: {
          ...metadata,
          // Ensure string values for Stripe metadata
          orderId: String(metadata.orderId),
          orderNumber: String(metadata.orderNumber),
          organizerId: String(metadata.organizerId),
        },
      });

      return {
        paymentIntentId: paymentIntent.id,
        clientSecret: paymentIntent.client_secret,
        status: paymentIntent.status,
        amount: paymentIntent.amount / 100,
        currency: paymentIntent.currency.toUpperCase(),
      };
    } catch (error: any) {
      console.error('Error creating payment intent:', error);
      throw new Error(`Payment intent creation failed: ${error.message}`);
    }
  }

  /**
   * Confirm a payment intent
   */
  async confirmPayment(paymentIntentId: string): Promise<PaymentConfirmation> {
    try {
      const paymentIntent = await this.stripe.paymentIntents.retrieve(paymentIntentId);

      let status: PaymentConfirmation['status'];
      switch (paymentIntent.status) {
        case 'succeeded':
          status = 'succeeded';
          break;
        case 'canceled':
          status = 'canceled';
          break;
        case 'requires_action':
        case 'requires_confirmation':
        case 'requires_payment_method':
        case 'processing':
          status = 'pending';
          break;
        default:
          status = 'failed';
      }

      return {
        status,
        paymentMethod: paymentIntent.payment_method_types?.[0] || 'card',
        errorCode: paymentIntent.last_payment_error?.code,
        errorMessage: paymentIntent.last_payment_error?.message,
      };
    } catch (error: any) {
      console.error('Error confirming payment:', error);
      return {
        status: 'failed',
        paymentMethod: 'unknown',
        errorCode: 'retrieval_error',
        errorMessage: error.message,
      };
    }
  }

  /**
   * Refund a payment
   */
  async refundPayment(paymentIntentId: string, amount?: number): Promise<RefundResult> {
    try {
      const refundParams: any = {
        payment_intent: paymentIntentId,
      };

      if (amount) {
        refundParams.amount = Math.round(amount * 100);
      }

      const refund = await this.stripe.refunds.create(refundParams);

      return {
        refundId: refund.id,
        status: refund.status === 'succeeded' ? 'succeeded' : 'pending',
        amount: refund.amount / 100,
        currency: refund.currency.toUpperCase(),
      };
    } catch (error: any) {
      console.error('Error creating refund:', error);
      throw new Error(`Refund failed: ${error.message}`);
    }
  }

  /**
   * Verify webhook signature
   */
  verifyWebhookSignature(payload: string | Buffer, signature: string): boolean {
    try {
      this.stripe.webhooks.constructEvent(payload, signature, this.webhookSecret);
      return true;
    } catch (error) {
      console.error('Webhook signature verification failed:', error);
      return false;
    }
  }

  /**
   * Parse webhook event
   */
  parseWebhookEvent(payload: string | Buffer): WebhookEvent {
    // In production, would use constructEvent with signature verification
    const data = typeof payload === 'string' ? JSON.parse(payload) : JSON.parse(payload.toString());

    return {
      type: data.type,
      data: data.data?.object || data.data,
      id: data.id,
      created: new Date(data.created * 1000),
    };
  }

  /**
   * Create mock Stripe instance for development
   */
  private createMockStripe(): Stripe {
    return {
      paymentIntents: {
        create: async (params: any) => ({
          id: `pi_mock_${Date.now()}`,
          client_secret: `pi_mock_${Date.now()}_secret_${Math.random().toString(36).substring(2)}`,
          status: 'requires_payment_method',
          amount: params.amount,
          currency: params.currency,
          metadata: params.metadata,
        }),
        retrieve: async (id: string) => ({
          id,
          status: 'succeeded',
          payment_method_types: ['card'],
          amount: 10000,
          currency: 'ron',
        }),
      },
      refunds: {
        create: async (params: any) => ({
          id: `re_mock_${Date.now()}`,
          status: 'succeeded',
          amount: params.amount || 10000,
          currency: 'ron',
        }),
      },
      webhooks: {
        constructEvent: (payload: string | Buffer, signature: string, secret: string) => {
          // Mock verification
          if (!signature || !secret) {
            throw new Error('Invalid signature');
          }
          return JSON.parse(typeof payload === 'string' ? payload : payload.toString());
        },
      },
    };
  }
}

export default StripeIntegration;
