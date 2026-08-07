/* =========================================================
   SETARI (§6.5) — Cont (+ „Comuta tipul de cont"), Scanner,
   Vanzare POS, Mod offline & sincronizare, Imprimanta, Aspect,
   Securitate, Manual, Comenzi Admin, incheie tura.
   ========================================================= */
import { Button, Card, Icon, InfoRow, SectionHead, Toggle, type IconName } from '../../design/components';
import { useSession, isAdminRole, type AppTheme, type SettingsFlags } from '../../store/session';
import { useCtx } from './OrgChrome';

const APP_VERSION = 'Tixello · Cont organizator v0.1.0';

function ToggleRow({ label, k, last }: { label: string; k: keyof SettingsFlags; last?: boolean }) {
  const { set, toggleSet } = useSession();
  return (
    <div
      style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        padding: '13px 0',
        borderBottom: last ? 'none' : '1px solid var(--border)',
      }}
    >
      <span style={{ fontSize: 14, fontWeight: 600, color: 'var(--text)' }}>{label}</span>
      <Toggle on={set[k]} onChange={() => toggleSet(k)} />
    </div>
  );
}

function ThemeRow({ t, label, last }: { t: AppTheme; label: string; last?: boolean }) {
  const { appTheme, setTheme } = useSession();
  return (
    <div
      onClick={() => setTheme(t)}
      style={{
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        padding: '13px 0',
        borderBottom: last ? 'none' : '1px solid var(--border)',
        cursor: 'pointer',
      }}
    >
      <span style={{ fontSize: 14, fontWeight: 600, color: 'var(--text)' }}>{label}</span>
      <span
        style={{
          width: 20,
          height: 20,
          borderRadius: '50%',
          border: `2px solid ${appTheme === t ? 'var(--accent)' : 'var(--border-strong)'}`,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
        }}
      >
        {appTheme === t ? <span style={{ width: 10, height: 10, borderRadius: '50%', background: 'var(--accent)' }} /> : null}
      </span>
    </div>
  );
}

function AdminRow({ label, badge, onClick, last }: { label: string; badge?: string | number; onClick: () => void; last?: boolean }) {
  return (
    <div
      onClick={onClick}
      style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        padding: '13px 0',
        borderBottom: last ? 'none' : '1px solid var(--border)',
        cursor: 'pointer',
      }}
    >
      <span style={{ fontSize: 14, fontWeight: 600, color: 'var(--text)' }}>{label}</span>
      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        {badge !== undefined ? (
          <span
            style={{
              background: 'var(--accent-tint)',
              color: 'var(--accent)',
              fontSize: 11,
              fontWeight: 800,
              padding: '2px 8px',
              borderRadius: 999,
            }}
          >
            {badge}
          </span>
        ) : null}
        <Icon name="chev" size={16} className="chev" />
      </div>
    </div>
  );
}

function MenuRow({ icon, label, onClick, last }: { icon: IconName; label: string; onClick: () => void; last?: boolean }) {
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
      <span style={{ color: 'var(--accent)' }}>
        <Icon name={icon} size={18} />
      </span>
      <div style={{ flex: 1, fontSize: 14, fontWeight: 600, color: 'var(--text)' }}>{label}</div>
      <Icon name="chev" size={16} className="chev" />
    </div>
  );
}

