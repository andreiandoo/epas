/* =========================================================
   CLIENT — constructori comuni, portati 1:1 din client-app.html.
   Fiecare componenta de aici corespunde unei functii din prototip;
   clasele si sirurile de stil sunt copiate VERBATIM (prin sx()).
   ========================================================= */
import type { ReactNode } from 'react';
import { Ic, cn, sx } from '../../design/sx';
import { I } from '../../mock/prototype';
import { useNav } from './nav';

/* ---------- sb() — NU se porteaza ----------
   Prototipul deseneaza o bara de status falsa ("9:41" + semnal/wifi/baterie)
   pentru ca rula intr-o rama de telefon desenata. Pe telefonul real exista
   bara de status a sistemului, deci cea simulata ar fi dubla.
   Locul ei e tinut de safe-area-inset-top, aplicat in client.css pe
   .stickytop si .dbar — asa continutul incepe exact sub bara reala. */

/* ---------- topbar(inner, below) ---------- */
export function TopBar({ children, below }: { children: ReactNode; below?: ReactNode }) {
  return (
    <div className="stickytop">
      <div className="hrow">{children}</div>
      {below}
    </div>
  );
}

/* ---------- dbar(right, title) — header peste hero-image ---------- */
export function DBar({ right, title }: { right?: ReactNode; title?: string }) {
  const { back } = useNav();
  return (
    <div className="dbar" data-dbar="">
      <div className="between pad" style={sx('padding-top:4px')}>
        <div className="icon-btn glass" onClick={back}>
          <Ic svg={I.back} />
        </div>
        {title ? <div className="dtitle">{title}</div> : <div style={sx('flex:1')} />}
        <div className="row" style={sx('gap:9px')}>
          {right}
        </div>
      </div>
    </div>
  );
}

/* ---------- backTitle(title, sub, right) ---------- */
export function BackTitle({ title, sub, right }: { title: ReactNode; sub?: ReactNode; right?: ReactNode }) {
  const { back } = useNav();
  return (
    <>
      <div className="row" style={sx('gap:12px;min-width:0')}>
        <div className="icon-btn" onClick={back}>
          <Ic svg={I.back} />
        </div>
        <div style={sx('min-width:0')}>
          <div className="h2" style={sx('white-space:nowrap;overflow:hidden;text-overflow:ellipsis')}>
            {title}
          </div>
          {sub ? <div className="muted" style={sx('font-size:11.5px')}>{sub}</div> : null}
        </div>
      </div>
      {right ?? <div style={sx('width:42px')} />}
    </>
  );
}

/* ---------- nav(active) — bottom nav: 4 iteme + FAB central ---------- */
export function BottomNav({ active }: { active: string }) {
  const { tab } = useNav();
  const item = (id: string, icon: string) => (
    <div className={cn('nav', active === id && 'on')} onClick={() => tab(id)}>
      <Ic svg={icon} />
    </div>
  );
  return (
    <>
      <div className="navspace" />
      <div className="bnav">
        {item('home', I.nhome)}
        {item('explore', I.nexplore)}
        <div className="fab" onClick={() => tab('wallet')}>
          <Ic svg={I.nscan} />
        </div>
        {item('tickets', I.nticket)}
        {item('profile', I.nprofile)}
      </div>
    </>
  );
}

/* ---------- secH(ic, icbg, iccol, title, sub, more) ---------- */
export function SecH({
  icon,
  icbg,
  iccol,
  title,
  sub,
  more,
}: {
  icon: string;
  icbg: string;
  iccol: string;
  title: ReactNode;
  sub?: ReactNode;
  more?: [string, () => void];
}) {
  return (
    <div className="sec-h">
      <div className="ic" style={{ background: icbg, color: iccol }}>
        <Ic svg={icon} />
      </div>
      <div className="tx">
        <div className="t">{title}</div>
        {sub ? <div className="s">{sub}</div> : null}
      </div>
      {more ? (
        <span className="more" onClick={more[1]}>
          {more[0]} <Ic svg={I.arrow} />
        </span>
      ) : null}
    </div>
  );
}

/* ---------- setHead(title, sub) — antet pentru paginile de setari ---------- */
export function SetHead({ title, sub }: { title: string; sub?: string }) {
  return (
    <TopBar>
      <BackTitle title={title} sub={sub} />
    </TopBar>
  );
}
