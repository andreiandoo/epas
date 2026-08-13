/* =========================================================
   NAVIGATOR LEISURE / VENUE-OWNER (§7) — distinct de cel standard:
   Evenimente · Scanare · Vanzare · Setari.
   Evenimentele sunt ale LOCATIEI, indiferent de organizator;
   detaliu eveniment cu lista de participanti; detaliu bilet cu
   mentiuni & grupare bilete; Export CSV.
   ========================================================= */
import { useState } from 'react';
import { Button, Card, Icon, InfoRow, Pill, Toast, Toggle, type IconName } from '../../design/components';
import { useSession, type AppTheme, type VenueTab } from '../../store/session';
import { LEISURE } from '../../mock/org';
import { CheckIn } from '../org/CheckIn';
import { Sales } from '../org/Sales';
import { OrgModals } from '../org/OrgModals';

function useVenueEvent() {
  const id = useSession((s) => s.venueEventId);
  return LEISURE.events.find((e) => e.id === id) ?? LEISURE.events[0];
}

function VenueTabbar() {
  const { tab, go } = useSession();
  const item = (name: VenueTab, icon: IconName, label: string) => (
    <button className={`tab ${tab === name ? 'active' : ''}`} onClick={() => go(name)} type="button">
      <Icon name={icon} size={22} />
      <span>{label}</span>
    </button>
  );
  return (
    <div className="tabbar">
      {item('VenueEvents', 'cal', 'Evenimente')}
      {item('CheckIn', 'scan', 'Scanare')}
      {item('Sales', 'cart', 'Vânzare')}
      {item('Settings', 'cog', 'Setări')}
    </div>
  );
}

function VenueEventBand() {
  const e = useVenueEvent();
  return (
    <div style={{ background: 'var(--surface)', borderBottom: '1px solid var(--border)', padding: '12px 16px', flex: '0 0 auto' }}>
      <div style={{ fontSize: 15, fontWeight: 700, color: 'var(--text)' }}>{e.title}</div>
      <div style={{ display: 'flex', gap: 8, marginTop: 4 }}>
        <span style={{ fontSize: 12, fontWeight: 700, color: 'var(--accent)' }}>{e.date}</span>
        <span style={{ fontSize: 12, color: 'var(--text-2)' }}>{e.org}</span>
      </div>
    </div>
  );
}

function VenueList() {
  const { venueOpen, logout } = useSession();
  const [filter, setFilter] = useState<'Viitor' | 'Trecut' | 'Toate'>('Viitor');

  const events = LEISURE.events.filter((e) => (filter === 'Toate' ? true : filter === 'Trecut' ? e.past : !e.past));

  return (
    <>
      <div
        style={{
          background: 'var(--surface)',
          borderBottom: '1px solid var(--border)',
          padding: '14px 16px',
          display: 'flex',
          alignItems: 'flex-start',
          justifyContent: 'space-between',
          flex: '0 0 auto',
        }}
      >
        <div>
          <div className="h2">{LEISURE.venue}</div>
          <div className="sub" style={{ marginTop: 2 }}>
            Evenimente la locația ta
          </div>
        </div>
        <button className="link" onClick={logout} type="button">
          Ieșire
        </button>
      </div>

      <div className="pad" style={{ paddingBottom: 0, flex: '0 0 auto' }}>
        <div style={{ display: 'flex', gap: 8 }}>
          {(['Viitor', 'Trecut', 'Toate'] as const).map((t) => (
            <span
              key={t}
              onClick={() => setFilter(t)}
              style={{
                flex: 1,
                textAlign: 'center',
                fontSize: 13,
                fontWeight: filter === t ? 700 : 600,
                padding: 9,
                borderRadius: 11,
                cursor: 'pointer',
                background: filter === t ? 'var(--accent)' : 'var(--surface)',
                border: filter === t ? 'none' : '1px solid var(--border)',
                color: filter === t ? '#fff' : 'var(--text-2)',
              }}
            >
              {t}
            </span>
          ))}
        </div>
      </div>

      <div className="screen pad" style={{ paddingTop: 12 }}>
        {events.map((e) => (
          <Card key={e.id} pad style={{ marginBottom: 10, cursor: 'pointer' }} onClick={() => venueOpen(e.id)}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 10 }}>
              <b style={{ fontSize: 15.5, color: 'var(--text)' }}>{e.title}</b>
              {e.live ? (
                <Pill tone="live" style={{ flex: '0 0 auto' }}>
                  DESCHIS
                </Pill>
              ) : e.badge ? (
                <span style={{ fontSize: 10, fontWeight: 700, padding: '3px 9px', borderRadius: 999, background: 'var(--amber-tint)', color: 'var(--amber)' }}>
                  {e.badge}
                </span>
              ) : (
                <Icon name="chev" size={18} className="chev" />
              )}
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 7, marginTop: 8, fontSize: 12.5, color: 'var(--text-2)' }}>
              <span style={{ color: 'var(--accent)' }}>
                <Icon name="cal" size={15} />
              </span>
              {e.date}
            </div>
            <div style={{ fontSize: 12, color: 'var(--text-3)', marginTop: 4 }}>Organizator: {e.org}</div>
            <div style={{ display: 'flex', gap: 18, marginTop: 12, paddingTop: 12, borderTop: '1px solid var(--border)' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13, color: 'var(--text)' }}>
                <span style={{ color: 'var(--accent)' }}>
                  <Icon name="ticket" size={15} />
                </span>
                <b className="tnum">
                  {e.sold} / {e.cap}
                </b>
                <span className="muted">vândute</span>
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13, color: 'var(--text)' }}>
                <span style={{ color: 'var(--green)' }}>
                  <Icon name="checkc" size={15} />
                </span>
                <b className="tnum">{e.ci}</b>
                <span className="muted">check-in</span>
              </div>
            </div>
          </Card>
        ))}
        <div style={{ textAlign: 'center', fontSize: 12, color: 'var(--text-3)', padding: '10px 0' }}>
          ↓ Trage pentru refresh
        </div>
      </div>
    </>
  );
}

