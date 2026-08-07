/* =========================================================
   LOGIN — un singur ecran pentru client + organizator (§2).
   Emailul determina proprietatile → chooser sau intrare directa.
   ========================================================= */
import { useState } from 'react';
import { Button, Icon, Input } from '../../design/components';
import { useSession } from '../../store/session';
import type { IdentityKind } from '../../api/client';

const DEMO_EMAIL = 'andrei@tixello.ro';

export function LoginScreen() {
  const login = useSession((s) => s.login);
  const [email, setEmail] = useState(DEMO_EMAIL);
  const [password, setPassword] = useState('demo1234');
  const [show, setShow] = useState(false);
  const [busy, setBusy] = useState(false);

  /** In demo, forma emailului decide ce proprietati are contul. */
  const identityFor = (mail: string): IdentityKind => {
    const m = mail.trim().toLowerCase();
    if (m.startsWith('client')) return 'clientonly';
    if (m.startsWith('operator') || m.startsWith('org')) return 'orgonly';
    return 'multi';
  };

  const submit = () => {
    setBusy(true);
    setTimeout(() => {
      setBusy(false);
      login(identityFor(email));
    }, 350);
  };

  return (
    <div className="screen" style={{ display: 'flex', flexDirection: 'column', justifyContent: 'center', padding: 28 }}>
      <div style={{ textAlign: 'center', marginBottom: 30 }}>
        <div style={{ display: 'inline-flex', alignItems: 'center', gap: 8, marginBottom: 22 }}>
          <span className="brand-logo" style={{ fontSize: 20, padding: '8px 14px' }}>
            Tixello
          </span>
        </div>
        <div className="h1" style={{ fontSize: 24 }}>
          Bine ai revenit
        </div>
        <div className="sub" style={{ marginTop: 6 }}>
          Conectează-te la contul tău Tixello
        </div>
      </div>

      <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
        <Input label="Email" value={email} onChange={setEmail} placeholder="nume@exemplu.ro" />
        <Input
          label="Parolă"
          value={password}
          onChange={setPassword}
          type={show ? 'text' : 'password'}
          right={
            <span className="link" onClick={() => setShow((v) => !v)}>
              {show ? 'Ascunde' : 'Arată'}
            </span>
          }
        />
      </div>

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', margin: '14px 2px 20px' }}>
        <span className="sub" style={{ fontSize: 12.5 }}>
          Ține-mă minte
        </span>
        <span className="link" style={{ fontSize: 12.5 }}>
          Ai uitat parola?
        </span>
      </div>

      <Button variant="primary" onClick={submit} disabled={busy}>
        {busy ? 'Se conectează…' : 'Conectare'}
      </Button>

      <div
        className="card pad"
        style={{
          marginTop: 22,
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
          <b style={{ color: 'var(--text)' }}>Conturi demo</b> — orice parolă merge.
          <br />
          <code style={{ fontFamily: 'var(--mono)', fontSize: 11.5 }}>andrei@tixello.ro</code> → client + 3 organizatori
          (apare pasul „Alege contul")
          <br />
          <code style={{ fontFamily: 'var(--mono)', fontSize: 11.5 }}>client@tixello.ro</code> → doar client (intrare
          directă)
          <br />
          <code style={{ fontFamily: 'var(--mono)', fontSize: 11.5 }}>operator@tixello.ro</code> → doar organizatori
        </div>
      </div>
    </div>
  );
}
