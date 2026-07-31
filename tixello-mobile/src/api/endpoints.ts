/**
 * Thin, typed wrappers over the backend endpoints the app uses.
 * Every path here maps to an existing route in routes/api.php.
 */
import { apiRequest, type ApiContext } from './client';
import type {
  LoginResult,
  EventSummary,
  EventStats,
  TicketTypeSummary,
  ScanResult,
} from './types';

/** Public (marketplace.auth only) — no token yet. */
export function login(
  ctx: ApiContext,
  email: string,
  password: string,
): Promise<LoginResult> {
  return apiRequest<LoginResult>(ctx, '/login', {
    method: 'POST',
    body: { email, password },
  });
}

export function me(ctx: ApiContext): Promise<LoginResult> {
  return apiRequest<LoginResult>(ctx, '/me');
}

export function logout(ctx: ApiContext): Promise<unknown> {
  return apiRequest(ctx, '/logout', { method: 'POST' });
}

export function switchOrganizer(
  ctx: ApiContext,
  organizerId: number,
): Promise<LoginResult> {
  return apiRequest<LoginResult>(ctx, '/switch-organizer', {
    method: 'POST',
    body: { organizer_id: organizerId },
  });
}

export function listEvents(ctx: ApiContext): Promise<{ data: EventSummary[] }> {
  return apiRequest(ctx, '/events', {
    query: { published_only: true, per_page: 100 },
  });
}

export function eventDetail(ctx: ApiContext, eventId: number): Promise<unknown> {
  return apiRequest(ctx, `/events/${eventId}`);
}

export function eventStatistics(
  ctx: ApiContext,
  eventId: number,
): Promise<EventStats> {
  return apiRequest<EventStats>(ctx, `/events/${eventId}/statistics`);
}

/** Global scan/check-in by code (normalizes /v//t//verify/, STAFF- QR, etc). */
export function checkInByCode(
  ctx: ApiContext,
  ticketCode: string,
  eventId?: number,
): Promise<ScanResult> {
  return apiRequest<ScanResult>(ctx, '/participants/checkin', {
    method: 'POST',
    body: { ticket_code: ticketCode, event_id: eventId },
  });
}

export function undoCheckIn(
  ctx: ApiContext,
  eventId: number,
  barcode: string,
): Promise<unknown> {
  return apiRequest(ctx, `/events/${eventId}/check-in/${encodeURIComponent(barcode)}`, {
    method: 'DELETE',
  });
}

export function ticketTypesForSale(
  ctx: ApiContext,
  eventId: number,
): Promise<{ ticket_types: TicketTypeSummary[] }> {
  return apiRequest(ctx, `/events/${eventId}`);
}

export interface CreateOrderInput {
  event_id: number;
  tickets: { ticket_type_id: number; quantity: number }[];
  seat_uids?: string[];
  customer?: { email?: string; first_name?: string; last_name?: string; phone?: string };
  locale?: string;
}

export function createOrder(
  ctx: ApiContext,
  input: CreateOrderInput,
): Promise<{ id: number; order_number: string; total: number }> {
  return apiRequest(ctx, '/orders', {
    method: 'POST',
    body: { ...input, source: 'pos_app' },
  });
}

export function posComplete(
  ctx: ApiContext,
  orderId: number,
  autoCheckin = false,
): Promise<unknown> {
  return apiRequest(ctx, `/orders/${orderId}/pos-complete`, {
    method: 'POST',
    body: { auto_checkin: autoCheckin },
  });
}

export function seatingMap(ctx: ApiContext, eventId: number): Promise<unknown> {
  return apiRequest(ctx, `/events/${eventId}/seating-map`);
}
