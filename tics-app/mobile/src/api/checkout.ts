/* =========================================================
   CUMPARARE DE BILETE.

     POST /api/app/checkout/order            -> creeaza comanda (rezerva stoc)
     POST /api/app/checkout/order/{id}/pay   -> intoarce `payment_url`
     GET  /api/app/checkout/order/{id}       -> starea comenzii

   Serverul NU rescrie fluxul de vanzare: deleaga catre acelasi cod care ruleaza
   pentru ambilet.ro si bilete.online. Aici, in client, asta inseamna ca nu avem
   de calculat nimic — trimitem ce s-a ales si primim adresa procesatorului.

   PLATA se face in browserul sistemului, nu intr-un WebView al nostru: 3-D
   Secure, Apple Pay si aplicatiile bancare refuza sa ruleze intr-un WebView
   incorporat, iar bancile romanesti sunt printre cele mai stricte. Dupa
   intoarcere, ecranul intreaba serverul cum a iesit — confirmarea vine oricum
   pe webhook, deci nu depindem de revenirea utilizatorului in aplicatie.
   ========================================================= */
import { APP_API, getAppToken } from './orgApp';

export type CheckoutTicket = { ticket_type_id: number; quantity: number };

export type CheckoutCustomer = {
  email: string;
  first_name: string;
  last_name: string;
  phone?: string;
};

export type CheckoutOrder = {
  id: number;
  reference: string | null;
  status: string;
  total: number;
  paid: boolean;
  expires_at: string | null;
};

/** Eroare cu inteles, nu doar „a esuat": ecranul are ce spune utilizatorului. */
export type CheckoutError = { code?: string; message: string };

type Result<T> = { ok: true; data: T } | { ok: false; error: CheckoutError };

async function call<T>(path: string, init?: RequestInit): Promise<Result<T>> {
  const token = getAppToken();

  if (!token) {
    return { ok: false, error: { code: 'no_account', message: 'Intră în contul Tics ca să cumperi bilete.' } };
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
      | { success?: boolean; data?: T; message?: string; code?: string }
      | null;

    if (!res.ok || !body || body.success === false) {
      return {
        ok: false,
        error: {
          code: body?.code,
          message: body?.message ?? 'Nu am putut finaliza operațiunea. Încearcă din nou.',
        },
      };
    }

    return { ok: true, data: (body.data ?? body) as T };
  } catch {
    return { ok: false, error: { message: 'Fără conexiune. Verifică internetul și încearcă din nou.' } };
  }
}

export const createOrder = (payload: {
  event_id: number;
  tickets: CheckoutTicket[];
  customer: CheckoutCustomer;
}) => call<{ order_id?: number; id?: number }>('/checkout/order', { method: 'POST', body: JSON.stringify(payload) });

export const startPayment = (orderId: number) =>
  call<{ payment_url: string | null; processor: string | null }>(`/checkout/order/${orderId}/pay`, { method: 'POST' });

export const orderStatus = (orderId: number) => call<CheckoutOrder>(`/checkout/order/${orderId}`);
