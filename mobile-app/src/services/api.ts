import { LoginCredentials, AdminLoginCredentials, User, Tenant } from '../types/auth';
import { Ticket, Order, EventSummary } from '../types';

// Configure your API base URL here
const API_BASE_URL = process.env.EXPO_PUBLIC_API_URL || 'http://localhost:8000/api';

class ApiError extends Error {
  status: number;

  constructor(message: string, status: number) {
    super(message);
    this.status = status;
  }
}

async function request<T>(
  endpoint: string,
  options: RequestInit = {},
  token?: string
): Promise<T> {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    ...options.headers,
  };

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    ...options,
    headers,
  });

  const data = await response.json();

  if (!response.ok) {
    throw new ApiError(
      data.message || data.error || 'Request failed',
      response.status
    );
  }

  return data;
}

// Auth Service
export const authService = {
  async customerLogin(credentials: LoginCredentials) {
    return request<{
      token: string;
      user: User;
      tenants: Tenant[];
    }>('/mobile/customer/login', {
      method: 'POST',
      body: JSON.stringify(credentials),
    });
  },

  async adminLogin(credentials: AdminLoginCredentials) {
    return request<{
      token: string;
      user: User;
      tenant: Tenant;
      permissions: string[];
    }>('/mobile/admin/login', {
      method: 'POST',
      body: JSON.stringify(credentials),
    });
  },

  async logout(token: string) {
    return request('/mobile/customer/logout', {
      method: 'POST',
    }, token);
  },

  async getMe(token: string) {
    return request<{ user: User }>('/mobile/customer/me', {}, token);
  },
};

// Customer Service
export const customerService = {
  async getTickets(token: string) {
    return request<{ tickets: Ticket[] }>('/mobile/customer/tickets', {}, token);
  },

  async getTicket(token: string, code: string) {
    return request<{ ticket: Ticket }>(`/mobile/customer/tickets/${code}`, {}, token);
  },

  async getOrders(token: string) {
    return request<{ orders: Order[] }>('/mobile/customer/orders', {}, token);
  },

  async getOrder(token: string, id: string) {
    return request<{ order: Order }>(`/mobile/customer/orders/${id}`, {}, token);
  },

  async getEvents(token: string) {
    return request<{ events: EventSummary[] }>('/mobile/customer/events', {}, token);
  },
};

// Admin Service
export const adminService = {
  async getEvents(token: string) {
    return request<{ events: EventSummary[] }>('/mobile/admin/events', {}, token);
  },

  async getEventSummary(token: string, eventId: string) {
    return request<{
      tickets_sold: number;
      tickets_total: number;
      revenue_cents: number;
      check_ins: number;
    }>(`/mobile/admin/events/${eventId}/summary`, {}, token);
  },

  async getOrders(token: string) {
    return request<{ orders: Order[] }>('/mobile/admin/orders', {}, token);
  },

  async validateTicket(token: string, qrData: string, gateRef?: string) {
    return request<{
      valid: boolean;
      message: string;
      ticket?: Ticket;
    }>('/mobile/admin/scan/validate', {
      method: 'POST',
      body: JSON.stringify({ qr_data: qrData, gate_ref: gateRef }),
    }, token);
  },

  async downloadEventTickets(token: string, eventId: string) {
    return request<{ tickets: Ticket[] }>(
      `/mobile/admin/scan/download/${eventId}`,
      {},
      token
    );
  },
};
