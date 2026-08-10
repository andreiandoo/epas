/* =========================================================
   Contul de organizator conectat la un partener.

   Fara legatura asta, tot backend-ul de organizator (/api/app/org/*) raspunde
   403 — scoping-ul se impune pe server, din legatura, niciodata din ce trimite
   aplicatia. Deci ecranul de conectare e poarta de intrare a intregii parti
   operationale.
   ========================================================= */
import { useCallback, useEffect, useState } from 'react';
import { connectOrganizer, fetchOrgEvents, getAppToken, type OrgEvent } from '../../api/orgApp';

/** Partenerii publici. Lista e publica prin design — vezi discutia de arhitectura. */
export const PARTNERS: { id: number; name: string; host: string }[] = [
  { id: 1, name: 'ambilet.ro', host: 'ambilet.ro' },
  { id: 3, name: 'bilete.online', host: 'bilete.online' },
];

export type OrgAccountState = {
  /** true cand exista o legatura activa si serverul ne da evenimente */
  connected: boolean;
  events: OrgEvent[];
  loading: boolean;
  error: string | null;
};

export function useOrgAccount() {
  const [state, setState] = useState<OrgAccountState>({
    connected: false,
    events: [],
    loading: !!getAppToken(),
    error: null,
  });

  const refresh = useCallback(async () => {
    if (!getAppToken()) {
      setState({ connected: false, events: [], loading: false, error: null });
      return;
    }
    setState((s) => ({ ...s, loading: true }));
    const events = await fetchOrgEvents();
    setState({
      // null = 403 (nicio legatura) sau retea cazuta; lista goala = legat, dar
      // fara evenimente. Diferenta conteaza pentru ce arata ecranul.
      connected: events !== null,
      events: events ?? [],
      loading: false,
      error: null,
    });
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  const connect = useCallback(
    async (marketplaceClientId: number, email: string, password: string) => {
      setState((s) => ({ ...s, loading: true, error: null }));
      const r = await connectOrganizer(marketplaceClientId, email, password);
      if (!r.ok) {
        setState((s) => ({ ...s, loading: false, error: r.error ?? 'Conectare eșuată.' }));
        return false;
      }
      await refresh();
      return true;
    },
    [refresh],
  );

  return { ...state, refresh, connect };
}
