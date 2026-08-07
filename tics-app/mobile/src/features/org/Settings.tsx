/* =========================================================
   SETARI (§6.5) — Cont (+ „Comuta tipul de cont"), Scanner,
   Vanzare POS, Mod offline & sincronizare, Imprimanta, Aspect,
   Securitate, Manual, Comenzi Admin, incheie tura.
   ========================================================= */
import { useEffect, useState } from 'react';
import { Button, Card, Icon, InfoRow, SectionHead, Toggle, type IconName } from '../../design/components';
import { useSession, isAdminRole, type AppTheme, type SettingsFlags } from '../../store/session';
import { useCtx } from './OrgChrome';
import { STAFF } from '../../mock/org';
import { applyPendingUpdate, checkForUpdate, getOtaState, onOtaChange, type OtaState } from '../../ota';

const APP_VERSION = 'Tixello · Cont organizator';

/** Card „Actualizări" — OTA self-hosted (§14). Vizibil in ambele shell-uri. */
export function UpdateCard() {
  const showToast = useSession((s) => s.showToast);
  const [ota, setOta] = useState<OtaState>(getOtaState);
  const [busy, setBusy] = useState(false);

  useEffect(() => onOtaChange(setOta), []);

  return (
    <Card style={{ padding: '4px 16px' }}>
      <InfoRow label="Versiune conținut" value={ota.current === 'builtin' ? 'inclusă în APK' : ota.current} />
      <InfoRow label="Versiune aplicație" value={ota.native || '0.2.0'} />
      {ota.downloading ? (
        <InfoRow label="Se descarcă" value={`${Math.round(ota.progress)}%`} last={!ota.pending} />
      ) : null}
      {ota.pending ? (
        <div style={{ padding: '13px 0' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
            <span className="chip-i chip-green" style={{ width: 32, height: 32 }}>
              <Icon name="download" size={16} />
            </span>
            <div style={{ flex: 1, fontSize: 13.5, color: 'var(--text)' }}>
              Versiunea <b>{ota.pending}</b> e descărcată și gata de aplicat.
            </div>
          </div>
          <Button variant="primary" icon="swap" onClick={() => void applyPendingUpdate()}>
            Repornește și actualizează
          </Button>
        </div>
      ) : (
        <div style={{ padding: '13px 0' }}>
          <Button
            variant="ghost"
            icon="download"
            disabled={busy || ota.downloading}
            onClick={async () => {
              setBusy(true);
              showToast(await checkForUpdate());
              setBusy(false);
            }}
          >
            {busy ? 'Se verifică…' : 'Verifică actualizările'}
          </Button>
        </div>
      )}
    </Card>
  );
}

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

function AdminRow({ label, badge, hint, onClick, last }: { label: string; badge?: string | number; hint?: string; onClick: () => void; last?: boolean }) {
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
        {hint ? <span style={{ fontSize: 12, color: 'var(--text-3)' }}>{hint}</span> : null}
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

function MenuRow({ icon, label, sub, onClick, last }: { icon: IconName; label: string; sub?: string; onClick: () => void; last?: boolean }) {
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
      <div style={{ flex: 1 }}>
        <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--text)' }}>{label}</div>
        {sub ? <div style={{ fontSize: 12, color: 'var(--text-3)' }}>{sub}</div> : null}
      </div>
      <Icon name="chev" size={16} className="chev" />
    </div>
  );
}

export function Settings() {
  const { role, ctx, account, set, openModal, goChooser, logout, showToast } = useSession();
  useCtx();
  const admin = isAdminRole(role);
  const isFestival = account !== 'venue' && ctx === 'festival';
  const roleLabel = role === 'admin' ? 'Administrator' : role === 'manager' ? 'Manager' : 'Staff';
  /* Numele afisat e al membrului de personal cu rolul curent, ca in prototip —
     nu al contului de client. */
  const staffName = STAFF.find((m) => m.role === role)?.nm ?? 'Mihai Coman';
  const [autoLogout, setAutoLogout] = useState('5 min');

  return (
    <div className="screen pad stack">
      <div className="h1" style={{ fontSize: 20 }}>
        Setări
      </div>

      <SectionHead title="Cont" />
      <Card style={{ padding: '4px 16px' }}>
        <InfoRow label="Nume" value={staffName} />
        <InfoRow label="Rol" value={<span className={`tag tag-${role === 'admin' ? 'admin' : role === 'manager' ? 'mgr' : 'staff'}`}>{roleLabel}</span>} />
        <InfoRow label="Poartă Asignată" value="Poarta 1" />
        <AdminRow label="Comută tipul de cont" hint="client / organizator" onClick={goChooser} last />
      </Card>

      <SectionHead title="Scanner" />
      <Card style={{ padding: '4px 16px' }}>
        <ToggleRow label="Vibrație" k="vibr" />
        <ToggleRow label="Efecte Sonore" k="sound" />
        <ToggleRow label="Auto-confirmare Valide" k="autoconf" />
        <ToggleRow label="Scanner Bluetooth (portabil)" k="bt" last />
      </Card>

      {admin ? (
        <>
          <SectionHead title="Vânzare POS" />
          <Card style={{ padding: '4px 16px' }}>
            <ToggleRow label="Card prin NFC (Stripe Tap)" k="nfc" last />
          </Card>
          <Card pad>
            <div style={{ display: 'flex', gap: 10, background: 'var(--cyan-tint)', borderRadius: 12, padding: 12 }}>
              <span style={{ color: 'var(--cyan)', flex: '0 0 auto' }}>
                <Icon name="nfc" size={18} />
              </span>
              <span style={{ fontSize: 12, color: 'var(--text-2)', lineHeight: 1.4 }}>
                Adaugă în ecranul Vânzare butonul „Card prin NFC". Dezactivat = doar Numerar și Card POS.
              </span>
            </div>
          </Card>
        </>
      ) : null}

      <SectionHead title="Mod Offline & sincronizare" />
      <Card style={{ padding: '4px 16px' }}>
        <ToggleRow label="Activează Modul Offline" k="offline" />
        <div style={{ fontSize: 12, color: 'var(--text-3)', padding: '0 0 10px' }}>
          {set.offline
            ? '1.486 bilete în cache local · scanezi fără internet'
            : 'Descarcă biletele pentru a scana fără internet'}
        </div>
        <AdminRow label="Coadă de sincronizare" badge="3 în așteptare" onClick={() => openModal('sync')} last />
      </Card>

      {admin ? (
        <>
          <SectionHead title="Imprimantă" />
          <Card style={{ padding: '4px 16px' }}>
            <ToggleRow label="Imprimantă termică" k="printer" />
            <div style={{ fontSize: 12, color: 'var(--text-3)', padding: '0 0 10px' }}>
              {set.printer
                ? 'Star mC-Print3 · Bluetooth · conectată'
                : 'Bonuri, bilete la ușă și badge-uri / acreditări'}
            </div>
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
      <Card pad>
        <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--text)', marginBottom: 10 }}>
          Auto-logout după inactivitate
        </div>
        <div style={{ display: 'flex', gap: 7, flexWrap: 'wrap' }}>
          {['Oprit', '5 min', '10 min', '15 min', '30 min'].map((o) => (
            <span
              key={o}
              className={`typechip ${o === autoLogout ? 'on' : ''}`}
              style={{ cursor: 'pointer' }}
              onClick={() => setAutoLogout(o)}
            >
              {o}
            </span>
          ))}
        </div>
      </Card>

      <SectionHead title="Actualizări" />
      <UpdateCard />

      <SectionHead title="Manual utilizare" />
      <Card style={{ padding: '4px 16px' }}>
        <MenuRow
          icon="book"
          label="Manual utilizare"
          sub="Ghid complet — 28 capitole"
          onClick={() => openModal('manual')}
          last
        />
      </Card>

      {admin ? (
        <>
          <SectionHead title="Comenzi Admin" />
          <Card style={{ padding: '4px 16px' }}>
            <div
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 8,
                padding: '12px 0',
                borderBottom: '1px solid var(--border)',
              }}
            >
              <span style={{ color: 'var(--accent)' }}>
                <Icon name="cog" size={16} />
              </span>
              <span style={{ fontSize: 12, fontWeight: 700, color: 'var(--accent)' }}>Acces Administrator</span>
            </div>
            <AdminRow label="Administrare Porți" badge={4} onClick={() => openModal('gates')} />
            <AdminRow label="Asignare Personal" badge={4} onClick={() => openModal('staff')} />
            <AdminRow label="Difuzare către staff" onClick={() => openModal('broadcast')} />
            <AdminRow label="Ocupare pe zone" badge="1 alertă" onClick={() => openModal('occupancy')} />
            <AdminRow label="Reconciliere casă" onClick={() => openModal('cashcount')} />
            <AdminRow label="Listă neagră (blocări)" badge={2} onClick={() => openModal('banlist')} />
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
