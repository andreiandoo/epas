/* =========================================================
   ALEGE ORAȘUL — orice localitate din România

   Selectoarele de pana acum ofereau doar orasele care APAR in feed-ul
   Radarului: cateva zeci. Cine locuieste in alta parte nu se putea alege pe
   sine, iar „orasul tau" devenea „cel mai apropiat oras in care avem noi
   evenimente" — alt lucru.

   Sursa e `/catalog/cities`, adica `geo_localities`. Cautarea se face pe
   SERVER, nu in aplicatie: dataset-ul are zeci de mii de randuri si n-are ce
   cauta descarcat pe telefon ca sa filtram trei litere.
   ========================================================= */
import { useEffect, useState } from 'react';
import { sx } from '../../design/sx';
import { fetchCities } from '../../api/catalog';

export function CityPicker({
  value,
  onPick,
  onClose,
}: {
  value: string;
  onPick: (city: string) => void;
  onClose: () => void;
}) {
  const [q, setQ] = useState('');
  const [list, setList] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const ctrl = new AbortController();
    setLoading(true);

    /* 250 ms de liniste inseamna „a terminat de scris". Fara ele, fiecare
       litera ar fi o cerere, iar raspunsurile ar putea sosi in alta ordine
       decat au plecat si lista ar clipi intre doua cautari. */
    const t = setTimeout(() => {
      void fetchCities(q.trim(), ctrl.signal).then((r) => {
        if (ctrl.signal.aborted) return;
        setList(Array.isArray(r) ? r : []);
        setLoading(false);
      });
    }, 250);

    return () => {
      clearTimeout(t);
      ctrl.abort();
    };
  }, [q]);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', onKey);

    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  return (
    <div
      onClick={onClose}
      style={sx('position:fixed;inset:0;z-index:60;background:rgba(4,3,9,.6);backdrop-filter:blur(3px);display:flex;align-items:flex-end')}
    >
      <div
        onClick={(e) => e.stopPropagation()}
        style={{
          width: '100%',
          maxHeight: '72%',
          display: 'flex',
          flexDirection: 'column',
          background: 'var(--bg)',
          borderRadius: '24px 24px 0 0',
          border: '1px solid var(--line)',
          borderBottom: 0,
          paddingBottom: 'calc(14px + var(--safe-bottom, 0px))',
        }}
      >
        <div style={sx('width:40px;height:4px;border-radius:9px;background:var(--line-2);margin:10px auto 4px;flex:none')} />
        <div className="between pad" style={sx('flex:none')}>
          <div className="h2" style={sx('font-size:15px')}>
            Alege orașul
          </div>
          <button className="chip" onClick={onClose} style={sx('padding:5px 11px;font-size:11px')}>
            Închide
          </button>
        </div>

        <div className="pad" style={sx('flex:none;padding-bottom:6px')}>
          <div className="field">
            <input
              value={q}
              placeholder="Caută localitatea"
              autoFocus
              onChange={(e) => setQ(e.target.value)}
            />
          </div>
        </div>

        <div style={sx('overflow-y:auto;padding:4px 20px 0')}>
          {list.map((c) => (
            <div
              key={c}
              className="selrow"
              onClick={() => {
                onPick(c);
                onClose();
              }}
              style={{ cursor: 'pointer', borderTop: '1px solid var(--line)' }}
            >
              <div style={sx('flex:1;min-width:0;font-size:13.5px;font-weight:500')}>{c}</div>
              {c === value ? <span style={sx('color:var(--indigo-2);font-weight:700')}>✓</span> : null}
            </div>
          ))}

          {loading ? (
            <div className="muted" style={sx('font-size:12.5px;text-align:center;padding:18px 0')}>
              Caut…
            </div>
          ) : null}

          {!loading && !list.length ? (
            <div className="muted" style={sx('font-size:12.5px;text-align:center;padding:20px 0;line-height:1.5')}>
              Nicio localitate pentru „{q}".
            </div>
          ) : null}
        </div>
      </div>
    </div>
  );
}
