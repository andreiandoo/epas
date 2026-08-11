/* =========================================================
   SETARI — port 1:1 din client-app.html:
     S.settings   (1024) lista principala, pe sectiuni
     S.setPersonal(1045) · S.setSecurity(1051) · S.setPayment(1057)
     S.setAddCard (1064) · S.setBilling(1071) · S.setInvoices(1077)
     S.setDelete  (1081) · legalPage(1087) -> S.setTerms / S.setPrivacy
     S.setRate    (1091)
   ========================================================= */
import { useRef, useState } from 'react';
import { Ic, cn, sx } from '../../../design/sx';
import { I } from '../../../mock/prototype';
import { BottomNav, SetHead, TopBar } from '../kit';
import { useNav } from '../nav';
import { useClient } from '../../../store/client';
import { useSession } from '../../../store/session';
import { usePaymentMethods } from '../accountData';
import { addPaymentMethod, isLoggedIn, removeAvatar, uploadAvatar } from '../../../api/customer';
import { customerName, initialsOf, useCustomer } from '../accountData';
import { APP_VERSION } from '../../../version';

/* ---------- fld(label, val, ph, type) ----------
   `onChange` e optional: campurile decorative din prototip raman exact cum erau,
   iar cele care chiar trimit ceva la server isi ridica valoarea in parinte. */
function Fld({
  label,
  val,
  ph,
  type,
  onChange,
}: {
  label: string;
  val?: string;
  ph?: string;
  type?: string;
  onChange?: (v: string) => void;
}) {
  const [v, setV] = useState(val ?? '');
  return (
    <div style={sx('margin-top:13px')}>
      <div className="label">{label}</div>
      <div className="field">
        <input
          type={type}
          value={v}
          placeholder={ph ?? ''}
          onChange={(e) => {
            setV(e.target.value);
            onChange?.(e.target.value);
          }}
        />
      </div>
    </div>
  );
}

/* ---------- rand cu toggle, folosit in mai multe ecrane ---------- */
function TglRow({ emoji, label, on, last }: { emoji: string; label: string; on?: boolean; last?: boolean }) {
  const [v, setV] = useState(!!on);
  return (
    <div
      className="between"
      onClick={() => setV((x) => !x)}
      style={{ padding: '13px 0', borderBottom: last ? undefined : '1px solid var(--line)', cursor: 'pointer' }}
    >
      <div className="row" style={sx('gap:11px')}>
        <span style={sx('font-size:16px')}>{emoji}</span>
        <span style={sx('font-weight:500;font-size:13.5px')}>{label}</span>
      </div>
      <div className={cn('toggle', v && 'on')} />
    </div>
  );
}

/* =========================================================
   S.settings
   ========================================================= */
type Row = [string, string, string, string?];

const SECTIONS: [string, Row[]][] = [
  [
    'Cont',
    [
      ['👤', 'Date personale', '›', 'setPersonal'],
      ['🔐', 'Parolă & securitate', '›', 'setSecurity'],
      ['💳', 'Carduri & plată', '›', 'setPayment'],
      ['🌍', 'Limbă', 'Română'],
    ],
  ],
  [
    'Notificări',
    [
      ['🔔', 'Push', 'tgl'],
      ['📧', 'Email', 'tgl'],
      ['🎯', 'Recomandări AI', 'tgl'],
      ['🔥', 'Alerte preț & stoc', 'tgl'],
    ],
  ],
  [
    'Confidențialitate',
    [
      ['📍', 'Locație', 'tgl'],
      ['📊', 'Date pentru personalizare', 'tgl'],
      ['🗑️', 'Șterge contul', '›', 'setDelete'],
    ],
  ],
  [
    'Despre',
    [
      ['📄', 'Termeni & condiții', '›', 'setTerms'],
      ['🛡️', 'Politica de confidențialitate', '›', 'setPrivacy'],
      ['⭐', 'Evaluează aplicația', '›', 'setRate'],
      ['ℹ️', 'Versiune', APP_VERSION],
    ],
  ],
];

