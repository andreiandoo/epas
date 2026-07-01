// API client pentru endpoint-urile leisure (Sf. Ana)
// Toate funcțiile reutilizează apiGet/Post din ./client.js
import { apiGet, apiPost, apiPut, apiDelete } from './client';

// ============================================================================
// Active shift (F11) — pentru a determina rolul curent al operatorului
// ============================================================================

export async function fetchActiveShift(teamMemberId = null) {
  const params = teamMemberId ? { team_member_id: teamMemberId } : {};
  return apiGet('/organizer/me/active-shift', params);
}

// ============================================================================
// Events + leisure config
// ============================================================================

export async function fetchOrganizerEvents() {
  return apiGet('/organizer/events');
}

export async function fetchLeisureConfig(eventId) {
  return apiGet(`/organizer/events/${eventId}/leisure/config`);
}

// ============================================================================
// Dashboard live (F5.6)
// ============================================================================

export async function fetchDashboardLive(eventId) {
  return apiGet(`/organizer/events/${eventId}/leisure/dashboard/live`);
}

// ============================================================================
// Boats CRUD (F7)
// ============================================================================

export async function fetchBoats(eventId, ticketTypeId) {
  return apiGet(`/organizer/events/${eventId}/leisure/boats`, {
    ticket_type_id: ticketTypeId,
  });
}

export async function syncBoats(eventId, ticketTypeId) {
  return apiPost(`/organizer/events/${eventId}/leisure/boats/sync`, {
    ticket_type_id: ticketTypeId,
  });
}

// ============================================================================
// Rentals CRUD (F7 — timer + calup)
// ============================================================================

export async function fetchActiveRentals(eventId, ticketTypeId = null) {
  const params = ticketTypeId ? { ticket_type_id: ticketTypeId } : {};
  return apiGet(`/organizer/events/${eventId}/leisure/active-rentals`, params);
}

export async function startRental(eventId, payload) {
  return apiPost(`/organizer/events/${eventId}/leisure/boat-rentals/start`, payload);
}

export async function endRental(eventId, rentalId) {
  return apiPost(`/organizer/events/${eventId}/leisure/boat-rentals/${rentalId}/end`, {});
}

export async function finalizeRental(eventId, rentalId) {
  return apiPost(`/organizer/events/${eventId}/leisure/boat-rentals/${rentalId}/finalize`, {});
}

// ============================================================================
// POS sale (F5.7) — vânzare on-site
// ============================================================================

export async function posSale(eventId, payload) {
  return apiPost(`/organizer/events/${eventId}/leisure/pos-sale`, payload);
}

// ============================================================================
// Slot availability (F3 — Vaporașe)
// ============================================================================

export async function fetchSlotAvailability(eventSlug, ticketTypeId, date) {
  // Endpoint public sub /marketplace-events/{slug}/slot-availability
  return apiGet(`/marketplace-events/${eventSlug}/slot-availability`, {
    ticket_type_id: ticketTypeId,
    date,
  });
}

// ============================================================================
// Resource availability (F5 — Bărci, overlap check)
// ============================================================================

export async function fetchResourceAvailability(eventSlug, payload) {
  return apiGet(`/marketplace-events/${eventSlug}/resource-availability`, payload);
}

// ============================================================================
// Ticket lookup (pentru scanare — verificare bilet)
// ============================================================================

export async function lookupTicket(code) {
  // Folosesc endpoint-ul public de verificare
  return apiGet(`/tickets/lookup`, { code });
}

// Auto-checkin ca operator organizator (marcheaza checked_in_at + returneaza
// numele/tipul biletului). Folosita de KioskScreen pentru tableta self-service.
// Backend: POST /organizer/participants/checkin { ticket_code } (checkInByCode).
export async function organizerCheckInByCode(ticketCode) {
  return apiPost('/organizer/participants/checkin', { ticket_code: ticketCode });
}
