// User Roles
export type UserRole = 'admin' | 'supervisor' | 'pos' | 'scanner';

// Role permissions - defines what each role can access
export const ROLE_PERMISSIONS = {
  admin: {
    canAccessDashboard: true,
    canAccessCheckIn: true,
    canAccessSales: true,
    canAccessReports: true,
    canAccessSettings: true,
    canViewRevenue: true,
    canManageStaff: true,
  },
  supervisor: {
    canAccessDashboard: true,
    canAccessCheckIn: true,
    canAccessSales: true,
    canAccessReports: true,
    canAccessSettings: true,
    canViewRevenue: true,
    canManageStaff: false,
  },
  pos: {
    canAccessDashboard: true,
    canAccessCheckIn: true,
    canAccessSales: true,
    canAccessReports: false,
    canAccessSettings: true,
    canViewRevenue: false,
    canManageStaff: false,
  },
  scanner: {
    canAccessDashboard: true,
    canAccessCheckIn: true,
    canAccessSales: false,
    canAccessReports: false,
    canAccessSettings: true,
    canViewRevenue: false,
    canManageStaff: false,
  },
} as const;

// User and Authentication Types
export interface User {
  id: number;
  email: string;
  first_name: string;
  last_name: string;
  role: UserRole;
  tenant_id: number;
}

export interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
}

// Tenant Types
export interface Tenant {
  id: number;
  name: string;
  slug: string;
  domain: string;
  currency: string;
  logo_url?: string;
}

// Event Types
export interface Event {
  id: number;
  tenant_id: number;
  title: string;
  slug: string;
  event_date: string;
  start_time: string;
  door_time?: string;
  end_time?: string;
  venue: Venue;
  capacity: number;
  tickets_sold: number;
  checked_in: number;
  revenue: number;
  status: 'upcoming' | 'live' | 'ended' | 'cancelled';
  is_sold_out: boolean;
}

export interface Venue {
  id: number;
  name: string;
  address: string;
  city: string;
  capacity: number;
}

// Ticket Types
export interface TicketType {
  id: number;
  event_id: number;
  name: string;
  sku: string;
  price_cents: number;
  sale_price_cents?: number;
  quota_total: number;
  quota_sold: number;
  available_quantity: number;
  status: 'active' | 'hidden';
  color?: string;
}

export interface Ticket {
  id: number;
  order_id: number;
  ticket_type_id: number;
  code: string;
  status: 'pending' | 'valid' | 'used' | 'cancelled' | 'no-show';
  holder_name?: string;
  seat_label?: string;
  checked_in_at?: string;
  meta?: Record<string, any>;
}

// Check-in Types
export interface CheckInResult {
  success: boolean;
  ticket?: {
    code: string;
    holder_name: string;
    ticket_type: string;
    seat_label?: string;
    status: 'valid' | 'used' | 'cancelled';
  };
  message: string;
  timestamp: string;
}

export interface ScanHistoryItem {
  id: string;
  code: string;
  holder_name: string;
  ticket_type: string;
  status: 'valid' | 'duplicate' | 'invalid' | 'cancelled';
  scanned_at: string;
  zone?: string;
}

// POS / Door Sales Types
export interface CartItem {
  ticket_type: TicketType;
  quantity: number;
}

export interface DoorSale {
  id: number;
  tenant_id: number;
  event_id: number;
  user_id: number;
  customer_name?: string;
  customer_email?: string;
  subtotal: number;
  platform_fee: number;
  total: number;
  payment_method: 'card_tap' | 'apple_pay' | 'google_pay' | 'cash';
  status: 'pending' | 'processing' | 'completed' | 'failed' | 'refunded';
  gateway_transaction_id?: string;
  created_at: string;
}

export interface SaleHistoryItem {
  id: number;
  tickets: number;
  ticket_type: string;
  total: number;
  payment_method: 'card' | 'cash';
  time: string;
}

// Dashboard / Reports Types
export interface DashboardStats {
  total_events: number;
  active_events: number;
  total_orders: number;
  total_revenue: number;
  tickets_sold: number;
  checked_in: number;
  check_in_rate: number;
  customers: number;
}

export interface LiveStats {
  check_ins_per_minute: number;
  sales_per_minute: number;
  current_capacity_percent: number;
  peak_hour: string;
  avg_wait_time: string;
  top_gate: string;
}

export interface GatePerformance {
  gate_name: string;
  scans: number;
  percent: number;
}

// Staff Types
export interface StaffMember {
  id: number;
  name: string;
  role: string;
  gate: string;
  scans: number;
  sales: number;
  status: 'active' | 'break' | 'offline';
  shift_start: string;
  cash_collected: number;
  card_collected: number;
  last_active: string;
}

// Notification Types
export interface AppNotification {
  id: number;
  type: 'alert' | 'info' | 'success' | 'warning';
  message: string;
  time: string;
  unread: boolean;
}

// Emergency Types
export interface EmergencyOption {
  id: number;
  icon: string;
  label: string;
  severity: 'high' | 'medium' | 'low';
}

// Settings Types
export interface ScannerSettings {
  vibration_feedback: boolean;
  sound_effects: boolean;
  auto_confirm_valid: boolean;
}

// Offline Queue Types
export interface QueuedOperation {
  id: string;
  type: 'check-in' | 'door-sale';
  endpoint: string;
  method: 'POST' | 'DELETE';
  data: Record<string, any>;
  timestamp: number;
  retries: number;
}

// API Response Types
export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  message?: string;
  errors?: Record<string, string[]>;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}
