/* Punct de intrare — Capacitor + React. Fontul Outfit e inline (fonts.css). */
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './design/fonts.css';
import './design/tokens.css';
import './design/base.css';
import { App } from './app/App';
import { initNative } from './native';

initNative();

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
