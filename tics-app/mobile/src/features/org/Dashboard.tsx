/* =========================================================
   PANOU (§6.1) — varianta Admin/Manager si varianta Scanner/Staff.
   Portat 1:1 dupa dashAdmin() / dashScanner() din organizer-app.html.
   ========================================================= */
import type { ReactNode } from 'react';
import { Button, Card, Icon, Progress, SectionHead, type IconName } from '../../design/components';
import { useSession, isAdminRole } from '../../store/session';
import { useCtx } from './OrgChrome';

const ro = (n: number) => n.toLocaleString('ro-RO');

function StatCell({
  icon,
  chip,
  border,
  value,
  label,
  unit,
  onClick,
}: {
  icon: IconName;
  chip: string;
  border: string;
  value: string;
  label: string;
  unit?: string;
  onClick?: () => void;
}) {
  return (
    <div className="stat" style={{ borderColor: border }} onClick={onClick}>
      <span className={`chip-i ${chip}`} style={{ width: 30, height: 30, borderRadius: 9 }}>
        <Icon name={icon} size={16} />
      </span>
      <div className="value tnum" style={{ fontSize: 22, marginTop: 8 }}>
        {value}
        {unit ? <span className="unit"> {unit}</span> : null}
      </div>
      <div className="label">{label}</div>
    </div>
  );
}

function QuickAction({
  icon,
  bg,
  border,
  color,
  label,
  onClick,
}: {
  icon: IconName;
  bg: string;
  border: string;
  color: string;
  label: string;
  onClick: () => void;
}) {
  return (
    <div
      className="card"
      style={{
        padding: 15,
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        gap: 8,
        background: bg,
        borderColor: border,
        color,
        cursor: 'pointer',
      }}
      onClick={onClick}
    >
      <Icon name={icon} size={24} />
      <b style={{ fontSize: 12 }}>{label}</b>
    </div>
  );
}

function RecentRow({ nm, meta, color, icon, last }: { nm: string; meta: string; color: string; icon: IconName; last?: boolean }) {
  return (
    <div
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: 12,
        padding: '11px 0',
        borderBottom: last ? 'none' : '1px solid var(--border)',
      }}
    >
      <span style={{ width: 8, height: 8, borderRadius: '50%', background: `var(--${color})` }} />
      <div style={{ flex: 1 }}>
        <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--text)' }}>{nm}</div>
        <div style={{ fontSize: 12, color: 'var(--text-3)' }}>{meta}</div>
      </div>
      <span style={{ color: `var(--${color})` }}>
        <Icon name={icon} size={18} />
      </span>
    </div>
  );
}

function OccRow({ nm, pct, col, last }: { nm: string; pct: number; col: 'green' | 'amber' | 'danger'; last?: boolean }) {
  return (
    <div style={{ marginBottom: last ? 0 : 11 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, marginBottom: 5 }}>
        <span style={{ color: 'var(--text)', fontWeight: 600 }}>{nm}</span>
        <span className="tnum" style={{ color: `var(--${col})`, fontWeight: 700 }}>
          {pct}%
        </span>
      </div>
      <Progress pct={pct} tone={col} />
    </div>
  );
}

function ScStat({ icon, tone, value, label }: { icon: IconName; tone: string; value: string; label: string }) {
  return (
    <div
      className="stat"
      style={{ textAlign: 'center', background: `var(--${tone}-tint)`, borderColor: `var(--${tone}-border)`, cursor: 'default' }}
    >
      <div style={{ color: `var(--${tone})`, margin: '0 auto', width: 20 }}>
        <Icon name={icon} size={20} />
      </div>
      <div className="value tnum" style={{ fontSize: 18, color: `var(--${tone})` }}>
        {value}
      </div>
      <div className="label" style={{ fontSize: 11 }}>
        {label}
      </div>
    </div>
  );
}

