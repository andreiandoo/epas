/**
 * PaymentGateway Interface
 * Abstract interface for payment providers
 */

export interface PaymentIntentResult {
  paymentIntentId: string;
  clientSecret: string;
  status: string;
  amount: number;
  currency: string;
}

export interface PaymentConfirmation {
  status: 'succeeded' | 'failed' | 'pending' | 'canceled';
  paymentMethod: string;
  errorCode?: string;
  errorMessage?: string;
}

export interface RefundResult {
  refundId: string;
  status: 'succeeded' | 'pending' | 'failed';
  amount: number;
  currency: string;
}

export interface PaymentGateway {
  /**
   * Create a payment intent for a given amount
   */
  createPaymentIntent(
    amount: number,
    currency: string,
    metadata: Record<string, any>
  ): Promise<PaymentIntentResult>;

  /**
   * Confirm a payment intent
   */
  confirmPayment(paymentIntentId: string): Promise<PaymentConfirmation>;

  /**
   * Refund a payment
   */
  refundPayment(
    paymentIntentId: string,
    amount?: number
  ): Promise<RefundResult>;

  /**
   * Verify webhook signature
   */
  verifyWebhookSignature(
    payload: string | Buffer,
    signature: string
  ): boolean;

  /**
   * Parse webhook event
   */
  parseWebhookEvent(payload: string | Buffer): WebhookEvent;
}

export interface WebhookEvent {
  type: string;
  data: any;
  id: string;
  created: Date;
}

export default PaymentGateway;
