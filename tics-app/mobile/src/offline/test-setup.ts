/* Shim de `localStorage` pentru rularea testelor in Node.
   `indexedDB` vine din `fake-indexeddb/auto`, importat in fisierul de test. */
if (typeof globalThis.localStorage === 'undefined') {
  const store = new Map<string, string>();
  const mem: Storage = {
    get length() {
      return store.size;
    },
    clear: () => store.clear(),
    getItem: (k: string) => (store.has(k) ? store.get(k)! : null),
    key: (i: number) => [...store.keys()][i] ?? null,
    removeItem: (k: string) => void store.delete(k),
    setItem: (k: string, v: string) => void store.set(k, String(v)),
  };
  Object.defineProperty(globalThis, 'localStorage', { value: mem, writable: false });
}
