import { apiPost } from './client';

// Sends an emergency notification that lands in every admin/owner staff
// member's notifications panel for the current organizer. Called from
// the Raportează Problemă sheet.
export function reportEmergency({ type, title, message, severity = 'high' }) {
  return apiPost('/organizer/notifications/emergency-report', {
    type, title, message, severity,
  });
}
