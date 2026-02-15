import * as SecureStore from 'expo-secure-store';

const BASE_URL = 'https://core.tixello.com/api/marketplace-client';
const PUBLIC_API_URL = 'https://core.tixello.com/api';

let _token = null;
let _apiKey = null;

export async function initApiClient() {
  _token = await SecureStore.getItemAsync('auth_token');
  _apiKey = await SecureStore.getItemAsync('api_key');
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

  if (response.status === 401) {
    setToken(null);
    throw new Error('Unauthorized');
  }

  const data = await response.json();

  if (!response.ok) {
    throw new Error(data.message || `HTTP ${response.status}`);
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
