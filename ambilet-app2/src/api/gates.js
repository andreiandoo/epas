import { apiGet, apiPost, apiPut, apiDelete } from './client';

export function getVenueGates(venueId) {
  return apiGet(`/organizer/venues/${venueId}/gates`);
}

export function createVenueGate(venueId, data) {
  return apiPost(`/organizer/venues/${venueId}/gates`, data);
}

export function updateVenueGate(venueId, gateId, data) {
  return apiPut(`/organizer/venues/${venueId}/gates/${gateId}`, data);
}

export function deleteVenueGate(venueId, gateId) {
  return apiDelete(`/organizer/venues/${venueId}/gates/${gateId}`);
}
