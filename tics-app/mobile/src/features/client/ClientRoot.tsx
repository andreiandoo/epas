/* =========================================================
   SHELL CLIENT — randeaza stiva de ecrane cu tranzitiile prototipului.
   Ecranul care iese ramane montat 420ms cu clasa .exit-*, exact ca
   paint(dir) din client-app.html.

   Ecranele portate se inregistreaza in SCREENS. Cele inca neportate cad pe
   un placeholder explicit, ca sa se vada clar ce lipseste — nu pe o
   aproximare care ar putea trece drept "gata".
   ========================================================= */
import { useLayoutEffect, useState } from 'react';
import { cn } from '../../design/sx';
import { NavProvider, useNav, type Frame } from './nav';
import { useClient } from '../../store/client';
import { Home } from './screens/Home';
import { Explore } from './screens/Explore';
import { Tickets } from './screens/Tickets';
import { PayQr, Topup, Wallet } from './screens/Wallet';

type ScreenFn = (data: Record<string, unknown>) => JSX.Element;

/** Registrul de ecrane. Cheile corespund 1:1 cu S.* din prototip. */
const SCREENS: Record<string, ScreenFn> = {
  home: () => <Home />,
  explore: () => <Explore />,
  tickets: () => <Tickets />,
  wallet: () => <Wallet />,
  topup: () => <Topup />,
  payqr: () => <PayQr />,
};

function Placeholder({ id }: { id: string }) {
  const { back } = useNav();
  return (
    <div className="grid" style={{ minHeight: '100%', display: 'grid', placeItems: 'center', padding: 28 }}>
      <div style={{ textAlign: 'center' }}>
        <div style={{ fontSize: 40, marginBottom: 12 }}>🚧</div>
        <div className="h2">Ecranul „{id}" nu e încă portat</div>
        <div className="muted" style={{ fontSize: 13, marginTop: 6, lineHeight: 1.5 }}>
          Se portează 1:1 din prototip, pe rând.
        </div>
        <button className="cta ghost" style={{ marginTop: 18 }} onClick={back}>
          Înapoi
        </button>
      </div>
    </div>
  );
}

/** Ecran care iese: pastreaza .exit-* pana il demonteaza navigatorul. */
function LeavingScreen({ frame, cls }: { frame: Frame; cls: string }) {
  const render = SCREENS[frame.id];
  return <div className={cn('screen', cls)}>{render ? render(frame.data ?? {}) : <Placeholder id={frame.id} />}</div>;
}

/**
 * Ecran care intra: se monteaza cu .enter-* (opacity 0 + translate din
 * client.css) si scapa de clasa la frame-ul urmator, ceea ce porneste
 * tranzitia — echivalentul lui `void el.offsetWidth` din prototip.
 *
 * Starea e locala componentei, nu o cautare in DOM: `.screen:last-child` nu
 * merge, pentru ca ultimul copil al lui .app-client e #toast, si ecranul ar
 * ramane blocat la opacity 0.
 */
function EnteringScreen({ frame, cls }: { frame: Frame; cls: string }) {
  const [entered, setEntered] = useState(false);
  useLayoutEffect(() => {
    const raf = requestAnimationFrame(() => setEntered(true));
    return () => cancelAnimationFrame(raf);
  }, []);
  const render = SCREENS[frame.id];
  return (
    <div className={cn('screen', !entered && cls)}>
      {render ? render(frame.data ?? {}) : <Placeholder id={frame.id} />}
    </div>
  );
}

function Stack() {
  const { stack, leaving, dir } = useNav();
  const top = stack[stack.length - 1];
  return (
    <>
      {leaving ? (
        <LeavingScreen frame={leaving.frame} cls={leaving.dir === 'back' ? 'exit-back' : 'exit-forward'} />
      ) : null}
      <EnteringScreen key={top.key} frame={top} cls={dir === 'back' ? 'enter-back' : 'enter-forward'} />
    </>
  );
}

export function ClientRoot() {
  const toast = useClient((s) => s.toast);
  return (
    <div className="app-client">
      <NavProvider initial="home">
        <Stack />
      </NavProvider>
      <div id="toast" className={toast ? 'show' : ''}>
        {toast}
      </div>
    </div>
  );
}
