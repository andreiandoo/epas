/* =========================================================
   Modale ORGANIZATOR — setul de baza pentru scheletul navigabil.
   Inventarul COMPLET de 28 (§6.6) se porteaza in Faza 2/3; aici
   sunt cele necesare chrome-ului si celor 4 stari de scanare:
   events · notifs · switch · staff · gates · guests · emergency ·
   manual · sync · remaining · ticketsales · breakdown · scandetails ·
   manualentry · payconfirm · banlist · ticketaction · printbadge ·
   occupancy · emailcapture · export.
   ========================================================= */
import { useState, useRef } from 'react';
import { Avatar, Button, Card, CenterModal, FullModal, Icon, Input, Progress, Sheet, TypeChip, money } from '../../design/components';
import { useSession } from '../../store/session';
import { ConnectOrganizer } from './ConnectOrganizer';
import { useOrgAccount } from './useOrgAccount';
import { posSale } from '../../api/orgApp';
import { useStaff } from './useStaff';
import { CTX, CTX_ORDER, EMERGENCY, GATES, GUESTS, NOTIFS } from '../../mock/org';
import { useCtx } from './OrgChrome';

/* ---------- events (selector eveniment) ---------- */
function MEvents() {
  const { closeModal, showToast } = useSession();
  const c = useCtx();
  return (
    <Sheet title="Alege evenimentul" onClose={closeModal}>
      {[c.event, 'Wild Experience Fest 2026 · Ziua 2', 'Concert acustic · Sala Mică'].map((e, i) => (
        <div
          key={e}
          className="card pad"
          style={{ marginBottom: 10, display: 'flex', alignItems: 'center', gap: 12, cursor: 'pointer', borderColor: i === 0 ? 'var(--accent-border)' : undefined }}
          onClick={() => {
            closeModal();
            if (i > 0) showToast('Selecția multi-eveniment vine în Faza 2');
          }}
        >
          <span className="chip-i chip-accent">
            <Icon name="cal" size={20} />
          </span>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontSize: 14, fontWeight: 700, color: 'var(--text)' }}>{e}</div>
            <div style={{ fontSize: 12, color: 'var(--text-3)' }}>
              {c.venue}, {c.city}
            </div>
          </div>
          {i === 0 ? <span className="pill pill-live"><span className="dot" />LIVE</span> : <Icon name="chev" size={16} className="chev" />}
        </div>
      ))}
    </Sheet>
  );
}

/* ---------- notifs ---------- */
function MNotifs() {
  const closeModal = useSession((s) => s.closeModal);
  return (
    <Sheet title="Notificări" onClose={closeModal}>
      {NOTIFS.map((n) => (
        <div
          key={n.msg}
          className="card pad"
          style={{ marginBottom: 10, display: 'flex', gap: 12, opacity: n.unread ? 1 : 0.6 }}
        >
          <span className={`chip-i ${n.type === 'alert' ? 'chip-red' : n.type === 'success' ? 'chip-green' : 'chip-cyan'}`}>
            <Icon name={n.type === 'alert' ? 'alert' : n.type === 'success' ? 'checkc' : 'info'} size={18} />
          </span>
          <div style={{ flex: 1 }}>
            <div style={{ fontSize: 13.5, fontWeight: 600, color: 'var(--text)', lineHeight: 1.4 }}>{n.msg}</div>
            <div style={{ fontSize: 11.5, color: 'var(--text-3)', marginTop: 3 }}>{n.time}</div>
          </div>
        </div>
      ))}
    </Sheet>
  );
}

/* ---------- switch (comuta organizator) ---------- */
function MSwitch() {
  const { closeModal, goChooser, properties, applyProp, ctx } = useSession();
  const orgProps = properties.filter((p) => p.kind === 'org');
  return (
    <Sheet title="Comută organizator" onClose={closeModal}>
      {orgProps.map((p) => (
        <div
          key={p.key}
          className="card pad"
          style={{ marginBottom: 10, display: 'flex', alignItems: 'center', gap: 12, cursor: 'pointer' }}
          onClick={() => applyProp(p)}
        >
          <Avatar icon={p.icon} color={p.av} size={42} radius={13} />
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontSize: 14.5, fontWeight: 700, color: 'var(--text)' }}>{p.name}</div>
            <div style={{ fontSize: 12, color: 'var(--text-3)' }}>{p.sub}</div>
          </div>
          {p.kind === 'org' && p.ctx === ctx ? <Icon name="checkc" size={18} style={{ color: 'var(--green)' }} /> : <Icon name="chev" size={16} className="chev" />}
        </div>
      ))}
      <Button variant="ghost" icon="swap" style={{ marginTop: 6 }} onClick={goChooser}>
        Vezi toate conturile
      </Button>
      <div style={{ marginTop: 14, fontSize: 11.5, color: 'var(--text-4)', textAlign: 'center' }}>
        Verticale disponibile în demo: {CTX_ORDER.map((k) => CTX[k].label).join(' · ')}
      </div>
    </Sheet>
  );
}

/* ---------- staff (echipa) ---------- */
/** Culoarea avatarului, stabila per membru — nu vine de la server. */
const AV_COLORS = ['red', 'blue', 'amber', 'purple', 'green'] as const;
const avatarColor = (id: number) => AV_COLORS[id % AV_COLORS.length];

