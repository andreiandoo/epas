import { defineConfig } from 'vitest/config';

/**
 * Motorul offline se testeaza in Node, nu in browser, dar are nevoie de
 * `localStorage` (ancora ceasului, id-ul de dispozitiv) si de `indexedDB`
 * (inventar + coada). Pe cel din urma il aduce `fake-indexeddb/auto`, importat
 * din test; pentru primul e suficient un shim in memorie.
 */
export default defineConfig({
  /* Fara asta, Vite urca in arbore si gaseste `epas/postcss.config.js`, care
     cere tailwind — acelasi motiv ca in vite.config.ts. */
  css: { postcss: { plugins: [] } },
  test: {
    include: ['src/**/*.test.ts'],
    setupFiles: ['./src/offline/test-setup.ts'],
  },
});
