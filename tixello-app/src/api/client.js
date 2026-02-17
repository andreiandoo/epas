import * as SecureStore from 'expo-secure-store';

const BASE_URL = 'https://core.tixello.com/api/marketplace-client';
const PUBLIC_API_URL = 'https://core.tixello.com/api';
const DEFAULT_API_KEY = 'mpc_4qkv4pcuogusFM9234dwihfTrrkBNT2PzpHflnLLmKfSXgkef9BvefCISPFB';

let _token = null;
let _apiKey = DEFAULT_API_KEY;

export async function initApiClient() {
  _token = await SecureStore.getItemAsync('auth_token');
  const storedKey = await SecureStore.getItemAsync('api_key');
  _apiKey = storedKey || DEFAULT_API_KEY;
}

export function setToken(token) {
  _token = token;
  if (token) {
    SecureStore.setItemAsync('auth_token', token);
  } else {
    SecureStore.deleteItemAsync('auth_token');
  }
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
  const query = new URLSearchParams(params).toString();
  const url = `${BASE_URL}${path}${query ? '?' + query : ''}`;
  return request(url, { method: 'GET' });
}

export function apiPost(path, body = {}) {
  return request(`${BASE_URL}${path}`, {
    method: 'POST',
    body: JSON.stringify(body),
  });
}

export function apiPut(path, body = {}) {
  return request(`${BASE_URL}${path}`, {
    method: 'PUT',
    body: JSON.stringify(body),
  });
}

export function apiDelete(path) {
  return request(`${BASE_URL}${path}`, {
    method: 'DELETE',
  });
}

export function publicApiGet(path, params = {}) {
  const query = new URLSearchParams(params).toString();
  const url = `${PUBLIC_API_URL}${path}${query ? '?' + query : ''}`;
  return request(url, { method: 'GET' });
}
