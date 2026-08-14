/* =========================================================
   DETALIU BILET — port 1:1 al lui S.ticket() (client-app.html, linia 943)
   + INIT.ticket (slider orizontal cu snap, chips, puncte, contor) si al
   helperelor ticketInfo() / checkinBlock().

   Biletul e un "whitecard" (fundal alb) cu poster sus, datele beneficiarului,
   linie perforata cu doua gauri, QR mare si starea de check-in. Cand comanda
   are mai multe bilete, ele stau intr-un slider orizontal: chips deasupra,
   puncte dedesubt, contorul "Bilet X din N" se actualizeaza la scroll.
   ========================================================= */
import { useCallback, useEffect, useRef, useState } from 'react';
import { Ic, Raw, cn, sx } from '../../../design/sx';
import { EV, I, MYTIX, VEN, poster } from '../../../mock/prototype';
import { BottomNav, TopBar, MissingContent } from '../kit';
import { useNav } from '../nav';
import { useTickets } from '../accountData';
import { Qr } from '../qr';

type Pass = { name: string; code: string; checkedIn?: string };
type Ticket = {
  ev: string;
  passes: Pass[];
  seat: string;
  cat: string;
  date?: string;
  slot?: string;
  day?: string;
};
type Ev = Record<string, unknown>;

/* ---------- ticketInfo(ev, tk, pi) ---------- */
function Col({ l, v }: { l: string; v: string }) {
  return (
    <div style={sx('flex:1')}>
      <div className="wm2" style={sx('font-size:10px;font-weight:500;text-transform:uppercase')}>
        {l}
      </div>
      <div style={sx('font-weight:600;font-size:13px')}>{v}</div>
    </div>
  );
}

function TicketInfo({ ev, tk, pi }: { ev: Ev; tk: Ticket; pi: number }) {
  const name = tk.passes[pi].name;
  if (ev.type === 'experience')
    return (
      <>
        <Col l="Beneficiar" v={name} />
        <Col l="Data" v={tk.date || (ev.d as string)} />
        <Col l="Interval" v={tk.slot || (ev.time as string)} />
      </>
    );
  if (ev.cat === 'Festival')
    return (
      <>
        <Col l="Beneficiar" v={name} />
        <Col l="Zi" v={tk.day || 'Joi 10'} />
        <Col l="Brățară" v="NV-8842" />
      </>
    );
  return (
    <>
      <Col l="Beneficiar" v={name} />
      <Col l="Categorie" v={tk.cat} />
      <Col l="Loc" v={tk.seat !== '—' ? tk.seat.split(', ')[pi] : '—'} />
    </>
  );
}

/* ---------- checkinBlock(p) ---------- */
function CheckinBlock({ p }: { p: Pass }) {
  if (p.checkedIn) {
    return (
      <div
        style={sx('margin-top:14px;background:#e7f7ee;border:1px solid #b9e6cd;border-radius:12px;padding:10px 12px;display:flex;align-items:center;gap:9px')}
      >
        <div style={sx('width:26px;height:26px;border-radius:50%;background:#16a34a;display:grid;place-items:center;color:#fff')}>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
            <path d="M20 6L9 17l-5-5" />
          </svg>
        </div>
        <div>
          <div style={sx('font-weight:600;font-size:12.5px;color:#15803d')}>Validat la intrare</div>
          <div style={sx('font-size:11px;color:#4b8c68')}>{p.checkedIn}</div>
        </div>
      </div>
    );
  }
  return (
    <div
      style={sx('margin-top:14px;background:#f4f5f8;border:1px solid #e2e5ee;border-radius:12px;padding:10px 12px;display:flex;align-items:center;gap:9px')}
    >
      <div style={sx('width:26px;height:26px;border-radius:50%;background:#c7ccdb;display:grid;place-items:center;color:#fff')}>
        ◷
      </div>
      <div>
        <div style={sx('font-weight:600;font-size:12.5px;color:#5b6178')}>Neutilizat</div>
        <div style={sx('font-size:11px;color:#8a90a5')}>Valabil — se validează la scanare</div>
      </div>
    </div>
  );
}

