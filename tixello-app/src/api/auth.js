import { apiPost, apiGet, setToken, setApiKey } from './client';
import * as venueOwnerApi from './venueOwner';

/**
 * Unified login: try organizer (owner + team member) first, then fall back
 * to venue-owner. Response includes user_type so callers know the shape.
 */
export async function login(email, password) {
  // Try organizer login first (covers owner + team member flows)
  try {
    const data = await apiPost('/organizer/login', { email, password });
    if (data.success && data.data?.token) {
      setToken(data.data.token);
      return {
        ...data,
        data: {
          ...data.data,
          user_type: data.data.user_type || 'organizer',
        },
      };
    }
    // Non-successful but non-401: surface it
    return data;
  } catch (err) {
    // 401 → fall through to venue-owner attempt
    const isAuthError = err?.message?.toLowerCase().includes('invalid') ||
      err?.status === 401 ||
      err?.data?.message?.toLowerCase().includes('invalid credentials');
    if (!isAuthError) {
      throw err;
    }
  }

  // Fall back to venue-owner login
  try {
    const data = await venueOwnerApi.login(email, password);
    return {
      ...data,
      data: {
        ...data.data,
        user_type: 'venue_owner',
      },
    };
  } catch (err) {
    // Surface venue-owner failure if we fell through
    throw err;
  }
}

export async function logout() {
  try {
    await apiPost('/organizer/logout');
  } catch (e) {}
  try {
    await apiPost('/venue-owner/logout');
  } catch (e) {}
  setToken(null);
}

/**
 * Session restore: try organizer/me first, then venue-owner/me.
 */
export async function getMe() {
  try {
    const data = await apiGet('/organizer/me');
    if (data?.success) {
      return {
        ...data,
        data: {
          ...data.data,
          user_type: data.data.user_type || 'organizer',
        },
      };
    }
  } catch (e) {}

  const data = await apiGet('/venue-owner/me');
  return {
    ...data,
    data: {
      ...(data.data || {}),
      user_type: 'venue_owner',
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
