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
   verifica adresa. Deci cerem parola.

   TREI DRUMURI, nu unul
   Prima versiune trata orice raspuns fara token drept „date gresite". Dar cele
   mai multe persoane cu cont de client NU au inca un cont tics — pentru ele
   parola corecta ducea tot la „nu am putut intra", fara nicio cale mai
   departe. Acum:
     - cont existent + parola buna  -> intri
     - cont existent neverificat    -> cod pe email, camp de cod aici
     - cont inexistent              -> butonul de creare, apoi acelasi cod
   Serverul raspunde LA FEL pentru „nu exista" si „parola gresita" (ca sa nu se
   poata afla ce adrese sunt inregistrate), deci nu putem alege noi drumul —
   le aratam pe amandoua si lasam omul sa aleaga.
   ========================================================= */
import { useState } from 'react';
import { sx } from '../../design/sx';
import { appLogin, appRegister, appVerify } from '../../api/orgApp';
import { getCustomer } from '../../api/customer';

type Step = 'credentials' | 'code';

export function ConnectTics({ what, onDone }: { what: string; onDone: () => void }) {
  const known = getCustomer();
  const [email, setEmail] = useState(known?.email ?? '');
  const [pass, setPass] = useState('');
  const [code, setCode] = useState('');
  const [step, setStep] = useState<Step>('credentials');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const [note, setNote] = useState<string | null>(null);

  const done = () => {
    setPass('');
    setCode('');
    onDone();
  };

  const run = async (action: () => Promise<Awaited<ReturnType<typeof appLogin>>>) => {
    setBusy(true);
    setErr(null);
    const r = await action();
    setBusy(false);

    if (r.ok) {
      done();

      return;
    }

    if (r.needsCode) {
      setStep('code');
      setNote(`Ți-am trimis un cod pe ${r.email}.`);

      return;
    }

    setErr(r.message);
  };

  const submit = () => {
    if (!email.trim() || !pass) {
      setErr('Completează adresa și parola.');

      return;
    }

    void run(() => appLogin(email.trim(), pass));
  };

  const create = () => {
    if (!email.trim() || pass.length < 8) {
      setErr('Pentru un cont nou, parola are minim 8 caractere.');

      return;
    }

    void run(() => appRegister(email.trim(), pass, known?.name ?? undefined));
  };

  const confirm = () => {
    if (!code.trim()) {
      setErr('Scrie codul din email.');

      return;
    }

    void run(() => appVerify(email.trim(), code.trim()));
  };

  return (
    <div className="pad" style={sx('margin-top:26px')}>
      <div className="card" style={sx('padding:18px')}>
        <div style={sx('font-size:34px;text-align:center;opacity:.6')}>🤝</div>
        <div className="h2" style={sx('font-size:15px;margin-top:10px;text-align:center')}>
          {step === 'code' ? 'Confirmă adresa' : 'Conectează contul tics'}
        </div>
        <div className="muted" style={sx('font-size:12px;margin-top:7px;line-height:1.55;text-align:center')}>
          {step === 'code'
            ? (note ?? 'Scrie codul primit pe email.')
            : `${what} merge pe contul tău tics. Ești autentificat, dar contul tics nu e încă legat pe telefonul ăsta.`}
        </div>

        {step === 'credentials' ? (
          <>
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
                  if (e.key === 'Enter') submit();
                }}
              />
            </div>

            {err ? (
              <div style={sx('color:var(--red);font-size:12px;margin-top:9px;text-align:center;line-height:1.5')}>
                {err}
              </div>
            ) : null}

            <button className="cta" style={sx('margin-top:13px;padding:13px')} disabled={busy} onClick={submit}>
              {busy ? 'Se conectează…' : 'Conectează'}
            </button>

            {/* Al doilea drum, la vedere de la inceput: cine n-are cont tics
                n-ar avea de unde sa banuiasca ca se creeaza de aici. */}
            <button
              className="cta ghost"
              style={sx('margin-top:9px;padding:12px')}
              disabled={busy}
              onClick={create}
            >
              Nu am cont tics — creează-mi unul
            </button>
            <div className="muted" style={sx('font-size:11px;margin-top:9px;text-align:center;line-height:1.5')}>
              Contul tics e separat de contul de bilete și ține prietenii și cumpărăturile din aplicație.
            </div>
          </>
        ) : (
          <>
            <div className="field" style={sx('margin-top:14px')}>
              <input
                inputMode="numeric"
                autoComplete="one-time-code"
                placeholder="Codul din email"
                value={code}
                onChange={(e) => setCode(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') confirm();
                }}
              />
            </div>

            {err ? (
              <div style={sx('color:var(--red);font-size:12px;margin-top:9px;text-align:center')}>{err}</div>
            ) : null}

            <button className="cta" style={sx('margin-top:13px;padding:13px')} disabled={busy} onClick={confirm}>
              {busy ? 'Se verifică…' : 'Confirmă'}
            </button>
            <button
              className="cta ghost"
              style={sx('margin-top:9px;padding:12px')}
              disabled={busy}
              onClick={() => {
                setStep('credentials');
                setErr(null);
                setNote(null);
              }}
            >
              Înapoi
            </button>
          </>
        )}
      </div>
    </div>
  );
}
