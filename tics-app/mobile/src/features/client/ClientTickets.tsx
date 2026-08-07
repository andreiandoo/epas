/* =========================================================
   BILETE (§5.6) — lista bilete active/trecute, detaliu cu QR,
   transfer catre alt user, Apple/Google Wallet.
   ========================================================= */
import { useState } from 'react';
import { Button, Card, Icon, QrPlaceholder, SectionHead, TypeChip } from '../../design/components';
import { EV, MYTIX, VEN } from '../../mock/client';
import { useSession } from '../../store/session';

export function ClientTickets() {
  const { showToast, openModal } = useSession();
  const [tab, setTab] = useState<'active' | 'past'>('active');
  const [openIdx, setOpenIdx] = useState(0);

  const tk = MYTIX[openIdx];
  const ev = EV[tk.ev];
  const venue = VEN[ev.ven];

  return (
    <div className="screen pad stack">
      <div className="h1" style={{ fontSize: 22 }}>
        Biletele mele
      </div>
      <div className="sub" style={{ marginTop: -6 }}>
        Bilete QR individuale
      </div>

      <div style={{ display: 'flex', gap: 8 }}>
        <TypeChip on={tab === 'active'} onClick={() => setTab('active')}>
          Active ({MYTIX.length})
        </TypeChip>
        <TypeChip on={tab === 'past'} onClick={() => setTab('past')}>
          Trecute
        </TypeChip>
      </div>

      {tab === 'past' ? (
        <Card pad style={{ textAlign: 'center', color: 'var(--text-3)', fontSize: 13, padding: 28 }}>
          Nu ai bilete în istoric în contul demo.
        </Card>
      ) : (
        <>
          <div style={{ display: 'flex', gap: 8, overflowX: 'auto', margin: '0 -16px', padding: '0 16px' }}>
            {MYTIX.map((t, i) => (
              <TypeChip key={t.ev} on={openIdx === i} onClick={() => setOpenIdx(i)}>
                {EV[t.ev].s}
              </TypeChip>
            ))}
          </div>

          <Card style={{ overflow: 'hidden' }}>
            <div style={{ height: 96, background: ev.tone, position: 'relative' }}>
              <span style={{ position: 'absolute', bottom: -8, right: 6, fontSize: 58 }}>{ev.g}</span>
              <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg,transparent,rgba(4,3,9,.7))' }} />
              <div style={{ position: 'absolute', left: 16, bottom: 12, color: '#fff' }}>
                <div style={{ fontSize: 16, fontWeight: 700 }}>{ev.t}</div>
                <div style={{ fontSize: 11.5, opacity: 0.85 }}>
                  {ev.d} · {ev.time} · {venue.name}
                </div>
              </div>
            </div>

            <div style={{ padding: 18, textAlign: 'center' }}>
              <QrPlaceholder size={158} />
              <div style={{ marginTop: 14, fontSize: 15, fontWeight: 700, color: 'var(--text)' }}>{tk.passes[0].name}</div>
              <div className="sub" style={{ marginTop: 3 }}>
                {tk.cat} · loc {tk.seat} · {tk.passes.length} {tk.passes.length === 1 ? 'bilet' : 'bilete'}
              </div>
              <div
                style={{
                  fontFamily: 'var(--mono)',
                  fontSize: 12,
                  color: 'var(--text-3)',
                  marginTop: 6,
                  letterSpacing: 1,
                }}
              >
                {tk.passes[0].code}
              </div>

              <div style={{ display: 'flex', gap: 10, marginTop: 16 }}>
                <Button variant="ghost" style={{ flex: 1 }} onClick={() => openModal('transfer')}>
                  Transferă
                </Button>
                <Button variant="primary" style={{ flex: 1 }} onClick={() => showToast('Adaugă în Google Wallet')}>
                  Wallet
                </Button>
              </div>
            </div>
          </Card>

          <SectionHead title={`Bilete individuale (${tk.passes.length})`} />
          {tk.passes.map((p) => (
            <Card key={p.code} pad style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
              <span className={`chip-i ${p.checkedIn ? 'chip-green' : 'chip-accent'}`}>
                <Icon name={p.checkedIn ? 'checkc' : 'ticket'} size={20} />
              </span>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--text)' }}>{p.name}</div>
                <div style={{ fontSize: 11.5, color: 'var(--text-3)', marginTop: 2 }}>
                  {p.checkedIn ? `Validat la intrare · ${p.checkedIn}` : 'Neutilizat — se validează la scanare'}
                </div>
              </div>
              <span className="tag" style={{ background: 'var(--surface-2)', color: 'var(--text-2)', fontFamily: 'var(--mono)', fontSize: 10.5 }}>
                {p.code}
              </span>
            </Card>
          ))}
        </>
      )}
    </div>
  );
}
