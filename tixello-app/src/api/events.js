import { apiGet } from './client';

export async function getEvents(params = {}) {
  return apiGet('/organizer/events', {
    per_page: 50,
    sort: 'event_date',
    order: 'desc',
    // Hide drafts / pending_review / rejected / cancelled — the mobile app
    // can only operate on approved live events.
    published_only: true,
    ...params,
  });
}

export async function getEvent(eventId) {
  return apiGet(`/organizer/events/${eventId}`);
}