function MStaff() {
  const { closeModal, showToast } = useSession();
  const staff = useStaff();
  const [adding, setAdding] = useState(false);
  const [form, setForm] = useState({ name: '', email: '', password: '', role: 'staff' as 'admin' | 'manager' | 'staff' });
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    if (busy) return;
    if (!form.email.trim() || form.password.length < 8) {
      showToast('Email și parolă de minim 8 caractere');
      return;
    }
    setBusy(true);
    try {
      const r = await staff.add({
        name: form.name.trim() || undefined,
        email: form.email.trim(),
        password: form.password,
        role: form.role,
      });
      if (r.ok) {
        showToast('Membru adăugat');
        setForm({ name: '', email: '', password: '', role: 'staff' });
        setAdding(false);
      } else {
        showToast(r.error ?? 'Adăugare eșuată');
      }
    } finally {
      setBusy(false);
    }
  };

  const field = (
    label: string,
    value: string,
    onChange: (v: string) => void,
    type = 'text',
  ) => (
    <div style={{ marginBottom: 10 }}>
      <div className="label" style={{ fontSize: 12, color: 'var(--text-2)', marginBottom: 5 }}>
        {label}
      </div>
      <input
        type={type}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        style={{
          width: '100%',
          background: 'var(--surface-2)',
          border: '1px solid var(--border)',
          borderRadius: 12,
          padding: '11px 13px',
          color: 'var(--text)',
          font: 'inherit',
          fontSize: 14,
        }}
      />
    </div>
  );

  return (
    <FullModal title="Echipă & personal" onClose={closeModal}>
      {staff.rows.map((s) => (
        <Card key={s.id} pad style={{ marginBottom: 10, display: 'flex', alignItems: 'center', gap: 12 }}>
          <Avatar initials={s.initials} color={avatarColor(s.id)} size={42} radius={13} />
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontSize: 14.5, fontWeight: 700, color: 'var(--text)' }}>{s.name}</div>
            <div style={{ fontSize: 12, color: 'var(--text-3)' }}>
              {s.gate ?? 'fără poartă'} · {s.email}
            </div>
          </div>
          <span className={`tag tag-${s.role === 'admin' ? 'admin' : s.role === 'manager' ? 'mgr' : 'staff'}`}>
            {s.roleLabel}
          </span>
        </Card>
      ))}

      {adding ? (
        <Card pad style={{ marginBottom: 10 }}>
          {field('Nume (opțional)', form.name, (v) => setForm({ ...form, name: v }))}
          {field('Email', form.email, (v) => setForm({ ...form, email: v }))}
          {field('Parolă', form.password, (v) => setForm({ ...form, password: v }), 'password')}
          <div className="label" style={{ fontSize: 12, color: 'var(--text-2)', marginBottom: 6 }}>
            Rol
          </div>
          <div style={{ display: 'flex', gap: 7, marginBottom: 12 }}>
            {(['staff', 'manager', 'admin'] as const).map((r) => (
              <span
                key={r}
                className={`typechip ${form.role === r ? 'on' : ''}`}
                style={{ cursor: 'pointer' }}
                onClick={() => setForm({ ...form, role: r })}
              >
                {r === 'admin' ? 'Administrator' : r === 'manager' ? 'Manager' : 'Staff'}
              </span>
            ))}
          </div>
          <div style={{ display: 'flex', gap: 8 }}>
            <Button variant="primary" onClick={submit} style={{ flex: 1 }}>
              {busy ? 'Se adaugă…' : 'Salvează'}
            </Button>
            <Button variant="ghost" onClick={() => setAdding(false)} style={{ flex: 1 }}>
              Renunță
            </Button>
          </div>
        </Card>
      ) : (
        <Button variant="primary" icon="plus" style={{ marginTop: 6 }} onClick={() => setAdding(true)}>
          Adaugă membru
        </Button>
      )}

      {/* Cand nu exista un organizator conectat, lista e cea din prototip —
          spunem asta, in loc s-o dam drept echipa reala. */}
      {!staff.live ? (
        <div className="muted" style={{ fontSize: 11, textAlign: 'center', marginTop: 12, lineHeight: 1.5 }}>
          Echipă demonstrativă. Conectează contul de organizator ca să vezi echipa reală.
        </div>
      ) : null}
    </FullModal>
  );
}

/* ---------- gates (porti) ---------- */
function MGates() {
  const { closeModal, showToast } = useSession();
  return (
    <FullModal title="Administrare porți" onClose={closeModal}>
      {GATES.map((g) => (
        <Card key={g.id} pad style={{ marginBottom: 10, display: 'flex', alignItems: 'center', gap: 12 }}>
          <span className={`chip-i ${g.chip}`}>
            <Icon name={g.icon} size={19} />
          </span>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontSize: 14.5, fontWeight: 700, color: 'var(--text)' }}>{g.nm}</div>
            <div style={{ fontSize: 12, color: 'var(--text-3)' }}>
              {g.loc} · {g.typeL}
            </div>
          </div>
          <span className="tag" style={{ background: g.active ? 'var(--green-tint)' : 'var(--role-staff-bg)', color: g.active ? 'var(--green)' : 'var(--text-3)' }}>
            {g.active ? 'Activă' : 'Închisă'}
          </span>
        </Card>
      ))}
      <Button variant="primary" icon="plus" style={{ marginTop: 6 }} onClick={() => showToast('Adăugare poartă — Faza 2')}>
        Adaugă poartă
      </Button>
    </FullModal>
  );
}

/* ---------- guests (lista invitati) ---------- */
function MGuests() {
  const { closeModal, showToast } = useSession();
  return (
    <FullModal title="Listă invitați" onClose={closeModal}>
      {GUESTS.map((g) => (
        <Card key={g.nm} pad style={{ marginBottom: 10, display: 'flex', alignItems: 'center', gap: 12 }}>
          <Avatar initials={g.ini} color={g.tc === 'staff' ? 'blue' : (g.tc as 'purple' | 'amber')} size={40} radius={13} />
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontSize: 14.5, fontWeight: 700, color: 'var(--text)' }}>{g.nm}</div>
            <div style={{ fontSize: 12, color: 'var(--text-3)' }}>{g.type}</div>
          </div>
          {g.checked ? (
            <span className="tag tag-mgr">Intrat</span>
          ) : (
            <button className="typechip" onClick={() => showToast(`${g.nm} check-in efectuat`)} type="button">
              Check-in
            </button>
          )}
        </Card>
      ))}
    </FullModal>
  );
}

