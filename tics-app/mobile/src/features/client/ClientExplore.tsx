/* =========================================================
   EXPLOREAZA (§5.3) — cautare, categorii, filtre, Radar (resale).
   Schelet navigabil; Radar/marketplace complet vine in Faza 1.
   ========================================================= */
import { useState } from 'react';
import { Card, Icon, SectionHead, TypeChip } from '../../design/components';
import { CATEGORIES, EV } from '../../mock/client';
import { useSession } from '../../store/session';
import { EventRow } from './EventCard';

const RADAR_OFFERS = [
  { s: 'Smiley Live', city: 'Arad', day: '02 Mai', best: 95, offers: 3, tone: 'linear-gradient(150deg,#4c1d95,#a78bfa)', g: '🎙' },
  { s: 'Untold 2026', city: 'Cluj', day: '06 Aug', best: 340, offers: 5, tone: 'linear-gradient(150deg,#1e1b4b,#7c3aed)', g: '🎪' },
];

export function ClientExplore() {
  const { showToast } = useSession();
  const [cat, setCat] = useState<string | null>(null);

  const list = Object.values(EV).filter((e) => !cat || e.cat === cat);

  return (
    <div className="screen pad stack">
      <div className="h1" style={{ fontSize: 22 }}>
        Explorează
      </div>

      <div style={{ position: 'relative' }}>
        <span style={{ position: 'absolute', left: 14, top: '50%', transform: 'translateY(-50%)', color: 'var(--text-3)' }}>
          <Icon name="search" size={18} />
        </span>
        <input
          className="inputbox"
          style={{ paddingLeft: 42 }}
          placeholder="Caută evenimente, artiști, locații…"
          onFocus={() => showToast('Căutarea completă vine în Faza 1')}
        />
      </div>

      <div style={{ display: 'flex', gap: 8, overflowX: 'auto', margin: '0 -16px', padding: '2px 16px 4px' }}>
        <TypeChip on={cat === null} onClick={() => setCat(null)}>
          Toate
        </TypeChip>
        {CATEGORIES.map((c) => (
          <TypeChip key={c} on={cat === c} onClick={() => setCat(c)}>
            {c}
          </TypeChip>
        ))}
      </div>

      <SectionHead title="Radar · revânzare" action={{ label: 'Vezi tot', onClick: () => showToast('Radar complet — Faza 1') }} />
      <div style={{ display: 'flex', gap: 12, overflowX: 'auto', margin: '0 -16px', padding: '0 16px 4px' }}>
        {RADAR_OFFERS.map((r) => (
          <div
            key={r.s}
            onClick={() => showToast(`${r.s} · ${r.offers} oferte — marketplace în Faza 1`)}
            style={{
              minWidth: 270,
              borderRadius: 20,
              overflow: 'hidden',
              border: '1px solid var(--border)',
              background: 'var(--surface)',
              flex: '0 0 auto',
              cursor: 'pointer',
            }}
          >
            <div style={{ height: 140, background: r.tone, position: 'relative' }}>
              <span style={{ position: 'absolute', bottom: -4, right: 0, fontSize: 60 }}>{r.g}</span>
              <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg,transparent,rgba(4,3,9,.75))' }} />
              <span
                style={{
                  position: 'absolute',
                  top: 9,
                  left: 9,
                  background: 'rgba(4,3,9,.5)',
                  color: '#fff',
                  fontSize: 10.5,
                  fontWeight: 700,
                  padding: '4px 9px',
                  borderRadius: 999,
                  display: 'flex',
                  alignItems: 'center',
                  gap: 5,
                }}
              >
                <span style={{ width: 6, height: 6, borderRadius: '50%', background: 'var(--green)' }} />
                LIVE · {r.offers} oferte
              </span>
              <div style={{ position: 'absolute', left: 12, bottom: 10, color: '#fff' }}>
                <div style={{ fontSize: 17, fontWeight: 700 }}>{r.s}</div>
                <div style={{ fontSize: 11.5, opacity: 0.85 }}>
                  {r.city} · {r.day}
                </div>
              </div>
            </div>
            <div style={{ padding: '11px 14px', display: 'flex', alignItems: 'center', gap: 10 }}>
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: 10.5, color: 'var(--text-3)', fontWeight: 600 }}>cel mai bun preț</div>
                <div style={{ fontSize: 18, fontWeight: 800, color: 'var(--text)' }}>
                  {r.best}
                  <small style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-3)' }}> lei</small>
                </div>
              </div>
              <span className="tag tag-mgr">↓22%</span>
            </div>
          </div>
        ))}
      </div>

      <SectionHead title={cat ? cat : 'Toate evenimentele'} />
      <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
        {list.map((ev) => (
          <EventRow key={ev.id} ev={ev} onClick={() => showToast(`${ev.s} — pagina de eveniment vine în Faza 1`)} />
        ))}
        {list.length === 0 ? (
          <Card pad style={{ textAlign: 'center', color: 'var(--text-3)', fontSize: 13 }}>
            Niciun rezultat pentru „{cat}".
          </Card>
        ) : null}
      </div>
    </div>
  );
}
