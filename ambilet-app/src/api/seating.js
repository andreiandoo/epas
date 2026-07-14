import { apiPost } from './client';

/**
 * Issue a signed URL for the WebView-based seating widget. The mobile
 * SalesScreen opens the returned URL inside a react-native-webview; the
 * page renders the canvas chart with all data baked in (zero round-trips
 * on first paint) and subscribes to Reverb for real-time status changes.
 *
 * Token expires in 30 minutes — the page is meant to live for a single
 * sale flow.
 */
export async function issueSeatingEmbedToken({ eventId, ticketTypeId = null }) {
  return apiPost('/organizer/seating/embed-token', {
    event_id: eventId,
    ticket_type_id: ticketTypeId,
  });
}