export function Settings() {
  const { go, back } = useNav();
  const prefsSel = useClient((s) => s.prefsSel);
  const logout = useSession((s) => s.logout);

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <TopBar>
        <div className="row" style={sx('gap:12px')}>
          <div className="icon-btn" onClick={back}>
            <Ic svg={I.back} />
          </div>
          <div className="h2">Setări cont</div>
        </div>
        <div style={sx('width:42px')} />
      </TopBar>

      <div className="pad" style={sx('margin-top:14px')}>
        <div className="card" style={sx('padding:14px;display:flex;align-items:center;gap:13px')}>
          <div style={sx('width:52px;height:52px;border-radius:16px;background:linear-gradient(135deg,var(--indigo),var(--indigo-4));display:grid;place-items:center;color:#fff;font-weight:600')}>
            AP
          </div>
          <div style={sx('flex:1')}>
            <div style={sx('font-weight:600;font-size:15px')}>Andrei Popescu</div>
            <div className="muted" style={sx('font-size:12px')}>
              andrei@tixello.ro
            </div>
          </div>
          <button className="chip ind on" style={sx('padding:8px 14px')} onClick={() => go('setPersonal')}>
            Editează
          </button>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:16px')}>
        <div
          className="listitem"
          onClick={() => go('prefsEdit')}
          style={sx('cursor:pointer;background:linear-gradient(135deg,var(--indigo-soft),var(--surface-solid));border-color:var(--indigo-line)')}
        >
          <div style={sx('width:38px;height:38px;border-radius:12px;background:var(--indigo-soft);color:var(--indigo-2);display:grid;place-items:center;font-size:17px')}>
            🎯
          </div>
          <div style={sx('flex:1')}>
            <div style={sx('font-weight:600;font-size:14px')}>Preferințele mele</div>
            <div className="muted" style={sx('font-size:11.5px')}>
              {prefsSel.length} interese pentru recomandări
            </div>
          </div>
          <span className="muted">
            <Ic svg={I.arrow} />
          </span>
        </div>
      </div>

      {SECTIONS.map(([title, rows]) => (
        <div key={title} className="pad" style={sx('margin-top:18px')}>
          <div className="label" style={sx('margin-bottom:9px')}>
            {title}
          </div>
          <div className="card" style={sx('padding:4px 14px')}>
            {rows.map((r, i) =>
              r[2] === 'tgl' ? (
                <TglRow key={r[1]} emoji={r[0]} label={r[1]} on last={i === rows.length - 1} />
              ) : (
                <div
                  key={r[1]}
                  onClick={() => r[3] && go(r[3])}
                  className="between"
                  style={{
                    padding: '13px 0',
                    borderBottom: i < rows.length - 1 ? '1px solid var(--line)' : undefined,
                    cursor: r[3] ? 'pointer' : 'default',
                  }}
                >
                  <div className="row" style={sx('gap:11px')}>
                    <span style={sx('font-size:16px')}>{r[0]}</span>
                    <span style={sx('font-weight:500;font-size:13.5px')}>{r[1]}</span>
                  </div>
                  <span className="muted" style={sx('font-size:12.5px')}>
                    {r[2] === '›' ? <Ic svg={I.arrow} /> : r[2]}
                  </span>
                </div>
              ),
            )}
          </div>
        </div>
      ))}

      <div className="pad" style={sx('margin-top:18px')}>
        <button className="cta ghost" onClick={logout} style={sx('color:var(--red);border-color:rgba(240,97,109,.3)')}>
          Deconectare
        </button>
      </div>
      <div style={sx('height:14px')} />
      <BottomNav active="profile" />
    </div>
  );
}