/* ---------- Card festival: Cashless & bratari (§8) ---------- */
function FestivalCashlessCard({ onOpen }: { onOpen: () => void }) {
  return (
    <div
      className="card pad"
      style={{ borderColor: 'var(--accent-border)', background: 'linear-gradient(160deg,var(--accent-tint),var(--surface))', cursor: 'pointer' }}
      onClick={onOpen}
    >
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 12 }}>
        <span className="chip-i chip-accent">
          <Icon name="band" size={20} />
        </span>
        <span style={{ flex: 1, fontSize: 14, fontWeight: 700, color: 'var(--text)' }}>Cashless &amp; brățări</span>
        <span className="pill pill-live" style={{ flex: '0 0 auto' }}>
          <span className="dot" />
          Sync
        </span>
      </div>
      <div className="grid3">
        <ScStat icon="band" tone="accent" value="9.860" label="Brățări activate" />
        <ScStat icon="cash" tone="green" value="412k" label="Sold încărcat (lei)" />
        <ScStat icon="cart" tone="cyan" value="38" label="Vendori activi" />
      </div>
      <Button variant="primary" icon="band" style={{ marginTop: 12, padding: 12 }} onClick={onOpen}>
        Gestionează cashless
      </Button>
    </div>
  );
}

/* =========================================================
   PANOU — ADMIN / MANAGER
   ========================================================= */
