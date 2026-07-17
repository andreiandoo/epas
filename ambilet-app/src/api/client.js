import * as SecureStore from 'expo-secure-store';

const BASE_URL = 'https://core.tixello.com/api/marketplace-client';
const PUBLIC_API_URL = 'https://core.tixello.com/api';
const DEFAULT_API_KEY = 'mpc_4qkv4pcuogusFM9234dwihfTrrkBNT2PzpHflnLLmKfSXgkef9BvefCISPFB';

let _token = null;
let _apiKey = DEFAULT_API_KEY;
let _userType = null; // 'organizer' | 'venue_owner'

export async function initApiClient() {
  _token = await SecureStore.getItemAsync('auth_token');
  const storedKey = await SecureStore.getItemAsync('api_key');
  _apiKey = storedKey || DEFAULT_API_KEY;
  _userType = await SecureStore.getItemAsync('user_type');
}

export function setToken(token) {
  _token = token;
  if (token) {
    SecureStore.setItemAsync('auth_token', token);
  } else {
    SecureStore.deleteItemAsync('auth_token');
  }
}

export function setUserType(userType) {
  _userType = userType;
  if (userType) {
    SecureStore.setItemAsync('user_type', userType);
  } else {
    SecureStore.deleteItemAsync('user_type');
  }
}

export function getUserType() {
  return _userType;
}

export function setApiKey(apiKey) {
  _apiKey = apiKey;
  if (apiKey) {
    SecureStore.setItemAsync('api_key', apiKey);
  } else {
    SecureStore.deleteItemAsync('api_key');
  }
}

export function getToken() {
  return _token;
}

/**
 * Rewrite organizer-style paths to their venue-owner equivalents when the
 * authenticated user is a venue owner. Keeps the organizer screens (Sales,
 * CheckIn, Dashboard etc.) usable verbatim — they call the same `apiPost`
 * helpers, but the URL transparently lands on the venue-owner namespace.
 *
 * Rules (only applied when userType==='venue_owner'):
 *   /organizer/...                          → /venue-owner/...
 *   /orders[, /orders/{id}/*]               → /venue-owner/orders[, /...]
 *   /events/{id}/sales-breakdown            → /venue-owner/events/{id}/sales-breakdown
 *
 * Already-prefixed paths (/venue-owner/..., /customer/..., /admin/...) pass
 * through unchanged.
 */
function rewritePath(path) {
  if (_userType !== 'venue_owner' || !path) return path;
  if (path.startsWith('/venue-owner/')) return path;

  if (path.startsWith('/organizer/')) {
    return '/venue-owner/' + path.slice('/organizer/'.length);
  }
  if (path === '/orders' || path.startsWith('/orders/') || path.startsWith('/orders?')) {
    return '/venue-owner' + path;
  }
  // /events/{id}/sales-breakdown — used by DashboardScreen's SalesBreakdownModal
  if (/^\/events\/\d+\/sales-breakdown(\?|$)/.test(path)) {
    return '/venue-owner' + path;
  }
  return path;
}

async function request(url, options = {}) {
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    ...options.headers,
  };

  if (_token) {
    headers['Authorization'] = `Bearer ${_token}`;
  }
  if (_apiKey) {
    headers['X-API-Key'] = _apiKey;
  }

  const response = await fetch(url, {
    ...options,
    headers,
  });

  const data = await response.json();

  if (response.status === 401) {
    // Only clear token if we had one (not during login)
    if (_token) {
      setToken(null);
    }
    throw new Error(data.message || 'Invalid email or password');
  }

  if (!response.ok) {
    const error = new Error(data.message || `HTTP ${response.status}`);
    error.data = data;
    error.status = response.status;
    throw error;
  }

  return data;
}

export function apiGet(path, params = {}) {
  const rewritten = rewritePath(path);
  const query = new URLSearchParams(params).toString();
  const url = `${BASE_URL}${rewritten}${query ? '?' + query : ''}`;
  return request(url, { method: 'GET' });
}

// Bypasses the JSON-parsing `request()` and returns the raw fetch response.
// Used by the CSV export flow (server sets Content-Type: text/csv, not JSON,
// so response.json() would blow up). Callers get to decide how to read the
// body (.text() for CSV, .blob() for binary if we ever add PDF, etc.).
export async function apiGetRaw(path, params = {}) {
  const rewritten = rewritePath(path);
  const query = new URLSearchParams(params).toString();
  const url = `${BASE_URL}${rewritten}${query ? '?' + query : ''}`;
  const headers = { Accept: '*/*' };
  if (_token) headers['Authorization'] = `Bearer ${_token}`;
  if (_apiKey) headers['X-API-Key'] = _apiKey;
  const response = await fetch(url, { method: 'GET', headers });
  return response;
}

export function apiPost(path, body = {}) {
  return request(`${BASE_URL}${rewritePath(path)}`, {
    method: 'POST',
    body: JSON.stringify(body),
  });
}

export function apiPut(path, body = {}) {
  return request(`${BASE_URL}${rewritePath(path)}`, {
    method: 'PUT',
    body: JSON.stringify(body),
  });
}

export function apiPatch(path, body = {}) {
  return request(`${BASE_URL}${rewritePath(path)}`, {
    method: 'PATCH',
    body: JSON.stringify(body),
  });
}

export function apiDelete(path) {
  return request(`${BASE_URL}${rewritePath(path)}`, {
    method: 'DELETE',
  });
}

export function publicApiGet(path, params = {}) {
  const query = new URLSearchParams(params).toString();
  const url = `${PUBLIC_API_URL}${path}${query ? '?' + query : ''}`;
  return request(url, { method: 'GET' });
}