/* ========================================================= */
export function SetPersonal() {
  const { back } = useNav();
  const showToast = useClient((s) => s.showToast);
  const customer = useCustomer();
  const [avatar, setAvatar] = useState<string | null>(customer?.avatar ?? null);
  const [uploading, setUploading] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);

  const initials = initialsOf(customerName(customer) ?? 'Andrei Popescu');

  const pickAvatar = async (file: File) => {
    if (!isLoggedIn()) {
      showToast('Intră în cont ca să schimbi poza');

      return;
    }

    setUploading(true);
    const url = await uploadAvatar(file);
    setUploading(false);

    if (!url) {
      showToast('Nu am putut încărca poza');

      return;
    }

    /* `?v=` forteaza reincarcarea: calea poate ramane aceeasi, iar fara ea
       browserul ar arata in continuare imaginea veche din cache. */
    setAvatar(`${url}?v=${Date.now()}`);
    showToast('Poză actualizată');
  };

  const dropAvatar = async () => {
    if (await removeAvatar()) {
      setAvatar(null);
      showToast('Poză ștearsă');
    }
  };

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <SetHead title="Date personale" />
      <div className="pad" style={sx('margin-top:14px')}>
        <div style={sx('display:flex;flex-direction:column;align-items:center;gap:10px')}>
          <div
            style={{
              width: 78,
              height: 78,
              borderRadius: 26,
              background: avatar
                ? `url('${avatar}') center/cover, #14101f`
                : 'linear-gradient(135deg,var(--indigo),var(--indigo-4))',
              display: 'grid',
              placeItems: 'center',
              color: '#fff',
              fontSize: 26,
              fontWeight: 600,
            }}
          >
            {avatar ? '' : initials}
          </div>
          {/* Input ascuns: butonul din prototip ramane cum arata, dar acum
              deschide selectorul de fisiere al telefonului. */}
          <input
            ref={fileRef}
            type="file"
            accept="image/*"
            hidden
            onChange={(e) => {
              const f = e.target.files?.[0];
              if (f) void pickAvatar(f);
              e.target.value = '';
            }}
          />
          <div className="row" style={sx('gap:8px')}>
            <button className="chip" onClick={() => fileRef.current?.click()} disabled={uploading}>
              {uploading ? 'Se încarcă…' : 'Schimbă poza'}
            </button>
            {avatar ? (
              <button className="chip" onClick={() => void dropAvatar()}>
                Șterge
              </button>
            ) : null}
          </div>
        </div>
      </div>
      <div className="pad">
        <Fld label="Nume complet" val="Andrei Popescu" ph="Nume complet" />
        <Fld label="Email" val="andrei@tixello.ro" ph="email@exemplu.ro" type="email" />
        <Fld label="Telefon" val="0722 145 388" ph="07xx xxx xxx" type="tel" />
        <Fld label="Oraș" val="Cluj-Napoca" ph="Orașul tău" />
        {/* `type=date` — selectorul nativ al telefonului. Cu un camp de text
            utilizatorul trebuia sa scrie singur punctele, si orice alta forma
            („12/05/1994", „12 mai 1994") ar fi fost respinsa la salvare fara
            sa-i spuna nimeni de ce. */}
        <Fld label="Data nașterii" val="1994-05-12" type="date" />
      </div>
      <div className="pad" style={sx('margin-top:6px')}>
        <button className="cta" onClick={() => { showToast('Salvat'); back(); }}>
          Salvează modificările
        </button>
      </div>
      <div style={sx('height:14px')} />
    </div>
  );
}

export function SetSecurity() {
  const { back } = useNav();
  const showToast = useClient((s) => s.showToast);
  const sessions: [string, string, string][] = [
    ['📱', 'iPhone 15 · Cluj-Napoca', 'acum'],
    ['💻', 'Chrome · Windows', 'acum 2 zile'],
  ];
  return (
    <div className="grid" style={sx('min-height:100%')}>
      <SetHead title="Parolă & securitate" />
      <div className="pad" style={sx('margin-top:14px')}>
        <div className="label" style={sx('margin-bottom:9px')}>
          Schimbă parola
        </div>
        <div className="card" style={sx('padding:15px')}>
          <Fld label="Parola actuală" ph="••••••••" type="password" />
          <Fld label="Parola nouă" ph="Minim 8 caractere" type="password" />
          <Fld label="Confirmă parola nouă" ph="Repetă parola" type="password" />
          <button className="cta" style={sx('margin-top:14px')} onClick={() => { showToast('Parolă actualizată'); back(); }}>
            Actualizează parola
          </button>
        </div>
      </div>

      <div className="pad" style={sx('margin-top:18px')}>
        <div className="label" style={sx('margin-bottom:9px')}>
          Securitate
        </div>
        <div className="card" style={sx('padding:4px 14px')}>
          <TglRow emoji="🔐" label="Autentificare în 2 pași" on />
          <TglRow emoji="👆" label="Deblocare cu biometrie" on />
          <TglRow emoji="🔔" label="Alertă la login nou" last />
        </div>
      </div>

      <div className="pad" style={sx('margin-top:18px')}>
        <div className="label" style={sx('margin-bottom:9px')}>
          Sesiuni active
        </div>
        <div className="card" style={sx('padding:4px 14px')}>
          {sessions.map((r, i) => (
            <div
              key={r[1]}
              className="between"
              style={{ padding: '13px 0', borderBottom: i < 1 ? '1px solid var(--line)' : undefined }}
            >
              <div className="row" style={sx('gap:11px')}>
                <span style={sx('font-size:16px')}>{r[0]}</span>
                <div>
                  <div style={sx('font-weight:500;font-size:13px')}>{r[1]}</div>
                  <div className="muted" style={sx('font-size:11px')}>
                    Activ {r[2]}
                  </div>
                </div>
              </div>
              {i > 0 ? (
                <button className="chip" style={sx('padding:7px 12px;color:var(--red)')}>
                  Ieși
                </button>
              ) : (
                <span className="badge" style={sx('background:var(--green-soft);color:var(--green-2)')}>
                  acest device
                </span>
              )}
            </div>
          ))}
        </div>
      </div>
      <div style={sx('height:16px')} />
    </div>
  );
}

