import apiClient from './client';
import { DashboardStats, LiveStats, ApiResponse } from '../types';

interface TimelineData {
  period: string;
  orders: number;
  revenue: number;
  tickets: number;
}

interface GateStats {
  gate_name: string;
  scans: number;
  percent: number;
}

export const reportsApi = {
  /**
   * Get dashboard statistics
   */
  getDashboard: async (): Promise<ApiResponse<DashboardStats>> => {
    return apiClient.get('/api/tenant-client/admin/dashboard');
  },

  /**
   * Get organizer dashboard (more detailed)
   */
  getOrganizerDashboard: async (): Promise<
    ApiResponse<{
      stats: DashboardStats;
      recent_orders: any[];
      upcoming_events: any[];
    }>
  > => {
    return apiClient.get('/api/marketplace-client/organizer/dashboard');
  },

  /**
   * Get sales timeline data
   */
  getTimeline: async (params?: {
    event_id?: number;
    period?: 'hour' | 'day' | 'week' | 'month';
  }): Promise<ApiResponse<TimelineData[]>> => {
    return apiClient.get('/api/marketplace-client/organizer/dashboard/timeline', {
      params,
    });
  },

  /**
   * Get gate performance statistics
   */
  getGateStats: async (eventId: number): Promise<ApiResponse<GateStats[]>> => {
    return apiClient.get(`/api/tenant-client/admin/events/${eventId}/gates/stats`);
  },

  /**
   * Get live statistics (for real-time updates)
   */
  getLiveStats: async (eventId: number): Promise<ApiResponse<LiveStats>> => {
    return apiClient.get(`/api/tenant-client/admin/events/${eventId}/live-stats`);
  },

  /**
   * Get orders for reporting
   */
  getOrders: async (params?: {
    page?: number;
    per_page?: number;
    event_id?: number;
    status?: string;
  }): Promise<ApiResponse<any[]>> => {
    return apiClient.get('/api/marketplace-client/organizer/orders', { params });
  },

  /**
   * Get balance information
   */
  getBalance: async (): Promise<
    ApiResponse<{
      available: number;
      pending: number;
      currency: string;
    }>
  > => {
    return apiClient.get('/api/marketplace-client/organizer/balance');
  },

  /**
   * Get transaction history
   */
  getTransactions: async (params?: {
    page?: number;
    per_page?: number;
  }): Promise<ApiResponse<any[]>> => {
    return apiClient.get('/api/marketplace-client/organizer/transactions', { params });
  },

  /**
   * Get payout history
   */
  getPayouts: async (): Promise<ApiResponse<any[]>> => {
    return apiClient.get('/api/marketplace-client/organizer/payouts');
  },

  /**
   * Export report data
   */
  exportReport: async (
    type: 'sales' | 'checkins' | 'participants',
    eventId: number,
    format: 'csv' | 'pdf' = 'csv'
  ): Promise<Blob> => {
    const response = await apiClient.instance.get(
      `/api/tenant-client/admin/events/${eventId}/export/${type}`,
      {
        params: { format },
        responseType: 'blob',
      }
    );
    return response.data;
  },
};

export default reportsApi;
