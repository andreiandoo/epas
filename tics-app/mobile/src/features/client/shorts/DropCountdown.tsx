/* =========================================================
   Countdown pana la deschiderea vanzarii (D2).

   Cand biletele nu sunt inca la vanzare, butonul „Ia bilet" ar fi o fundatura;
   il inlocuim cu un countdown si „Amintește-mi". Tick-ul e client-side, o data
   pe secunda, ca sa nu batem serverul pentru o valoare pe care o poate calcula
   singur.
   ========================================================= */
import { useEffect, useState } from 'react';

type Props = { onSaleAt: string; reminded: boolean; onRemind: () => void };

function remaining(target: number): { d: number; h: number; m: number; s: number; done: boolean } {
  const delta = Math.max(0, target - Date.now());

  return {
    d: Math.floor(delta / 86400000),
    h: Math.floor((delta / 3600000) % 24),
    m: Math.floor((delta / 60000) % 60),
    s: Math.floor((delta / 1000) % 60),
    done: delta === 0,
  };
}

const pad = (n: number) => String(n).padStart(2, '0');

export function DropCountdown({ onSaleAt, reminded, onRemind }: Props) {
  const target = new Date(onSaleAt).getTime();
  const [left, setLeft] = useState(() => remaining(target));

  useEffect(() => {
    if (Number.isNaN(target)) return;

    const timer = setInterval(() => setLeft(remaining(target)), 1000);

    return () => clearInterval(timer);
  }, [target]);

  if (Number.isNaN(target)) return null;

  const label = left.d > 0 ? `${left.d}z ${pad(left.h)}:${pad(left.m)}:${pad(left.s)}` : `${pad(left.h)}:${pad(left.m)}:${pad(left.s)}`;

  return (
    <div className="shdrop">
      <div className="shdrop-time" aria-live="off">
        {left.done ? 'Biletele intră acum' : `Biletele intră în ${label}`}
      </div>

      <button className="shcta" onClick={onRemind} disabled={reminded} aria-pressed={reminded}>
        {reminded ? '✓ Te anunțăm' : 'Amintește-mi'}
      </button>
    </div>
  );
}