/* Cardurile reale n-au gradient stocat; il alegem dupa brand, ca sa arate ca in
   prototip (unde gradientul venea odata cu datele demo). */
const CARD_GRAD: Record<string, string> = {
  visa: 'linear-gradient(140deg,#1a1f71,#2d4bd6)',
  mastercard: 'linear-gradient(140deg,#3d1c00,#eb001b 130%)',
  amex: 'linear-gradient(140deg,#0b3d5c,#1f8fd6)',
};
const gradOf = (brand: string | null) =>
  CARD_GRAD[(brand ?? '').toLowerCase()] ?? 'linear-gradient(140deg,#241d3a,#4c3f8a)';

export function SetPayment() {
  const { go } = useNav();
  const localCards = useClient((s) => s.cards);
  const cardPrimary = useClient((s) => s.cardPrimary);
  const cardDel = useClient((s) => s.cardDel);
  const api = usePaymentMethods();

  /* Un singur rand pentru ambele surse. `id` exista doar la cele reale — dupa el
     decidem daca stergerea merge la server sau doar in starea locala. */
  type Row = { key: string; id: number | null; brand: string; last: string; exp: string; holder: string; grad: string; primary: boolean };

  const cards: Row[] = api.cards
    ? api.cards.map((c) => ({
        key: String(c.id),
        id: c.id,
        brand: (c.brand ?? 'card').replace(/^./, (m) => m.toUpperCase()),
        last: c.last4 ?? '••••',
        exp: c.exp ?? '—',
        holder: c.holder ?? api.billing?.name ?? '',
        grad: gradOf(c.brand),
        primary: c.is_default,
      }))
    : /* maparea e 1:1, deci indexul randului e chiar indexul din starea locala,
         cel pe care il asteapta `cardPrimary`/`cardDel` */
      localCards.map((c) => ({
        key: c.last,
        id: null,
        brand: c.brand,
        last: c.last,
        exp: c.exp,
        holder: 'Andrei Popescu',
        grad: c.grad,
        primary: c.primary,
      }));

  const onPrimary = (r: Row, i: number) => (r.id ? api.makeDefault(r.id) : cardPrimary(i));
  const onDelete = (r: Row, i: number) => (r.id ? api.remove(r.id) : cardDel(i));

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <SetHead title="Carduri & plată" />
      <div className="pad" style={sx('margin-top:14px')}>
        <div className="label" style={sx('margin-bottom:9px')}>
          Cardurile tale
        </div>
        <div style={sx('display:flex;flex-direction:column;gap:11px')}>
          {cards.length ? (
            cards.map((c, i) => (
              <div
                key={c.key}
                style={{ borderRadius: 18, padding: 16, background: c.grad, color: '#fff', position: 'relative', boxShadow: 'var(--sh)' }}
              >
                <div className="between">
                  <span style={sx('font-weight:600;font-size:14px')}>{c.brand}</span>
                  <div className="row" style={sx('gap:7px')}>
                    {c.primary ? (
                      <span className="badge" style={sx('background:rgba(255,255,255,.22);color:#fff')}>
                        Principal
                      </span>
                    ) : (
                      <button
                        className="chip"
                        onClick={() => onPrimary(c, i)}
                        style={sx('padding:5px 10px;background:rgba(255,255,255,.18);color:#fff;border:none;font-size:11px')}
                      >
                        Setează principal
                      </button>
                    )}
                    <button
                      className="circ"
                      onClick={() => onDelete(c, i)}
                      style={sx('position:static;width:30px;height:30px;background:rgba(255,255,255,.18);color:#fff;border:none')}
                      aria-label="Șterge cardul"
                    >
                      <Ic svg={I.x} />
                    </button>
                  </div>
                </div>
                <div style={sx('font-size:19px;letter-spacing:2px;margin-top:22px;font-weight:500')}>•••• {c.last}</div>
                <div className="between" style={sx('margin-top:10px;font-size:11px;opacity:.85')}>
                  <span>{c.holder}</span>
                  <span>Exp {c.exp}</span>
                </div>
              </div>
            ))
          ) : (
            <div className="card" style={sx('padding:22px;text-align:center')}>
              <div style={sx('font-size:34px;opacity:.5')}>💳</div>
              <div className="muted" style={sx('font-size:12.5px;margin-top:8px')}>
                Niciun card salvat
              </div>
            </div>
          )}
        </div>
        <button className="cta ghost" onClick={() => go('setAddCard')} style={sx('margin-top:14px')}>
          <Ic svg={I.plus} /> Adaugă un card nou
        </button>
      </div>

      <div className="pad" style={sx('margin-top:18px')}>
        <div className="label" style={sx('margin-bottom:9px')}>
          Facturare
        </div>
        <div className="card" style={sx('padding:4px 14px')}>
          {[
            ['🏢', 'Date de facturare', 'setBilling'],
            ['🧾', 'Istoric facturi', 'setInvoices'],
          ].map((r, i) => (
            <div
              key={r[1]}
              onClick={() => go(r[2])}
              className="between"
              style={{ padding: '13px 0', borderBottom: i < 1 ? '1px solid var(--line)' : undefined, cursor: 'pointer' }}
            >
              <div className="row" style={sx('gap:11px')}>
                <span style={sx('font-size:16px')}>{r[0]}</span>
                <span style={sx('font-weight:500;font-size:13.5px')}>{r[1]}</span>
              </div>
              <span className="muted">
                <Ic svg={I.arrow} />
              </span>
            </div>
          ))}
        </div>
      </div>
      <div style={sx('height:16px')} />
    </div>
  );
}

