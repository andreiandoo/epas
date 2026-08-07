/* =========================================================
   PROFIL (§5.8) — avatar, statistici, comuta la Organizator,
   carduri, facturi, invita prieteni, abonament, preferinte,
   manual, delete account, logout.
   ========================================================= */
import { Avatar, Button, Card, Icon, InfoRow, SectionHead } from '../../design/components';
import { CLIENT_PROFILE } from '../../mock/client';
import { useSession } from '../../store/session';
import { SwitchBanner } from './SwitchBanner';
import { UpdateCard } from '../org/Settings';

function MenuRow({
  icon,
  label,
  onClick,
  last,
  danger,
}: {
  icon: Parameters<typeof Icon>[0]['name'];
  label: string;
  onClick?: () => void;
  last?: boolean;
  danger?: boolean;
}) {
  return (
    <div
      onClick={onClick}
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: 12,
        padding: '13px 0',
        borderBottom: last ? 'none' : '1px solid var(--border)',
        cursor: 'pointer',
      }}
    >
      <span style={{ color: danger ? 'var(--danger)' : 'var(--accent)' }}>
        <Icon name={icon} size={18} />
      </span>
      <div style={{ flex: 1, fontSize: 14, fontWeight: 600, color: danger ? 'var(--danger)' : 'var(--text)' }}>{label}</div>
      <Icon name="chev" size={16} className="chev" />
    </div>
  );
}

export function ClientProfile() {
  const { showToast, openModal, goChooser, logout } = useSession();

  return (
    <div className="screen pad stack">
      <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', padding: '10px 0 4px' }}>
        <Avatar initials={CLIENT_PROFILE.initials} color="grad" size={80} radius={26} />
        <div style={{ fontSize: 19, fontWeight: 700, marginTop: 12, color: 'var(--text)' }}>{CLIENT_PROFILE.name}</div>
        <div className="sub">{CLIENT_PROFILE.email}</div>
      </div>

      <SwitchBanner />

      <div className="grid3">
        <Card pad style={{ textAlign: 'center', padding: 12 }}>
          <div className="tnum" style={{ fontSize: 20, fontWeight: 800, color: 'var(--text)' }}>
            {CLIENT_PROFILE.activeTickets}
          </div>
          <div style={{ fontSize: 11, color: 'var(--text-3)' }}>Bilete active</div>
        </Card>
        <Card pad style={{ textAlign: 'center', padding: 12 }}>
          <div className="tnum" style={{ fontSize: 20, fontWeight: 800, color: 'var(--text)' }}>
            {CLIENT_PROFILE.points}
          </div>
          <div style={{ fontSize: 11, color: 'var(--text-3)' }}>Puncte</div>
        </Card>
        <Card pad style={{ textAlign: 'center', padding: 12 }}>
          <div className="tnum" style={{ fontSize: 20, fontWeight: 800, color: 'var(--text)' }}>
            {CLIENT_PROFILE.balance.toFixed(0)}
          </div>
          <div style={{ fontSize: 11, color: 'var(--text-3)' }}>Sold (lei)</div>
        </Card>
      </div>

      <SectionHead title="Cont" />
      <Card style={{ padding: '4px 16px' }}>
        <MenuRow icon="user" label="Date personale" onClick={() => showToast('Date personale — Faza 1')} />
        <MenuRow icon="card" label="Carduri & plată" onClick={() => showToast('Carduri salvate — Faza 1')} />
        <MenuRow icon="download" label="Istoric facturi" onClick={() => showToast('Facturi — Faza 1')} />
        <MenuRow icon="star" label="Preferințele mele" onClick={() => showToast('Preferințe & interese — Faza 1')} last />
      </Card>

      <SectionHead title="Comunitate" />
      <Card style={{ padding: '4px 16px' }}>
        <MenuRow icon="people" label="Invită prieteni · afiliere" onClick={() => openModal('invite')} />
        <MenuRow icon="list" label="Salvate" onClick={() => showToast('Evenimente salvate — Faza 1')} last />
      </Card>

      <SectionHead title="Actualizări" />
      <UpdateCard />

      <SectionHead title="Aplicație" />
      <Card style={{ padding: '4px 16px' }}>
        <InfoRow label="Limbă" value="Română" />
        <MenuRow icon="book" label="Manual utilizare" onClick={() => openModal('manual')} />
        <MenuRow icon="info" label="Termeni & confidențialitate" onClick={() => showToast('Documente legale — Faza 1')} />
        <MenuRow icon="trash" label="Șterge contul" danger onClick={() => showToast('Ștergere cont — Faza 1')} last />
      </Card>

      <Button variant="ghost" icon="swap" onClick={goChooser}>
        Schimbă contul
      </Button>
      <Button
        variant="ghost"
        icon="logout"
        style={{ color: 'var(--danger)', borderColor: 'var(--danger-border)' }}
        onClick={logout}
      >
        Deconectare
      </Button>

      <div className="center sub" style={{ fontSize: 11.5, color: 'var(--text-4)', paddingBottom: 8 }}>
        Tixello · Cont client v0.2.0
      </div>
    </div>
  );
}
