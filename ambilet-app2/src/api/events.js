import { apiGet } from './client';

export async function getEvents(params = {}) {
  return apiGet('/organizer/events', {
    per_page: 100,
    sort: 'event_date',
    order: 'desc',
    // Do NOT set published_only — we want drafts / pending_review / rejected
    // to show up in the selector with a "Nepublicat" badge so the organizer
    // can see what's in-flight from the mobile app without switching to web.
    ...params,
  });
}

export async function getEvent(eventId) {
  return apiGet(`/organizer/events/${eventId}`);
}