export function SetAddCard() {
  const { back } = useNav();
  const showToast = useClient((s) => s.showToast);
  const [num, setNum] = useState('');
  const [exp, setExp] = useState('');
  const [holder, setHolder] = useState('');
  const [busy, setBusy] = useState(false);

  /* Cifrele scrise, grupate cate patru, cu restul locurilor ca puncte —
     lungimea cardului ramane constanta, deci mock-up-ul nu se lungeste si nu se
     scurteaza in timp ce scrii. */
  const cardDigits = (() => {
    const digits = num.replace(/\D/g, '').slice(0, 16).padEnd(16, '•');

    return (digits.match(/.{1,4}/g) ?? []).join(' ');
  })();

  /* Cand exista cont, cardul se salveaza pe server (doar brand + ultimele 4
     cifre — restul nu pleaca nicaieri). Fara cont ramane comportamentul din
     prototip: doar confirmarea vizuala. */
  const save = async () => {
    if (!isLoggedIn()) {
      showToast('Card salvat');
      back();
      return;
    }
    setBusy(true);
    const id = await addPaymentMethod(num, holder || undefined, exp || undefined);
    setBusy(false);
    showToast(id ? 'Card salvat' : 'Nu am putut salva cardul');
    if (id) back();
  };

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <SetHead title="Adaugă un card" />
      <div className="pad" style={sx('margin-top:16px')}>
        <div
          style={sx('border-radius:18px;padding:18px;background:linear-gradient(135deg,var(--indigo),#2a1065);color:#fff;box-shadow:var(--sh)')}
        >
          <div className="between">
            <span style={sx('font-weight:600;font-size:14px')}>Card nou</span>
            <Ic svg={I.wallet} />
          </div>
          {/* Mock-up-ul urmareste ce se scrie: cifrele apar pe card pe masura
              ce sunt introduse, ca sa se vada imediat o greseala de tastare
              inainte de a apasa „Salvează". */}
          <div style={sx('font-size:19px;letter-spacing:2px;margin-top:22px;font-weight:500;font-variant-numeric:tabular-nums')}>
            {cardDigits}
          </div>
          <div className="between" style={sx('margin-top:10px;font-size:11px;opacity:.85')}>
            <span style={sx('white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:60%')}>
              {holder.trim() ? holder.toUpperCase() : 'NUME PRENUME'}
            </span>
            <span>{exp.trim() || 'MM/AA'}</span>
          </div>
        </div>
      </div>
      <div className="pad">
        <Fld label="Numărul cardului" ph="1234 5678 9012 3456" onChange={setNum} />
        <div className="row" style={sx('gap:11px')}>
          <div style={sx('flex:1')}>
            <Fld label="Expirare" ph="MM/AA" onChange={setExp} />
          </div>
          <div style={sx('flex:1')}>
            <Fld label="CVV" ph="•••" />
          </div>
        </div>
        <Fld label="Nume pe card" val="Andrei Popescu" ph="Nume complet" onChange={setHolder} />
      </div>
      <div className="pad" style={sx('margin-top:8px')}>
        <button className="cta" onClick={save} disabled={busy}>
          {busy ? 'Se salvează…' : 'Salvează cardul'}
        </button>
      </div>
      <div className="pad" style={sx('margin-top:12px')}>
        <div className="row" style={sx('gap:8px;justify-content:center;color:var(--faint);font-size:11px')}>
          <Ic svg={I.lock} /> Datele sunt criptate și securizate
        </div>
      </div>
      <div style={sx('height:16px')} />
    </div>
  );
}

