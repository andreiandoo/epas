/* =========================================================
   CLIENT — constructori comuni, portati 1:1 din client-app.html.
   Fiecare componenta de aici corespunde unei functii din prototip;
   clasele si sirurile de stil sunt copiate VERBATIM (prin sx()).
   ========================================================= */
import { useEffect, useRef, type ReactNode } from 'react';
import { claimToken, releaseBottomNav, requestBottomNav, useBottomNavState } from './bottomNav';
import { Ic, cn, sx } from '../../design/sx';
import { I } from '../../mock/prototype';
import { useNav } from './nav';

/* ---------- sb() -> SafeTop ----------
   Prototipul deseneaza o bara de status falsa ("9:41" + semnal/wifi/baterie),
   pentru ca rula intr-o rama de telefon desenata. Pe telefonul real avem bara
   reala a sistemului, deci CONTINUTUL ei nu se porteaza — dar LOCUL ei da.

   `sb()` are `padding:15px 26px 4px`: ocupa inaltimea barei si lasa 4px pana
   la titlu. Reproducem exact asta: inaltimea reala a barei (--safe-top, pusa
   de MainActivity) plus aceiasi 4px de respiro.

   Se pune fix unde era `sb()` in prototip — asa toate ecranele pornesc din
   acelasi punct, iar fundalul paginii urca sub ora si iconitele de sistem. */
export const SafeTop = () => <div className="safe-top" aria-hidden="true" />;

/* ---------- topbar(inner, below) ---------- */
export function TopBar({ children, below }: { children: ReactNode; below?: ReactNode }) {
  return (
    <div className="stickytop">
      <SafeTop />
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
      <SafeTop />
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
/**
 * Ecranul CERE bara de jos; carcasa o deseneaza (vezi bottomNav.ts).
 *
 * Randeaza doar spatiul de sub continut, ca ultimul rand sa nu ramana ascuns
 * dupa bara. Semnatura e neschimbata, deci niciun ecran n-a trebuit atins.
 */
export function BottomNav({ active }: { active: string }) {
  const token = useRef(0);
  if (token.current === 0) token.current = claimToken();

  useEffect(() => {
    requestBottomNav(token.current, active);
  }, [active]);

  useEffect(() => {
    const mine = token.current;

    return () => releaseBottomNav(mine);
  }, []);

  return <div className="navspace" />;
}

/** Bara propriu-zisa. Se monteaza o singura data, in carcasa. */
export function AppBottomNav() {
  const { tab } = useNav();
  const { visible, active } = useBottomNavState();

  const item = (id: string, icon: string) => (
    <div className={cn('nav', active === id && 'on')} onClick={() => tab(id)}>
      <Ic svg={icon} />
    </div>
  );

  return (
    <div className={cn('bnav', !visible && 'hiddenbar')} aria-hidden={!visible}>
      {item('home', I.nhome)}
      {item('explore', I.nexplore)}
      <div className={cn('fab', active === 'wallet' && 'on')} onClick={() => tab('wallet')}>
        <Ic svg={I.nscan} />
      </div>
      {item('tickets', I.nticket)}
      {item('profile', I.nprofile)}
    </div>
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

/* =========================================================
   Continut care nu exista in aplicatie.

   NU e din prototip. Ecranele de eveniment, artist si locatie citesc acum din
   catalogul real, deci ajung aici DOAR cand serverul spune ca nu exista sau
   cand cererea esueaza — nu pentru ca ecranul n-ar fi gata.

   Deliberat spune adevarul, in loc sa inventeze un inlocuitor: un ecran de
   eveniment care arata alt eveniment e mai rau decat unul care recunoaste ca
   n-are ce arata.
   ========================================================= */
export function MissingContent({ what = 'Conținutul' }: { what?: string }) {
  const { back } = useNav();

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar>
        <div className="row" style={sx('gap:12px')}>
          <div className="icon-btn" onClick={back}>
            <Ic svg={I.back} />
          </div>
          <div className="h2">Indisponibil</div>
        </div>
        <div style={sx('width:42px')} />
      </TopBar>

      <div className="pad" style={sx('margin-top:70px;text-align:center')}>
        <div style={sx('font-size:44px;opacity:.5')}>🔍</div>
        <div style={sx('font-weight:600;font-size:15px;margin-top:10px')}>{what} nu a putut fi găsit</div>
        <div className="muted" style={sx('font-size:12.5px;margin-top:6px;line-height:1.5')}>
          Poate a fost șters sau nu mai este public. Verifică-ți conexiunea și încearcă din nou.
        </div>
        <button className="cta" style={sx('width:auto;padding:12px 22px;margin:20px auto 0')} onClick={back}>
          Înapoi
        </button>
      </div>
    </div>
  );
}

/**
 * Ecranul cat timp se aduce o fisa din catalog.
 *
 * Un ecran gol pentru o secunda arata ca o eroare; bara de sus ramane pe loc,
 * deci butonul de inapoi e disponibil imediat chiar daca datele intarzie.
 */
export function CatalogLoading({ title }: { title?: string }) {
  const { back } = useNav();

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar>
        <div className="row" style={sx('gap:12px')}>
          <div className="icon-btn" onClick={back}>
            <Ic svg={I.back} />
          </div>
          <div className="h2">{title ?? 'Se încarcă'}</div>
        </div>
        <div style={sx('width:42px')} />
      </TopBar>

      <div className="pad" style={sx('margin-top:16px;display:flex;flex-direction:column;gap:12px')}>
        <div className="sk" style={sx('height:200px;border-radius:22px')} />
        <div className="sk" style={sx('height:20px;width:70%;border-radius:8px')} />
        <div className="sk" style={sx('height:14px;width:45%;border-radius:8px')} />
        <div className="sk" style={sx('height:14px;width:90%;border-radius:8px')} />
        <div className="sk" style={sx('height:14px;width:80%;border-radius:8px')} />
      </div>
    </div>
  );
}
