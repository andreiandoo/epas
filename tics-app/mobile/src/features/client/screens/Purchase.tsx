/* =========================================================
   FLUXUL DE CUMPARARE — port 1:1 din client-app.html:
     S.expdate     (861) alegerea zilei, pentru experiente
     S.tickettypes (871) tipuri de bilete + pachete + extra
     S.seatmap     (884) harta de locuri
     S.cart        (899) cos & checkout
     S.success     (921) confirmare + confetti (INIT.success)
   plus cartCompute() (linia 893), care calculeaza subtotal / taxa 2% /
   protectie 8% / reducere / puncte.
   ========================================================= */
import { useEffect, useRef, useState } from 'react';
import { Ic, Raw, cn, sx } from '../../../design/sx';
import { ADDONS, EV, EXPDAYS, I, VEN, lei, occInfo, poster } from '../../../mock/prototype';
import { TopBar, BackTitle, CatalogLoading, MissingContent, SafeTop } from '../kit';
import { useNav } from '../nav';
import { useClient, ttCountsFor } from '../../../store/client';
import { cachedEvent, useCatalogEvent } from '../catalogData';
import { createOrder, startPayment } from '../../../api/checkout';
import { customerName, useCustomer } from '../accountData';

type Ev = Record<string, any>;
/**
 * Evenimentul, din prototip sau din catalogul real.
 *
 * Poate lipsi: fisa reala se aduce asincron, iar cine intra direct pe un ecran
 * de cumparare (revenire din background, deep link) o poate gasi neincarcata.
 * De aceea intoarce `Ev | undefined` si fiecare ecran verifica — inainte,
 * absenta ei se termina intr-un ecran negru.
 */
const evOf = (id: string): Ev | undefined =>
  (EV as Record<string, Ev>)[id] ?? cachedEvent(id)?.ev;

/* ---------- cartCompute() ---------- */
export type CartItem = { n: string; p: number; q: number; pts: number; addon?: boolean };

export function cartCompute(evId: string) {
  const ev = evOf(evId);
  const st = useClient.getState();

  /* Fara eveniment (fisa neincarcata inca) intoarcem un cos gol, nu aruncam:
     functia e apelata si din ecrane care se randeaza inainte ca datele sa
     ajunga, iar o exceptie aici oprea toata aplicatia. */
  if (!ev) {
    return {
      items: [] as CartItem[],
      subtotal: 0,
      fee: 0,
      protect: 0,
      disc: 0,
      total: 0,
      pts: 0,
      pricing: { source: 'tenant', mode: 'added_on_top', rate: 2 },
      feeIncluded: false,
    };
  }

  const seatT = ev.tt.find((t: Ev) => t.seat)?.p || 120;

  let items: CartItem[] = [];
  const counts = st.ttCounts[evId];
  if (counts) {
    ev.tt.forEach((t: Ev, i: number) => {
      const q = counts[i] || 0;
      if (q > 0) items.push({ n: t.n, p: t.p, q, pts: t.pts });
    });
  }
  if (!items.length) items = [{ n: 'Categoria I', p: seatT, q: st.seats.length || 2, pts: seatT }];

  const ad = (ADDONS as Record<string, Ev[]>)[ev.id];
  if (ad)
    ad.forEach((x, i) => {
      if (st.addons[ev.id + '_' + i]) items.push({ n: x.n, p: x.p, q: 1, pts: 0, addon: true });
    });

  const subtotal = items.reduce((s, it) => s + it.p * it.q, 0);

  /* COMISIONUL nu mai e 2% fix.
     Fiecare eveniment isi poarta regula (`_pricing`, de la server):
       - eveniment de MARKETPLACE -> comisionul marketplace-ului, in modul lui.
         tics nu mai adauga nimic: isi ia partea de la marketplace, iar un al
         doilea comision ar taxa cumparatorul de doua ori pentru acelasi bilet;
       - eveniment de TENANT -> comisionul tics, adaugat peste pret.
     `included` inseamna ca e deja in pretul afisat, deci pentru cumparator
     linia e zero — nu o ascundem, o aratam ca „inclus".
     Pe evenimentele demo, unde nu exista `_pricing`, ramane 2% ca in prototip. */
  const pricing = (ev._pricing ?? { source: 'tenant', mode: 'added_on_top', rate: 2 }) as {
    source: string;
    mode: string;
    rate: number;
  };
  const feeIncluded = pricing.mode === 'included';
  const fee = feeIncluded ? 0 : Math.round(subtotal * (pricing.rate / 100) * 100) / 100;
  const protect = st.cart.protect ? Math.round(subtotal * 0.08 * 100) / 100 : 0;
  const disc = st.cart.discount || 0;
  const total = Math.max(0, subtotal + fee + protect - disc);
  const pts = items.reduce((s, it) => s + it.pts * it.q, 0);
  return { items, subtotal, fee, protect, disc, total, pts, pricing, feeIncluded };
}

