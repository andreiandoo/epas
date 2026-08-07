/* =========================================================
   Modale client — subsetul necesar scheletului navigabil.
   Restul (§5: lightbox galerie, seat map, group buy, Stay22,
   recenzii, abonament…) se adauga in Faza 1.
   ========================================================= */
import { useState } from 'react';
import { Button, Card, FullModal, Icon, Input, Sheet, TypeChip } from '../../design/components';
import { useSession } from '../../store/session';
import { CLIENT_PROFILE, EV } from '../../mock/client';

const TOPUP_AMOUNTS = [50, 100, 150, 200];

function TopupSheet() {
  const { closeModal, showToast } = useSession();
  const [amount, setAmount] = useState(100);
  return (
    <Sheet title="Încarcă portofelul" onClose={closeModal}>
      <div className="sub" style={{ marginBottom: 14 }}>
        Sold curent: <b style={{ color: 'var(--text)' }}>{CLIENT_PROFILE.balance.toFixed(2)} lei</b>
      </div>
      <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
        {TOPUP_AMOUNTS.map((a) => (
          <TypeChip key={a} on={amount === a} onClick={() => setAmount(a)}>
            {a} lei
          </TypeChip>
        ))}
      </div>
      <Input label="Sumă personalizată" value={String(amount)} onChange={(v) => setAmount(Number(v) || 0)} />
      <div
        className="card pad"
        style={{ margin: '16px 0', display: 'flex', gap: 11, background: 'var(--cyan-tint)', borderColor: 'var(--cyan-border)' }}
      >
        <span style={{ color: 'var(--cyan)', flex: '0 0 auto' }}>
          <Icon name="info" size={18} />
        </span>
        <div style={{ fontSize: 12.5, color: 'var(--text-2)', lineHeight: 1.5 }}>
          Plata se face prin <b>Stripe</b> (card / Apple Pay / Google Pay) — valoare stocată pentru consum fizic pe
          locație, deci în afara IAP.
        </div>
      </div>
      <Button
        variant="primary"
        onClick={() => {
          closeModal();
          showToast(`Reîncărcare ${amount} lei — Stripe PaymentSheet vine în Faza 1`);
        }}
      >
        Plătește {amount} lei
      </Button>
    </Sheet>
  );
}

function TransferSheet() {
  const { closeModal, showToast } = useSession();
  const [email, setEmail] = useState('');
  return (
    <Sheet title="Transferă biletul" onClose={closeModal}>
      <div className="sub" style={{ marginBottom: 14, lineHeight: 1.5 }}>
        Biletul se transferă către alt utilizator Tixello. Codul QR se regenerează pentru noul beneficiar, iar al tău
        devine invalid.
      </div>
      <Input label="Email beneficiar" value={email} onChange={setEmail} placeholder="prieten@exemplu.ro" />
      <Button
        variant="primary"
        style={{ marginTop: 18 }}
        onClick={() => {
          closeModal();
          showToast(email ? `Bilet transferat către ${email}` : 'Completează emailul beneficiarului');
        }}
      >
        Trimite transferul
      </Button>
    </Sheet>
  );
}

function InviteSheet() {
  const { closeModal, showToast } = useSession();
  return (
    <Sheet title="Invită prieteni" onClose={closeModal}>
      <Card pad style={{ textAlign: 'center', marginBottom: 14 }}>
        <div className="sub">Codul tău de afiliere</div>
        <div
          className="tnum"
          style={{ fontSize: 26, fontWeight: 800, letterSpacing: 2, color: 'var(--accent-accent)', marginTop: 6 }}
        >
          ANDREI25
        </div>
      </Card>
      <div className="sub" style={{ lineHeight: 1.5, marginBottom: 16 }}>
        Prietenii primesc 25 lei reducere la prima comandă, iar tu primești 25 lei în portofel după prima lor achiziție.
      </div>
      <Button variant="primary" icon="mail" onClick={() => { closeModal(); showToast('Cod copiat'); }}>
        Copiază & trimite
      </Button>
    </Sheet>
  );
}

function ShortsModal() {
  const { closeModal } = useSession();
  const ids = ['coldplay', 'neversea', 'atv', 'wine'];
  return (
    <div className="fullmodal" style={{ background: '#000' }}>
      <div className="modal-topbar" style={{ background: 'transparent', border: 'none', position: 'absolute', top: 'var(--safe-top)', left: 0, right: 0, zIndex: 2 }}>
        <b style={{ color: '#fff' }}>Shorts</b>
        <button className="iconbtn" onClick={closeModal} style={{ background: 'rgba(255,255,255,.14)', border: 'none', color: '#fff' }} type="button">
          <Icon name="x" size={16} />
        </button>
      </div>
      <div style={{ flex: 1, overflowY: 'auto', scrollSnapType: 'y mandatory' }}>
        {ids.map((id) => (
          <div
            key={id}
            style={{
              height: '100%',
              minHeight: '100%',
              scrollSnapAlign: 'start',
              background: EV[id].tone,
              position: 'relative',
              display: 'grid',
              placeItems: 'center',
            }}
          >
            <span style={{ fontSize: 96 }}>{EV[id].g}</span>
            <div style={{ position: 'absolute', left: 18, right: 18, bottom: 34, color: '#fff' }}>
              <div style={{ fontSize: 19, fontWeight: 700 }}>{EV[id].s}</div>
              <div style={{ fontSize: 12.5, opacity: 0.85, marginTop: 3 }}>
                {EV[id].d} · {EV[id].city} · de la {EV[id].from} lei
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function ManualModal() {
  const { closeModal } = useSession();
  return (
    <FullModal title="Manual utilizare" onClose={closeModal}>
      <div className="sub" style={{ lineHeight: 1.6 }}>
        Ghidul complet al aplicației de client — cum cumperi bilete, cum folosești portofelul cashless, cum transferi un
        bilet și cum funcționează punctele Tixello.
      </div>
      <Card pad style={{ marginTop: 14, color: 'var(--text-3)', fontSize: 13 }}>
        Conținutul capitolelor se adaugă în Faza 1.
      </Card>
    </FullModal>
  );
}

export function ClientModals() {
  const modal = useSession((s) => s.modal);
  if (!modal) return null;
  if (modal === 'topup') return <TopupSheet />;
  if (modal === 'transfer') return <TransferSheet />;
  if (modal === 'invite') return <InviteSheet />;
  if (modal === 'shorts') return <ShortsModal />;
  if (modal === 'manual') return <ManualModal />;
  return null;
}
