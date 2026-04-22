import { apiPost, apiGet, setToken, setUserType, getUserType } from './client';
import * as venueOwnerApi from './venueOwner';

/**
 * Unified login: try organizer (owner + team member) first, then fall back
 * to venue-owner. Persists user_type so subsequent /me calls route to the
 * right endpoint without a probe that would clear the token on 401.
 */
export async function login(email, password) {
  // Try organizer login first (covers owner + team member flows)
  try {
    const data = await apiPost('/organizer/login', { email, password });
    if (data.success && data.data?.token) {
      setToken(data.data.token);
      setUserType('organizer');
      return {
        ...data,
        data: { ...data.data, user_type: 'organizer' },
      };
    }
    return data;
  } catch (err) {
    const isAuthError = err?.message?.toLowerCase().includes('invalid') ||
      err?.status === 401 ||
      err?.data?.message?.toLowerCase().includes('invalid credentials');
    if (!isAuthError) {
      throw err;
    }
  }

  // Fall back to venue-owner login
  const data = await venueOwnerApi.login(email, password);
  if (data.success && data.data?.token) {
    setUserType('venue_owner');
  }
  return {
    ...data,
    data: { ...(data.data || {}), user_type: 'venue_owner' },
  };
}

export async function logout() {
  const userType = getUserType();
  try {
    if (userType === 'venue_owner') {
      await apiPost('/venue-owner/logout');
    } else {
      await apiPost('/organizer/logout');
    }
  } catch (e) {}
  setToken(null);
  setUserType(null);
}

/**
 * Session restore — calls only the endpoint matching the persisted user_type.
 * Avoids probing /organizer/me for a venue_owner session, which would
 * return 401 and cause the client to wipe the token.
 */
export async function getMe() {
  const userType = getUserType();

  if (userType === 'venue_owner') {
    const data = await apiGet('/venue-owner/me');
    return {
      ...data,
      data: { ...(data.data || {}), user_type: 'venue_owner' },
    };
  }

  // Default / legacy: organizer. If user_type wasn't persisted (upgraded app
  // with an existing session), this still works for organizers.
  const data = await apiGet('/organizer/me');
  return {
    ...data,
    data: {
      ...(data.data || {}),
      user_type: data.data?.user_type || 'organizer',
    },
  };
}

export async function switchOrganizer(organizerId) {
  const data = await apiPost('/organizer/switch-organizer', { organizer_id: organizerId });
  if (data.success && data.data?.token) {
    setToken(data.data.token);
  }
  return data;
}
