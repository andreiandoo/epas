/* =========================================================
   Conectarea contului de organizator de la un partener.

   De ce credentialele sunt acceptabile AICI, spre deosebire de partea de
   client (unde am evitat legarea prin parola): parteneriatele sunt publice,
   iar un organizator apartine unui singur partener — deci reusita legarii nu
   dezvaluie nimic despre relatia dintre platforme.

   Partenerul ramane autoritatea: daca dezactiveaza organizatorul, serverul
   inceteaza sa raspunda si ecranul asta reapare.
   ========================================================= */
import { useState } from 'react';
import { Button, Card, Icon } from '../../design/components';
import { PARTNERS } from './useOrgAccount';

function Field({
  label,
  value,
  onChange,
  type = 'text',
  placeholder,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  type?: string;
  placeholder?: string;
}) {
  return (
    <div style={{ marginBottom: 12 }}>
      <div className="label" style={{ fontSize: 12, color: 'var(--text-2)', marginBottom: 5 }}>
        {label}
      </div>
      <input
        type={type}
        value={value}
        placeholder={placeholder}
        onChange={(e) => onChange(e.target.value)}
        style={{
          width: '100%',
          background: 'var(--surface-2)',
          border: '1px solid var(--border)',
          borderRadius: 12,
          padding: '12px 14px',
          color: 'var(--text)',
          font: 'inherit',
          fontSize: 14,
        }}
      />
    </div>
  );
}

export function ConnectOrganizer({
  onConnect,
  busy,
  error,
}: {
  onConnect: (partnerId: number, email: string, password: string) => Promise<boolean>;
  busy: boolean;
  error: string | null;
}) {
  const [partner, setPartner] = useState(PARTNERS[0].id);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');

  const submit = () => {
    if (busy || !email.trim() || !password) return;
    void onConnect(partner, email.trim(), password);
  };

  return (
    <div className="screen pad stack">
      <div>
        <div className="h1">Conectează-ți contul</div>
        <div className="sub" style={{ marginTop: 4, lineHeight: 1.5 }}>
          Ai deja evenimente la un partener Tics? Autentifică-te cu contul de organizator
          de acolo — evenimentele, porțile și echipa vin automat.
        </div>
      </div>

      <Card pad>
        <div className="label" style={{ fontSize: 12, color: 'var(--text-2)', marginBottom: 8 }}>
          Partener
        </div>
        <div style={{ display: 'flex', gap: 7, flexWrap: 'wrap', marginBottom: 14 }}>
          {PARTNERS.map((p) => (
            <span
              key={p.id}
              className={`typechip ${partner === p.id ? 'on' : ''}`}
              style={{ cursor: 'pointer' }}
              onClick={() => setPartner(p.id)}
            >
              {p.name}
            </span>
          ))}
        </div>

        <Field label="Email organizator" value={email} onChange={setEmail} placeholder="nume@companie.ro" />
        <Field label="Parolă" value={password} onChange={setPassword} type="password" />

        {error ? (
          <div style={{ fontSize: 12.5, color: 'var(--danger)', marginBottom: 10 }}>{error}</div>
        ) : null}

        <Button variant="primary" onClick={submit} disabled={busy}>
          {busy ? 'Se verifică…' : 'Conectează contul'}
        </Button>
      </Card>

      <Card pad style={{ background: 'var(--accent-tint)', borderColor: 'var(--accent-border)' }}>
        <div style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}>
          <span style={{ color: 'var(--accent-accent)', flex: '0 0 auto' }}>
            <Icon name="info" size={18} />
          </span>
          <div style={{ fontSize: 12.5, color: 'var(--text-2)', lineHeight: 1.5 }}>
            Nu se creează un cont nou. Evenimentele, prețurile și contractele rămân în panoul
            partenerului; aici faci doar operarea: scanare, vânzare la ușă, rapoarte.
          </div>
        </div>
      </Card>
    </div>
  );
}
