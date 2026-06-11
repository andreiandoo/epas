/**
 * usePromotionOrder Hook
 * Handles order management, checkout, and payment
 */

import { useState, useCallback } from 'react';
import {
  PromotionOrder,
  CartItem,
  PaymentIntent,
  PromotionStatistics,
  OrderStatus,
} from '../types';

// API base URL - configure based on environment
const API_BASE_URL = '/api/organizer/promotions';

interface CreateOrderParams {
  eventId?: number;
  items: CartItem[];
  discountCode?: string;
  notes?: string;
}

interface UsePromotionOrderReturn {
  // State
  currentOrder: PromotionOrder | null;
  orders: PromotionOrder[];
  statistics: PromotionStatistics | null;
  paymentIntent: PaymentIntent | null;
  isLoading: boolean;
  error: string | null;

  // Order Actions
  createOrder: (params: CreateOrderParams) => Promise<PromotionOrder>;
  updateOrder: (orderId: number, params: Partial<CreateOrderParams>) => Promise<PromotionOrder>;
  cancelOrder: (orderId: number) => Promise<void>;
  fetchOrder: (orderId: number) => Promise<PromotionOrder>;
  fetchOrders: (options?: { status?: OrderStatus[]; limit?: number; offset?: number }) => Promise<void>;
  fetchStatistics: () => Promise<void>;

  // Checkout Actions
  initiateCheckout: (orderId: number) => Promise<{ order: PromotionOrder; payment: PaymentIntent }>;
  confirmPayment: (orderId: number, paymentIntentId: string, paymentMethod: string) => Promise<PromotionOrder>;
  applyDiscountCode: (orderId: number, code: string) => Promise<PromotionOrder>;

  // State Management
  setCurrentOrder: (order: PromotionOrder | null) => void;
  clearError: () => void;
}

