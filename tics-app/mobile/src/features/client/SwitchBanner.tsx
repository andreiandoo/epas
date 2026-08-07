/* =========================================================
   Banner „Comuta la Organizator" (§3 — puncte de switch in UI).
   Apare pe Acasa & Profil DOAR daca emailul are proprietati de organizator.
   ========================================================= */
import { Icon } from '../../design/components';
import { useSession } from '../../store/session';

export function SwitchBanner() {
  const { properties, switchMode } = useSession();
  const hasOrg = properties.some((p) => p.kind === 'org');
  if (!hasOrg) return null;

  return (
    <div
      className="card pad"
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: 12,
        background: 'var(--grad)',
        border: 'none',
        color: '#fff',
        cursor: 'pointer',
        boxShadow: 'var(--shadow-btn)',
      }}
      onClick={switchMode}
    >
      <div
        style={{
          width: 42,
          height: 42,
          borderRadius: 13,
          background: 'rgba(255,255,255,.2)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
        }}
      >
        <Icon name="swap" size={20} />
      </div>
      <div style={{ flex: 1 }}>
        <div style={{ fontSize: 14.5, fontWeight: 700 }}>Comută la Organizator</div>
        <div style={{ fontSize: 12, opacity: 0.85 }}>Ai și acces de organizator cu acest email</div>
      </div>
      <Icon name="chev" size={18} />
    </div>
  );
}
