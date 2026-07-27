import { apiPost, apiMultipart } from './client';

// Sends an emergency notification that lands in every admin/owner staff
// member's notifications panel for the current organizer. Called from
// the Raportează Problemă sheet.
//
// `photo` and `audio` are optional { uri, name, type } file descriptors
// (as returned by expo-image-picker / expo-av). When either is present
// the request auto-switches to multipart/form-data.
export function reportEmergency({ type, title, message, severity = 'high', photo, audio }) {
  if (!photo && !audio) {
    return apiPost('/organizer/notifications/emergency-report', {
      type, title, message, severity,
    });
  }
  const form = new FormData();
  form.append('type', type);
  form.append('title', title);
  if (message) form.append('message', message);
  form.append('severity', severity);
  if (photo) form.append('photo', photo);
  if (audio) form.append('audio', audio);
  return apiMultipart('/organizer/notifications/emergency-report', form);
}
