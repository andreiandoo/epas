import { apiGet } from './client';

export async function getEvents(params = {}) {
  return apiGet('/organizer/events', {
    per_page: 50,
    sort: 'event_date',
    order: 'desc',
    ...params,
  });
}

export async function getEvent(eventId) {
  return apiGet(`/organizer/events/${eventId}`);
}
