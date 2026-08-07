/* =========================================================
   Contracte API — mapare EPAS tenant API (§13 din ghid).
   Formele de mai jos sunt "sursa de adevar" pentru UI: mock-urile
   din src/mock/* respecta aceleasi forme, deci cand endpointurile
   reale exista se schimba DOAR implementarea din api/client.ts.
   ========================================================= */
import type { VerticalKey } from '../mock/org';

/* ---------- identitate (§3) ---------- */
export type PropertyRole = 'admin' | 'manager' | 'staff';

export type ClientProperty = {
  kind: 'client';
  key: string;
  name: string;
  sub: string;
  email: string;
  icon: 'user';
  av: 'purple';
};

export type OrgProperty = {
  kind: 'org';
  key: string;
  /** id-ul tenantului EPAS */
  orgId: number;
  /** 'organizer' = navigator standard, 'venue' = navigator leisure */
  account: 'organizer' | 'venue';
  /** verticala (§7) */
  vertical: VerticalKey;
  /** cheia datasetului CTX folosit pentru navigatorul standard */
  ctx: Exclude<VerticalKey, 'leisure'>;
  name: string;
  sub: string;
  role: PropertyRole;
  icon: 'star' | 'ticket' | 'boat' | 'people';
  av: 'red' | 'blue' | 'amber' | 'purple' | 'green';
};

export type Property = ClientProperty | OrgProperty;

/* ---------- auth ---------- */
/** POST /auth/login → token */
export type LoginRequest = { email: string; password: string };
export type LoginResponse = { token: string; user: { id: number; name: string; email: string } };

/** GET /me/properties → [{kind, orgId, vertical, role}] */
export type PropertiesResponse = { properties: Property[] };

/* ---------- check-in (§6.2) ---------- */
/** POST /checkin/scan → {code, gate} */
export type ScanRequest = { code: string; gate: string; eventId: number };
export type ScanResponse =
  | { result: 'valid'; holder: string; ticketType: string; seat?: string; ticketId: number }
  | { result: 'duplicate'; holder: string; ticketType: string; scannedAt: string; scannedGate: string }
  | { result: 'invalid' }
  | { result: 'banned'; reason: string; code: string };

/* ---------- comenzi / POS (§6.3) ---------- */
export type OrderLine = { ticketTypeId: number; qty: number; unitPrice: number };
export type CreateOrderRequest = {
  eventId: number;
  lines: OrderLine[];
  channel: 'pos' | 'online';
  paymentMethod: 'cash' | 'card' | 'nfc';
};
export type CreateOrderResponse = { orderId: number; total: number; paymentIntentClientSecret?: string };

/* ---------- rapoarte / decontare (§6.4) ---------- */
export type ReportResponse = {
  checkinRate: number;
  totalSold: number;
  peakHour: string;
  byTicketType: { name: string; sold: number; revenue: number }[];
  hourly: number[];
  settlement: { gross: number; commissionPct: number; commission: number; net: number; payoutEta: string };
};

/* ---------- ocupare pe zone (§9.5) ---------- */
export type OccupancyResponse = { zones: { name: string; current: number; capacity: number; threshold: number }[] };

/* ---------- coada offline (§11) ---------- */
export type SyncQueueItem =
  | { kind: 'scan'; localId: string; payload: ScanRequest; at: string; operator: string }
  | { kind: 'sale'; localId: string; payload: CreateOrderRequest; at: string; operator: string };

export type SyncBatchRequest = { items: SyncQueueItem[] };
export type SyncBatchResponse = { accepted: string[]; rejected: { localId: string; reason: string }[] };
