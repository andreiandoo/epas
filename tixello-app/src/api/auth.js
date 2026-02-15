import { apiPost, apiGet, setToken, setApiKey } from './client';

export async function login(email, password) {
  const data = await apiPost('/organizer/login', { email, password });
  if (data.success && data.data?.token) {
    setToken(data.data.token);
    if (data.data.organizer?.api_key_masked) {
      // API key is handled server-side via token auth
    }
  }
  return data;
}

export async function logout() {
  try {
    await apiPost('/organizer/logout');
  } catch (e) {
    // Ignore errors on logout
  }
  setToken(null);
}

export async function getMe() {
  return apiGet('/organizer/me');
}
