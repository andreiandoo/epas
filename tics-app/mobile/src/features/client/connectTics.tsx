/* =========================================================
   CONECTEAZA CONTUL tics

   Aplicatia are doua identitati reale, nu una: contul de client al platformei
   (bilete, comenzi, portofel) si contul tics (prieteni, cumparare din app).
   La autentificare le legam pe amandoua cu aceleasi credentiale — dar cine era
   deja logat INAINTE ca legatura sa existe n-are token de tics si nu are cum
   sa-l obtina: nu se mai trece prin ecranul de login.

   Nu putem face legatura pe tacute dintr-un token de client: ar insemna „cine
   controleaza o adresa intr-un sistem intra automat in contul cu aceeasi
   adresa din celalalt" — adica preluare de cont daca undeva inregistrarea nu
   verifica adresa. Deci cerem parola. O data.
   ========================================================= */
import { useState } from 'react';
import { sx } from '../../design/sx';
import { appLogin } from '../../api/orgApp';
import { getCustomer } from '../../api/customer';

export function ConnectTics({ what, onDone }: { what: string; onDone: () => void }) {
  const known = getCustomer();
  const [email, setEmail] = useState(known?.email ?? '');
  const [pass, setPass] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  const submit = async () => {
    if (!email.trim() || !pass) {
      setErr('Completează adresa și parola.');

      return;
    }

    setBusy(true);
    setErr(null);
    const ok = await appLogin(email.trim(), pass);
    setBusy(false);

    if (!ok) {
      setErr('Nu am putut intra în contul tics cu datele astea.');

      return;
    }

    setPass('');
    onDone();
  };

  return (
    <div className="pad" style={sx('margin-top:26px')}>
      <div className="card" style={sx('padding:18px')}>
        <div style={sx('font-size:34px;text-align:center;opacity:.6')}>🤝</div>
        <div className="h2" style={sx('font-size:15px;margin-top:10px;text-align:center')}>
          Conectează contul tics
        </div>
        <div className="muted" style={sx('font-size:12px;margin-top:7px;line-height:1.55;text-align:center')}>
          {what} merge pe contul tău tics. Ești autentificat, dar contul tics nu e încă legat pe telefonul
          ăsta — confirmă o dată parola și rămâne legat.
        </div>

        <div className="field" style={sx('margin-top:14px')}>
          <input
            type="email"
            autoComplete="email"
            placeholder="nume@email.ro"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
        </div>
        <div className="field" style={sx('margin-top:9px')}>
          <input
            type="password"
            autoComplete="current-password"
            placeholder="Parola"
            value={pass}
            onChange={(e) => setPass(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') void submit();
            }}
          />
        </div>

        {err ? (
          <div style={sx('color:var(--red);font-size:12px;margin-top:9px;text-align:center')}>{err}</div>
        ) : null}

        <button className="cta" style={sx('margin-top:13px;padding:13px')} disabled={busy} onClick={() => void submit()}>
          {busy ? 'Se conectează…' : 'Conectează'}
        </button>
      </div>
    </div>
  );
}
