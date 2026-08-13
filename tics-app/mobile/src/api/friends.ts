/* =========================================================
   PRIETENI (/api/app/friends).

   Graful trăiește pe contul Tics, nu pe cel de partener: un prieten de pe
   ambilet.ro și unul de pe bilete.online trebuie să fie aceiași oameni în
   aplicație. De aceea toate apelurile de aici merg pe tokenul de aplicație.

   DESCOPERIREA se face DOAR prin cod sau link. Nu există căutare după nume sau
   email — nici aici, nici pe server — pentru că ar transforma aplicația într-un
   director în care oricine verifică dacă o anumită persoană are cont.
   ========================================================= */
import { APP_API, getAppToken } from './orgApp';

export type FriendCard = { id: number; name: string; avatar: string | null };

export type FriendRequest = { id: number; source: string; account: FriendCard };

export type PendingInvite = { email: string; name: string | null; source: string };

export type FriendsState = {
  friends: FriendCard[];
  requests: FriendRequest[];
  sent: { id: number; account: FriendCard }[];
  invite_code: string;
  invite_url: string;
  invited: PendingInvite[];
  visibility: 'nobody' | 'friends';
};

type Result<T> = { ok: true; data: T } | { ok: false; message: string };

async function call<T>(path: string, init?: RequestInit): Promise<Result<T>> {
  const token = getAppToken();

  if (!token) {
    return { ok: false, message: 'Intră în contul Tics ca să vezi prietenii.' };
  }

  try {
    const res = await fetch(APP_API + path, {
      ...init,
      headers: {
        Accept: 'application/json',
        ...(init?.body ? { 'Content-Type': 'application/json' } : null),
        Authorization: `Bearer ${token}`,
        ...init?.headers,
      },
    });

    const body = (await res.json().catch(() => null)) as
      | { success?: boolean; data?: T; message?: string }
      | null;

    if (!res.ok || !body || body.success === false) {
      return { ok: false, message: body?.message ?? 'Nu a mers. Încearcă din nou.' };
    }

    return { ok: true, data: (body.data ?? (body as unknown)) as T };
  } catch {
    return { ok: false, message: 'Fără conexiune. Verifică internetul.' };
  }
}

export const fetchFriends = () => call<FriendsState>('/friends');

export const redeemInviteCode = (code: string) =>
  call<null>('/friends/redeem', { method: 'POST', body: JSON.stringify({ code: code.trim().toUpperCase() }) });

export const inviteFriendByEmail = (email: string, name?: string) =>
  call<null>('/friends/invite', { method: 'POST', body: JSON.stringify({ email, name }) });

export const respondToRequest = (friendshipId: number, accept: boolean) =>
  call<null>(`/friends/${friendshipId}/respond`, { method: 'POST', body: JSON.stringify({ accept }) });

export const removeFriend = (accountId: number) => call<null>(`/friends/${accountId}`, { method: 'DELETE' });

export const blockFriend = (accountId: number) => call<null>(`/friends/${accountId}/block`, { method: 'POST' });

export const fetchFriendProfile = (accountId: number) => call<FriendCard>(`/friends/${accountId}/profile`);

export type EventFriends = {
  friends: FriendCard[];
  count: number;
  /** Dacă EU sunt vizibil la evenimentul ăsta — regula mea plus excepția lui. */
  visible: boolean;
};

/** Cine dintre prieteni merge la un eveniment, şi cum sunt eu văzut acolo. */
export const fetchEventFriends = (eventId: number) => call<EventFriends>(`/events/${eventId}/friends`);

export type ReportReason = 'spam' | 'harassment' | 'fake_profile' | 'inappropriate' | 'other';

/** Raportarea blochează automat contul — vezi FriendsController::report(). */
export const reportAccount = (accountId: number, reason: ReportReason, note?: string) =>
  call<null>('/reports', { method: 'POST', body: JSON.stringify({ subject_id: accountId, reason, note }) });

/** `scope: 'global'` schimbă regula; `'event'` scrie o excepție pentru un eveniment. */
export const setFriendsVisibility = (visible: boolean, eventId?: number) =>
  call<{ visibility?: string }>('/friends/visibility', {
    method: 'POST',
    body: JSON.stringify(
      eventId ? { scope: 'event', visible, event_id: eventId } : { scope: 'global', visible },
    ),
  });
