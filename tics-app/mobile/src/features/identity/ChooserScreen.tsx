/* =========================================================
   „ALEGE CONTUL" — pasul intermediar (§3).
   Apare doar cand emailul are > 1 proprietate.
   Portat 1:1 dupa chooserScreen() din organizer-app.html.
   ========================================================= */
import { Avatar, Button, Icon } from '../../design/components';
import { useSession } from '../../store/session';
import type { Property } from '../../api/types';

const roleLabel = (r: string) => (r === 'admin' ? 'Administrator' : r === 'manager' ? 'Manager' : 'Staff');

function PropertyCard({ p, onPick }: { p: Property; onPick: () => void }) {
  const badge =
    p.kind === 'client' ? (
      <span className="tag" style={{ background: 'var(--accent-tint)', color: 'var(--accent-accent)' }}>
        Client
      </span>
    ) : (
      <span className="tag tag-mgr">Organizator</span>
    );

  return (
    <div
      className="card pad"
      style={{ marginBottom: 10, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 13 }}
      onClick={onPick}
    >
      <Avatar icon={p.icon} color={p.av} size={46} radius={15} />
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontSize: 15.5, fontWeight: 700, color: 'var(--text)' }}>{p.name}</div>
        <div style={{ fontSize: 12.5, color: 'var(--text-3)', marginTop: 2 }}>
          {p.sub}
          {p.kind === 'org' ? ` · ${roleLabel(p.role)}` : ''}
        </div>
      </div>
      {badge}
      <Icon name="chev" size={18} className="chev" />
    </div>
  );
}

export function ChooserScreen() {
  const { properties, applyProp, logout } = useSession();
  const firstName = (properties.find((p) => p.kind === 'client')?.name ?? 'Andrei').split(' ')[0];

  return (
    // ecranul n-are appbar, deci nu primeste spatiul de sus de acolo — il adaugam
    // la padding, ca sa nu se lipeasca logo-ul de ora si iconitele de sistem
    <div
      className="screen"
      style={{ padding: 'calc(var(--safe-top, 0px) + 26px) 20px 26px', display: 'flex', flexDirection: 'column' }}
    >
      <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 22 }}>
        <span className="brand-logo" style={{ fontSize: 18 }}>
          tics
        </span>
      </div>

      <div className="h1" style={{ fontSize: 23 }}>
        Bună, {firstName} 👋
      </div>
      <div className="sub" style={{ margin: '6px 0 20px', lineHeight: 1.5 }}>
        Emailul tău are acces la mai multe conturi. Alege unde vrei să intri — poți comuta oricând din meniul de profil.
      </div>

      <div
        style={{
          fontSize: 11,
          fontWeight: 800,
          letterSpacing: 1,
          textTransform: 'uppercase',
          color: 'var(--text-3)',
          marginBottom: 10,
        }}
      >
        Conturile tale
      </div>

      {properties.map((p) => (
        <PropertyCard key={p.key} p={p} onPick={() => applyProp(p)} />
      ))}

      <div
        className="card pad"
        style={{
          marginTop: 6,
          display: 'flex',
          gap: 11,
          alignItems: 'flex-start',
          background: 'var(--accent-tint)',
          borderColor: 'var(--accent-border)',
        }}
      >
        <span style={{ color: 'var(--accent-accent)', flex: '0 0 auto' }}>
          <Icon name="info" size={18} />
        </span>
        <div style={{ fontSize: 12.5, color: 'var(--text-2)', lineHeight: 1.5 }}>
          Cu o singură proprietate pe email, acest pas se sare și intri direct în contul respectiv.
        </div>
      </div>

      <Button variant="danger-text" style={{ width: '100%', marginTop: 18 }} onClick={logout}>
        Nu ești tu? Deconectare
      </Button>
    </div>
  );
}
