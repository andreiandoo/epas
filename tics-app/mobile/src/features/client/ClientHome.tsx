/* =========================================================
   ACASA (§5.2) — Descopera, Recomandat pentru tine, card portofel
   cashless, Biletele tale, rail Shorts.
   Schelet navigabil cu date mock; ecranele de detaliu vin in Faza 1.
   ========================================================= */
import { Card, Icon, Row, SectionHead } from '../../design/components';
import { useSession } from '../../store/session';
import { CLIENT_PROFILE, EV } from '../../mock/client';
import { SwitchBanner } from './SwitchBanner';
import { EventTile } from './EventCard';

const FOR_YOU = ['coldplay', 'celestial', 'swan'];
const EXPERIENCES = ['salina', 'atv', 'wine'];

export function ClientHome() {
  const { clientGo, showToast, openModal } = useSession();

  return (
    <div className="screen pad stack">
      <div style={{ display: 'flex', alignItems: 'center', gap: 11 }}>
        <div>
          <div style={{ fontSize: 11, color: 'var(--text-3)', display: 'flex', alignItems: 'center', gap: 4, fontWeight: 600 }}>
            <Icon name="pin" size={12} /> Locația ta
          </div>
          <div style={{ fontSize: 17, fontWeight: 700, color: 'var(--text)', marginTop: 2 }}>{CLIENT_PROFILE.city}</div>
        </div>
      </div>

      <SwitchBanner />

      <Card pad onClick={() => clientGo('Wallet')} style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
        <span className="chip-i chip-accent" style={{ width: 42, height: 42 }}>
          <Icon name="cash" size={20} />
        </span>
        <div style={{ flex: 1 }}>
          <div style={{ fontSize: 14, fontWeight: 700, color: 'var(--text)' }}>Portofel cashless</div>
          <div style={{ fontSize: 12, color: 'var(--text-3)' }}>
            Sold {CLIENT_PROFILE.balance.toFixed(0)} lei · plătește cu brățara la festival
          </div>
        </div>
        <Icon name="chev" size={16} className="chev" />
      </Card>

      <SectionHead title="Recomandate pentru tine" action={{ label: 'Vezi tot', onClick: () => clientGo('Explore') }} />
      <div style={{ display: 'flex', gap: 12, overflowX: 'auto', margin: '0 -16px', padding: '0 16px 4px' }}>
        {FOR_YOU.map((id) => (
          <EventTile key={id} ev={EV[id]} onClick={() => showToast(`${EV[id].s} — pagina de eveniment vine în Faza 1`)} />
        ))}
      </div>

      <SectionHead title="Experiențe" action={{ label: 'Toate', onClick: () => clientGo('Explore') }} />
      <div style={{ display: 'flex', gap: 12, overflowX: 'auto', margin: '0 -16px', padding: '0 16px 4px' }}>
        {EXPERIENCES.map((id) => (
          <EventTile key={id} ev={EV[id]} width={236} onClick={() => showToast(`${EV[id].s} — detaliu în Faza 1`)} />
        ))}
      </div>

      <SectionHead title="Shorts" action={{ label: 'Vezi tot', onClick: () => openModal('shorts') }} />
      <div style={{ display: 'flex', gap: 10, overflowX: 'auto', margin: '0 -16px', padding: '0 16px 4px' }}>
        {['coldplay', 'neversea', 'atv', 'wine'].map((id) => (
          <div
            key={id}
            onClick={() => openModal('shorts')}
            style={{
              minWidth: 108,
              height: 172,
              borderRadius: 16,
              background: EV[id].tone,
              position: 'relative',
              flex: '0 0 auto',
              cursor: 'pointer',
              overflow: 'hidden',
            }}
          >
            <span style={{ position: 'absolute', bottom: 26, right: 4, fontSize: 40 }}>{EV[id].g}</span>
            <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg,transparent,rgba(4,3,9,.8))' }} />
            <div style={{ position: 'absolute', left: 8, right: 8, bottom: 8, color: '#fff', fontSize: 11.5, fontWeight: 600 }}>
              {EV[id].s}
            </div>
            <div
              style={{
                position: 'absolute',
                top: 8,
                left: 8,
                width: 22,
                height: 22,
                borderRadius: '50%',
                background: 'rgba(4,3,9,.45)',
                display: 'grid',
                placeItems: 'center',
                color: '#fff',
              }}
            >
              <Icon name="play" size={11} />
            </div>
          </div>
        ))}
      </div>

      <SectionHead title="Biletele tale" action={{ label: 'Toate', onClick: () => clientGo('Tickets') }} />
      <Row
        icon="ticket"
        title="Coldplay — Music of the Spheres"
        meta="2 bilete · 19 Apr · Cluj Arena"
        onClick={() => clientGo('Tickets')}
      />
    </div>
  );
}