export function SetBilling() {
  const { back } = useNav();
  const showToast = useClient((s) => s.showToast);
  /* Butoanele erau decorative: primul avea clasa „activ" scrisa fix, deci
     „Companie" nu se putea alege niciodata. Alegerea schimba si campurile —
     o firma are denumire si CUI, nu nume si CNP. */
  const [kind, setKind] = useState<'person' | 'company'>('person');
  const isCompany = kind === 'company';

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <SetHead title="Date de facturare" />
      <div className="pad" style={sx('margin-top:14px')}>
        <div className="row" style={sx('gap:9px')}>
          {([
            ['person', 'Persoană fizică'],
            ['company', 'Companie'],
          ] as const).map(([value, label]) => (
            <button
              key={value}
              className={cn('chip', kind === value && 'ind on')}
              onClick={() => setKind(value)}
              style={sx('flex:1;justify-content:center')}
            >
              {label}
            </button>
          ))}
        </div>
      </div>
      <div className="pad">
        {isCompany ? (
          <>
            <Fld label="Denumire firmă" ph="ex: Tixello SRL" />
            <Fld label="CUI / CIF" ph="RO12345678" />
            <Fld label="Nr. Reg. Comerțului" ph="J12/345/2020" />
            <Fld label="Bancă" ph="Denumirea băncii" />
            <Fld label="IBAN" ph="RO49 AAAA 1B31 0075 9384 0000" />
          </>
        ) : (
          <>
            <Fld label="Nume complet" val="Andrei Popescu" ph="Nume complet" />
            <Fld label="CNP" ph="Cod numeric personal" />
          </>
        )}
        <Fld label="Adresă" val="Str. Memorandumului 28" ph="Stradă, număr" />
        <div className="row" style={sx('gap:11px')}>
          <div style={sx('flex:1')}>
            <Fld label="Oraș" val="Cluj-Napoca" ph="Oraș" />
          </div>
          <div style={sx('flex:1')}>
            <Fld label="Cod poștal" val="400114" ph="Cod" />
          </div>
        </div>
        <Fld label="Țară" val="România" ph="Țară" />
      </div>
      <div className="pad" style={sx('margin-top:8px')}>
        <button className="cta" onClick={() => { showToast('Date salvate'); back(); }}>
          Salvează datele
        </button>
      </div>
      <div style={sx('height:16px')} />
    </div>
  );
}

const INVOICES: [string, string, string, string, string][] = [
  ['TIX-2026-0412', 'Coldplay — Music of the Spheres', '19 Apr 2026', '350 lei', 'plătită'],
  ['TIX-2026-0231', 'Salina Turda — 2 bilete', '2 Feb 2026', '70 lei', 'plătită'],
  ['TIX-2025-1180', 'UNTOLD — abonament 4 zile', '2 Aug 2025', '899 lei', 'plătită'],
  ['TIX-2025-0904', 'Smiley Live', '12 Iun 2025', '160 lei', 'rambursată'],
];

