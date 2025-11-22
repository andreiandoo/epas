export * from './auth';

export interface Ticket {
  id: string;
  code: string;
  status: 'valid' | 'used' | 'void';
  qr_data?: string;
  seat_label?: string;
  event: EventSummary;
  ticket_type: TicketType;
}

export interface TicketType {
  id: string;
  name: string;
  price_cents: number;
  currency: string;
}

export interface EventSummary {
  id: string;
  title: string;
  event_date: string;
  start_time?: string;
  venue_name?: string;
  image_url?: string;
}

export interface Order {
  id: string;
  status: 'pending' | 'paid' | 'cancelled' | 'refunded';
  total_cents: number;
  currency: string;
  created_at: string;
  tickets: Ticket[];
  event: EventSummary;
}

export interface EventDetail extends EventSummary {
  description?: string;
  door_time?: string;
  end_time?: string;
  is_sold_out: boolean;
  is_cancelled: boolean;
  ticket_types: TicketType[];
}
