import apiClient from './client';
import { DoorSale, ApiResponse } from '../types';

interface CalculateRequest {
  items: Array<{
    ticket_type_id: number;
    quantity: number;
  }>;
}

interface CalculateResponse {
  items: Array<{
    ticket_type_id: number;
    name: string;
    quantity: number;
    unit_price: number;
    total: number;
  }>;
  subtotal: number;
  platform_fee: number;
  total: number;
  currency: string;
}

interface ProcessRequest {
  tenant_id: number;
  event_id: number;
  user_id: number;
  items: Array<{
    ticket_type_id: number;
    quantity: number;
  }>;
  payment_method: 'card_tap' | 'apple_pay' | 'google_pay' | 'cash';
  customer_name?: string;
  customer_email?: string;
  device_id?: string;
}

interface ProcessResponse {
  success: boolean;
  door_sale: DoorSale;
  tickets_issued: number;
  order_id: number;
}

export const doorSalesApi = {
  /**
   * Calculate order totals before processing
   */
  calculate: async (data: CalculateRequest): Promise<ApiResponse<CalculateResponse>> => {
    return apiClient.post('/api/door-sales/calculate', data);
  },

  /**
   * Process a door sale payment
   */
  process: async (data: ProcessRequest): Promise<ApiResponse<ProcessResponse>> => {
    return apiClient.post('/api/door-sales/process', data);
  },

  /**
   * Get a specific door sale by ID
   */
  getDoorSale: async (id: number): Promise<ApiResponse<DoorSale>> => {
    return apiClient.get(`/api/door-sales/${id}`);
  },

  /**
   * Get door sales history
   */
  getHistory: async (params?: {
    page?: number;
    per_page?: number;
    event_id?: number;
  }): Promise<ApiResponse<DoorSale[]>> => {
    return apiClient.get('/api/door-sales/history', { params });
  },

  /**
   * Get door sales summary/stats
   */
  getSummary: async (eventId?: number): Promise<
    ApiResponse<{
      total_sales: number;
      total_revenue: number;
      cash_total: number;
      card_total: number;
      tickets_sold: number;
    }>
  > => {
    return apiClient.get('/api/door-sales/summary', {
      params: eventId ? { event_id: eventId } : undefined,
    });
  },

  /**
   * Refund a door sale
   */
  refund: async (
    id: number,
    amount?: number
  ): Promise<ApiResponse<{ refunded_amount: number }>> => {
    return apiClient.post(`/api/door-sales/${id}/refund`, { amount });
  },

  /**
   * Resend tickets to customer email
   */
  resendTickets: async (id: number, email?: string): Promise<ApiResponse<void>> => {
    return apiClient.post(`/api/door-sales/${id}/resend`, { email });
  },

  /**
   * Get Stripe Terminal connection token
   */
  getConnectionToken: async (): Promise<ApiResponse<{ secret: string }>> => {
    return apiClient.post('/api/door-sales/stripe/connection-token');
  },

  /**
   * Create a payment intent for Stripe Terminal
   */
  createPaymentIntent: async (data: {
    amount: number;
    currency: string;
    event_id: number;
  }): Promise<ApiResponse<{ client_secret: string; payment_intent_id: string }>> => {
    return apiClient.post('/api/door-sales/stripe/payment-intent', data);
  },

  /**
   * Capture a payment after terminal success
   */
  capturePayment: async (paymentIntentId: string): Promise<ApiResponse<void>> => {
    return apiClient.post('/api/door-sales/stripe/capture', {
      payment_intent_id: paymentIntentId,
    });
  },
};

export default doorSalesApi;
