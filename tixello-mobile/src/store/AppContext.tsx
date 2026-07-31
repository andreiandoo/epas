/**
 * App-wide session state: who is logged in, the active context (which
 * drives the accent + API routing), and the available workspaces.
 */
import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react';
import type { ApiContext } from '@/api/client';
import * as api from '@/api/endpoints';
import type { AvailableOrganizer, LoginResult, Organizer } from '@/api/types';
import { accentFor, type Accent, type ContextKind } from '@/theme/colors';
import {
  clearSession,
  loadSession,
  saveSession,
  type PersistedSession,
} from './session';

/** Map an organizer_type string to a themed context kind. */
export function kindFromType(type?: string | null): ContextKind {
  switch ((type || '').toLowerCase()) {
    case 'theatre':
    case 'teatru':
      return 'theatre';
    case 'venue':
      return 'venue';
    case 'artist':
      return 'artist';
    case 'agency':
    case 'agentie':
      return 'agency';
    case 'festival':
      return 'festival';
    case 'tenant':
      return 'tenant';
    default:
      return 'organizer';
  }
}

interface AppState {
  ready: boolean;
  authed: boolean;
  organizer: Organizer | null;
  contextKind: ContextKind;
  accent: Accent;
  available: AvailableOrganizer[];
  apiCtx: ApiContext;
  signIn: (email: string, password: string) => Promise<void>;
  switchTo: (organizerId: number) => Promise<void>;
  signOut: () => Promise<void>;
}

const AppCtx = createContext<AppState | undefined>(undefined);

// Marketplace key is injected per build; empty until wired to a bootstrap.
const MARKETPLACE_KEY: string | null = null;

export function AppProvider({ children }: { children: React.ReactNode }) {
  const [ready, setReady] = useState(false);
  const [token, setToken] = useState<string | null>(null);
  const [organizer, setOrganizer] = useState<Organizer | null>(null);
  const [contextKind, setContextKind] = useState<ContextKind>('organizer');
  const [accentHex, setAccentHex] = useState<string | null>(null);
  const [available, setAvailable] = useState<AvailableOrganizer[]>([]);

  const apiCtx: ApiContext = useMemo(
    () => ({ kind: contextKind, token, marketplaceKey: MARKETPLACE_KEY }),
    [contextKind, token],
  );

  const applyResult = useCallback(
    async (r: LoginResult, persist = true) => {
      const kind = kindFromType(r.organizer.organizer_type);
      setToken(r.token);
      setOrganizer(r.organizer);
      setContextKind(kind);
      setAvailable(r.available_organizers ?? []);
      if (persist) {
        const s: PersistedSession = {
          token: r.token,
          contextKind: kind,
          organizerId: r.organizer.id,
          organizerName: r.organizer.name,
          accent: accentHex,
          marketplaceKey: MARKETPLACE_KEY,
        };
        await saveSession(s);
      }
    },
    [accentHex],
  );

  useEffect(() => {
    (async () => {
      const s = await loadSession();
      if (s?.token) {
        setToken(s.token);
        setContextKind(s.contextKind);
        setAccentHex(s.accent ?? null);
        try {
          const fresh = await api.me({
            kind: s.contextKind,
            token: s.token,
            marketplaceKey: s.marketplaceKey,
          });
          await applyResult(fresh, false);
        } catch {
          // token invalid / offline — keep cached minimal identity
          setOrganizer({ id: s.organizerId, name: s.organizerName, slug: '' });
        }
      }
      setReady(true);
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const signIn = useCallback(
    async (email: string, password: string) => {
      const r = await api.login(
        { kind: 'organizer', marketplaceKey: MARKETPLACE_KEY },
        email,
        password,
      );
      await applyResult(r);
    },
    [applyResult],
  );

  const switchTo = useCallback(
    async (organizerId: number) => {
      const r = await api.switchOrganizer(apiCtx, organizerId);
      await applyResult(r);
    },
    [apiCtx, applyResult],
  );

  const signOut = useCallback(async () => {
    try {
      if (token) await api.logout(apiCtx);
    } catch {
      /* ignore */
    }
    await clearSession();
    setToken(null);
    setOrganizer(null);
    setAvailable([]);
  }, [apiCtx, token]);

  const accent = useMemo(
    () => accentFor(contextKind, accentHex),
    [contextKind, accentHex],
  );

  const value: AppState = {
    ready,
    authed: !!token,
    organizer,
    contextKind,
    accent,
    available,
    apiCtx,
    signIn,
    switchTo,
    signOut,
  };

  return <AppCtx.Provider value={value}>{children}</AppCtx.Provider>;
}

export function useApp(): AppState {
  const ctx = useContext(AppCtx);
  if (!ctx) throw new Error('useApp must be used within AppProvider');
  return ctx;
}
