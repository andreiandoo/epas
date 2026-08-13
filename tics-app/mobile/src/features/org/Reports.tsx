/* =========================================================
   RAPOARTE (§6.4) — doar admin. Rata check-in, total vandute,
   ora de varf, performanta pe tip bilet, distributie orara,
   card „Decontare & payout" (comision tics 3%, Stripe Connect T+3),
   export CSV.
   ========================================================= */
import { Button, Card, Icon, money } from '../../design/components';
import { HOURLABELS, HOURLY } from '../../mock/org';
import { useSession } from '../../store/session';
import { useCtx } from './OrgChrome';

const COMMISSION_PCT = 3;

export function Reports() {
  const { openModal } = useSession();
  const c = useCtx();

  const checkinRate = Math.round((c.checkedin / c.sold) * 100);
  const commission = Math.round((c.revenue * COMMISSION_PCT) / 100);
  const net = c.revenue - commission;
  const maxHour = Math.max(...HOURLY);

  return (
    <div className="screen pad stack">
      <div>
        <div className="h1">Rapoarte</div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 3 }}>
          <span style={{ width: 7, height: 7, borderRadius: '50%', background: 'var(--green)' }} />
          <span className="sub" style={{ color: 'var(--green)' }}>
            Actualizat acum
          </span>
        </div>
      </div>

      {/* selectorul de eveniment trecut — lipsea din port */}
      <div className="row" style={{ cursor: 'pointer' }} onClick={() => openModal('events')}>
        <Icon name="clock" size={18} className="chev" />
        <div className="grow">
          <div className="name">Eveniment trecut</div>
        </div>
        <span className="muted" style={{ fontSize: 13 }}>
          Selectează
        </span>
        <Icon name="chev" size={16} className="chev" />
      </div>

      <Card pad>
        <div className="label" style={{ fontSize: 13, color: 'var(--text-2)', fontWeight: 600 }}>
          Rata Check-in
        </div>
        <div className="tnum" style={{ fontSize: 34, fontWeight: 800, letterSpacing: -1, color: 'var(--text)' }}>
          {checkinRate}
          <span style={{ fontSize: 20, color: 'var(--text-3)' }}>%</span>
        </div>
        <svg viewBox="0 0 300 40" style={{ width: '100%', height: 38, marginTop: 6 }}>
          <polyline
            points="0,30 40,26 80,28 120,18 160,22 200,12 240,16 300,8"
            fill="none"
            stroke="var(--accent)"
            strokeWidth="2.5"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </svg>
      </Card>

      <div className="grid2">
        <Card pad>
          <div className="label" style={{ fontSize: 12.5, color: 'var(--text-2)', fontWeight: 600 }}>
            Total Vândute
          </div>
          <div className="tnum" style={{ fontSize: 24, fontWeight: 800, color: 'var(--text)', marginTop: 4 }}>
            {c.sold.toLocaleString('ro-RO')}
          </div>
        </Card>
        <Card pad>
          <div className="label" style={{ fontSize: 12.5, color: 'var(--text-2)', fontWeight: 600 }}>
            Ora de Vârf
          </div>
          <div className="tnum" style={{ fontSize: 24, fontWeight: 800, color: 'var(--text)', marginTop: 4 }}>
            20:00
          </div>
        </Card>
      </div>

      {/* Prototipul are DOUA carduri distincte aici: cate au intrat (check-in)
          si cat s-a incasat. Portul le comprimase intr-unul singur, si arata
          vandute/cota in loc de rata de intrare — alt indicator. */}
      <Card pad>
        <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 14, color: 'var(--text)' }}>
          Performanța pe tip bilet
        </div>
        {c.tt.map((t) => {
          const pct = Math.round((t.ci / t.s) * 100) || 0;
          return (
            <div key={t.n} style={{ marginBottom: 12 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, marginBottom: 6 }}>
                <span style={{ fontWeight: 600, color: 'var(--text)' }}>{t.n}</span>
                <span>
                  <b style={{ color: 'var(--accent)' }}>{pct}%</b>{' '}
                  <span style={{ color: 'var(--text-3)' }}>{t.ci} intrați</span>
                </span>
              </div>
              <div className="bar">
                <span style={{ width: `${pct}%` }} />
              </div>
            </div>
          );
        })}
      </Card>

      <Card pad>
        <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 14, color: 'var(--text)' }}>Detalii venituri</div>
        {c.tt.map((t) => {
          const r = t.p * t.s;
          const mx = Math.max(...c.tt.map((x) => x.p * x.s));
          return (
            <div key={t.n} style={{ marginBottom: 10 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, marginBottom: 5 }}>
                <span style={{ display: 'flex', alignItems: 'center', gap: 7, color: 'var(--text)' }}>
                  <span style={{ width: 9, height: 9, borderRadius: 3, background: t.c }} />
                  {t.n}
                </span>
                <b className="tnum" style={{ color: 'var(--text)' }}>
                  {money(r)}
                </b>
              </div>
              <div className="bar">
                <span style={{ width: `${Math.round((r / mx) * 100) || 2}%`, background: t.c }} />
              </div>
            </div>
          );
        })}
      </Card>

      <Card pad>
        <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 14, color: 'var(--text)' }}>Distribuție orară</div>
        <div style={{ display: 'flex', alignItems: 'flex-end', gap: 6, height: 110 }}>
          {HOURLY.map((h, i) => (
            <div key={HOURLABELS[i]} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6 }}>
              <div
                style={{
                  width: '100%',
                  height: `${(h / maxHour) * 84}px`,
                  background: h === maxHour ? 'var(--accent)' : 'var(--accent-tint-2)',
                  borderRadius: 6,
                }}
              />
              <span style={{ fontSize: 10.5, color: 'var(--text-3)' }}>{HOURLABELS[i]}</span>
            </div>
          ))}
        </div>
      </Card>

      <Card pad style={{ borderColor: 'var(--accent-border)', background: 'linear-gradient(160deg,var(--accent-tint),var(--surface))' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 12 }}>
          <Icon name="wallet" size={18} />
          <div style={{ fontSize: 15, fontWeight: 700, color: 'var(--text)' }}>Decontare & payout</div>
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '9px 0', borderBottom: '1px solid var(--border)' }}>
          <span className="muted" style={{ fontSize: 13.5 }}>
            Total încasat
          </span>
          <b style={{ fontSize: 13.5, color: 'var(--text)' }}>{money(c.revenue)}</b>
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '9px 0', borderBottom: '1px solid var(--border)' }}>
          <span className="muted" style={{ fontSize: 13.5 }}>
            Comision tics ({COMMISSION_PCT}%)
          </span>
          <b style={{ fontSize: 13.5, color: 'var(--danger)' }}>− {money(commission)}</b>
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '13px 0', alignItems: 'baseline' }}>
          <span style={{ fontSize: 15, fontWeight: 700, color: 'var(--text)' }}>Net de decontat</span>
          <b className="tnum" style={{ fontSize: 22, fontWeight: 800, color: 'var(--green)' }}>
            {money(net)}
          </b>
        </div>
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: 8,
            fontSize: 12,
            color: 'var(--text-2)',
            background: 'var(--surface)',
            borderRadius: 10,
            padding: '10px 12px',
          }}
        >
          <Icon name="card" size={15} />
          Payout programat prin Stripe Connect · T+3 zile lucrătoare
        </div>
      </Card>

      <Button variant="ghost" icon="download" onClick={() => openModal('export')}>
        Exportă Raport (CSV)
      </Button>
    </div>
  );
}