export function usePromotionOrder(): UsePromotionOrderReturn {
  const [currentOrder, setCurrentOrder] = useState<PromotionOrder | null>(null);
  const [orders, setOrders] = useState<PromotionOrder[]>([]);
  const [statistics, setStatistics] = useState<PromotionStatistics | null>(null);
  const [paymentIntent, setPaymentIntent] = useState<PaymentIntent | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  // Helper function for API calls
  const apiCall = useCallback(async <T>(
    endpoint: string,
    options?: RequestInit
  ): Promise<T> => {
    const response = await fetch(`${API_BASE_URL}${endpoint}`, {
      headers: {
        'Content-Type': 'application/json',
        ...options?.headers,
      },
      ...options,
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error || 'API request failed');
    }

    return data.data;
  }, []);

  // Create a new order
  const createOrder = useCallback(
    async (params: CreateOrderParams): Promise<PromotionOrder> => {
      setIsLoading(true);
      setError(null);

      try {
        const order = await apiCall<PromotionOrder>('/orders', {
          method: 'POST',
          body: JSON.stringify(params),
        });

        setCurrentOrder(order);
        return order;
      } catch (err: any) {
        setError(err.message);
        throw err;
      } finally {
        setIsLoading(false);
      }
    },
    [apiCall]
  );

  // Update an order
  const updateOrder = useCallback(
    async (orderId: number, params: Partial<CreateOrderParams>): Promise<PromotionOrder> => {
      setIsLoading(true);
      setError(null);

      try {
        const order = await apiCall<PromotionOrder>(`/orders/${orderId}`, {
          method: 'PATCH',
          body: JSON.stringify(params),
        });

        setCurrentOrder(order);
        return order;
      } catch (err: any) {
        setError(err.message);
        throw err;
      } finally {
        setIsLoading(false);
      }
    },
    [apiCall]
  );

  // Cancel an order
  const cancelOrder = useCallback(
    async (orderId: number): Promise<void> => {
      setIsLoading(true);
      setError(null);

      try {
        await apiCall(`/orders/${orderId}`, {
          method: 'DELETE',
        });

        setCurrentOrder(null);

        // Update orders list if applicable
        setOrders((prev) =>
          prev.map((o) =>
            o.id === orderId ? { ...o, status: OrderStatus.CANCELLED } : o
          )
        );
      } catch (err: any) {
        setError(err.message);
        throw err;
      } finally {
        setIsLoading(false);
      }
    },
    [apiCall]
  );

  // Fetch a single order
  const fetchOrder = useCallback(
    async (orderId: number): Promise<PromotionOrder> => {
      setIsLoading(true);
      setError(null);

      try {
        const order = await apiCall<PromotionOrder>(`/orders/${orderId}`);
        setCurrentOrder(order);
        return order;
      } catch (err: any) {
        setError(err.message);
        throw err;
      } finally {
        setIsLoading(false);
      }
    },
    [apiCall]
  );

  // Fetch all orders
  const fetchOrders = useCallback(
    async (options?: { status?: OrderStatus[]; limit?: number; offset?: number }): Promise<void> => {
      setIsLoading(true);
      setError(null);

      try {
        const params = new URLSearchParams();

        if (options?.status?.length) {
          params.append('status', options.status.join(','));
        }
        if (options?.limit) {
          params.append('limit', String(options.limit));
        }
        if (options?.offset) {
          params.append('offset', String(options.offset));
        }

        const response = await fetch(`${API_BASE_URL}/orders?${params.toString()}`);
        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.error || 'Failed to fetch orders');
        }

        setOrders(data.data);
      } catch (err: any) {
        setError(err.message);
      } finally {
        setIsLoading(false);
      }
    },
    []
  );

  // Fetch statistics
  const fetchStatistics = useCallback(async (): Promise<void> => {
    try {
      const stats = await apiCall<PromotionStatistics>('/statistics');
      setStatistics(stats);
    } catch (err: any) {
      console.error('Error fetching statistics:', err);
    }
  }, [apiCall]);

  // Initiate checkout
  const initiateCheckout = useCallback(
    async (orderId: number): Promise<{ order: PromotionOrder; payment: PaymentIntent }> => {
      setIsLoading(true);
      setError(null);

      try {
        const response = await fetch(`${API_BASE_URL}/orders/${orderId}/checkout`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
        });

        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.error || 'Failed to initiate checkout');
        }

        const { order, payment } = data.data;
        setCurrentOrder(order);
        setPaymentIntent(payment);

        return { order, payment };
      } catch (err: any) {
        setError(err.message);
        throw err;
      } finally {
        setIsLoading(false);
      }
    },
    []
  );

  // Confirm payment
  const confirmPayment = useCallback(
    async (
      orderId: number,
      paymentIntentId: string,
      paymentMethod: string
    ): Promise<PromotionOrder> => {
      setIsLoading(true);
      setError(null);

      try {
        const order = await apiCall<PromotionOrder>(`/orders/${orderId}/confirm-payment`, {
          method: 'POST',
          body: JSON.stringify({ paymentIntentId, paymentMethod }),
        });

        setCurrentOrder(order);
        setPaymentIntent(null);

        return order;
      } catch (err: any) {
        setError(err.message);
        throw err;
      } finally {
        setIsLoading(false);
      }
    },
    [apiCall]
  );

  // Apply discount code
  const applyDiscountCode = useCallback(
    async (orderId: number, code: string): Promise<PromotionOrder> => {
      try {
        const order = await updateOrder(orderId, { discountCode: code });
        return order;
      } catch (err: any) {
        throw err;
      }
    },
    [updateOrder]
  );

  // Clear error
  const clearError = useCallback(() => {
    setError(null);
  }, []);

  return {
    currentOrder,
    orders,
    statistics,
    paymentIntent,
    isLoading,
    error,
    createOrder,
    updateOrder,
    cancelOrder,
    fetchOrder,
    fetchOrders,
    fetchStatistics,
    initiateCheckout,
    confirmPayment,
    applyDiscountCode,
    setCurrentOrder,
    clearError,
  };
}

export default usePromotionOrder;
