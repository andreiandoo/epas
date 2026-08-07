/* Punct de intrare — Capacitor + React. Fontul Outfit e inline (fonts.css). */
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './design/fonts.css';
import './design/tokens.css';
import './design/base.css';
// CSS-ul de client, portat verbatim din prototip si scopat sub .app-client.
// Se incarca dupa base.css: fiind scopat, nu poate suprascrie organizatorul.
import './design/client.css';
import { App } from './app/App';
import { initNative } from './native';
import { initOta } from './ota';

initNative();

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
);

// OTA: `notifyAppReady()` trebuie apelat DUPA ce primul render a reusit, altfel
// pluginul considera bundle-ul stricat si face rollback (appReadyTimeout = 10s).
requestAnimationFrame(() => {
  void initOta();
});
