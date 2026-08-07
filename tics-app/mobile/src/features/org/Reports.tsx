/* =========================================================
   RAPOARTE (§6.4) — doar admin. Rata check-in, total vandute,
   ora de varf, performanta pe tip bilet, distributie orara,
   card „Decontare & payout" (comision Tixello 3%, Stripe Connect T+3),
   export CSV.
   ========================================================= */
import { Button, Card, Icon, Progress, SectionHead, money } from '../../design/components';
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
      <div className="h1" style={{ fontSize: 20 }}>
        Rapoarte
      </div>
      <div className="sub" style={{ marginTop: -6 }}>
        {c.event} · {c.date}
      </div>

      <Card pad>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 }}>
          <div style={{ fontSize: 14, fontWeight: 700, color: 'var(--text)' }}>Rata de check-in</div>
          <span className="tag tag-mgr">{checkinRate}%</span>
        </div>
        <div className="tnum" style={{ fontSize: 28, fontWeight: 800, color: 'var(--text)' }}>
          {c.checkedin.toLocaleString('ro-RO')}
          <span style={{ fontSize: 17, fontWeight: 500, color: 'var(--text-3)' }}> / {c.sold.toLocaleString('ro-RO')} vândute</span>
        </div>
        <div style={{ marginTop: 12 }}>
          <Progress pct={checkinRate} tone="green" />
        </div>
      </Card>

      <div className="grid2">
        <Card pad>
          <div className="label" style={{ fontSize: 12.5, color: 'var(--text-2)', fontWeight: 600 }}>
            Total vândute
          </div>
          <div className="tnum" style={{ fontSize: 24, fontWeight: 800, color: 'var(--text)', marginTop: 4 }}>
            {c.sold.toLocaleString('ro-RO')}
          </div>
        </Card>
        <Card pad>
          <div className="label" style={{ fontSize: 12.5, color: 'var(--text-2)', fontWeight: 600 }}>
            Ora de vârf
          </div>
          <div className="tnum" style={{ fontSize: 24, fontWeight: 800, color: 'var(--text)', marginTop: 4 }}>
            20:00
          </div>
        </Card>
      </div>

      <SectionHead title="Performanță pe tip bilet" />
      <Card pad>
        {c.tt.map((t, i) => (
          <div key={t.n} style={{ marginBottom: i === c.tt.length - 1 ? 0 : 14 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, marginBottom: 5 }}>
              <span style={{ color: 'var(--text)', fontWeight: 600 }}>{t.n}</span>
              <span className="tnum" style={{ color: 'var(--text-2)', fontWeight: 700 }}>
                {t.s} / {t.q}
              </span>
            </div>
            <div className="bar">
              <span style={{ width: `${Math.round((t.s / t.q) * 100)}%`, background: t.c }} />
            </div>
            <div style={{ fontSize: 11.5, color: 'var(--text-3)', marginTop: 4 }}>{money(t.s * t.p)} încasat</div>
          </div>
        ))}
      </Card>

      <SectionHead title="Distribuție orară" />
      <Card pad>
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

      {/* Decontare & payout */}
      <SectionHead title="Decontare & payout" />
      <Card pad style={{ borderColor: 'var(--accent-border)', background: 'linear-gradient(160deg,var(--accent-tint),var(--surface))' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '9px 0', borderBottom: '1px solid var(--border)' }}>
          <span className="muted" style={{ fontSize: 13.5 }}>
            Total încasat
          </span>
          <b style={{ fontSize: 13.5, color: 'var(--text)' }}>{money(c.revenue)}</b>
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '9px 0', borderBottom: '1px solid var(--border)' }}>
          <span className="muted" style={{ fontSize: 13.5 }}>
            Comision Tixello {COMMISSION_PCT}%
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
          Payout Stripe Connect · T+3 zile lucrătoare
        </div>
      </Card>

      <Button variant="ghost" icon="download" onClick={() => openModal('export')}>
        Export CSV
      </Button>
    </div>
  );
}
