import { apiGet, apiPost } from './client';

export function getTeamMembers(params = {}) {
  // Params (all optional):
  //   event_id — future-proof: when the backend enriches /organizer/team
  //     to return per-event stats (scans, sales, cash, card), the modal
  //     already passes this so results scope to the selected event.
  return apiGet('/organizer/team', params);
}

export function inviteTeamMember(data) {
  return apiPost('/organizer/team/invite', data);
}

export function updateTeamMember(data) {
  return apiPost('/organizer/team/update', data);
}

export function removeTeamMember(memberId) {
  return apiPost('/organizer/team/remove', { member_id: memberId });
}

export function activateTeamMember(memberId, password) {
  return apiPost('/organizer/team/activate', { member_id: memberId, password });
}

export function resetTeamMemberPassword(memberId, password) {
  return apiPost('/organizer/team/reset-password', { member_id: memberId, password });
}
