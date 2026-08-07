/* =========================================================
   BILETE — port 1:1 al lui S.tickets() (client-app.html, linia 935) +
   INIT.tickets (mini-QR-urile desenate pe canvas).

   Structura din prototip:
     antet cu back + titlu + iconita QR · filtre (Active/Trecute/Transferate) ·
     cate un card per comanda: rand de eveniment (poster + meta + badge cu
     numarul de bilete), apoi o linie punctata cu doua "perforatii" si cate un
     rand per bilet individual, fiecare cu mini-QR.
   ========================================================= */
import { Ic, Raw, sx } from '../../../design/sx';
import { EV, I, MYTIX, VEN, poster } from '../../../mock/prototype';
import { BottomNav, SafeTop } from '../kit';
import { useNav } from '../nav';
import { Qr } from '../qr';

type Pass = { name: string; code: string; checkedIn?: string };
type Ticket = { ev: string; passes: Pass[]; seat: string; cat: string };

export function Tickets() {
  const { go } = useNav();

  return (
    <div className="grid" style={sx('min-height:100%;padding-bottom:6px')}>
      {/* Antet de ecran-radacina, ca pe Acasa si Explorează: fara sageata de
          back — Biletele mele e un tab, se ajunge acolo din bara de jos. */}
      <div className="stickytop">
        <SafeTop />
        <div className="hrow">
          <div>
            <div className="eyebrow">Bilete QR individuale</div>
            <h1 className="h1" style={sx('font-size:23px;margin-top:2px')}>
              Biletele mele
            </h1>
          </div>
          <div className="icon-btn">
            <Ic svg={I.qr} />
          </div>
        </div>
      </div>

      <div className="scroll-x" style={sx('margin-top:14px')}>
        <button className="chip ind on">Active</button>
        <button className="chip">Trecute</button>
        <button className="chip">Transferate</button>
      </div>

      <div className="pad" style={sx('margin-top:14px;display:flex;flex-direction:column;gap:14px')}>
        {(MYTIX as unknown as Ticket[]).map((tk) => {
          const ev = (EV as Record<string, Record<string, unknown>>)[tk.ev];
          const venue = (VEN as Record<string, { name: string }>)[ev.ven as string];
          return (
            <div key={tk.ev} className="card" style={sx('overflow:hidden')}>
              <div style={sx('display:flex;gap:12px;padding:13px')} onClick={() => go('ticket', { id: tk.ev, pi: 0 })}>
                <Raw html={poster(ev, '', 'width:56px;height:56px;border-radius:15px;flex:none', undefined)} />
                <div style={sx('flex:1')}>
                  <div style={sx('font-weight:600;font-size:14px')}>{ev.s as string}</div>
                  <div className="row muted" style={sx('gap:5px;font-size:11.5px;margin-top:3px')}>
                    <Ic svg={I.cal} /> {ev.d as string} · {ev.time as string}
                  </div>
                  <div className="row muted" style={sx('gap:5px;font-size:11.5px;margin-top:2px')}>
                    <Ic svg={I.pin} /> {venue?.name}
                  </div>
                </div>
                <span
                  className="badge"
                  style={sx('background:var(--indigo-soft);color:var(--indigo-2);align-self:flex-start')}
                >
                  {tk.passes.length} {tk.passes.length > 1 ? 'bilete' : 'bilet'}
                </span>
              </div>

              <div style={sx('border-top:2px dashed var(--line-2);padding:8px;position:relative')}>
                <span
                  style={sx('position:absolute;left:-8px;top:-8px;width:16px;height:16px;border-radius:50%;background:var(--bg)')}
                />
                <span
                  style={sx('position:absolute;right:-8px;top:-8px;width:16px;height:16px;border-radius:50%;background:var(--bg)')}
                />
                {tk.passes.map((p, pi) => (
                  <div
                    key={p.code}
                    className="row"
                    onClick={() => go('ticket', { id: tk.ev, pi })}
                    style={sx('gap:11px;padding:9px 8px;cursor:pointer;border-radius:12px')}
                  >
                    <div style={sx('width:44px;height:44px;border-radius:11px;background:#fff;padding:4px;flex:none')}>
                      <Qr seed={p.code.length + pi + 3} style={{ width: '100%', height: '100%' }} />
                    </div>
                    <div style={sx('flex:1')}>
                      <div style={sx('font-weight:500;font-size:13px')}>{p.name}</div>
                      <div className="muted" style={sx('font-size:11px')}>
                        {tk.cat}
                        {tk.seat !== '—' ? ' · ' + tk.seat.split(', ')[pi] : ''} · {p.code}
                      </div>
                    </div>
                    <span style={sx('font-size:11px;font-weight:600;color:var(--indigo-2)')}>
                      {pi + 1}/{tk.passes.length} ›
                    </span>
                  </div>
                ))}
              </div>
            </div>
          );
        })}
      </div>

      <BottomNav active="tickets" />
    </div>
  );
}
