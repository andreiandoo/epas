import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  // Proiectul traieste in interiorul repo-ului epas (Laravel). Fara configul inline
  // de mai jos, Vite urca in arbore si incarca `epas/postcss.config.js` (Tailwind-ul
  // Laravel), care cere `tailwindcss` din node_modules-ul nostru -> build esuat.
  // Nu folosim PostCSS aici: CSS-ul e scris de mana (tokens/base), fara utility classes.
  css: {
    postcss: { plugins: [] },
  },
  build: {
    outDir: 'dist',
    // Fontul Outfit e inline (base64) in fonts.css — nu limita chunk-ul de CSS.
    assetsInlineLimit: 0,
    chunkSizeWarningLimit: 2000,
  },
  server: { port: 5175, host: true },
});
