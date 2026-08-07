/* =========================================================
   SHELL CLIENT (§5) — app bar + bottom nav + ecrane.
   Bottom nav dupa prototipul client-app.html: 4 iteme + FAB central
   (Acasa · Exploreaza · [FAB → Portofel] · Bilete · Profil).
   ========================================================= */
import { Avatar, Icon, IconButton, Toast } from '../../design/components';
import { useSession, type ClientTab } from '../../store/session';
import { CLIENT_PROFILE } from '../../mock/client';
import { ClientHome } from './ClientHome';
import { ClientExplore } from './ClientExplore';
import { ClientTickets } from './ClientTickets';
import { ClientWallet } from './ClientWallet';
import { ClientProfile } from './ClientProfile';
import { ClientModals } from './ClientModals';

function ClientTabbar() {
  const { clientTab, clientGo } = useSession();
  const item = (name: ClientTab, icon: Parameters<typeof Icon>[0]['name'], label: string) => (
    <button className={`tab ${clientTab === name ? 'active' : ''}`} onClick={() => clientGo(name)} type="button">
      <Icon name={icon} size={22} />
      <span>{label}</span>
    </button>
  );
  return (
    <div className="tabbar client">
      {item('Acasa', 'grid', 'Acasă')}
      {item('Explore', 'search', 'Explorează')}
      <div className="fab" onClick={() => clientGo('Wallet')} role="button" aria-label="Portofel">
        <Icon name="wallet" size={24} />
      </div>
      {item('Tickets', 'ticket', 'Bilete')}
      {item('Profile', 'user', 'Profil')}
    </div>
  );
}

export function ClientShell() {
  const { clientTab, clientGo, toast } = useSession();

  const body =
    clientTab === 'Explore' ? (
      <ClientExplore />
    ) : clientTab === 'Tickets' ? (
      <ClientTickets />
    ) : clientTab === 'Wallet' ? (
      <ClientWallet />
    ) : clientTab === 'Profile' ? (
      <ClientProfile />
    ) : (
      <ClientHome />
    );

  return (
    <>
      <div className="appbar">
        <div className="brand">
          <span className="brand-logo">Tixello</span>
        </div>
        <div className="appbar-right">
          <IconButton icon="bell" badge={2} />
          <IconButton
            onClick={() => clientGo('Profile')}
            style={{ background: 'transparent', border: 'none', width: 38, height: 38 }}
          >
            <Avatar initials={CLIENT_PROFILE.initials} color="purple" size={38} radius={13} />
          </IconButton>
        </div>
      </div>
      {body}
      <ClientTabbar />
      <ClientModals />
      {toast ? <Toast message={toast} /> : null}
    </>
  );
}