function DashAdmin() {
  const { ctx, openModal, go, onlineSeg } = useSession();
  const setSeg = (v: 'online' | 'door' | null) => useSession.setState({ onlineSeg: v });
  const c = useCtx();
  const isFestival = ctx === 'festival';

  const pct = Math.round((c.checkedin / c.cap) * 100);
  const soldPct = Math.round((c.sold / c.cap) * 100);
  const onlinePct = Math.round((c.online / (c.online + c.door)) * 100);

  const breakdown: ReactNode = onlineSeg
    ? c.tt
        .filter((t) => t.s > 0)
        .map((t) => (
          <div
            key={t.n}
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              padding: '8px 0',
              borderTop: '1px solid var(--border)',
              fontSize: 13,
              color: 'var(--text)',
            }}
          >
            <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <span style={{ width: 9, height: 9, borderRadius: 3, background: t.c }} />
              {t.n}
            </span>
            <b>{Math.round((onlineSeg === 'online' ? 0.73 : 0.27) * t.s)}</b>
          </div>
        ))
    : null;

  return (
    <div className="screen pad stack">
      <div>
        <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--accent-accent)' }}>{c.date}</div>
        <div className="h1" style={{ fontSize: 20, marginTop: 2 }}>
          {c.event}
        </div>
        <div className="sub" style={{ marginTop: 2 }}>
          {c.venue}, {c.city} · <span style={{ color: 'var(--accent)' }}>{c.label}</span>
        </div>
      </div>

      {/* Card „Intrati" → modal remaining */}
      <div className="card pad" style={{ borderColor: 'var(--accent-border)', cursor: 'pointer' }} onClick={() => openModal('remaining')}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 12 }}>
          <span className="chip-i chip-accent">
            <Icon name="people" size={20} />
          </span>
          <span style={{ flex: 1, fontSize: 14, fontWeight: 600, color: 'var(--text-2)' }}>Intrați</span>
          <span
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: 4,
              background: 'var(--green-tint)',
              color: 'var(--green)',
              fontSize: 12,
              fontWeight: 700,
              padding: '4px 9px',
              borderRadius: 8,
            }}
          >
            <Icon name="trend" size={12} />
            {pct}%
          </span>
        </div>
        <div className="tnum" style={{ fontSize: 30, fontWeight: 800, letterSpacing: -0.5, color: 'var(--text)' }}>
          {ro(c.checkedin)}
          <span style={{ fontSize: 20, fontWeight: 500, color: 'var(--text-3)' }}> / {ro(c.cap)}</span>
        </div>
        <div style={{ marginTop: 12 }}>
          <Progress pct={pct} />
        </div>
      </div>

      <div className="grid2">
        <StatCell icon="ticket" chip="chip-green" border="var(--green-border)" value={ro(c.sold)} label="Vândute" onClick={() => openModal('ticketsales')} />
        <StatCell icon="cash" chip="chip-cyan" border="var(--cyan-border)" value={`${(c.revenue / 1000).toFixed(0)}k`} unit="lei" label="Încasări" onClick={() => openModal('breakdown')} />
        <StatCell icon="clock" chip="chip-amber" border="var(--amber-border)" value={ro(c.cap - c.sold)} label="Disponibile" onClick={() => openModal('remaining')} />
        <StatCell icon="chart" chip="chip-accent" border="var(--accent-border)" value={`${soldPct}%`} label="Capacitate" />
      </div>

      {isFestival ? <FestivalCashlessCard onOpen={() => openModal('cashless')} /> : null}

      <Card pad>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
          <div style={{ fontSize: 14, fontWeight: 700, color: 'var(--text)' }}>Ritm vânzare</div>
          <span
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: 4,
              background: 'var(--green-tint)',
              color: 'var(--green)',
              fontSize: 12,
              fontWeight: 700,
              padding: '3px 8px',
              borderRadius: 8,
            }}
          >
            <Icon name="trend" size={12} /> +18%
          </span>
        </div>
        <div style={{ display: 'flex', alignItems: 'baseline', gap: 6 }}>
          <b className="tnum" style={{ fontSize: 26, color: 'var(--text)' }}>
            4.2
          </b>
          <span className="muted" style={{ fontSize: 13 }}>
            bilete / min
          </span>
        </div>
        <div className="sub" style={{ fontSize: 12, marginTop: 2 }}>
          ultimele 10 min · vs 3.5/min anterior
        </div>
      </Card>

      <Card pad>
        <div style={{ fontSize: 14, fontWeight: 700, marginBottom: 10, color: 'var(--text)' }}>Online vs. la ușă</div>
        <div className="seg-bar">
          <div className="on" style={{ flex: `0 0 ${onlinePct}%` }} onClick={() => setSeg(onlineSeg === 'online' ? null : 'online')}>
            Online {ro(c.online)} ({onlinePct}%)
          </div>
          <div className="door" onClick={() => setSeg(onlineSeg === 'door' ? null : 'door')}>
            La ușă {c.door} ({100 - onlinePct}%)
          </div>
        </div>
        {breakdown ? <div style={{ marginTop: 10 }}>{breakdown}</div> : null}
      </Card>

      <Card pad onClick={() => openModal('occupancy')}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
          <div style={{ fontSize: 14, fontWeight: 700, color: 'var(--text)' }}>Ocupare pe zone</div>
          <span
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: 5,
              background: 'var(--danger-tint)',
              color: 'var(--danger)',
              fontSize: 11,
              fontWeight: 700,
              padding: '3px 9px',
              borderRadius: 8,
            }}
          >
            <Icon name="alert" size={12} /> 1 alertă
          </span>
        </div>
        <OccRow nm="Ring Principal" pct={92} col="danger" />
        <OccRow nm="Zona VIP" pct={74} col="amber" />
        <OccRow nm="Tribună" pct={48} col="green" last />
      </Card>

      <div>
        <SectionHead title="Acțiuni rapide" />
        <div className="grid2">
          <QuickAction icon="scan" bg="var(--accent-tint)" border="var(--accent-border)" color="var(--accent)" label="Scanare" onClick={() => go('CheckIn')} />
          <QuickAction icon="cart" bg="var(--green-tint)" border="var(--green-border)" color="var(--green)" label="Vânzare" onClick={() => go('Sales')} />
          {isFestival ? (
            <QuickAction icon="band" bg="var(--accent-tint)" border="var(--accent-border)" color="var(--accent)" label="Cashless" onClick={() => openModal('cashless')} />
          ) : (
            <QuickAction icon="people" bg="var(--cyan-tint)" border="var(--cyan-border)" color="var(--cyan)" label="Listă Invitați" onClick={() => openModal('guests')} />
          )}
          <QuickAction icon="people" bg="var(--amber-tint)" border="var(--amber-border)" color="var(--amber)" label="Echipă" onClick={() => openModal('staff')} />
        </div>
      </div>

      <div>
        <SectionHead title="Activitate recentă" />
        <Card style={{ padding: '6px 14px' }}>
          <RecentRow nm="Andrei Popescu" meta="Abonament General · acum" color="green" icon="checkc" />
          <RecentRow nm="Maria Ionescu" meta="Acces 1 zi · acum 1 min" color="green" icon="checkc" />
          <RecentRow nm="Bilet #4821" meta="Deja scanat · acum 2 min" color="danger" icon="xc" last />
        </Card>
      </div>

      <Button variant="ghost" icon="xc" style={{ color: 'var(--danger)', borderColor: 'var(--danger-border)' }} onClick={() => openModal('shiftsummary')}>
        Închide Tura
      </Button>
      <div style={{ textAlign: 'center', fontSize: 12, color: 'var(--text-3)', padding: '10px 0' }}>
        ↓ Trage în jos pentru refresh
      </div>
    </div>
  );
}