/* ---------- emergency ---------- */
function MEmergency() {
  const { closeModal, showToast } = useSession();
  return (
    <Sheet title="Raportează o urgență" onClose={closeModal}>
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
        {EMERGENCY.map((e) => (
          <div
            key={e.id}
            className="card pad"
            style={{
              cursor: 'pointer',
              padding: 13,
              borderColor: e.sev === 'high' ? 'var(--danger-border)' : e.sev === 'med' ? 'var(--amber-border)' : 'var(--border)',
              background: e.sev === 'high' ? 'var(--danger-tint)' : e.sev === 'med' ? 'var(--amber-tint)' : 'var(--surface)',
            }}
            onClick={() => {
              closeModal();
              showToast(`Urgență raportată: ${e.l}`);
            }}
          >
            <div style={{ fontSize: 13, fontWeight: 700, color: 'var(--text)' }}>{e.l}</div>
          </div>
        ))}
      </div>
      <div style={{ display: 'flex', gap: 10, marginTop: 14 }}>
        <Button variant="ghost" icon="camera" style={{ flex: 1 }} onClick={() => showToast('Atașare foto — Faza 3')}>
          Foto
        </Button>
        <Button variant="ghost" icon="mic" style={{ flex: 1 }} onClick={() => showToast('Mesaj vocal — Faza 3')}>
          Voce
        </Button>
      </div>
    </Sheet>
  );
}

/* ---------- remaining / ticketsales ---------- */
function MTicketBreakdown({ mode }: { mode: 'remaining' | 'ticketsales' }) {
  const closeModal = useSession((s) => s.closeModal);
  const c = useCtx();
  return (
    <Sheet title={mode === 'remaining' ? 'Intrați / rămase per tip' : 'Vânzări per tip'} onClose={closeModal}>
      {c.tt.map((t) => {
        const pct = mode === 'remaining' ? Math.round((t.ci / Math.max(t.s, 1)) * 100) : Math.round((t.s / t.q) * 100);
        return (
          <div key={t.n} style={{ marginBottom: 16 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13.5, marginBottom: 6 }}>
              <span style={{ display: 'flex', alignItems: 'center', gap: 8, color: 'var(--text)', fontWeight: 600 }}>
                <span style={{ width: 9, height: 9, borderRadius: 3, background: t.c }} />
                {t.n}
              </span>
              <b className="tnum" style={{ color: 'var(--text-2)' }}>
                {mode === 'remaining' ? `${t.ci} / ${t.s}` : `${t.s} / ${t.q}`}
              </b>
            </div>
            <div className="bar">
              <span style={{ width: `${pct}%`, background: t.c }} />
            </div>
            {mode === 'ticketsales' ? (
              <div style={{ fontSize: 11.5, color: 'var(--text-3)', marginTop: 4 }}>{money(t.s * t.p)}</div>
            ) : (
              <div style={{ fontSize: 11.5, color: 'var(--text-3)', marginTop: 4 }}>{t.s - t.ci} încă neintrate</div>
            )}
          </div>
        );
      })}
    </Sheet>
  );
}

/* ---------- breakdown (online vs POS) ---------- */
function MBreakdown() {
  const closeModal = useSession((s) => s.closeModal);
  const c = useCtx();
  const onlinePct = Math.round((c.online / (c.online + c.door)) * 100);
  return (
    <Sheet title="Online vs. la ușă" onClose={closeModal}>
      <div className="seg-bar" style={{ marginBottom: 16 }}>
        <div className="on" style={{ flex: `0 0 ${onlinePct}%` }}>
          Online {c.online.toLocaleString('ro-RO')}
        </div>
        <div className="door">La ușă {c.door}</div>
      </div>
      <Card style={{ padding: '4px 16px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '11px 0', borderBottom: '1px solid var(--border)' }}>
          <span className="muted" style={{ fontSize: 13.5 }}>Total încasat</span>
          <b style={{ fontSize: 13.5, color: 'var(--text)' }}>{money(c.revenue)}</b>
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '11px 0', borderBottom: '1px solid var(--border)' }}>
          <span className="muted" style={{ fontSize: 13.5 }}>Din online ({onlinePct}%)</span>
          <b style={{ fontSize: 13.5, color: 'var(--text)' }}>{money(Math.round((c.revenue * onlinePct) / 100))}</b>
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '11px 0' }}>
          <span className="muted" style={{ fontSize: 13.5 }}>Din POS la ușă</span>
          <b style={{ fontSize: 13.5, color: 'var(--text)' }}>{money(c.revenue - Math.round((c.revenue * onlinePct) / 100))}</b>
        </div>
      </Card>
    </Sheet>
  );
}

/* ---------- scandetails ---------- */
function MScanDetails() {
  const closeModal = useSession((s) => s.closeModal);
  return (
    <CenterModal onClose={closeModal}>
      <div style={{ fontSize: 17, fontWeight: 800, color: 'var(--text)', marginBottom: 10 }}>Detalii scanare</div>
      <Card style={{ padding: '4px 14px', background: 'var(--surface-2)' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '10px 0', borderBottom: '1px solid var(--border)' }}>
          <span className="muted" style={{ fontSize: 13 }}>Bilet</span>
          <b style={{ fontSize: 13, color: 'var(--text)' }}>#12588</b>
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '10px 0', borderBottom: '1px solid var(--border)' }}>
          <span className="muted" style={{ fontSize: 13 }}>Prima scanare</span>
          <b style={{ fontSize: 13, color: 'var(--text)' }}>Poarta 2 · 20:41</b>
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '10px 0' }}>
          <span className="muted" style={{ fontSize: 13 }}>Operator</span>
          <b style={{ fontSize: 13, color: 'var(--text)' }}>Ioan Barbu</b>
        </div>
      </Card>
      <Button variant="ghost" style={{ marginTop: 14 }} onClick={closeModal}>
        Închide
      </Button>
    </CenterModal>
  );
}

