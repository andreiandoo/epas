/* =========================================================
   Suprafata nativa (Capacitor) — se schimba rar; doar aici e
   nevoie de release in store (§2). UI-ul se livreaza prin OTA.
   Deocamdata: status bar, back button hardware, network status.
   Urmeaza (Faza 2+): camera/QR, NFC, push, secure storage, SQLite,
   Stripe Terminal / Tap to Pay.
   ========================================================= */
import { Capacitor } from '@capacitor/core';
import { App as CapApp } from '@capacitor/app';
import { StatusBar, Style } from '@capacitor/status-bar';
import { Network } from '@capacitor/network';
import { useSession } from './store/session';

export function initNative() {
  if (!Capacitor.isNativePlatform()) return;

  /* bara de status — dark-first, se reactualizeaza la schimbarea temei */
  StatusBar.setStyle({ style: Style.Dark }).catch(() => {});
  StatusBar.setBackgroundColor({ color: '#151122' }).catch(() => {});

  /* butonul hardware Back: inchide modalul, apoi urca in ierarhie */
  CapApp.addListener('backButton', ({ canGoBack }) => {
    const s = useSession.getState();
    if (s.modal) {
      s.closeModal();
      return;
    }
    if (s.mode === 'organizer' && s.account === 'venue' && s.venueScreen !== 'list') {
      s.venueBack();
      return;
    }
    if (s.mode === 'organizer' && s.sale !== 'select') {
      s.setSale('select');
      return;
    }
    if (s.authed && s.mode !== 'chooser' && s.properties.length > 1) {
      s.goChooser();
      return;
    }
    if (!canGoBack) CapApp.exitApp();
  }).catch(() => {});

  /* pastila Live/Offline din app bar reflecta reteaua reala */
  Network.getStatus()
    .then((st) => useSession.setState({ online: st.connected }))
    .catch(() => {});
  Network.addListener('networkStatusChange', (st) => {
    useSession.setState({ online: st.connected });
  }).catch(() => {});
}
