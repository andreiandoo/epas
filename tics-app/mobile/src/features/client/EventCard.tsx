/* =========================================================
   Carduri de eveniment — forma image-forward din client-app.html
   (mcard/cover/scrim/gpill). Gradientul tine locul imaginii
   pana la conectarea la media reala din EPAS.
   ========================================================= */
import { Icon } from '../../design/components';
import type { ClientEvent } from '../../mock/client';
import { VEN } from '../../mock/client';

export function EventTile({ ev, onClick, width = 212 }: { ev: ClientEvent; onClick?: () => void; width?: number }) {
  return (
    <div
      onClick={onClick}
      style={{
        minWidth: width,
        borderRadius: 20,
        overflow: 'hidden',
        position: 'relative',
        cursor: 'pointer',
        boxShadow: 'var(--shadow-sm)',
        border: '1px solid var(--border)',
        flex: '0 0 auto',
      }}
    >
      <div style={{ height: 248, background: ev.tone, position: 'relative' }}>
        <span style={{ position: 'absolute', bottom: -6, right: -4, fontSize: 76, opacity: 0.85 }}>{ev.g}</span>
        <div
          style={{
            position: 'absolute',
            inset: 0,
            background: 'linear-gradient(180deg,rgba(4,3,9,.12),rgba(4,3,9,.82))',
          }}
        />
        <div style={{ position: 'absolute', top: 10, left: 10, right: 10, display: 'flex', justifyContent: 'space-between' }}>
          <span style={pillStyle(ev.type === 'experience' ? 'rgba(34,197,94,.92)' : 'rgba(139,92,246,.92)')}>
            {ev.type === 'experience' ? '⛰ Experiență' : ev.cat}
          </span>
          <span style={{ ...pillStyle('rgba(4,3,9,.5)'), color: '#ffd27a', display: 'flex', alignItems: 'center', gap: 3 }}>
            <Icon name="star" size={11} />
            {ev.rat}
          </span>
        </div>
        <div style={{ position: 'absolute', left: 12, right: 12, bottom: 12, color: '#fff' }}>
          <div style={{ fontSize: 16, fontWeight: 700, letterSpacing: '-.3px' }}>{ev.s}</div>
          <div style={{ display: 'flex', gap: 10, marginTop: 5, fontSize: 11.5, opacity: 0.86 }}>
            <span style={{ display: 'flex', alignItems: 'center', gap: 3 }}>
              <Icon name="cal" size={12} />
              {ev.d}
            </span>
            <span style={{ display: 'flex', alignItems: 'center', gap: 3 }}>
              <Icon name="pin" size={12} />
              {ev.city}
            </span>
          </div>
          <div style={{ marginTop: 8, fontSize: 16, fontWeight: 800 }}>
            <small style={{ fontSize: 11, fontWeight: 600, opacity: 0.75, marginRight: 4 }}>de la</small>
            {ev.from} lei
          </div>
        </div>
      </div>
    </div>
  );
}

export function EventRow({ ev, onClick }: { ev: ClientEvent; onClick?: () => void }) {
  return (
    <div
      onClick={onClick}
      style={{
        display: 'flex',
        alignItems: 'stretch',
        borderRadius: 20,
        overflow: 'hidden',
        background: 'var(--surface)',
        border: '1px solid var(--border)',
        boxShadow: 'var(--shadow-sm)',
        cursor: 'pointer',
      }}
    >
      <div style={{ width: 116, flex: 'none', background: ev.tone, position: 'relative' }}>
        <span style={{ position: 'absolute', bottom: -8, right: -6, fontSize: 58 }}>{ev.g}</span>
        <span style={{ ...pillStyle('rgba(4,3,9,.5)'), position: 'absolute', top: 9, left: 9 }}>{ev.d}</span>
      </div>
      <div style={{ flex: 1, minWidth: 0, padding: '12px 14px', display: 'flex', flexDirection: 'column', justifyContent: 'center' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 7 }}>
          <span
            style={{
              fontSize: 10.5,
              fontWeight: 700,
              padding: '3px 8px',
              borderRadius: 999,
              background: 'var(--surface-2)',
              color: 'var(--text-2)',
            }}
          >
            {ev.type === 'experience' ? '⛰ Experiență' : ev.cat}
          </span>
          <span style={{ display: 'flex', gap: 3, alignItems: 'center', color: 'var(--amber)', fontSize: 11, fontWeight: 600, marginLeft: 'auto' }}>
            <Icon name="star" size={11} />
            {ev.rat}
          </span>
        </div>
        <div
          style={{
            fontWeight: 600,
            fontSize: 14.5,
            marginTop: 7,
            whiteSpace: 'nowrap',
            overflow: 'hidden',
            textOverflow: 'ellipsis',
            color: 'var(--text)',
          }}
        >
          {ev.s}
        </div>
        <div style={{ display: 'flex', gap: 8, marginTop: 5, fontSize: 11.5, color: 'var(--text-3)', alignItems: 'center' }}>
          <Icon name="pin" size={12} />
          <span>{VEN[ev.ven].name}</span>
          <Icon name="clock" size={12} />
          <span>{ev.time}</span>
        </div>
        <div
          style={{
            fontWeight: 700,
            color: ev.type === 'experience' ? 'var(--green-2)' : 'var(--accent-accent)',
            fontSize: 14,
            marginTop: 8,
          }}
        >
          de la {ev.from} lei
        </div>
      </div>
    </div>
  );
}

const pillStyle = (bg: string) => ({
  background: bg,
  color: '#fff',
  fontSize: 10.5,
  fontWeight: 700,
  padding: '4px 9px',
  borderRadius: 999,
  backdropFilter: 'blur(6px)',
});