export function Settings() {
  const { role, ctx, account, online, openModal, goChooser, logout, showToast } = useSession();
  const c = useCtx();
  const admin = isAdminRole(role);
  const isFestival = account !== 'venue' && ctx === 'festival';
  const roleLabel = role === 'admin' ? 'Administrator' : role === 'manager' ? 'Manager' : 'Staff';

  return (
    <div className="screen pad stack">
      <div className="h1" style={{ fontSize: 20 }}>
        Setări
      </div>

      <SectionHead title="Cont" />
      <Card style={{ padding: '4px 16px' }}>
        <InfoRow label="Nume" value="Andrei Popescu" />
        <InfoRow label="Organizație" value={account === 'venue' ? 'Delta Adventure Park' : c.org} />
        <InfoRow label="Rol" value={<span className={`tag tag-${role === 'admin' ? 'admin' : role === 'manager' ? 'mgr' : 'staff'}`}>{roleLabel}</span>} />
        <InfoRow label="Poartă" value="Poarta 1" />
        <AdminRow label="Comută tipul de cont" onClick={goChooser} last />
      </Card>

      <SectionHead title="Scanner" />
      <Card style={{ padding: '4px 16px' }}>
        <ToggleRow label="Vibrație la scanare" k="vibr" />
        <ToggleRow label="Sunet la scanare" k="sound" />
        <ToggleRow label="Auto-confirmare" k="autoconf" />
        <ToggleRow label="Scanner Bluetooth" k="bt" last />
      </Card>

      {admin ? (
        <>
          <SectionHead title="Vânzare POS" />
          <Card style={{ padding: '4px 16px' }}>
            <ToggleRow label="Card prin NFC (Stripe Tap to Pay)" k="nfc" last />
          </Card>
        </>
      ) : null}

      <SectionHead title="Mod offline & sincronizare" />
      <Card style={{ padding: '4px 16px' }}>
        <ToggleRow label="Activează modul offline" k="offline" />
        <AdminRow label="Coadă de sincronizare" badge={online ? 0 : 3} onClick={() => openModal('sync')} last />
      </Card>

      {admin ? (
        <>
          <SectionHead title="Imprimantă" />
          <Card style={{ padding: '4px 16px' }}>
            <ToggleRow label="Imprimantă termică Bluetooth" k="printer" />
            <AdminRow label="Test print badge" onClick={() => openModal('printbadge')} last />
          </Card>
        </>
      ) : null}

      <SectionHead title="Aspect" />
      <Card style={{ padding: '4px 16px' }}>
        <ThemeRow t="light" label="Standard" />
        <ThemeRow t="lowlight" label="Contrast Mărit" />
        <ThemeRow t="dark" label="Noapte" last />
      </Card>

      <SectionHead title="Securitate" />
      <Card style={{ padding: '4px 16px' }}>
        <InfoRow label="Auto-logout" value="după 30 min inactivitate" last />
      </Card>

      <SectionHead title="Ajutor" />
      <Card style={{ padding: '4px 16px' }}>
        <MenuRow icon="book" label="Manual utilizare" onClick={() => openModal('manual')} last />
      </Card>

      {admin ? (
        <>
          <SectionHead title="Comenzi Admin" />
          <Card style={{ padding: '4px 16px' }}>
            <AdminRow label="Administrare Porți" badge={4} onClick={() => openModal('gates')} />
            <AdminRow label="Asignare Personal" badge={4} onClick={() => openModal('staff')} />
            <AdminRow label="Difuzare către staff" onClick={() => openModal('broadcast')} />
            <AdminRow label="Ocupare pe zone" badge="1 alertă" onClick={() => openModal('occupancy')} />
            <AdminRow label="Reconciliere casă" onClick={() => openModal('cashcount')} />
            <AdminRow label="Listă neagră" badge={2} onClick={() => openModal('banlist')} />
            {isFestival ? (
              <AdminRow label="Vendori festival" badge={38} onClick={() => openModal('vendors')} last />
            ) : (
              <AdminRow label="Imprimare & acreditări" onClick={() => openModal('printbadge')} last />
            )}
          </Card>
        </>
      ) : null}

      <Button variant="ghost" icon="xc" style={{ color: 'var(--danger)', borderColor: 'var(--danger-border)' }} onClick={() => openModal('shiftsummary')}>
        Încheie tura
      </Button>
      <Button
        variant="ghost"
        icon="logout"
        onClick={() => {
          showToast('Deconectat');
          logout();
        }}
      >
        Deconectare
      </Button>

      <div className="center sub" style={{ fontSize: 11.5, color: 'var(--text-4)', paddingBottom: 8 }}>
        {APP_VERSION}
      </div>
    </div>
  );
}
