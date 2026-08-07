/* =========================================================
   Chrome comun ORGANIZATOR (§6):
   App bar (logo + switch organizator + pastila Live/Offline +
   clopotel cu badge) · selector eveniment · bara de tura.
   ========================================================= */
import { Icon, IconButton, Pill, money } from '../../design/components';
import { useSession } from '../../store/session';
import { CTX, LEISURE, NOTIFS } from '../../mock/org';

export function useCtx() {
  const ctx = useSession((s) => s.ctx);
  return CTX[ctx];
}

export function OrgAppbar() {
  const { account, online, openModal } = useSession();
  const ctx = useCtx();
  const unread = NOTIFS.filter((n) => n.unread).length;
  const orgName = account === 'venue' ? LEISURE.venue : ctx.org;

  return (
    <div className="appbar">
      <div className="brand">
        <span className="brand-logo">Tixello</span>
        <button className="brand-switch" onClick={() => openModal('switch')} type="button">
          <span className="nm">{orgName}</span>
          <Icon name="chev" size={15} />
        </button>
      </div>
      <div className="appbar-right">
        {!online && (
          <Pill tone="amber" onClick={() => openModal('sync')}>
            <Icon name="clock" size={13} /> 3
          </Pill>
        )}
        <Pill tone={online ? 'live' : 'off'}>{online ? 'Live' : 'Offline'}</Pill>
        <IconButton icon="bell" badge={unread} onClick={() => openModal('notifs')} />
      </div>
    </div>
  );
}

export function EventSelector() {
  const openModal = useSession((s) => s.openModal);
  const c = useCtx();
  return (
    <div
      onClick={() => openModal('events')}
      style={{
        background: 'var(--accent-tint)',
        borderBottom: '1px solid var(--border)',
        padding: '11px 16px',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        cursor: 'pointer',
        flex: '0 0 auto',
      }}
    >
      <div style={{ minWidth: 0 }}>
        <div style={{ fontSize: 14.5, fontWeight: 700, color: 'var(--text)' }}>
          <span style={{ color: 'var(--accent-accent)' }}>{c.date.split(' · ')[0]}</span>&nbsp; {c.event}
        </div>
        <div style={{ fontSize: 12, color: 'var(--text-2)', marginTop: 2 }}>
          {c.venue}, {c.city}
        </div>
      </div>
      <Pill tone="live" style={{ flex: '0 0 auto' }}>
        LIVE
      </Pill>
    </div>
  );
}

export function ShiftBar() {
  const { shift, shiftPaused, toggleShiftPause, openModal } = useSession();
  if (!shift) return null;

  return (
    <div
      style={{
        background: shiftPaused ? 'var(--amber-tint)' : 'var(--surface-2)',
        borderBottom: '1px solid var(--border)',
        padding: '9px 16px',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flex: '0 0 auto',
      }}
    >
      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        <Icon name="clock" size={15} />
        <b className="tnum" style={{ fontSize: 15, color: 'var(--text)' }}>
          02:14:53
        </b>
        {shiftPaused && (
          <span
            style={{
              background: 'var(--amber)',
              color: '#fff',
              fontSize: 10,
              fontWeight: 800,
              padding: '3px 8px',
              borderRadius: 6,
            }}
          >
            PAUZĂ
          </span>
        )}
        <span style={{ fontSize: 12, color: 'var(--green)', marginLeft: 4, fontWeight: 700 }}>◆ {money(28560)}</span>
      </div>
      <div style={{ display: 'flex', gap: 8 }}>
        <button
          className="typechip"
          style={{ background: shiftPaused ? 'var(--green)' : 'var(--amber)', color: '#fff', border: 'none' }}
          onClick={toggleShiftPause}
          type="button"
        >
          {shiftPaused ? 'Continuă' : 'Pauză'}
        </button>
        <button
          className="iconbtn"
          style={{ width: 34, height: 34, background: 'var(--danger)', color: '#fff', border: 'none' }}
          onClick={() => openModal('emergency')}
          type="button"
        >
          <Icon name="alert" size={16} />
        </button>
      </div>
    </div>
  );
}
