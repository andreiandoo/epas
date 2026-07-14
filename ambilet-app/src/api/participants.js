import { apiGet, apiPost, apiDelete } from './client';

export async function getParticipants(eventId, params = {}) {
  return apiGet(`/organizer/events/${eventId}/participants`, {
    per_page: 50,
    ...params,
  });
}

export async function getAllParticipants(params = {}) {
  return apiGet('/organizer/participants', params);
}

export async function checkinByCode(ticketCode) {
  return apiPost('/organizer/participants/checkin', {
    ticket_code: ticketCode,
  });
}

export async function checkinByBarcode(eventId, barcode) {
  return apiPost(`/organizer/events/${eventId}/check-in/${barcode}`);
}

export async function undoCheckin(eventId, barcode) {
  return apiDelete(`/organizer/events/${eventId}/check-in/${barcode}`);
}

export async function exportParticipants(eventId) {
  return apiGet(`/organizer/events/${eventId}/participants/export`);
}