/* ---------- manualentry ---------- */
function MManualEntry() {
  const { closeModal, showToast } = useSession();
  const [code, setCode] = useState('');
  return (
    <Sheet title="Check-in manual" onClose={closeModal}>
      <Input label="Cod bilet sau nume / email" value={code} onChange={setCode} placeholder="TIX-… sau Andrei Popescu" />
      <Button
        variant="primary"
        style={{ marginTop: 16 }}
        onClick={() => {
          closeModal();
          showToast(code ? `Căutare pentru „${code}"` : 'Introdu un cod sau un nume');
        }}
      >
        Caută
      </Button>
    </Sheet>
  );
}

/* ---------- payconfirm ---------- */
/* ---------- conectarea contului de organizator de la un partener ---------- */
function MConnectOrg() {
  const { closeModal, showToast } = useSession();
  const org = useOrgAccount();

  return (
    <FullModal title="Cont de organizator" onClose={closeModal}>
      {org.connected ? (
        <Card pad style={{ background: 'var(--green-tint)', borderColor: 'var(--green-border)' }}>
          <div style={{ fontSize: 14, fontWeight: 700, color: 'var(--text)' }}>Cont conectat</div>
          <div style={{ fontSize: 12.5, color: 'var(--text-2)', marginTop: 4 }}>
            {org.events.length
              ? `${org.events.length} evenimente disponibile pentru operare.`
              : 'Niciun eveniment încă — apar aici imediat ce partenerul le publică.'}
          </div>
        </Card>
      ) : (
        <ConnectOrganizer
          busy={org.loading}
          error={org.error}
          onConnect={async (pid, email, pass) => {
            const ok = await org.connect(pid, email, pass);
            if (ok) {
              showToast('Cont conectat');
              closeModal();
            }
            return ok;
          }}
        />
      )}
    </FullModal>
  );
}

function MPayConfirm() {
  const { closeModal, modalArg, setSale, cart, showToast } = useSession();
  const c = useCtx();
  const [busy, setBusy] = useState(false);

  /* `sale_id` se genereaza O SINGURA DATA per cos si se retrimite identic la
     reincercare: casierul apasa din nou cand reteaua intarzie, iar serverul
     recunoaste id-ul si nu creeaza a doua comanda. */
  const saleId = useRef(
    typeof crypto !== 'undefined' && 'randomUUID' in crypto
      ? crypto.randomUUID()
      : `sale-${Date.now().toString(36)}-${Math.floor(Math.random() * 1e9).toString(36)}`,
  );
  const subtotal = Object.entries(cart).reduce((a, [i, q]) => a + c.tt[Number(i)].p * q, 0);
  const total = subtotal + Math.round(subtotal * 0.03);
  const label = modalArg === 'cash' ? 'Numerar' : modalArg === 'nfc' ? 'Card prin NFC' : 'Card / POS';
  return (
    <CenterModal onClose={closeModal}>
      <div style={{ textAlign: 'center' }}>
        <span className={`chip-i ${modalArg === 'cash' ? 'chip-green' : 'chip-cyan'}`} style={{ width: 54, height: 54, borderRadius: 17, margin: '0 auto 12px' }}>
          <Icon name={modalArg === 'cash' ? 'cash' : modalArg === 'nfc' ? 'nfc' : 'card'} size={26} />
        </span>
        <div style={{ fontSize: 17, fontWeight: 800, color: 'var(--text)' }}>Confirmă încasarea</div>
        <div className="sub" style={{ marginTop: 4 }}>{label}</div>
        <div className="tnum" style={{ fontSize: 30, fontWeight: 800, color: 'var(--accent-accent)', margin: '12px 0 4px' }}>
          {money(total)}
        </div>
      </div>
      <Button
        variant="primary"
        style={{ marginTop: 14 }}
        disabled={busy}
        onClick={async () => {
          if (busy) return;
          setBusy(true);

          /* Trimitem DOAR tipul si cantitatea. Pretul il calculeaza serverul —
             un pret venit de la client poate fi modificat de oricine
             intercepteaza cererea. */
          const items = Object.entries(cart)
            .filter(([, q]) => q > 0)
            .map(([i, q]) => ({ id: c.tt[Number(i)]?.id, qty: q }));

          /* Datasetul demo n-are id-uri reale de tip de bilet. Mai bine
             refuzam explicit decat sa trimitem o comanda inventata: casierul
             are omul in fata si trebuie sa stie ca n-a incasat. */
          if (items.some((it) => !it.id)) {
            setBusy(false);
            showToast('Vânzarea cere un eveniment real — conectează contul de organizator');
            return;
          }

          const r = await posSale({
            eventId: Number(c.event),
            items: items.map((it) => ({ ticket_type_id: it.id as number, qty: it.qty })),
            paymentMethod: (modalArg === 'cash' ? 'cash' : modalArg === 'nfc' ? 'nfc' : 'card') as
              | 'cash'
              | 'card'
              | 'nfc',
            saleId: saleId.current,
          });

          setBusy(false);

          if (!r.ok) {
            // Ramanem in modal: casierul are omul in fata si trebuie sa poata
            // reincerca sau anula, nu sa fie aruncat pe un ecran de succes fals.
            showToast(r.error ?? 'Vânzare eșuată');
            return;
          }

          closeModal();
          setSale('success');
        }}
      >
        {busy ? 'Se încasează…' : 'Confirmă plata'}
      </Button>
      <Button variant="ghost" style={{ marginTop: 8 }} onClick={closeModal}>
        Anulează
      </Button>
    </CenterModal>
  );
}

