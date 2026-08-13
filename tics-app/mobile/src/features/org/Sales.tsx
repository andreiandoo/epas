/* =========================================================
   VANZARE / POS (§6.3) — lista tipuri de bilete, cos cu +/-,
   comision 3%, metode de plata (Numerar / Card POS / NFC),
   confirmare → ecran de succes cu QR.
   Plata reala (Stripe Terminal / Tap to Pay) intra in Faza 2.
   ========================================================= */
import { Button, Card, Icon, QrPlaceholder, SectionHead, money } from '../../design/components';
import { useSession } from '../../store/session';
import { useCtx } from './OrgChrome';

const COMMISSION = 0.03;

function SaleRow({ m, desc, time, amt, last }: { m: 'cash' | 'card'; desc: string; time: string; amt: string; last?: boolean }) {
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
      <span className={`chip-i ${m === 'cash' ? 'chip-green' : 'chip-cyan'}`} style={{ width: 34, height: 34 }}>
        <Icon name={m} size={17} />
      </span>
      <div style={{ flex: 1 }}>
        <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--text)' }}>{desc}</div>
        <div style={{ fontSize: 11.5, color: 'var(--text-3)' }}>{time}</div>
      </div>
      <b style={{ fontSize: 14, color: 'var(--text)' }}>{amt}</b>
    </div>
  );
}