/* =========================================================
   PANOU — SCANNER / STAFF
   ========================================================= */
function DashScanner() {
  const { go, openModal } = useSession();
  const c = useCtx();
  return (
    <div className="screen pad stack">
      <div>
        <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--accent-accent)' }}>{c.date}</div>
        <div className="h1" style={{ fontSize: 20, marginTop: 3 }}>
          {c.event}
        </div>
      </div>

      <Card pad style={{ borderColor: 'var(--green-border)' }}>
        <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 14, color: 'var(--text)' }}>Încasări</div>
        <div style={{ display: 'flex', alignItems: 'center' }}>
          <div style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 12 }}>
            <span className="chip-i chip-green" style={{ width: 40, height: 40 }}>
              <Icon name="cash" size={20} />
            </span>
            <div>
              <div style={{ fontSize: 12, color: 'var(--text-2)' }}>Numerar</div>
              <div className="tnum" style={{ fontSize: 18, fontWeight: 800, color: 'var(--green)' }}>
                1.240 lei
              </div>
            </div>
          </div>
          <div style={{ width: 1, height: 38, background: 'var(--border)', margin: '0 12px' }} />
          <div style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 12 }}>
            <span className="chip-i chip-cyan" style={{ width: 40, height: 40 }}>
              <Icon name="card" size={20} />
            </span>
            <div>
              <div style={{ fontSize: 12, color: 'var(--text-2)' }}>Card</div>
              <div className="tnum" style={{ fontSize: 18, fontWeight: 800, color: 'var(--cyan)' }}>
                3.180 lei
              </div>
            </div>
          </div>
        </div>
      </Card>

      <div className="grid3">
        <ScStat icon="scan" tone="accent" value="247" label="Scanările Mele" />
        <ScStat icon="cart" tone="green" value="36" label="Vânzările Mele" />
        <ScStat icon="clock" tone="amber" value="2h 14m" label="Durata Turei" />
      </div>

      <Button variant="primary" icon="scan" style={{ padding: 19 }} onClick={() => go('CheckIn')}>
        Începe Scanarea
      </Button>
      <Button variant="green" icon="cart" style={{ padding: 19 }} onClick={() => go('Sales')}>
        Începe Vânzarea
      </Button>
      <Button variant="ghost" icon="xc" style={{ color: 'var(--danger)', borderColor: 'var(--danger-border)' }} onClick={() => openModal('shiftsummary')}>
        Închide Tura
      </Button>
    </div>
  );
}

export function Dashboard() {
  const role = useSession((s) => s.role);
  return isAdminRole(role) ? <DashAdmin /> : <DashScanner />;
}