export function SetInvoices() {
  const showToast = useClient((s) => s.showToast);
  return (
    <div className="grid" style={sx('min-height:100%')}>
      <SetHead title="Istoric facturi" />
      <div className="pad" style={sx('margin-top:14px')}>
        <div style={sx('display:flex;flex-direction:column;gap:11px')}>
          {INVOICES.map((inv) => (
            <div key={inv[0]} className="card" style={sx('padding:14px')}>
              <div className="between">
                <div style={sx('min-width:0')}>
                  <div style={sx('font-weight:600;font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis')}>
                    {inv[1]}
                  </div>
                  <div className="muted" style={sx('font-size:11px;margin-top:2px')}>
                    {inv[0]} · {inv[2]}
                  </div>
                </div>
                <span
                  className="badge"
                  style={{
                    background: inv[4] === 'plătită' ? 'var(--green-soft)' : 'var(--surface-3)',
                    color: inv[4] === 'plătită' ? 'var(--green-2)' : 'var(--muted)',
                    flex: 'none',
                  }}
                >
                  {inv[4]}
                </span>
              </div>
              <div className="between" style={sx('margin-top:11px;padding-top:11px;border-top:1px solid var(--line)')}>
                <span style={sx('font-weight:600;font-size:14px')}>{inv[3]}</span>
                <button className="chip" onClick={() => showToast('Factura ' + inv[0] + ' descărcată')}>
                  Descarcă PDF
                </button>
              </div>
            </div>
          ))}
        </div>
      </div>
      <div style={sx('height:16px')} />
    </div>
  );
}

export function SetDelete() {
  const { back } = useNav();
  const logout = useSession((s) => s.logout);
  const losses = [
    'Biletele și comenzile tale',
    'Punctele și recompensele acumulate',
    'Soldul din portofelul cashless',
    'Istoricul și preferințele',
  ];
  return (
    <div className="grid" style={sx('min-height:100%')}>
      <SetHead title="Șterge contul" />
      <div className="pad" style={sx('margin-top:16px')}>
        <div className="card" style={sx('padding:18px;border-color:rgba(240,97,109,.3);background:rgba(240,97,109,.06)')}>
          <div style={sx('width:48px;height:48px;border-radius:15px;background:rgba(240,97,109,.15);color:var(--red);display:grid;place-items:center;font-size:24px')}>
            🗑️
          </div>
          <div className="h2" style={sx('font-size:16px;margin-top:12px;color:var(--red)')}>
            Ștergerea e definitivă
          </div>
          <div className="muted" style={sx('font-size:12.5px;line-height:1.6;margin-top:8px')}>
            Când ștergi contul, pierzi definitiv:
          </div>
          <div style={sx('display:flex;flex-direction:column;gap:8px;margin-top:12px')}>
            {losses.map((t) => (
              <div key={t} className="row" style={sx('gap:9px;font-size:12.5px')}>
                <span style={sx('color:var(--red)')}>✕</span>
                <span>{t}</span>
              </div>
            ))}
          </div>
        </div>
      </div>
      <div className="pad" style={sx('margin-top:16px')}>
        <div className="label" style={sx('margin-bottom:7px')}>
          Scrie <b>ȘTERGE</b> ca să confirmi
        </div>
        <div className="field">
          <input placeholder="ȘTERGE" />
        </div>
      </div>
      <div className="pad" style={sx('margin-top:8px')}>
        <button className="cta" onClick={logout} style={sx('background:var(--red);border-color:var(--red)')}>
          Șterge contul definitiv
        </button>
        <button className="cta ghost" onClick={back} style={sx('margin-top:10px')}>
          Renunță, păstrează contul
        </button>
      </div>
      <div style={sx('height:16px')} />
    </div>
  );
}

/* ---------- legalPage(title, intro, secs) ---------- */
function LegalPage({ title, intro, secs }: { title: string; intro: string; secs: [string, string][] }) {
  return (
    <div className="grid" style={sx('min-height:100%')}>
      <SetHead title={title} />
      <div className="pad" style={sx('margin-top:14px')}>
        <div className="muted" style={sx('font-size:11px')}>
          Ultima actualizare · 1 August 2026
        </div>
        <p style={sx('font-size:13px;line-height:1.65;margin-top:10px')}>{intro}</p>
      </div>
      {secs.map((s, i) => (
        <div key={s[0]} className="pad" style={sx('margin-top:16px')}>
          <div className="h2" style={sx('font-size:14.5px')}>
            {i + 1}. {s[0]}
          </div>
          <p className="muted" style={sx('font-size:12.5px;line-height:1.65;margin-top:7px')}>
            {s[1]}
          </p>
        </div>
      ))}
      <div style={sx('height:20px')} />
    </div>
  );
}

