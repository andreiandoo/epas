/* =========================================================
   SHELL ORGANIZATOR — navigatorul STANDARD (§6):
   Panou · Scanare · Vanzare · Rapoarte · Setari.
   Staff nu are acces la Rapoarte.
   ========================================================= */
import { Icon, Toast, type IconName } from '../../design/components';
import { useSession, type OrgTab } from '../../store/session';
import { EventSelector, OrgAppbar, ShiftBar } from './OrgChrome';
import { Dashboard } from './Dashboard';
import { CheckIn } from './CheckIn';
import { Sales } from './Sales';
import { Reports } from './Reports';
import { Settings } from './Settings';
import { OrgModals } from './OrgModals';

function OrgTabbar() {
  const { tab, go, role } = useSession();
  const item = (name: OrgTab, icon: IconName, label: string, disabled?: boolean) => (
    <button
      className={`tab ${tab === name ? 'active' : ''} ${disabled ? 'disabled' : ''}`}
      onClick={() => go(name)}
      type="button"
    >
      <Icon name={icon} size={22} />
      <span>{label}</span>
    </button>
  );
  return (
    <div className="tabbar">
      {item('Dashboard', 'grid', 'Panou')}
      {item('CheckIn', 'scan', 'Scanare')}
      {item('Sales', 'cart', 'Vânzare')}
      {item('Reports', 'chart', 'Rapoarte', role !== 'admin')}
      {item('Settings', 'cog', 'Setări')}
    </div>
  );
}

export function OrgShell() {
  const { tab, toast } = useSession();

  const body =
    tab === 'CheckIn' ? (
      <CheckIn />
    ) : tab === 'Sales' ? (
      <Sales />
    ) : tab === 'Reports' ? (
      <Reports />
    ) : tab === 'Settings' ? (
      <Settings />
    ) : (
      <Dashboard />
    );

  return (
    <>
      <OrgAppbar />
      {tab === 'Dashboard' ? <EventSelector /> : null}
      <ShiftBar />
      {body}
      <OrgTabbar />
      <OrgModals />
      {toast ? <Toast message={toast} /> : null}
    </>
  );
}
