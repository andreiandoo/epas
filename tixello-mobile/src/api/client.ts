/**
 * Tixello API client.
 *
 * All operator/organizer traffic goes to the existing Laravel backend
 * (core.tixello.com). Auth is a Sanctum bearer token obtained at login.
 * The marketplace is identified by an API key header (X-API-Key) that the
 * `marketplace.auth` middleware resolves before Sanctum runs.
 *
 * The `context` decides the path prefix (rewriter pattern from the web app):
 *   - organizer / team-member -> /marketplace-client/organizer/*
 *   - venue-owner             -> /marketplace-client/venue-owner/*
 *   - tenant                  -> /tenant-client/*  (coverage under review)
 */
import Constants from 'expo-constants';
import type { ContextKind } from '@/theme/colors';

const API_BASE: string =
  (Constants.expoConfig?.extra as { apiBaseUrl?: string } | undefined)?.apiBaseUrl ??
  'https://core.tixello.com/api';

export interface ApiContext {
  kind: ContextKind;
  /** Sanctum bearer token for the active session. */
  token?: string | null;
  /** Marketplace API key (mpc_...) identifying the marketplace brand. */
  marketplaceKey?: string | null;
}

export class ApiError extends Error {
  status: number;
  payload: unknown;
  constructor(status: number, message: string, payload: unknown) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.payload = payload;
  }
}

/** Prefix that scopes a relative path to the active context. */
function prefixFor(kind: ContextKind): string {
  switch (kind) {
    case 'venue':
      return '/marketplace-client/venue-owner';
    case 'tenant':
      return '/tenant-client';
    default:
      return '/marketplace-client/organizer';
  }
}

export interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: unknown;
  /** Skip the context prefix and hit an absolute API path (e.g. public routes). */
  raw?: boolean;
  query?: Record<string, string | number | boolean | undefined>;
  signal?: AbortSignal;
}

function buildQuery(query?: RequestOptions['query']): string {
  if (!query) return '';
  const parts = Object.entries(query)
    .filter(([, v]) => v !== undefined)
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`);
  return parts.length ? `?${parts.join('&')}` : '';
}

export async function apiRequest<T = unknown>(
  ctx: ApiContext,
  path: string,
  opts: RequestOptions = {},
): Promise<T> {
  const prefix = opts.raw ? '' : prefixFor(ctx.kind);
  const url = `${API_BASE}${prefix}${path}${buildQuery(opts.query)}`;

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  };
  if (ctx.token) headers.Authorization = `Bearer ${ctx.token}`;
  if (ctx.marketplaceKey) headers['X-API-Key'] = ctx.marketplaceKey;

  const res = await fetch(url, {
    method: opts.method ?? 'GET',
    headers,
    body: opts.body != null ? JSON.stringify(opts.body) : undefined,
    signal: opts.signal,
  });

  const text = await res.text();
  let data: unknown = null;
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    data = text;
  }

  if (!res.ok) {
    const message =
      (data as { message?: string } | null)?.message || `HTTP ${res.status}`;
    throw new ApiError(res.status, message, data);
  }
  return data as T;
}

export { API_BASE };
