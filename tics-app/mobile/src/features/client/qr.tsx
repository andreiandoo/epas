/* =========================================================
   QR — portat 1:1 din drawQR() al prototipului (client-app.html, linia 1144).
   Deseneaza un cod QR *decorativ* pe canvas: markeri de colt reali, timing
   pattern real, restul modulelor pseudo-aleator dintr-un seed stabil.

   NU e un encoder QR real — prototipul nu are unul. Cand biletele vin din
   EPAS, se inlocuieste continutul cu codul real; forma vizuala ramane.
   ========================================================= */
import { useEffect, useRef } from 'react';
import type { CSSProperties } from 'react';

/** Corpul functiei e copiat verbatim din prototip. */
export function drawQR(cv: HTMLCanvasElement, opt?: { seed?: number; color?: string }) {
  const ctx = cv.getContext('2d');
  if (!ctx) return;
  const n = 25,
    q = 2,
    N = n + q * 2,
    s = cv.width / N;
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, cv.width, cv.height);
  ctx.fillStyle = (opt && opt.color) || '#141020';
  let seed = (((opt && opt.seed) || 7) * 2654435761) % 2147483647;
  const rnd = () => {
    seed = (seed * 48271) % 2147483647;
    return seed / 2147483647;
  };
  const cell = (x: number, y: number) =>
    ctx.fillRect(Math.round((x + q) * s), Math.round((y + q) * s), Math.ceil(s), Math.ceil(s));
  const finder = (fx: number, fy: number) => {
    for (let y = 0; y < 7; y++)
      for (let x = 0; x < 7; x++) {
        const b = x === 0 || x === 6 || y === 0 || y === 6;
        const i = x >= 2 && x <= 4 && y >= 2 && y <= 4;
        if (b || i) cell(fx + x, fy + y);
      }
  };
  finder(0, 0);
  finder(n - 7, 0);
  finder(0, n - 7);
  for (let y = 0; y < n; y++)
    for (let x = 0; x < n; x++) {
      if ((x < 8 && y < 8) || (x > n - 9 && y < 8) || (x < 8 && y > n - 9)) continue;
      if (x === 6 || y === 6) {
        if ((x + y) % 2 === 0) cell(x, y);
        continue;
      }
      if (rnd() > 0.5) cell(x, y);
    }
}

export function Qr({
  seed,
  size = 72,
  color,
  className,
  style,
}: {
  seed: number;
  /** rezolutia canvas-ului; dimensiunea afisata se da din `style` */
  size?: number;
  color?: string;
  className?: string;
  style?: CSSProperties;
}) {
  const ref = useRef<HTMLCanvasElement>(null);
  useEffect(() => {
    if (ref.current) drawQR(ref.current, { seed, color });
  }, [seed, color]);
  return <canvas ref={ref} className={className} width={size} height={size} style={style} />;
}
