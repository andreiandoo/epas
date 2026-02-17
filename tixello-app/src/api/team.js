import { apiGet, apiPost } from './client';

export function getTeamMembers() {
  return apiGet('/organizer/team');
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