const ATTENDEES = [
  { nm: 'Andrei Popescu', meta: 'Abonament General · #12587 · CMD-4821', st: 'CHECKED-IN', stc: 'green' },
  { nm: 'Maria Ionescu', meta: 'Acces 1 zi · #12588 · CMD-4822', st: 'VALID', stc: 'cyan', warn: true },
  { nm: 'Radu Georgescu', meta: 'Tur barcă · Secțiune A · loc 12', st: 'ÎN AȘTEPTARE', stc: 'amber' },
  { nm: 'Elena Vasile', meta: 'Aventură copaci · #12590 · CMD-4823', st: 'CHECKED-IN', stc: 'green' },
];

function VenueDetail() {
  const { venueBack, venueTicket, go, openModal } = useSession();
  const e = useVenueEvent();

  return (
    <>
      <div className="modal-topbar" style={{ flex: '0 0 auto' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <button className="iconbtn" onClick={venueBack} type="button">
            <Icon name="back" size={18} />
          </button>
          <b>{e.title}</b>
        </div>
      </div>
      <div className="screen pad stack">
        <div>
          <div style={{ fontSize: 12, color: 'var(--accent-accent)', fontWeight: 700 }}>{e.date}</div>
          <div style={{ fontSize: 12, color: 'var(--text-3)', marginTop: 2 }}>Organizator: {e.org}</div>
        </div>

        <div style={{ display: 'flex', gap: 10 }}>
          <Card pad style={{ flex: 1 }}>
            <div className="muted" style={{ fontSize: 12 }}>
              Vândute
            </div>
            <div className="tnum" style={{ fontSize: 20, fontWeight: 800, color: 'var(--text)' }}>
              {e.sold}
              <span style={{ fontSize: 13, color: 'var(--text-3)' }}>/{e.cap}</span>
            </div>
          </Card>
          <Card pad style={{ flex: 1 }}>
            <div className="muted" style={{ fontSize: 12 }}>
              Check-in
            </div>
            <div className="tnum" style={{ fontSize: 20, fontWeight: 800, color: 'var(--text)' }}>
              {e.ci}
            </div>
          </Card>
          <Card
            pad
            onClick={() => openModal('export')}
            style={{ flex: '0 0 auto', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', color: 'var(--accent)', gap: 4 }}
          >
            <Icon name="download" size={20} />
            <span style={{ fontSize: 11, fontWeight: 700 }}>Export CSV</span>
          </Card>
        </div>

        <div style={{ display: 'flex', gap: 10 }}>
          <Button variant="primary" icon="scan" style={{ flex: 1 }} onClick={() => go('CheckIn')}>
            Scanare
          </Button>
          <Button variant="green" icon="cart" style={{ flex: 1 }} onClick={() => go('Sales')}>
            Vânzare
          </Button>
        </div>

        <Card style={{ padding: '12px 14px', display: 'flex', alignItems: 'center', gap: 10, color: 'var(--text-3)' }}>
          <Icon name="search" size={18} />
          <span style={{ fontSize: 13.5 }}>Caută după nume, telefon sau nr. comandă</span>
        </Card>

        <Card style={{ padding: '4px 14px' }}>
          {ATTENDEES.map((a, i) => (
            <div
              key={a.nm}
              onClick={venueTicket}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 10,
                padding: '12px 0',
                borderBottom: i < ATTENDEES.length - 1 ? '1px solid var(--border)' : 'none',
                cursor: 'pointer',
              }}
            >
              {a.warn ? (
                <span style={{ color: 'var(--amber)' }}>
                  <Icon name="alert" size={15} />
                </span>
              ) : null}
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--text)' }}>{a.nm}</div>
                <div style={{ fontSize: 11.5, color: 'var(--text-3)', marginTop: 2 }}>{a.meta}</div>
              </div>
              <span
                style={{
                  fontSize: 10,
                  fontWeight: 800,
                  padding: '3px 9px',
                  borderRadius: 999,
                  background: `var(--${a.stc}-tint)`,
                  color: `var(--${a.stc})`,
                }}
              >
                {a.st}
              </span>
            </div>
          ))}
        </Card>
      </div>
    </>
  );
}

function VenueTicketDetail() {
  const { venueBack, showToast } = useSession();
  const [group, setGroup] = useState(true);

  return (
    <>
      <div className="modal-topbar" style={{ flex: '0 0 auto' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <button className="iconbtn" onClick={venueBack} type="button">
            <Icon name="back" size={18} />
          </button>
          <b>Detalii bilet</b>
        </div>
      </div>
      <div className="screen pad stack">
        <Card pad>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 }}>
            <b style={{ fontSize: 15, color: 'var(--text)' }}>#12587</b>
            <span style={{ fontSize: 10, fontWeight: 800, padding: '3px 9px', borderRadius: 999, background: 'var(--green-tint)', color: 'var(--green)' }}>
              UTILIZAT
            </span>
          </div>
          <InfoRow label="Cod" value="WXF-8A21-QK" />
          <InfoRow label="Tip bilet" value="Abonament General" />
          <InfoRow label="Preț" value="150,00 RON" />
          <InfoRow label="Check-in" value="Azi, 20:15" last />
        </Card>

        <Card pad>
          <div className="eyebrow" style={{ marginBottom: 8 }}>
            Client
          </div>
          <div style={{ fontSize: 15, fontWeight: 700, color: 'var(--text)', marginBottom: 6 }}>Andrei Popescu</div>
          <div style={{ borderTop: '1px solid var(--border)' }}>
            <InfoRow label="Telefon" value="0722 123 456" last />
          </div>
        </Card>

        <Card pad>
          <div className="eyebrow" style={{ marginBottom: 10 }}>
            Mențiuni (1)
          </div>
          <div style={{ background: 'var(--surface-2)', border: '1px solid var(--border)', borderRadius: 12, padding: 12 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <b style={{ fontSize: 13, color: 'var(--text)' }}>Mihai Coman</b>
              <div style={{ display: 'flex', gap: 8, color: 'var(--text-3)' }}>
                <Icon name="edit" size={14} />
                <span style={{ color: 'var(--danger)' }}>
                  <Icon name="trash" size={14} />
                </span>
              </div>
            </div>
            <div style={{ fontSize: 11, color: 'var(--text-3)', margin: '3px 0 8px' }}>Azi, 19:40 · Bilet</div>
            <div style={{ fontSize: 13, color: 'var(--text)' }}>Client fidel, acces prioritar la zona VIP.</div>
          </div>

          <input className="inputbox" style={{ marginTop: 12 }} placeholder="Adaugă o mențiune nouă..." />

          <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 10, cursor: 'pointer' }} onClick={() => setGroup((g) => !g)}>
            <span
              style={{
                width: 20,
                height: 20,
                borderRadius: 6,
                border: '2px solid var(--accent)',
                background: group ? 'var(--accent)' : 'transparent',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                color: '#fff',
              }}
            >
              {group ? <Icon name="check" size={12} /> : null}
            </span>
            <span style={{ fontSize: 13, fontWeight: 600, color: 'var(--text)' }}>
              Grupează biletele (2 la acest eveniment)
            </span>
          </div>

          <Button variant="primary" style={{ marginTop: 12 }} onClick={() => showToast('Mențiune adăugată')}>
            Adaugă
          </Button>
        </Card>
      </div>
    </>
  );
}

function VenueSettings() {
  const { set, toggleSet, appTheme, setTheme, goChooser, logout, openModal } = useSession();

  const themeRow = (t: AppTheme, label: string, last?: boolean) => (
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

  return (
    <div className="screen pad stack">
      <div className="h1">Setări</div>

      <div className="eyebrow">Cont</div>
      <Card style={{ padding: '4px 16px' }}>
        <InfoRow label="Nume" value="Proprietar locație" />
        <InfoRow label="Rol" value="Venue owner" />
        <InfoRow label="Nume Venue" value={LEISURE.venue} />
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '13px 0', cursor: 'pointer' }} onClick={goChooser}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <span style={{ color: 'var(--accent)' }}>
              <Icon name="swap" size={18} />
            </span>
            <span style={{ fontSize: 14, fontWeight: 600, color: 'var(--text)' }}>Comută tipul de cont</span>
          </div>
          <Icon name="chev" size={16} className="chev" />
        </div>
      </Card>

      <div className="eyebrow">Scanner</div>
      <Card style={{ padding: '4px 16px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '13px 0', borderBottom: '1px solid var(--border)' }}>
          <span style={{ fontSize: 14, fontWeight: 600, color: 'var(--text)' }}>Vibrație</span>
          <Toggle on={set.vibr} onChange={() => toggleSet('vibr')} />
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '13px 0' }}>
          <span style={{ fontSize: 14, fontWeight: 600, color: 'var(--text)' }}>Efecte Sonore</span>
          <Toggle on={set.sound} onChange={() => toggleSet('sound')} />
        </div>
      </Card>

      <div className="eyebrow">Aspect</div>
      <Card style={{ padding: '4px 16px' }}>
        {themeRow('light', 'Standard')}
        {themeRow('lowlight', 'Contrast Mărit')}
        {themeRow('dark', 'Noapte', true)}
      </Card>

      <div className="eyebrow">Manual utilizare</div>
      <Card style={{ padding: '4px 16px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '13px 0', cursor: 'pointer' }} onClick={() => openModal('manual')}>
          <span style={{ color: 'var(--accent)' }}>
            <Icon name="book" size={18} />
          </span>
          <div style={{ flex: 1, fontSize: 14, fontWeight: 600, color: 'var(--text)' }}>Manual utilizare</div>
          <Icon name="chev" size={16} className="chev" />
        </div>
      </Card>

      <Button variant="ghost" icon="logout" style={{ color: 'var(--danger)', borderColor: 'var(--danger-border)' }} onClick={logout}>
        Deconectare
      </Button>
      <div className="center muted" style={{ fontSize: 12, paddingBottom: 8 }}>
        Tics · Cont organizator v0.1.0
      </div>
    </div>
  );
}

export function LeisureShell() {
  const { tab, venueScreen, toast } = useSession();

  let body: React.ReactNode = null;
  if (tab === 'VenueEvents') {
    body = venueScreen === 'detail' ? <VenueDetail /> : venueScreen === 'ticket' ? <VenueTicketDetail /> : <VenueList />;
  } else if (tab === 'CheckIn') {
    body = (
      <>
        <VenueEventBand />
        <CheckIn />
      </>
    );
  } else if (tab === 'Sales') {
    body = (
      <>
        <VenueEventBand />
        <Sales />
      </>
    );
  } else {
    body = <VenueSettings />;
  }

  return (
    <>
      {body}
      <VenueTabbar />
      <OrgModals />
      {toast ? <Toast message={toast} /> : null}
    </>
  );
}