export const SetTerms = () => (
  <LegalPage
    title="Termeni & condiții"
    intro="Bine ai venit pe Tixello. Prin folosirea aplicației ești de acord cu termenii de mai jos, care reglementează achiziția de bilete și accesul la evenimente și experiențe."
    secs={[
      ['Contul tău', 'Ești responsabil de păstrarea în siguranță a datelor de acces. Un cont e personal și nu poate fi cedat altei persoane.'],
      ['Bilete & comenzi', 'Biletele emise sunt nominale și valabile doar pentru evenimentul indicat. Codul QR e unic — o singură scanare validă per bilet.'],
      ['Retururi', 'Politica de retur depinde de organizator. Pentru evenimente anulate, contravaloarea se restituie automat în portofel sau pe cardul folosit.'],
      ['Portofel cashless', 'Soldul din portofel poate fi folosit la evenimentele partenere. Sumele nefolosite pot fi retrase conform regulilor fiecărui organizator.'],
      ['Răspundere', 'Tixello e o platformă intermediară. Organizatorul rămâne responsabil de desfășurarea evenimentului.'],
    ]}
  />
);

export const SetPrivacy = () => (
  <LegalPage
    title="Politica de confidențialitate"
    intro="Confidențialitatea ta contează. Explicăm mai jos ce date colectăm, de ce și cum le poți controla, conform GDPR."
    secs={[
      ['Ce date colectăm', 'Date de cont (nume, email, telefon), istoricul comenzilor și preferințele pe care ni le comunici pentru recomandări.'],
      ['Cum le folosim', 'Pentru a-ți livra biletele, a-ți recomanda evenimente relevante și a preveni frauda. Nu vindem datele tale.'],
      ['Personalizare & AI', 'Folosim interesele și istoricul tău ca să-ți sugerăm evenimente. Poți dezactiva personalizarea oricând din Setări → Confidențialitate.'],
      ['Drepturile tale', 'Ai dreptul de acces, rectificare, ștergere și portabilitate a datelor. Poți exercita aceste drepturi direct din aplicație.'],
      ['Contact', 'Pentru orice întrebare legată de date, ne poți scrie la privacy@tixello.ro.'],
    ]}
  />
);

export function SetRate() {
  const { back } = useNav();
  const rateStars = useClient((s) => s.rateStars);
  const setRateStars = useClient((s) => s.setRateStars);
  const showToast = useClient((s) => s.showToast);

  return (
    <div className="grid" style={sx('min-height:100%')}>
      <SetHead title="Evaluează aplicația" />
      <div className="pad" style={sx('margin-top:20px')}>
        <div className="card" style={sx('padding:24px 18px;text-align:center')}>
          <div style={sx('font-size:52px')}>💜</div>
          <div className="h2" style={sx('font-size:17px;margin-top:10px')}>
            Îți place Tixello?
          </div>
          <div className="muted" style={sx('font-size:12.5px;line-height:1.5;margin-top:6px')}>
            Părerea ta ne ajută să creștem. Cât de mulțumit ești?
          </div>
          <div className="row" style={sx('justify-content:center;gap:8px;margin-top:18px')}>
            {[1, 2, 3, 4, 5].map((n) => (
              <button key={n} onClick={() => setRateStars(n)} style={sx('background:none;border:none;cursor:pointer;padding:2px')}>
                <svg
                  width="34"
                  height="34"
                  viewBox="0 0 24 24"
                  fill={n <= (rateStars || 0) ? 'var(--amber)' : 'transparent'}
                  stroke="var(--amber)"
                  strokeWidth="1.6"
                  strokeLinejoin="round"
                >
                  <path d="M12 2.5l2.9 5.9 6.6.9-4.8 4.6 1.1 6.5L12 18.9 6.2 21l1.1-6.5L2.5 9.9l6.6-.9z" />
                </svg>
              </button>
            ))}
          </div>
          <button className="cta" style={sx('margin-top:20px')} onClick={() => { showToast('Mulțumim!'); back(); }}>
            Trimite evaluarea
          </button>
          <button className="chip" style={sx('margin:12px auto 0;display:inline-flex')}>
            Lasă un review în App Store ↗
          </button>
        </div>
      </div>
      <div style={sx('height:16px')} />
    </div>
  );
}