/* ---------- ticketaction (actiuni la usa) ---------- */
function MTicketAction() {
  const { closeModal, showToast } = useSession();
  const act = (label: string) => () => {
    closeModal();
    showToast(label);
  };
  return (
    <Sheet title="Acțiuni la ușă" onClose={closeModal}>
      <Button variant="ghost" icon="trend" style={{ marginBottom: 8 }} onClick={act('Upgrade — încasează diferența')}>
        Upgrade bilet
      </Button>
      <Button variant="ghost" icon="in" style={{ marginBottom: 8 }} onClick={act('Re-intrare autorizată')}>
        Permite re-intrare
      </Button>
      <Button variant="ghost" icon="cash" style={{ marginBottom: 8 }} onClick={act('Refund inițiat prin Stripe')}>
        Refund
      </Button>
      <Button variant="ghost" icon="xc" style={{ color: 'var(--danger)', borderColor: 'var(--danger-border)' }} onClick={act('Bilet anulat (void)')}>
        Anulează / void
      </Button>
    </Sheet>
  );
}

/* ---------- banlist ---------- */
function MBanlist() {
  const { closeModal, showToast } = useSession();
  const banned = [
    { code: '#4821', reason: 'Contestație plată', at: '12 iul. 2026' },
    { code: '#5107', reason: 'Comportament agresiv', at: '9 iul. 2026' },
  ];
  return (
    <FullModal title="Listă neagră" onClose={closeModal}>
      {banned.map((b) => (
        <Card key={b.code} pad style={{ marginBottom: 10, display: 'flex', alignItems: 'center', gap: 12, borderColor: 'var(--danger-border)' }}>
          <span className="chip-i chip-red">
            <Icon name="alert" size={19} />
          </span>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontSize: 14.5, fontWeight: 700, color: 'var(--text)' }}>Bilet {b.code}</div>
            <div style={{ fontSize: 12, color: 'var(--text-3)' }}>
              {b.reason} · {b.at}
            </div>
          </div>
          <button className="typechip" onClick={() => showToast(`Bilet ${b.code} deblocat`)} type="button">
            Deblochează
          </button>
        </Card>
      ))}
      <Button variant="primary" icon="plus" style={{ marginTop: 6 }} onClick={() => showToast('Adăugare pe lista neagră — Faza 3')}>
        Adaugă pe listă
      </Button>
    </FullModal>
  );
}

/* ---------- occupancy ---------- */
function MOccupancy() {
  const { closeModal, showToast } = useSession();
  const zones = [
    { nm: 'Ring Principal', pct: 92, col: 'danger' as const },
    { nm: 'Zona VIP', pct: 74, col: 'amber' as const },
    { nm: 'Tribună', pct: 48, col: 'green' as const },
    { nm: 'Food Court', pct: 61, col: 'amber' as const },
  ];
  return (
    <FullModal title="Ocupare pe zone" onClose={closeModal}>
      <div className="sub" style={{ marginBottom: 14 }}>
        Prag de alertă: <b style={{ color: 'var(--text)' }}>90%</b>
      </div>
      {zones.map((z) => (
        <Card key={z.nm} pad style={{ marginBottom: 10 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 8 }}>
            <span style={{ fontSize: 14, fontWeight: 700, color: 'var(--text)' }}>{z.nm}</span>
            <span className="tnum" style={{ color: `var(--${z.col})`, fontWeight: 800 }}>
              {z.pct}%
            </span>
          </div>
          <Progress pct={z.pct} tone={z.col} />
          {z.pct >= 90 ? (
            <div style={{ fontSize: 12, color: 'var(--danger)', marginTop: 8, display: 'flex', alignItems: 'center', gap: 6 }}>
              <Icon name="alert" size={13} /> Prag depășit
            </div>
          ) : null}
        </Card>
      ))}
      <Button variant="primary" icon="bell" style={{ marginTop: 6 }} onClick={() => showToast('Staff anunțat')}>
        Anunță staff
      </Button>
    </FullModal>
  );
}

/* ---------- sync (coada offline) ---------- */
function MSync() {
  const { closeModal, online, toggleOnline, showToast } = useSession();
  return (
    <Sheet title="Sincronizare" onClose={closeModal}>
      <Card pad style={{ marginBottom: 12, display: 'flex', alignItems: 'center', gap: 12, borderColor: online ? 'var(--green-border)' : 'var(--danger-border)' }}>
        <span className={`chip-i ${online ? 'chip-green' : 'chip-red'}`}>
          <Icon name={online ? 'checkc' : 'alert'} size={19} />
        </span>
        <div style={{ flex: 1 }}>
          <div style={{ fontSize: 14, fontWeight: 700, color: 'var(--text)' }}>{online ? 'Conectat' : 'Offline'}</div>
          <div style={{ fontSize: 12, color: 'var(--text-3)' }}>{online ? 'Toate datele sunt sincronizate' : '3 elemente în coadă'}</div>
        </div>
      </Card>

      {!online ? (
        <Card style={{ padding: '4px 16px', marginBottom: 12 }}>
          {['2× check-in · Poarta 1', '1× vânzare POS · 240 lei', '1× check-in · Poarta 2'].map((q, i, arr) => (
            <div key={q} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '11px 0', borderBottom: i === arr.length - 1 ? 'none' : '1px solid var(--border)' }}>
              <Icon name="clock" size={15} style={{ color: 'var(--amber)' }} />
              <span style={{ flex: 1, fontSize: 13.5, color: 'var(--text)' }}>{q}</span>
              <span className="tag" style={{ background: 'var(--amber-tint)', color: 'var(--amber)' }}>în așteptare</span>
            </div>
          ))}
        </Card>
      ) : null}

      <Button variant="primary" icon="swap" onClick={() => showToast('Sincronizare pornită')} disabled={online}>
        Sincronizează acum
      </Button>
      <Button variant="ghost" style={{ marginTop: 8 }} onClick={toggleOnline}>
        {online ? 'Simulează offline' : 'Revino online'}
      </Button>
      <div style={{ marginTop: 12, fontSize: 11.5, color: 'var(--text-4)', lineHeight: 1.5 }}>
        Cache-ul SQLite și coada reală de scanări/vânzări intră în Faza 3 (§11). Nicio pierdere silențioasă: orice
        element necomunicat rămâne vizibil aici până la confirmare.
      </div>
    </Sheet>
  );
}