const addonsTotal = (evId: string) => {
  const a = (ADDONS as Record<string, Ev[]>)[evId];
  if (!a) return 0;
  const st = useClient.getState();
  return a.reduce((s, ad, i) => s + (st.addons[evId + '_' + i] ? ad.p : 0), 0);
};

/* =========================================================
   S.expdate
   ========================================================= */
export function ExpDate() {
  const { go } = useNav();
  const evId = useClient((s) => s.ev);
  const expDay = useClient((s) => s.expDay);
  const setExpDay = (i: number) => useClient.setState({ expDay: i });
  const ev = evOf(evId);
  const days = EXPDAYS as Ev[];
  const sel = days[expDay] || days[4];

  if (!ev) return <MissingContent what="Evenimentul" />;

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar>
        <BackTitle title="Alege data" sub={ev.s} />
      </TopBar>

      <div className="pad" style={sx('margin-top:8px')}>
        <div className="listitem" style={sx('background:var(--indigo-soft);border-color:var(--indigo-line)')}>
          <div
            className="iconbadge"
            style={sx('width:40px;height:40px;background:var(--indigo-soft);color:var(--indigo-2)')}
          >
            <Ic svg={I.cal} />
          </div>
          <div className="muted" style={sx('font-size:11.5px;flex:1;line-height:1.45')}>
            Alege ziua vizitei — îți arătăm cât de aglomerat e ca să prinzi liniște. 🌿
          </div>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:16px')}>
        <div style={sx('display:grid;grid-template-columns:1fr 1fr;gap:12px')}>
          {days.map((day, i) => {
            const oi = occInfo(day.occ);
            const on = i === expDay;
            return (
              <div
                key={`${day.wd}${day.d}`}
                className="mcard"
                onClick={() => !oi.full && setExpDay(i)}
                style={{
                  padding: 14,
                  background: 'var(--surface-solid)',
                  border: `1.5px solid ${on ? 'var(--indigo)' : 'var(--line)'}`,
                  opacity: oi.full ? 0.5 : undefined,
                  position: 'relative',
                }}
              >
                {on ? (
                  <span
                    style={sx('position:absolute;top:11px;right:11px;width:22px;height:22px;border-radius:50%;background:var(--indigo);display:grid;place-items:center;color:#fff')}
                  >
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
                      <path d="M20 6L9 17l-5-5" />
                    </svg>
                  </span>
                ) : null}
                <div
                  className="muted"
                  style={sx('font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em')}
                >
                  {day.wd}
                </div>
                <div style={sx('font-size:25px;font-weight:700;margin-top:1px;line-height:1')}>
                  {day.d}
                  <span className="muted" style={sx('font-size:12px;font-weight:600')}> {day.mon}</span>
                </div>
                <div style={sx('height:6px;border-radius:9px;background:var(--surface-3);margin-top:13px;overflow:hidden')}>
                  <div style={{ height: '100%', width: `${day.occ}%`, background: oi.c, borderRadius: 9 }} />
                </div>
                <div className="row" style={sx('gap:5px;margin-top:8px')}>
                  <span className="stockdot" style={{ background: oi.c }} />
                  <span style={{ fontSize: '11px', fontWeight: 600, color: oi.c }}>{oi.l}</span>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      <div className="pad" style={sx('margin-top:16px')}>
        <div
          className="row"
          style={sx('gap:15px;justify-content:center;font-size:10.5px;font-weight:600;color:var(--muted)')}
        >
          {[
            ['var(--green-2)', 'Locuri multe'],
            ['var(--amber)', 'Se umple'],
            ['var(--red)', 'Aproape plin'],
          ].map((l) => (
            <span key={l[1]} className="row" style={sx('gap:5px')}>
              <span className="stockdot" style={{ background: l[0] }} />
              {l[1]}
            </span>
          ))}
        </div>
      </div>

      <div className="dock">
        <button className="cta" onClick={() => go('tickettypes')}>
          Continuă · {sel.wd} {sel.d} {sel.mon} <Ic svg={I.arrow} />
        </button>
      </div>
    </div>
  );
}

/* =========================================================
   S.tickettypes
   ========================================================= */
export function TicketTypes() {
  const { go, back } = useNav();
  const evId = useClient((s) => s.ev);
  const expDay = useClient((s) => s.expDay);
  const addons = useClient((s) => s.addons);
  const ttCounts = useClient((s) => s.ttCounts);
  const setTtCount = useClient((s) => s.setTtCount);
  const toggleAddon = useClient((s) => s.toggleAddon);

  const demo = evOf(evId);

  /* Evenimentele reale nu-s in datasetul prototipului; fisa lor (cu categoriile
     de bilet) e deja in cache-ul din catalogData dupa ecranul precedent, deci
     apelul de aici e de regula instant. */
  const live = useCatalogEvent(demo ? undefined : evId);

  const ev = demo ?? live.data?.ev;
  const eday = (EXPDAYS as Ev[])[expDay] || (EXPDAYS as Ev[])[4];

  if (!ev) {
    return live.loading ? <CatalogLoading title="Bilete" /> : <MissingContent what="Evenimentul" />;
  }

  const counts = ttCounts[evId] ?? ttCountsFor(evId, ev.tt.length);
  const evAddons = (ADDONS as Record<string, Ev[]>)[ev.id];

  const count = counts.reduce((a, b) => a + b, 0);
  const total = ev.tt.reduce((s: number, t: Ev, i: number) => s + t.p * (counts[i] || 0), 0) + addonsTotal(evId);

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar>
        <div className="row" style={sx('gap:12px')}>
          <div className="icon-btn" onClick={back}>
            <Ic svg={I.back} />
          </div>
          <div>
            <div className="h2">Alege bilete</div>
            <div className="muted" style={sx('font-size:11.5px')}>
              {ev.s}
            </div>
          </div>
        </div>
        <div className="icon-btn">
          <Ic svg={I.info} />
        </div>
      </TopBar>

      <div className="pad" style={sx('margin-top:8px')}>
        <div className="pts">
          <Ic svg={I.star} /> Câștigi puncte la fiecare bilet · 1 lei = 1 punct
        </div>
      </div>

      {ev.type === 'experience' ? (
        <div className="pad" style={sx('margin-top:14px')}>
          <div className="listitem" style={sx('background:var(--green-soft);border-color:var(--green-line)')}>
            <div
              className="iconbadge"
              style={sx('width:40px;height:40px;background:rgba(34,197,94,.18);color:var(--green-2)')}
            >
              <Ic svg={I.cal} />
            </div>
            <div style={sx('flex:1')}>
              <div style={sx('font-weight:600;font-size:13.5px')}>
                {eday.wd} {eday.d} {eday.mon}
              </div>
              <div className="muted" style={sx('font-size:11px')}>
                Data vizitei · {occInfo(eday.occ).l}
              </div>
            </div>
            <button className="chip" onClick={back} style={sx('padding:8px 14px')}>
              Schimbă
            </button>
          </div>
        </div>
      ) : null}

      <div className="pad" style={sx('margin-top:14px;display:flex;flex-direction:column;gap:11px')}>
        {ev.tt.map((t: Ev, i: number) => (
          /* RANDUL DE BILET, refacut.
             Purta doar nume + descriere + pret. Un tip de bilet are insa si
             beneficii („include o bautura", „acces zona VIP") si poate avea o
             reducere — informatii care decid alegerea si care lipseau cu totul.
             Asezarea urmeaza ordinea in care se citeste: ce cumperi, cat costa,
             ce include, cate iei. Pretul urca sus, langa nume, ca sa poata fi
             comparat intre categorii dintr-o privire, fara sa citesti tot. */
          <div key={t.n} className={cn('ttcard', (counts[i] || 0) > 0 && 'picked')}>
            <div className="row" style={sx('gap:12px;align-items:flex-start')}>
              <div style={sx('flex:1;min-width:0')}>
                <div className="row" style={sx('gap:7px;flex-wrap:wrap')}>
                  <div style={sx('font-weight:600;font-size:14.5px')}>{t.n}</div>
                  {t.old && t.old > t.p ? (
                    <span className="badge" style={sx('background:rgba(240,97,109,.16);color:#f0616d')}>
                      -{Math.round((1 - t.p / t.old) * 100)}%
                    </span>
                  ) : null}
                  {t.seat && ev.seatmap ? (
                    <span className="badge" style={sx('background:var(--indigo-soft);color:var(--indigo-2)')}>
                      Loc pe hartă
                    </span>
                  ) : null}
                  {t.sold ? (
                    <span className="badge" style={sx('background:var(--surface-3);color:var(--muted)')}>
                      Epuizat
                    </span>
                  ) : null}
                </div>
                {t.desc ? (
                  <div className="muted" style={sx('font-size:12px;margin-top:5px;line-height:1.45')}>
                    {t.desc}
                  </div>
                ) : null}
              </div>

              <div style={sx('text-align:right;flex:none')}>
                <div style={sx('font-weight:700;color:var(--indigo-2);font-size:17px;line-height:1;font-variant-numeric:tabular-nums')}>
                  {t.p}
                  <small style={sx('font-size:10.5px;color:var(--muted);font-weight:600')}> lei</small>
                </div>
                {t.old && t.old > t.p ? (
                  <div className="muted" style={sx('text-decoration:line-through;font-size:11.5px;margin-top:3px')}>
                    {t.old} lei
                  </div>
                ) : null}
                <div className="pts" style={sx('margin-top:6px;justify-content:flex-end')}>
                  <Ic svg={I.star} /> +{t.pts}
                </div>
              </div>
            </div>

            {/* Beneficiile: fiecare pe randul lui, cu bifa. Insirate cu virgula
                s-ar fi citit ca o descriere, nu ca o lista de lucruri incluse. */}
            {t.perks?.length ? (
              <ul className="ttperks">
                {(t.perks as string[]).map((perk) => (
                  <li key={perk}>
                    <Ic svg={I.check} />
                    <span>{perk}</span>
                  </li>
                ))}
              </ul>
            ) : null}

            <div className="between" style={sx('margin-top:12px;border-top:1px solid var(--line);padding-top:11px')}>
              <span className="muted" style={sx('font-size:11.5px')}>
                {t.sold
                  ? 'Nu mai sunt bilete'
                  : t.seat && ev.seatmap
                    ? 'Alege locul la pasul următor'
                    : 'Disponibil'}
              </span>
              <div className="row" style={sx('gap:10px')}>
                <div
                  className="icon-btn"
                  style={sx('width:32px;height:32px;border-radius:10px')}
                  onClick={() => setTtCount(evId, i, -1, ev.tt.length)}
                >
                  <Ic svg={I.minus} />
                </div>
                <span className="tnum" style={sx('font-weight:600;min-width:18px;text-align:center')}>
                  {counts[i] || 0}
                </span>
                <div
                  className="icon-btn"
                  style={sx('width:32px;height:32px;border-radius:10px;background:linear-gradient(135deg,var(--indigo),var(--indigo-3));color:#fff;border:0')}
                  onClick={() => setTtCount(evId, i, 1, ev.tt.length)}
                >
                  <Ic svg={I.plus} />
                </div>
              </div>
            </div>
          </div>
        ))}

        {ev.bundles?.length ? (
          <>
            <div className="h2" style={sx('font-size:15px;margin-top:8px')}>
              🎁 Pachete & bundles
            </div>
            {ev.bundles.map((b: Ev) => (
              <div
                key={b.n}
                className="card"
                style={sx('padding:14px;border:1.5px solid var(--indigo-line);background:var(--indigo-soft)')}
              >
                <div className="between">
                  <div style={sx('flex:1')}>
                    <div style={sx('font-weight:600;font-size:14.5px')}>{b.n}</div>
                    <div className="muted" style={sx('font-size:12px;margin-top:4px')}>
                      {b.desc}
                    </div>
                    <div className="row" style={sx('gap:8px;margin-top:8px')}>
                      <span style={sx('font-weight:600;color:var(--indigo-2);font-size:15px')}>{b.p} lei</span>
                      {b.old ? (
                        <span className="muted" style={sx('text-decoration:line-through;font-size:12.5px')}>
                          {b.old} lei
                        </span>
                      ) : null}
                      <span className="pts">
                        <Ic svg={I.star} /> +{b.pts}
                      </span>
                    </div>
                  </div>
                  <button className="chip ind on" style={sx('align-self:center')} onClick={() => go('cart')}>
                    Adaugă
                  </button>
                </div>
              </div>
            ))}
          </>
        ) : null}
      </div>

      {evAddons ? (
        <div className="pad" style={sx('margin-top:6px')}>
          <div className="h2" style={sx('font-size:15px;margin:4px 0 2px')}>
            Extra & recomandate ✨
          </div>
          <div className="muted" style={sx('font-size:11.5px;margin-bottom:11px')}>
            Închirieri, acces & servicii pentru experiența ta
          </div>
          <div style={sx('display:flex;flex-direction:column;gap:10px')}>
            {evAddons.map((ad, i) => (
              <div key={ad.n} className="card" style={sx('padding:12px;display:flex;align-items:center;gap:12px')}>
                <div
                  style={sx('width:42px;height:42px;border-radius:12px;background:var(--surface-3);display:grid;place-items:center;font-size:19px')}
                >
                  {ad.ic}
                </div>
                <div style={sx('flex:1')}>
                  <div style={sx('font-weight:600;font-size:13.5px')}>{ad.n}</div>
                  <div className="muted" style={sx('font-size:11.5px')}>
                    {ad.d} · <b style={sx('color:var(--indigo-2)')}>{ad.p} lei</b>
                    {ad.period ? ' /perioadă' : ''}
                  </div>
                </div>
                <div
                  className={cn('toggle', addons[ev.id + '_' + i] && 'on')}
                  onClick={() => toggleAddon(ev.id + '_' + i)}
                />
              </div>
            ))}
          </div>
        </div>
      ) : null}

      <div className="dock">
        <button className="cta block2" onClick={() => go(ev.seatmap ? 'seatmap' : 'cart')}>
          <span>
            Continuă · <span>{count}</span> bilete
          </span>
          <span className="tnum">{total} lei</span>
        </button>
      </div>
    </div>
  );
}

/* =========================================================
   S.seatmap
   ========================================================= */
export function SeatMap() {
  const { go, back } = useNav();
  const evId = useClient((s) => s.ev);
  const seats = useClient((s) => s.seats);
  const toggleSeat = useClient((s) => s.toggleSeat);
  const ev = evOf(evId);

  if (!ev) return <MissingContent what="Evenimentul" />;

  const seatPrice = ev.tt.find((t: Ev) => t.seat)?.p || 120;

  return (
    <div style={sx('min-height:100%;background:var(--bg);padding-bottom:2px')}>
      <TopBar>
        <div className="icon-btn" onClick={back}>
          <Ic svg={I.back} />
        </div>
        <div style={sx('text-align:center')}>
          <div style={sx('font-weight:600;font-size:15px')}>Alege locul</div>
          <div className="muted" style={sx('font-size:11px')}>
            {ev.s}
          </div>
        </div>
        <div className="icon-btn">
          <Ic svg={I.info} />
        </div>
      </TopBar>

      <div
        className="row pad"
        style={sx('justify-content:center;gap:16px;margin-top:12px;font-size:11px;color:var(--muted);font-weight:600')}
      >
        <span className="row" style={sx('gap:6px')}>
          <i style={sx('width:11px;height:11px;border-radius:3px;background:#2c2942;border:1px solid rgba(255,255,255,.14)')} />
          Liber
        </span>
        <span className="row" style={sx('gap:6px')}>
          <i style={sx('width:11px;height:11px;border-radius:3px;background:var(--indigo)')} />
          Ales
        </span>
        <span className="row" style={sx('gap:6px')}>
          <i style={sx('width:11px;height:11px;border-radius:3px;background:rgba(255,255,255,.06)')} />
          Ocupat
        </span>
      </div>

      <div style={sx('margin-top:14px')}>
        <div className="stagebar">S C E N A</div>
        <div className="seatgrid" style={sx('margin-top:14px')}>
          {'ABCDEF'.split('').map((r) => (
            <div className="seatrow" key={r}>
              {Array.from({ length: 8 }, (_, i) => {
                const id = r + (i + 1);
                const sold = (r === 'A' && [0, 1, 6, 7].includes(i)) || (r === 'C' && i === 3) || (r === 'D' && i === 2);
                const sel = seats.includes(id);
                return (
                  <div
                    key={id}
                    className={cn('seat', sold ? 'sold' : sel ? 'sel' : 'free')}
                    onClick={() => !sold && toggleSeat(id)}
                  />
                );
              })}
            </div>
          ))}
        </div>
      </div>

      <div className="pad" style={sx('margin-top:20px')}>
        <div className="listitem">
          <div style={sx('width:38px;height:38px;border-radius:11px;background:var(--indigo)')} />
          <div style={sx('flex:1')}>
            <div style={sx('font-weight:500;font-size:13.5px')}>Locuri selectate</div>
            <div className="muted" style={sx('font-size:11px')}>
              {seats.join(', ') || 'niciunul'}
            </div>
          </div>
          <div style={sx('font-weight:600')}>{seats.length * seatPrice} lei</div>
        </div>
      </div>

      <div className="dock">
        <button className="cta block2" onClick={() => go('cart')}>
          <span>
            Continuă · <span>{seats.length}</span> locuri
          </span>
          <Ic svg={I.arrow} />
        </button>
      </div>
    </div>
  );
}

/* =========================================================
   S.cart
   ========================================================= */
export function Cart() {
  const { go, back } = useNav();
  const evId = useClient((s) => s.ev);
  const cart = useClient((s) => s.cart);
  const setCart = useClient((s) => s.setCart);
  const showToast = useClient((s) => s.showToast);
  useClient((s) => s.ttCounts); // re-render la schimbarea contoarelor
  useClient((s) => s.addons);

  const ev = evOf(evId);
  const venue = ev ? ((VEN as Record<string, Ev>)[ev.ven] ?? cachedEvent(evId)?.venue) : null;
  const c = cartCompute(evId);
  const customer = useCustomer();
  const [paying, setPaying] = useState(false);

  /**
   * Plata.
   *
   * Doi pasi, ca pe site: se creeaza comanda (care rezerva stocul si porneste
   * ceasul de expirare), apoi se cere adresa procesatorului. Nu se sar peste
   * niciunul — un „succes" afisat fara comanda ar fi lasat clientul convins ca
   * are bilet.
   *
   * Adresa se deschide in browserul SISTEMULUI: 3-D Secure si aplicatiile
   * bancare refuza sa ruleze intr-un WebView incorporat. Confirmarea vine pe
   * webhook, deci comanda se finalizeaza chiar daca utilizatorul nu se mai
   * intoarce in aplicatie.
   */
  const pay = async () => {
    if (!ev) return;

    const eventId = Number(ev.id);

    if (!Number.isFinite(eventId)) {
      showToast('Acest eveniment nu se poate cumpăra din aplicație');

      return;
    }

    const tickets = (ev.tt as Ev[])
      .map((t: Ev, i: number) => ({ ticket_type_id: Number(t.id), quantity: (useClient.getState().ttCounts[evId] ?? [])[i] || 0 }))
      .filter((t) => Number.isFinite(t.ticket_type_id) && t.quantity > 0);

    if (!tickets.length) {
      showToast('Alege cel puțin un bilet');

      return;
    }

    const name = (customerName(customer) ?? '').trim();
    const [first, ...rest] = name.split(/\s+/);

    setPaying(true);

    const order = await createOrder({
      event_id: eventId,
      tickets,
      customer: {
        email: customer?.email ?? '',
        first_name: first || 'Client',
        last_name: rest.join(' ') || 'tics',
        phone: customer?.phone ?? undefined,
      },
    });

    if (!order.ok) {
      setPaying(false);
      showToast(order.error.message);

      return;
    }

    const orderId = order.data.order_id ?? order.data.id;

    if (!orderId) {
      setPaying(false);
      showToast('Comanda nu a putut fi creată');

      return;
    }

    const payment = await startPayment(orderId);
    setPaying(false);

    if (!payment.ok || !payment.data.payment_url) {
      showToast(payment.ok ? 'Plata nu este disponibilă pentru acest eveniment' : payment.error.message);

      return;
    }

    window.open(payment.data.payment_url, '_blank', 'noopener');
    go('success', { orderId });
  };

  if (!ev) return <MissingContent what="Evenimentul" />;

  let idx = 0;

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar>
        <div className="row" style={sx('gap:12px')}>
          <div className="icon-btn" onClick={back}>
            <Ic svg={I.back} />
          </div>
          <div className="h2">Coș & checkout</div>
        </div>
        <div className="icon-btn">
          <Ic svg={I.info} />
        </div>
      </TopBar>

      <div className="pad" style={sx('margin-top:14px')}>
        <div className="card" style={sx('padding:12px;display:flex;gap:12px;align-items:center')}>
          {/* Posterul real cand exista; altfel scena procedurala din prototip. */}
          {ev._bg ? (
            <div style={{ width: 56, height: 56, borderRadius: 15, flex: 'none', background: ev._bg }} />
          ) : (
            <Raw html={poster(ev, '', 'width:56px;height:56px;border-radius:15px;flex:none', undefined)} />
          )}
          <div style={sx('flex:1')}>
            <div style={sx('font-weight:600;font-size:13.5px')}>{ev.s}</div>
            <div className="muted" style={sx('font-size:11.5px')}>
              {[ev.d, ev.time, venue?.name].filter(Boolean).join(' · ')}
            </div>
          </div>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:12px')}>
        <div className="card" style={sx('padding:6px 14px')}>
          {c.items.map((it) => (
            <div key={it.n} className="between" style={sx('padding:11px 0;border-bottom:1px solid var(--line)')}>
              <div>
                <div style={sx('font-weight:500;font-size:13.5px')}>{it.n}</div>
                <div className="muted" style={sx('font-size:11.5px')}>
                  {it.q} × {it.p} lei
                </div>
              </div>
              <div style={sx('font-weight:600')}>{it.p * it.q} lei</div>
            </div>
          ))}
          <div style={sx('padding:11px 0')}>
            <span className="pts">
              <Ic svg={I.star} /> Câștigi +{c.pts} puncte
            </span>
          </div>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:14px')}>
        <div className="label">Cod de reducere</div>
        <div className="field">
          <span className="muted">
            <Ic svg={I.tag} />
          </span>
          <input placeholder="Ex: TIXELLO10" />
          <button
            className="chip ind on"
            style={sx('padding:7px 14px')}
            onClick={() => {
              setCart({ discount: 10 });
              showToast('Cod aplicat · -10 lei');
            }}
          >
            Aplică
          </button>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:14px')}>
        <div className="card" style={sx('padding:4px 14px')}>
          <div className="between" style={sx('padding:13px 0;border-bottom:1px solid var(--line)')}>
            <div style={sx('flex:1')}>
              <div style={sx('font-weight:500;font-size:13.5px')}>Nume diferit pe bilete</div>
              <div className="muted" style={sx('font-size:11px')}>
                Fiecare bilet poate avea alt beneficiar
              </div>
            </div>
            <div
              className={cn('toggle', cart.nameOnTicket && 'on')}
              onClick={() => setCart({ nameOnTicket: !cart.nameOnTicket })}
            />
          </div>
          <div className="between" style={sx('padding:13px 0')}>
            <div style={sx('flex:1')}>
              <div className="row" style={sx('gap:7px')}>
                <div style={sx('font-weight:500;font-size:13.5px')}>Protecție Bilet</div>
                <span className="badge" style={sx('color:var(--green-2);background:var(--green-soft)')}>
                  Recomandat
                </span>
              </div>
              <div className="muted" style={sx('font-size:11px')}>
                Returnezi biletele oricând · +8%
              </div>
            </div>
            <div className={cn('toggle', cart.protect && 'on')} onClick={() => setCart({ protect: !cart.protect })} />
          </div>
        </div>
      </div>

      {cart.nameOnTicket ? (
        <div className="pad" style={sx('margin-top:14px')}>
          <div className="h2" style={sx('font-size:14px;margin-bottom:10px')}>
            Nume pe fiecare bilet
          </div>
          <div style={sx('display:flex;flex-direction:column;gap:11px')}>
            {c.items
              .filter((it) => !it.addon)
              .flatMap((it) =>
                Array.from({ length: it.q }, () => {
                  idx++;
                  return (
                    <div key={`${it.n}-${idx}`} className="card" style={sx('padding:13px')}>
                      <div className="between" style={sx('margin-bottom:9px')}>
                        <span style={sx('font-weight:600;font-size:13px')}>
                          Bilet {idx} · {it.n}
                        </span>
                        <span className="badge" style={sx('background:var(--surface-3);color:var(--muted)')}>
                          QR
                        </span>
                      </div>
                      <div className="field" style={sx('margin-bottom:8px')}>
                        <Ic svg={I.user} />
                        <input placeholder="Nume beneficiar" />
                      </div>
                      <div className="row" style={sx('gap:8px')}>
                        <div className="field" style={sx('flex:1')}>
                          <input placeholder="Email" />
                        </div>
                        <div className="field" style={sx('flex:1')}>
                          <input placeholder="Telefon" />
                        </div>
                      </div>
                    </div>
                  );
                }),
              )}
          </div>
        </div>
      ) : null}

      <div className="pad" style={sx('margin-top:14px')}>
        <div className="h2" style={sx('font-size:14px;margin-bottom:10px')}>
          Metodă de plată
        </div>
        <div style={sx('display:flex;flex-direction:column;gap:10px')}>
          <div className="listitem" style={sx('border-color:var(--indigo)')}>
            <div
              style={sx('width:44px;height:30px;border-radius:7px;background:#1a1f71;color:#fff;display:grid;place-items:center;font-weight:600;font-size:11px;font-style:italic')}
            >
              VISA
            </div>
            <div style={sx('flex:1')}>
              <div style={sx('font-weight:500;font-size:13px')}>•••• 8756</div>
              <div className="muted" style={sx('font-size:11px')}>
                Card primar
              </div>
            </div>
            <div
              style={sx('width:22px;height:22px;border-radius:50%;background:var(--indigo);display:grid;place-items:center;color:#fff')}
            >
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
                <path d="M20 6L9 17l-5-5" />
              </svg>
            </div>
          </div>

          <div className="listitem" onClick={() => setCart({ cultural: !cart.cultural })}>
            <div
              style={sx('width:44px;height:30px;border-radius:7px;background:linear-gradient(135deg,#0f766e,#12b3a6);color:#fff;display:grid;place-items:center;font-size:15px')}
            >
              🎭
            </div>
            <div style={sx('flex:1')}>
              <div style={sx('font-weight:500;font-size:13px')}>Card cultural</div>
              <div className="muted" style={sx('font-size:11px')}>
                Up · Edenred · Sodexo
              </div>
            </div>
            <div
              style={{
                width: 20,
                height: 20,
                borderRadius: '50%',
                border: `2px solid ${cart.cultural ? 'var(--indigo)' : 'var(--line-2)'}`,
                background: cart.cultural ? 'var(--indigo)' : 'transparent',
              }}
            />
          </div>

          <div className="listitem">
            <div
              style={sx('width:44px;height:30px;border-radius:7px;background:linear-gradient(135deg,var(--indigo),var(--indigo-4));display:grid;place-items:center;color:#fff')}
            >
              <Ic svg={I.wallet} />
            </div>
            <div style={sx('flex:1')}>
              <div style={sx('font-weight:500;font-size:13px')}>Portofel tics</div>
              <div className="muted" style={sx('font-size:11px')}>
                Sold {lei(useClient.getState().balance)} lei
              </div>
            </div>
            <div style={sx('width:20px;height:20px;border-radius:50%;border:2px solid var(--line-2)')} />
          </div>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:16px')}>
        <div className="card" style={sx('padding:14px')}>
          <div className="between" style={sx('padding:5px 0')}>
            <span className="muted" style={sx('font-size:13px')}>
              Subtotal bilete
            </span>
            <span style={sx('font-weight:500')}>{c.subtotal} lei</span>
          </div>
          {/* Cine ia comisionul si cat — scris exact, nu „2%" fix: pe un
              eveniment de marketplace comisionul e al lor, iar tics nu mai
              adauga nimic peste. */}
          <div className="between" style={sx('padding:5px 0')}>
            <span className="muted" style={sx('font-size:13px')}>
              {c.pricing.source === 'marketplace' ? 'Taxă de serviciu' : 'Taxă tics'} ({c.pricing.rate}%)
            </span>
            <span style={sx('font-weight:500')}>
              {c.feeIncluded ? 'inclusă în preț' : `${lei(c.fee)} lei`}
            </span>
          </div>
          {c.protect ? (
            <div className="between" style={sx('padding:5px 0')}>
              <span className="muted" style={sx('font-size:13px')}>
                Protecție Bilet
              </span>
              <span style={sx('font-weight:500')}>{lei(c.protect)} lei</span>
            </div>
          ) : null}
          {c.disc ? (
            <div className="between" style={sx('padding:5px 0')}>
              <span style={sx('color:var(--green-2);font-size:13px;font-weight:500')}>Reducere</span>
              <span style={sx('font-weight:500;color:var(--green-2)')}>-{c.disc} lei</span>
            </div>
          ) : null}
          <div className="between" style={sx('padding:9px 0 2px;border-top:1px solid var(--line);margin-top:5px')}>
            <span style={sx('font-weight:600')}>Total</span>
            <span style={sx('font-weight:600;color:var(--indigo-2);font-size:17px')}>{lei(c.total)} lei</span>
          </div>
        </div>
      </div>

      <div className="dock">
        <button className="cta" onClick={() => void pay()} disabled={paying}>
          {paying ? (
            'Se pregătește plata…'
          ) : (
            <>
              Plătește · <span>{lei(c.total)}</span> lei
            </>
          )}
        </button>
      </div>
    </div>
  );
}

/* =========================================================
   S.success + INIT.success (confetti)
   ========================================================= */
const CONFETTI_COLORS = ['#8B5CF6', '#A78BFA', '#7C3AED', '#22C55E', '#22D3EE', '#F59E0B'];

export function Success() {
  const { tab } = useNav();
  const evId = useClient((s) => s.ev);
  const c = cartCompute(evId);
  const confetti = useRef<HTMLDivElement>(null);
  const credited = useRef(false);

  /* punctele se adauga o singura data, chiar daca React remonteaza */
  useEffect(() => {
    if (credited.current) return;
    credited.current = true;
    useClient.setState((s) => ({ points: s.points + c.pts }));
  }, [c.pts]);

  useEffect(() => {
    const el = confetti.current;
    if (!el) return;
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    for (let i = 0; i < 48; i++) {
      const s = document.createElement('span');
      s.style.left = Math.random() * 100 + '%';
      s.style.background = CONFETTI_COLORS[i % CONFETTI_COLORS.length];
      s.style.animationDuration = 1.6 + Math.random() * 1.7 + 's';
      s.style.animationDelay = Math.random() * 0.5 + 's';
      s.style.transform = 'rotate(' + Math.random() * 360 + 'deg)';
      el.appendChild(s);
    }
    return () => {
      el.innerHTML = '';
    };
  }, []);

  const points = useClient((s) => s.points);

  return (
    <div className="grid" style={sx('min-height:100%;display:flex;flex-direction:column')}>
      <SafeTop />
      <div className="confetti" ref={confetti} />
      <div
        style={sx('flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:30px;text-align:center;position:relative;z-index:5')}
      >
        <div className="burst">
          <Ic svg={I.check} />
        </div>
        <div className="eyebrow" style={sx('margin-top:22px')}>
          Confirmat
        </div>
        <h1 className="h1" style={sx('margin-top:8px')}>
          Comanda ta a reușit 🎉
        </h1>
        <p className="muted" style={sx('margin-top:10px;font-size:14px;line-height:1.55;max-width:28ch')}>
          Biletele QR sunt în „Biletele mele" și pe email.
        </p>
        <div className="pts" style={sx('margin-top:14px;font-size:13px;padding:8px 14px')}>
          <Ic svg={I.star} /> +{c.pts} puncte · total {points} pts
        </div>
      </div>
      <div className="pad" style={sx('padding-bottom:26px;display:flex;flex-direction:column;gap:11px')}>
        <button className="cta" onClick={() => tab('tickets')}>
          Vezi biletele mele <Ic svg={I.arrow} />
        </button>
        <button className="cta ghost" onClick={() => tab('home')}>
          Înapoi acasă
        </button>
      </div>
    </div>
  );
}
