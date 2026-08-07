/* =========================================================
   PORTOFEL cashless (§5.7) — sold, top-up (Stripe, fara IAP),
   refund sold, istoric tranzactii, puncte de loialitate.
   ========================================================= */
import { Card, Icon, SectionHead } from '../../design/components';
import { CLIENT_PROFILE, WALLET_TX } from '../../mock/client';
import { useSession } from '../../store/session';

export function ClientWallet() {
  const { showToast, openModal } = useSession();

  return (
    <div className="screen pad stack">
      <div className="eyebrow">Cashless</div>
      <div className="h1" style={{ fontSize: 22, marginTop: -6 }}>
        Portofel
      </div>

      <div
        className="card"
        style={{ padding: 20, background: 'var(--grad)', border: 'none', color: '#fff', boxShadow: 'var(--shadow-btn)' }}
      >
        <div style={{ fontSize: 12.5, opacity: 0.85, fontWeight: 600 }}>Sold cashless</div>
        <div className="tnum" style={{ fontSize: 36, fontWeight: 800, letterSpacing: -1, margin: '4px 0 2px' }}>
          {CLIENT_PROFILE.balance.toFixed(2).replace('.', ',')} lei
        </div>
        <div style={{ fontSize: 12, opacity: 0.8, display: 'flex', alignItems: 'center', gap: 6 }}>
          <Icon name="band" size={14} /> Brățară {CLIENT_PROFILE.wristband}
        </div>
        <div style={{ display: 'flex', gap: 10, marginTop: 16 }}>
          <button
            className="btn"
            style={{ flex: 1, background: 'rgba(255,255,255,.22)', color: '#fff', padding: 12 }}
            onClick={() => openModal('topup')}
            type="button"
          >
            <Icon name="plus" size={18} /> Reîncarcă
          </button>
          <button
            className="btn"
            style={{ flex: 1, background: 'rgba(255,255,255,.14)', color: '#fff', padding: 12 }}
            onClick={() => showToast('Cerere de refund sold trimisă')}
            type="button"
          >
            Refund sold
          </button>
        </div>
      </div>

      <Card pad onClick={() => showToast('Plată cu brățara — scanare NFC în Faza 4')} style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
        <span className="chip-i chip-cyan" style={{ width: 42, height: 42 }}>
          <Icon name="nfc" size={20} />
        </span>
        <div style={{ flex: 1 }}>
          <div style={{ fontSize: 14, fontWeight: 700, color: 'var(--text)' }}>Plătește cashless</div>
          <div style={{ fontSize: 12, color: 'var(--text-3)' }}>Apropie brățara de terminalul vendorului</div>
        </div>
        <Icon name="chev" size={16} className="chev" />
      </Card>

      <SectionHead title="Puncte Tixello" />
      <Card pad>
        <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between' }}>
          <div className="tnum" style={{ fontSize: 28, fontWeight: 800, color: 'var(--text)' }}>
            {CLIENT_PROFILE.points}
          </div>
          <div className="sub">din 2000 pentru următoarea recompensă</div>
        </div>
        <div className="bar" style={{ marginTop: 10 }}>
          <span style={{ width: `${Math.round((CLIENT_PROFILE.points / 2000) * 100)}%` }} />
        </div>
      </Card>

      <SectionHead title="Tranzacții" />
      <Card style={{ padding: '4px 14px' }}>
        {WALLET_TX.map((t, i) => (
          <div
            key={t.label}
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: 12,
              padding: '12px 0',
              borderBottom: i === WALLET_TX.length - 1 ? 'none' : '1px solid var(--border)',
            }}
          >
            <span className={`chip-i ${t.positive ? 'chip-green' : 'chip-accent'}`} style={{ width: 34, height: 34 }}>
              <Icon name={t.icon} size={17} />
            </span>
            <div style={{ flex: 1 }}>
              <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--text)' }}>{t.label}</div>
              <div style={{ fontSize: 11.5, color: 'var(--text-3)' }}>{t.time}</div>
            </div>
            <b style={{ fontSize: 14, color: t.positive ? 'var(--green)' : 'var(--text)' }}>{t.amount}</b>
          </div>
        ))}
      </Card>

      <div
        className="card pad"
        style={{ display: 'flex', gap: 11, background: 'var(--cyan-tint)', borderColor: 'var(--cyan-border)' }}
      >
        <span style={{ color: 'var(--cyan)', flex: '0 0 auto' }}>
          <Icon name="info" size={18} />
        </span>
        <div style={{ fontSize: 12.5, color: 'var(--text-2)', lineHeight: 1.5 }}>
          Portofelul e pentru consum <b>fizic</b> pe locație (mâncare, băutură, acces) → plăți externe
          (Stripe/Apple&nbsp;Pay), în afara IAP.
        </div>
      </div>
    </div>
  );
}