/* ---------- printbadge / emailcapture / export ---------- */
function MPrintBadge() {
  const { closeModal, showToast } = useSession();
  const [kind, setKind] = useState<'badge' | 'bon' | 'acred'>('badge');
  return (
    <Sheet title="Imprimare" onClose={closeModal}>
      <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
        <TypeChip on={kind === 'badge'} onClick={() => setKind('badge')}>Badge</TypeChip>
        <TypeChip on={kind === 'bon'} onClick={() => setKind('bon')}>Bon</TypeChip>
        <TypeChip on={kind === 'acred'} onClick={() => setKind('acred')}>Acreditare</TypeChip>
      </div>
      <Card pad style={{ textAlign: 'center', color: 'var(--text-3)', fontSize: 13, marginBottom: 14 }}>
        Imprimantă termică Bluetooth · conectată
      </Card>
      <Button variant="primary" icon="camera" onClick={() => { closeModal(); showToast('Trimis la imprimantă'); }}>
        Printează
      </Button>
    </Sheet>
  );
}

function MEmailCapture() {
  const { closeModal, showToast } = useSession();
  const [email, setEmail] = useState('');
  return (
    <Sheet title="Trimite biletele pe email" onClose={closeModal}>
      <Input label="Email client" value={email} onChange={setEmail} placeholder="client@exemplu.ro" />
      <Button variant="primary" icon="mail" style={{ marginTop: 16 }} onClick={() => { closeModal(); showToast(email ? `Bilete trimise la ${email}` : 'Completează emailul'); }}>
        Trimite
      </Button>
    </Sheet>
  );
}

function MExport() {
  const { closeModal, showToast } = useSession();
  return (
    <Sheet title="Export CSV" onClose={closeModal}>
      <div className="sub" style={{ marginBottom: 14, lineHeight: 1.5 }}>
        Exportă lista de bilete cu status check-in, poartă, operator și timestamp.
      </div>
      <Button variant="primary" icon="download" onClick={() => { closeModal(); showToast('Export generat'); }}>
        Descarcă CSV
      </Button>
    </Sheet>
  );
}

/* ---------- manual ---------- */
function MManual() {
  const closeModal = useSession((s) => s.closeModal);
  return (
    <FullModal title="Manual utilizare" onClose={closeModal}>
      <div className="sub" style={{ lineHeight: 1.6, marginBottom: 14 }}>
        Ghidul complet al contului de organizator — 28 de capitole: vânzare, check-in, panou, echipă, urgențe, setări.
      </div>
      <Card pad style={{ color: 'var(--text-3)', fontSize: 13 }}>
        Capitolele se adaugă în Faza 2.
      </Card>
    </FullModal>
  );
}

/* ---------- shiftsummary ---------- */
function MShiftSummary() {
  const { closeModal, showToast, openModal } = useSession();
  return (
    <Sheet title="Rezumat tură" onClose={closeModal}>
      <Card style={{ padding: '4px 16px', marginBottom: 12 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '11px 0', borderBottom: '1px solid var(--border)' }}>
          <span className="muted" style={{ fontSize: 13.5 }}>Durata</span>
          <b style={{ fontSize: 13.5, color: 'var(--text)' }}>02:14:53</b>
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '11px 0', borderBottom: '1px solid var(--border)' }}>
          <span className="muted" style={{ fontSize: 13.5 }}>Scanări</span>
          <b style={{ fontSize: 13.5, color: 'var(--text)' }}>247</b>
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '11px 0', borderBottom: '1px solid var(--border)' }}>
          <span className="muted" style={{ fontSize: 13.5 }}>Numerar</span>
          <b style={{ fontSize: 13.5, color: 'var(--green)' }}>{money(1240)}</b>
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '11px 0' }}>
          <span className="muted" style={{ fontSize: 13.5 }}>Card</span>
          <b style={{ fontSize: 13.5, color: 'var(--cyan)' }}>{money(3180)}</b>
        </div>
      </Card>
      <Button variant="ghost" icon="cash" style={{ marginBottom: 8 }} onClick={() => openModal('cashcount')}>
        Reconciliere casă
      </Button>
      <Button variant="primary" onClick={() => { closeModal(); showToast('Tură închisă'); }}>
        Închide tura
      </Button>
    </Sheet>
  );
}

/* ---------- cashcount ---------- */
function MCashCount() {
  const { closeModal, showToast } = useSession();
  const denoms = [500, 200, 100, 50, 20, 10, 5, 1];
  const [counts, setCounts] = useState<Record<number, number>>({});
  const counted = denoms.reduce((a, d) => a + d * (counts[d] || 0), 0);
  const expected = 1240;
  const diff = counted - expected;
  return (
    <FullModal title="Reconciliere casă" onClose={closeModal}>
      <Card style={{ padding: '4px 16px', marginBottom: 12 }}>
        {denoms.map((d, i) => (
          <div key={d} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '10px 0', borderBottom: i === denoms.length - 1 ? 'none' : '1px solid var(--border)' }}>
            <span style={{ flex: 1, fontSize: 14, fontWeight: 600, color: 'var(--text)' }}>{d} lei</span>
            <input
              className="inputbox"
              style={{ width: 78, padding: '8px 10px', textAlign: 'center' }}
              value={counts[d] ?? ''}
              placeholder="0"
              inputMode="numeric"
              onChange={(e) => setCounts({ ...counts, [d]: Number(e.target.value) || 0 })}
            />
          </div>
        ))}
      </Card>
      <Card pad style={{ borderColor: diff === 0 ? 'var(--green-border)' : 'var(--amber-border)' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '6px 0' }}>
          <span className="muted" style={{ fontSize: 13.5 }}>Numărat</span>
          <b style={{ fontSize: 13.5, color: 'var(--text)' }}>{money(counted)}</b>
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '6px 0' }}>
          <span className="muted" style={{ fontSize: 13.5 }}>Așteptat</span>
          <b style={{ fontSize: 13.5, color: 'var(--text)' }}>{money(expected)}</b>
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '6px 0' }}>
          <span style={{ fontSize: 14, fontWeight: 700, color: 'var(--text)' }}>Diferență</span>
          <b className="tnum" style={{ fontSize: 18, color: diff === 0 ? 'var(--green)' : 'var(--amber)' }}>
            {diff > 0 ? '+' : ''}
            {money(diff)}
          </b>
        </div>
      </Card>
      <Button variant="primary" icon="download" style={{ marginTop: 12 }} onClick={() => { closeModal(); showToast('Z-report generat'); }}>
        Generează Z-report
      </Button>
    </FullModal>
  );
}

