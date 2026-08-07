/* =========================================================
   Root — un singur binar, un singur login (§2).
   LOGIN → (>1 proprietate?) CHOOSER → CLIENT SHELL / ORGANIZER SHELL.
   ========================================================= */
import { useEffect } from 'react';
import { IconSprite } from '../design/icons/Icon';
import { useSession } from '../store/session';
import { LoginScreen } from '../features/auth/LoginScreen';
import { ChooserScreen } from '../features/identity/ChooserScreen';
import { ClientRoot } from '../features/client/ClientRoot';
import { OrgShell } from '../features/org/OrgShell';
import { LeisureShell } from '../features/leisure/LeisureShell';

export function App() {
  const { authed, mode, account, appTheme } = useSession();

  /* tema se aplica pe containerul aplicatiei + pe <html> pentru bara de status */
  useEffect(() => {
    document.documentElement.setAttribute('data-app-theme', appTheme);
    const bg = appTheme === 'light' ? '#F4F2FB' : appTheme === 'lowlight' ? '#0A0711' : '#0A0711';
    document.body.style.background = bg;
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', bg);
  }, [appTheme]);

  let body: React.ReactNode;
  if (!authed) body = <LoginScreen />;
  else if (mode === 'chooser') body = <ChooserScreen />;
  else if (mode === 'client') body = <ClientRoot />;
  else body = account === 'venue' ? <LeisureShell /> : <OrgShell />;

  return (
    <div className="app" data-app-theme={appTheme}>
      <IconSprite />
      {body}
    </div>
  );
}
