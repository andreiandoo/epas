/* =========================================================
   LIGHTBOX de galerie — port al lui renderLB/openLB/closeLB/stepLB
   (client-app.html, liniile 490-497).

   In prototip e un singur element #lightbox in afara viewport-ului, manipulat
   direct prin innerHTML. Aici e o componenta cu stare, randata in ClientRoot
   — CSS-ul (#lightbox, .lbtop/.lbstage/.lbimg/.lbnav/.lbdots) e cel portat,
   scopat sub .app-client, deci arata identic.

   `lbtop` are padding-top 52px in prototip, ca sa treaca de bara de status
   simulata. Pe telefonul real bara e reala, deci punem safe-area in loc.
   ========================================================= */
import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';
import { Ic, cn } from '../../design/sx';
import { I, xIcon } from '../../mock/prototype';

type LbState = { g: string[]; i: number; glyph: string } | null;

type LbApi = {
  open: (gallery: string[], index?: number, glyph?: string) => void;
  close: () => void;
  step: (d: number) => void;
  state: LbState;
};

const Ctx = createContext<LbApi | null>(null);

export function useLightbox() {
  const api = useContext(Ctx);
  if (!api) throw new Error('useLightbox folosit in afara <LightboxProvider>');
  return api;
}

export function LightboxProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<LbState>(null);

  const open = useCallback((g: string[], i = 0, glyph = '🖼') => {
    if (!g || !g.length) return;
    setState({ g, i, glyph });
  }, []);
  const close = useCallback(() => setState(null), []);
  const step = useCallback(
    (d: number) => setState((s) => (s ? { ...s, i: (s.i + d + s.g.length) % s.g.length } : s)),
    [],
  );

  const api = useMemo<LbApi>(() => ({ open, close, step, state }), [open, close, step, state]);

  return (
    <Ctx.Provider value={api}>
      {children}
      <Lightbox state={state} onClose={close} onStep={step} />
    </Ctx.Provider>
  );
}

function Lightbox({
  state,
  onClose,
  onStep,
}: {
  state: LbState;
  onClose: () => void;
  onStep: (d: number) => void;
}) {
  if (!state) return <div id="lightbox" />;
  const { g, i, glyph } = state;
  return (
    <div id="lightbox" className={cn(state && 'show')}>
      <div className="lbtop">
        <div style={{ color: '#fff', fontWeight: 600, fontSize: '13px' }}>
          {i + 1} / {g.length}
        </div>
        <div className="circ" onClick={onClose} style={{ position: 'static' }}>
          <Ic svg={xIcon} />
        </div>
      </div>
      <div className="lbstage" onClick={(e) => e.target === e.currentTarget && onClose()}>
        <div className="lbimg" style={{ background: g[i] }}>
          <span className="em">{glyph}</span>
        </div>
      </div>
      <div className="lbnav">
        {g.length > 1 ? (
          <div className="circ" onClick={() => onStep(-1)} style={{ position: 'static' }}>
            <Ic svg={I.back} />
          </div>
        ) : null}
        <div className="lbdots">
          {g.map((_, k) => (
            <span key={k} className={k === i ? 'on' : ''} />
          ))}
        </div>
        {g.length > 1 ? (
          <div className="circ" onClick={() => onStep(1)} style={{ position: 'static', transform: 'scaleX(-1)' }}>
            <Ic svg={I.back} />
          </div>
        ) : null}
      </div>
    </div>
  );
}