/* ---------- cashless (festival, §8) ---------- */
function MCashless() {
  const { closeModal, showToast } = useSession();
  const [amount, setAmount] = useState(100);
  return (
    <FullModal title="Cashless & brățări" onClose={closeModal}>
      <Card pad style={{ marginBottom: 12, borderColor: 'var(--accent-border)', background: 'linear-gradient(160deg,var(--accent-tint),var(--surface))' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <span className="chip-i chip-accent" style={{ width: 44, height: 44 }}>
            <Icon name="band" size={22} />
          </span>
          <div style={{ flex: 1 }}>
            <div style={{ fontSize: 14.5, fontWeight: 700, color: 'var(--text)' }}>Brățară #NV-2026-08841</div>
            <div style={{ fontSize: 12, color: 'var(--text-3)' }}>Andrei Popescu · VIP Pass</div>
          </div>
          <div className="tnum" style={{ fontSize: 20, fontWeight: 800, color: 'var(--accent-accent)' }}>
            162 lei
          </div>
        </div>
      </Card>

      <div style={{ display: 'flex', gap: 10, marginBottom: 14 }}>
        <Button variant="ghost" icon="nfc" style={{ flex: 1 }} onClick={() => showToast('Scanare NFC — Faza 4')}>
          Asociază
        </Button>
        <Button variant="ghost" icon="search" style={{ flex: 1 }} onClick={() => showToast('Sold: 162 lei')}>
          Verifică sold
        </Button>
      </div>

      <div className="eyebrow" style={{ marginBottom: 8 }}>Top-up</div>
      <div style={{ display: 'flex', gap: 8, marginBottom: 14 }}>
        {[50, 100, 150, 200].map((a) => (
          <TypeChip key={a} on={amount === a} onClick={() => setAmount(a)}>
            {a}
          </TypeChip>
        ))}
      </div>
      <div style={{ display: 'flex', gap: 10, marginBottom: 14 }}>
        <Button variant="green" icon="cash" style={{ flex: 1 }} onClick={() => showToast(`Top-up ${amount} lei numerar`)}>
          Numerar
        </Button>
        <Button variant="ghost" icon="nfc" style={{ flex: 1 }} onClick={() => showToast(`Top-up ${amount} lei card/NFC`)}>
          Card / NFC
        </Button>
      </div>
      <Button variant="ghost" style={{ color: 'var(--danger)', borderColor: 'var(--danger-border)' }} onClick={() => showToast('Refund sold la ieșire')}>
        Refund sold la ieșire
      </Button>

      <div className="card pad" style={{ marginTop: 14, display: 'flex', gap: 11, background: 'var(--cyan-tint)', borderColor: 'var(--cyan-border)' }}>
        <span style={{ color: 'var(--cyan)', flex: '0 0 auto' }}>
          <Icon name="info" size={18} />
        </span>
        <div style={{ fontSize: 12.5, color: 'var(--text-2)', lineHeight: 1.5 }}>
          Consum <b>fizic</b> pe locație → plăți externe (Stripe / Apple Pay / Tap to Pay), în afara IAP.
        </div>
      </div>
    </FullModal>
  );
}

/* ---------- vendors (festival) ---------- */
function MVendors() {
  const { closeModal, showToast } = useSession();
  const vendors = [
    { nm: 'Craft Bar', kind: 'bar', sales: 48200 },
    { nm: 'Burger Truck', kind: 'food', sales: 31400 },
    { nm: 'Merch Official', kind: 'merch', sales: 18900 },
    { nm: 'Coffee Point', kind: 'cafea', sales: 9600 },
  ];
  return (
    <FullModal title="Vendori festival" onClose={closeModal}>
      {vendors.map((v) => (
        <Card key={v.nm} pad style={{ marginBottom: 10, display: 'flex', alignItems: 'center', gap: 12 }}>
          <span className="chip-i chip-cyan">
            <Icon name="cart" size={19} />
          </span>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontSize: 14.5, fontWeight: 700, color: 'var(--text)' }}>{v.nm}</div>
            <div style={{ fontSize: 12, color: 'var(--text-3)' }}>{v.kind} · comision platformă 8%</div>
          </div>
          <b className="tnum" style={{ fontSize: 14, color: 'var(--green)' }}>{money(v.sales)}</b>
        </Card>
      ))}
      <Button variant="primary" icon="cash" style={{ marginTop: 6 }} onClick={() => { closeModal(); showToast('Decontare vendori inițiată'); }}>
        Decontează vendorii
      </Button>
    </FullModal>
  );
}

/* ---------- broadcast ---------- */
function MBroadcast() {
  const { closeModal, showToast } = useSession();
  const [target, setTarget] = useState('toți');
  const [urgent, setUrgent] = useState(false);
  const [msg, setMsg] = useState('');
  return (
    <Sheet title="Difuzare către staff" onClose={closeModal}>
      <div className="eyebrow" style={{ marginBottom: 8 }}>Destinatari</div>
      <div style={{ display: 'flex', gap: 8, marginBottom: 14, flexWrap: 'wrap' }}>
        {['toți', 'admin', 'manager', 'staff', 'Poarta 1', 'Poarta 2'].map((t) => (
          <TypeChip key={t} on={target === t} onClick={() => setTarget(t)}>
            {t}
          </TypeChip>
        ))}
      </div>
      <Input label="Mesaj" value={msg} onChange={setMsg} placeholder="Scrie mesajul…" />
      <div style={{ display: 'flex', gap: 8, margin: '14px 0' }}>
        <TypeChip on={!urgent} onClick={() => setUrgent(false)}>Normal</TypeChip>
        <TypeChip on={urgent} onClick={() => setUrgent(true)}>Urgent</TypeChip>
      </div>
      <Button variant="primary" icon="bell" onClick={() => { closeModal(); showToast(`Mesaj trimis către ${target}`); }}>
        Trimite
      </Button>
    </Sheet>
  );
}

