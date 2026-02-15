import { apiGet, apiPost } from './client';

export async function getTeam() {
  return apiGet('/organizer/team');
}

export async function inviteTeamMember(data) {
  return apiPost('/organizer/team/invite', data);
}