/* ---------- S.ticket + INIT.ticket ---------- */
export function TicketDetail({ id, pi }: { id?: string; pi?: number }) {
  const { go, back } = useNav();

  /* Biletele REALE ale contului, cand exista sesiune.

     Ecranul citea direct `MYTIX` — biletele prototipului — deci orice bilet
     deschis din „Biletele mele" arata Coldplay si Salina Turda, indiferent
     cine esti. Grupurile reale au aceeasi forma (`ev`, `passes`, `seat`,
     `cat`), asa ca restul ecranului ramane neatins. */
  const { groups, live } = useTickets();
  const list = (live ? groups : (MYTIX as unknown as Ticket[])) as unknown as Ticket[];
  const tk = list.find((t) => t.ev === id) || list[0];
  const pi0 = Number(pi || 0);

  /* Pentru biletele reale nu exista o fisa in datasetul prototipului. Titlul,
     data si sala vin din grupul insusi — nu se cauta in `EV`, unde n-au ce sa
     gaseasca si de unde ar veni evenimentul altcuiva. */
  const g = live ? (tk as unknown as { title?: string; venue?: string; date?: string; time?: string; cat?: string }) : null;
  const ev = (
    g
      ? { s: g.title ?? 'Bilet', d: g.date ?? '', time: g.time ?? '', cat: g.cat ?? '', city: g.venue ?? '', type: 'event', ven: '' }
      : (EV as Record<string, Ev>)[tk.ev]
  ) as Ev;
  const venue = g ? { name: g.venue ?? '' } : (VEN as Record<string, { name: string }>)[ev.ven as string];
  const n = tk.passes.length;

  if (!tk) return <MissingContent what="Biletul" />;

  const slider = useRef<HTMLDivElement>(null);
  const [active, setActive] = useState(pi0);

  /* pozitionarea initiala pe biletul cerut (echivalentul rAF din INIT.ticket) */
  useEffect(() => {
    const sl = slider.current;
    if (!sl) return;
    const raf = requestAnimationFrame(() => {
      const s = sl.children[pi0] as HTMLElement | undefined;
      if (s) sl.scrollLeft = s.offsetLeft;
    });
    return () => cancelAnimationFrame(raf);
  }, [pi0]);

  const onScroll = useCallback(() => {
    const sl = slider.current;
    if (!sl) return;
    setActive(Math.max(0, Math.min(n - 1, Math.round(sl.scrollLeft / sl.clientWidth))));
  }, [n]);

  const goTo = (j: number) => {
    const sl = slider.current;
    const s = sl?.children[j] as HTMLElement | undefined;
    if (sl && s) sl.scrollTo({ left: s.offsetLeft, behavior: 'smooth' });
  };

  return (
    <div style={sx('min-height:100%;background:var(--bg);padding-bottom:8px')}>
      <TopBar>
        <div className="icon-btn" onClick={back}>
          <Ic svg={I.back} />
        </div>
        <div style={sx('text-align:center')}>
          <div className="h2">Biletul tău</div>
          <div className="muted" style={sx('font-size:11px')}>
            Bilet {active + 1} din {n}
          </div>
        </div>
        <div className="icon-btn">
          <Ic svg={I.share} />
        </div>
      </TopBar>

      {n > 1 ? (
        <>
          <div className="scroll-x" style={sx('margin-top:12px;padding:0 20px')}>
            {tk.passes.map((pp, j) => (
              <button key={pp.code} className={cn('chip', j === active && 'ind on')} onClick={() => goTo(j)}>
                {pp.name.split(' ')[0]} · {j + 1}/{n}
              </button>
            ))}
          </div>
          <div className="muted" style={sx('text-align:center;font-size:10.5px;margin-top:9px')}>
            ‹ glisează pentru a trece printre bilete ›
          </div>
        </>
      ) : null}

      <div className="tkslider" ref={slider} onScroll={onScroll}>
        {tk.passes.map((p, idx) => (
          <div className="tkslide" key={p.code}>
            <div className="pad" style={sx('padding-top:14px')}>
              <div className="whitecard">
                <Raw html={poster(ev, '', 'height:130px', undefined)} />
                <div style={sx('padding:16px 18px 2px')}>
                  <div style={sx('font-weight:600;font-size:17px;letter-spacing:-.02em')}>{ev.s as string}</div>
                  <div className="wm2" style={sx('font-size:12.5px;margin-top:2px')}>
                    {venue?.name} · {ev.city as string}
                  </div>
                  <div className="row" style={sx('margin-top:14px')}>
                    <TicketInfo ev={ev} tk={tk} pi={idx} />
                  </div>
                </div>
                <div
                  style={sx('position:relative;padding:18px;margin-top:12px;border-top:2px dashed #e2e5ee;text-align:center')}
                >
                  <span
                    style={sx('position:absolute;left:-10px;top:-10px;width:20px;height:20px;border-radius:50%;background:var(--bg)')}
                  />
                  <span
                    style={sx('position:absolute;right:-10px;top:-10px;width:20px;height:20px;border-radius:50%;background:var(--bg)')}
                  />
                  <Qr seed={p.code.length + idx + 11} size={360} style={{ width: 196, height: 196 }} />
                  <div style={sx('margin-top:10px;font-size:12px;font-weight:600;letter-spacing:1px')}>{p.code}</div>
                  <div className="wm2" style={sx('font-size:11px;margin-top:2px')}>
                    Prezintă acest cod QR la intrare
                  </div>
                  <CheckinBlock p={p} />
                </div>
              </div>

              <div style={sx('display:flex;flex-direction:column;gap:10px;margin-top:16px')}>
                <button
                  className="cta ghost"
                  onClick={() => go('transfer', { id: tk.ev, pi: idx })}
                  style={sx('padding:14px')}
                >
                  <Ic svg={I.transfer} /> Transferă biletul
                </button>
                <button className="cta dark" style={sx('padding:14px')}>
                  <Ic svg={I.wallet} /> Adaugă în Wallet
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>

      {n > 1 ? (
        <div className="tkdots">
          {tk.passes.map((p, j) => (
            <span key={p.code} className={j === active ? 'on' : ''} />
          ))}
        </div>
      ) : null}

      <BottomNav active="tickets" />
    </div>
  );
}

/* ---------- S.transfer ---------- */
export function Transfer({ id, pi }: { id?: string; pi?: number }) {
  const { back } = useNav();
  const list = MYTIX as unknown as Ticket[];
  const tk = list.find((t) => t.ev === id) || list[0];
  const p = tk.passes[Number(pi || 0)];
  const ev = (EV as Record<string, Ev>)[tk.ev];

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar>
        <div className="row" style={sx('gap:12px')}>
          <div className="icon-btn" onClick={back}>
            <Ic svg={I.back} />
          </div>
          <div className="h2">Transferă biletul</div>
        </div>
        <div className="icon-btn">
          <Ic svg={I.info} />
        </div>
      </TopBar>

      <div className="pad" style={sx('margin-top:14px')}>
        <div className="card" style={sx('padding:13px;display:flex;gap:12px;align-items:center')}>
          <Raw html={poster(ev, '', 'width:56px;height:56px;border-radius:15px;flex:none', undefined)} />
          <div style={sx('flex:1')}>
            <div style={sx('font-weight:600;font-size:13.5px')}>{ev.s as string}</div>
            <div className="muted" style={sx('font-size:11.5px')}>
              {tk.cat} · {p.code}
            </div>
          </div>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:8px')}>
        <div className="listitem" style={sx('background:var(--amber-soft);border-color:rgba(245,158,11,.3)')}>
          <span style={sx('font-size:18px')}>⚠️</span>
          <div className="muted" style={sx('font-size:11.5px;color:#f0c98a')}>
            După transfer, biletul dispare din contul tău și codul QR se regenerează pentru noul beneficiar.
          </div>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:16px;display:flex;flex-direction:column;gap:14px')}>
        <div>
          <div className="label">Cui trimiți?</div>
          <div className="scroll-x" style={sx('padding:0 0 4px;margin:0 0 8px')}>
            <button className="chip ind on">📱 Telefon / email</button>
            <button className="chip">👥 Contacte tics</button>
          </div>
          <div className="field">
            <Ic svg={I.user} />
            <input placeholder="Email sau telefon beneficiar" />
          </div>
        </div>
        <div>
          <div className="label">Nume beneficiar (apare pe bilet)</div>
          <div className="field">
            <Ic svg={I.user} />
            <input placeholder="Nume complet" />
          </div>
        </div>
        <div>
          <div className="label">Mesaj (opțional)</div>
          <div className="field">
            <input placeholder="Ne vedem la eveniment! 🎉" />
          </div>
        </div>
      </div>

      <div className="dock">
        <button className="cta" onClick={back}>
          Trimite biletul <Ic svg={I.arrow} />
        </button>
      </div>
    </div>
  );
}