/* ---------- ticketlist / seatmap ---------- */
function MTicketList() {
  const { closeModal, showToast } = useSession();
  const rows = [
    { nm: 'Andrei Popescu', tt: 'Abonament General', code: '#12587', ci: true },
    { nm: 'Maria Ionescu', tt: 'Acces 1 zi', code: '#12588', ci: true },
    { nm: 'Radu Georgescu', tt: 'VIP', code: '#12601', ci: false },
    { nm: 'Elena Vasile', tt: 'Acces 1 zi', code: '#12614', ci: false },
  ];
  return (
    <FullModal title="Bilete eveniment" onClose={closeModal}>
      {rows.map((r) => (
        <Card key={r.code} pad style={{ marginBottom: 10, display: 'flex', alignItems: 'center', gap: 12 }}>
          <span className={`chip-i ${r.ci ? 'chip-green' : 'chip-accent'}`}>
            <Icon name={r.ci ? 'checkc' : 'ticket'} size={19} />
          </span>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontSize: 14.5, fontWeight: 700, color: 'var(--text)' }}>{r.nm}</div>
            <div style={{ fontSize: 12, color: 'var(--text-3)' }}>
              {r.tt} · {r.code}
            </div>
          </div>
          {r.ci ? (
            <span className="tag tag-mgr">Intrat</span>
          ) : (
            <button className="typechip" onClick={() => showToast(`${r.nm} check-in efectuat`)} type="button">
              Check-in
            </button>
          )}
        </Card>
      ))}
    </FullModal>
  );
}

function MSeatMap() {
  const { closeModal, showToast } = useSession();
  const [sel, setSel] = useState<string[]>([]);
  const rows = ['A', 'B', 'C', 'D', 'E', 'F'];
  const toggle = (id: string) => setSel((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));
  return (
    <FullModal title="Alege locurile" onClose={closeModal}>
      <div style={{ background: 'var(--surface-2)', borderRadius: 12, padding: '8px 0', textAlign: 'center', fontSize: 11.5, color: 'var(--text-3)', marginBottom: 18, fontWeight: 700, letterSpacing: 2 }}>
        SCENĂ
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 8, alignItems: 'center' }}>
        {rows.map((r) => (
          <div key={r} style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
            <span style={{ width: 16, fontSize: 11, color: 'var(--text-3)', fontWeight: 700 }}>{r}</span>
            {Array.from({ length: 12 }, (_, i) => {
              const id = `${r}${i + 1}`;
              const taken = (i + rows.indexOf(r)) % 7 === 0;
              return (
                <span
                  key={id}
                  className={`seat ${taken ? 'a' : sel.includes(id) ? 'sel' : 'g'}`}
                  onClick={() => !taken && toggle(id)}
                />
              );
            })}
          </div>
        ))}
      </div>
      <div style={{ display: 'flex', gap: 16, justifyContent: 'center', margin: '18px 0', fontSize: 11.5, color: 'var(--text-2)' }}>
        <span style={{ display: 'flex', alignItems: 'center', gap: 6 }}><span className="seat g" style={{ width: 12, height: 12 }} /> Liber</span>
        <span style={{ display: 'flex', alignItems: 'center', gap: 6 }}><span className="seat sel" style={{ width: 12, height: 12 }} /> Ales</span>
        <span style={{ display: 'flex', alignItems: 'center', gap: 6 }}><span className="seat a" style={{ width: 12, height: 12 }} /> Ocupat</span>
      </div>
      <Button variant="primary" onClick={() => { closeModal(); showToast(sel.length ? `Locuri alese: ${sel.join(', ')}` : 'Niciun loc ales'); }}>
        Confirmă {sel.length ? `(${sel.length})` : ''}
      </Button>
    </FullModal>
  );
}

/* =========================================================
   Dispecer
   ========================================================= */
export function OrgModals() {
  const modal = useSession((s) => s.modal);
  if (!modal) return null;
  switch (modal) {
    case 'events': return <MEvents />;
    case 'notifs': return <MNotifs />;
    case 'switch': return <MSwitch />;
    case 'staff': return <MStaff />;
    case 'gates': return <MGates />;
    case 'guests': return <MGuests />;
    case 'emergency': return <MEmergency />;
    case 'remaining': return <MTicketBreakdown mode="remaining" />;
    case 'ticketsales': return <MTicketBreakdown mode="ticketsales" />;
    case 'breakdown': return <MBreakdown />;
    case 'scandetails': return <MScanDetails />;
    case 'manualentry': return <MManualEntry />;
    case 'connectorg': return <MConnectOrg />;
    case 'payconfirm': return <MPayConfirm />;
    case 'ticketaction': return <MTicketAction />;
    case 'banlist': return <MBanlist />;
    case 'occupancy': return <MOccupancy />;
    case 'sync': return <MSync />;
    case 'printbadge': return <MPrintBadge />;
    case 'emailcapture': return <MEmailCapture />;
    case 'export': return <MExport />;
    case 'manual': return <MManual />;
    case 'shiftsummary': return <MShiftSummary />;
    case 'cashcount': return <MCashCount />;
    case 'cashless': return <MCashless />;
    case 'vendors': return <MVendors />;
    case 'broadcast': return <MBroadcast />;
    case 'ticketlist': return <MTicketList />;
    case 'seatmap': return <MSeatMap />;
    default: return null;
  }
}
