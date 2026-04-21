import { apiGet, apiPost, apiPatch, apiDelete, setToken } from './client';

// ── Auth ─────────────────────────────────────────────────────

export async function login(email, password) {
  const data = await apiPost('/venue-owner/login', { email, password });
  if (data.success && data.data?.token) {
    setToken(data.data.token);
  }
  return data;
}

export async function logout() {
  try {
    await apiPost('/venue-owner/logout');
  } catch (e) {}
  setToken(null);
}

export async function getMe() {
  return apiGet('/venue-owner/me');
}

// ── Events ───────────────────────────────────────────────────

export async function listEvents(scope = 'all') {
  return apiGet('/venue-owner/events', { scope });
}

export async function getEvent(eventId) {
  return apiGet(`/venue-owner/events/${eventId}`);
}

export async function listAttendees(eventId, { search = '', page = 1, perPage = 25, status = 'all' } = {}) {
  const params = { page, per_page: perPage, status };
  if (search) params.search = search;
  return apiGet(`/venue-owner/events/${eventId}/attendees`, params);
}

// ── Tickets ──────────────────────────────────────────────────

export async function getTicket(ticketId) {
  return apiGet(`/venue-owner/tickets/${ticketId}`);
}

export async function scanLookup(code) {
  return apiPost('/venue-owner/scan', { code });
}

// ── Notes (polymorphic) ──────────────────────────────────────

export async function listNotes(targetType, targetId) {
  return apiGet('/venue-owner/notes', { target_type: targetType, target_id: targetId });
}

export async function createNote(targetType, targetId, note) {
  return apiPost('/venue-owner/notes', { target_type: targetType, target_id: targetId, note });
}

export async function updateNote(noteId, note) {
  return apiPatch(`/venue-owner/notes/${noteId}`, { note });
}

export async function deleteNote(noteId) {
  return apiDelete(`/venue-owner/notes/${noteId}`);
}