export function Sales() {
  const { sale, setSale, cart, addCart, subCart, clearCart, openModal, showToast, set } = useSession();
  const c = useCtx();

  const count = Object.values(cart).reduce((a, b) => a + b, 0);
  const subtotal = Object.entries(cart).reduce((a, [i, q]) => a + c.tt[Number(i)].p * q, 0);
  const fee = Math.round(subtotal * COMMISSION);
  const total = subtotal + fee;

  /* ---------- ecran de succes ---------- */
  if (sale === 'success') {
    return (
      <div className="screen pad stack">
        <Card pad style={{ textAlign: 'center', borderColor: 'var(--green-border)', background: 'var(--green-tint)' }}>
          <div
            style={{
              width: 64,
              height: 64,
              borderRadius: '50%',
              background: 'var(--green)',
              color: '#fff',
              display: 'grid',
              placeItems: 'center',
              margin: '4px auto 12px',
            }}
          >
            <Icon name="check" size={32} />
          </div>
          <div className="h1" style={{ fontSize: 20 }}>
            Plată reușită
          </div>
          <div className="sub" style={{ marginTop: 4 }}>
            {count} {count === 1 ? 'bilet emis' : 'bilete emise'} · {money(total)}
          </div>
        </Card>

        <Card pad style={{ textAlign: 'center' }}>
          <QrPlaceholder size={150} />
          <div style={{ fontFamily: 'var(--mono)', fontSize: 12, color: 'var(--text-3)', marginTop: 12, letterSpacing: 1 }}>
            TIX-POS-{String(Math.floor(Math.random() * 90000) + 10000)}
          </div>
        </Card>

        <Button variant="ghost" icon="mail" onClick={() => openModal('emailcapture')}>
          Trimite pe email
        </Button>
        {set.printer ? (
          <Button variant="ghost" icon="camera" onClick={() => openModal('printbadge')}>
            Printează bon
          </Button>
        ) : null}
        <Button
          variant="primary"
          onClick={() => {
            clearCart();
            setSale('select');
            showToast('Vânzare finalizată · bilete emise');
          }}
        >
          Finalizează
        </Button>
      </div>
    );
  }

  /* ---------- cos ---------- */
  if (sale === 'cart') {
    return (
      <div className="screen pad stack">
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          <button className="iconbtn" onClick={() => setSale('select')} type="button">
            <Icon name="back" size={18} />
          </button>
          <div className="h1" style={{ fontSize: 20 }}>
            Coș ({count})
          </div>
        </div>

        <Card style={{ padding: '4px 16px' }}>
          {Object.entries(cart).map(([i, q], idx, arr) => {
            const t = c.tt[Number(i)];
            return (
              <div
                key={t.n}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: 12,
                  padding: '13px 0',
                  borderBottom: idx === arr.length - 1 ? 'none' : '1px solid var(--border)',
                }}
              >
                <span style={{ width: 4, height: 34, borderRadius: 2, background: t.c }} />
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--text)' }}>{t.n}</div>
                  <div style={{ fontSize: 12, color: 'var(--text-3)' }}>{money(t.p)} / buc</div>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                  <button className="iconbtn" style={{ width: 30, height: 30 }} onClick={() => subCart(Number(i))} type="button">
                    <Icon name="minus" size={15} />
                  </button>
                  <b className="tnum" style={{ minWidth: 18, textAlign: 'center', color: 'var(--text)' }}>
                    {q}
                  </b>
                  <button className="iconbtn" style={{ width: 30, height: 30 }} onClick={() => addCart(Number(i))} type="button">
                    <Icon name="plus" size={15} />
                  </button>
                </div>
              </div>
            );
          })}
        </Card>

        <Card style={{ padding: '4px 16px' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', padding: '11px 0', borderBottom: '1px solid var(--border)' }}>
            <span className="muted" style={{ fontSize: 13.5 }}>
              Subtotal
            </span>
            <b style={{ fontSize: 13.5, color: 'var(--text)' }}>{money(subtotal)}</b>
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', padding: '11px 0', borderBottom: '1px solid var(--border)' }}>
            <span className="muted" style={{ fontSize: 13.5 }}>
              Comision Tics 3%
            </span>
            <b style={{ fontSize: 13.5, color: 'var(--text)' }}>{money(fee)}</b>
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', padding: '13px 0', alignItems: 'baseline' }}>
            <span style={{ fontSize: 15, fontWeight: 700, color: 'var(--text)' }}>Total</span>
            <b className="tnum" style={{ fontSize: 22, fontWeight: 800, color: 'var(--accent-accent)' }}>
              {money(total)}
            </b>
          </div>
        </Card>

        <SectionHead title="Metodă de plată" />
        <Button variant="green" icon="cash" onClick={() => openModal('payconfirm', 'cash')}>
          Numerar
        </Button>
        <Button variant="ghost" icon="card" onClick={() => openModal('payconfirm', 'card')}>
          Card / POS
        </Button>
        {set.nfc ? (
          <Button variant="ghost" icon="nfc" onClick={() => openModal('payconfirm', 'nfc')}>
            Card prin NFC (Tap to Pay)
          </Button>
        ) : null}
      </div>
    );
  }

  /* ---------- selectie tipuri de bilete ---------- */
  return (
    <div className="screen pad stack">
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
        <div className="h1" style={{ fontSize: 20 }}>
          Vânzare la ușă
        </div>
        {c.seated ? (
          <button className="typechip" onClick={() => openModal('seatmap')} type="button">
            <Icon name="grid" size={13} /> Alege locul
          </button>
        ) : null}
      </div>

      {/* randul catre lista de bilete a evenimentului — lipsea din port */}
      <div className="row" style={{ cursor: 'pointer' }} onClick={() => openModal('ticketlist')}>
        <Icon name="list" size={18} className="chev" />
        <div className="grow">
          <div className="name">Bilete eveniment</div>
          <div className="meta">Istoric vânzări &amp; check-in inline</div>
        </div>
        <Icon name="chev" size={16} className="chev" />
      </div>
      <div className="h2" style={{ marginTop: 4 }}>
        Selectează Bilete
      </div>

      {c.tt.map((t, i) => {
        const soldOut = t.s >= t.q;
        return (
          <Card key={t.n} style={{ padding: 0, overflow: 'hidden', display: 'flex' }}>
            <span style={{ width: 5, background: t.c, flex: '0 0 auto' }} />
            <div style={{ flex: 1, padding: '13px 14px', display: 'flex', alignItems: 'center', gap: 12 }}>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 14.5, fontWeight: 700, color: 'var(--text)' }}>{t.n}</div>
                <div style={{ fontSize: 12, color: 'var(--text-3)', marginTop: 2 }}>
                  {money(t.p)} · {soldOut ? 'epuizat' : `${t.q - t.s} disponibile`}
                </div>
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                {cart[i] ? (
                  <>
                    <button className="iconbtn" style={{ width: 30, height: 30 }} onClick={() => subCart(i)} type="button">
                      <Icon name="minus" size={15} />
                    </button>
                    <b className="tnum" style={{ minWidth: 16, textAlign: 'center', color: 'var(--text)' }}>
                      {cart[i]}
                    </b>
                  </>
                ) : null}
                <button
                  className="iconbtn"
                  style={{ width: 30, height: 30, opacity: soldOut ? 0.4 : 1 }}
                  onClick={() => !soldOut && addCart(i)}
                  type="button"
                >
                  <Icon name="plus" size={15} />
                </button>
              </div>
            </div>
          </Card>
        );
      })}

      {/* Prototipul are aici "Vânzări azi" cu doua randuri, nu o lista de
          "vânzări recente" cu trei; iar accesul la lista de bilete se face din
          randul de sus, nu din butoane. */}
      <div>
        {/* section-head cu totalul zilei, ca in prototip */}
        <div className="section-head">
          <h3>Vânzări azi</h3>
          <span className="tag" style={{ background: 'var(--accent-tint)', color: 'var(--accent-accent)' }}>
            {money(1240)}
          </span>
        </div>
        <Card style={{ padding: '6px 14px' }}>
          <SaleRow m="cash" desc="2× Abonament General" time="acum 3 min" amt={money(300)} />
          <SaleRow m="card" desc="1× VIP" time="acum 11 min" amt={money(350)} last />
        </Card>
      </div>

      {count > 0 ? (
        <Button variant="primary" icon="cart" onClick={() => setSale('cart')}>
          Coș · {count} {count === 1 ? 'bilet' : 'bilete'} · {money(total)}
        </Button>
      ) : null}
    </div>
  );
}
