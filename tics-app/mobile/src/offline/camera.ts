/* =========================================================
   CAMERA DE SCANARE

   Fara plugin nativ: WebView-ul Capacitor e Chrome, deci are `getUserMedia`
   pentru fluxul video si `BarcodeDetector` pentru decodare. Acelasi rationament
   ca la IndexedDB — o cale mai putin de esec la build, si se poate testa in
   browser.

   Daca la o poarta reala se dovedeste ca decodarea e prea lenta sau prea slaba
   pe lumina proasta, upgrade-ul e `@capacitor-mlkit/barcode-scanning`:
   interfata de mai jos (start/stop/onCode) ramane, se schimba doar `decode`.

   `BarcodeDetector` NU exista peste tot. Cand lipseste, `supported` e false si
   ecranul trebuie sa ofere introducerea manuala a codului — un scanner care
   nu are alternativa manuala blocheaza poarta.
   ========================================================= */

type DetectedBarcode = { rawValue: string };
type BarcodeDetectorLike = { detect(source: CanvasImageSource): Promise<DetectedBarcode[]> };
type BarcodeDetectorCtor = new (opts?: { formats?: string[] }) => BarcodeDetectorLike;

const ctor = (): BarcodeDetectorCtor | null =>
  (globalThis as unknown as { BarcodeDetector?: BarcodeDetectorCtor }).BarcodeDetector ?? null;

/** Decodarea in WebView e disponibila? */
export const decoderSupported = (): boolean => ctor() !== null;

/** Camera e disponibila? (lipseste in browsere fara permisiuni sau pe desktop fara webcam) */
export const cameraSupported = (): boolean =>
  typeof navigator !== 'undefined' && !!navigator.mediaDevices?.getUserMedia;

export type ScannerHandle = {
  stop: () => void;
  /** true cand decodarea automata merge; false = doar introducere manuala */
  decoding: boolean;
};

export type StartOptions = {
  video: HTMLVideoElement;
  onCode: (code: string) => void;
  onError?: (e: Error) => void;
  /** cat asteptam intre doua citiri ale aceluiasi cod, ms */
  debounceMs?: number;
};

/**
 * Porneste camera si livreaza coduri prin `onCode`.
 *
 * Debounce pe COD, nu pe timp: la o poarta, camera vede acelasi bilet zeci de
 * cadre la rand. Fara asta, un singur bilet ar genera zeci de scanuri in coada.
 */
export async function startScanner(opts: StartOptions): Promise<ScannerHandle> {
  const { video, onCode, onError } = opts;
  const debounceMs = opts.debounceMs ?? 2500;

  if (!cameraSupported()) {
    throw new Error('Camera nu este disponibilă pe acest dispozitiv.');
  }

  const stream = await navigator.mediaDevices.getUserMedia({
    video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
    audio: false,
  });

  video.srcObject = stream;
  video.setAttribute('playsinline', 'true');
  video.muted = true;
  await video.play().catch(() => {
    /* unele WebView-uri redau si fara play() explicit */
  });

  const D = ctor();
  let stopped = false;
  let raf = 0;
  const lastSeen = new Map<string, number>();

  const stop = () => {
    if (stopped) return;
    stopped = true;
    if (raf) cancelAnimationFrame(raf);
    for (const t of stream.getTracks()) t.stop();
    video.srcObject = null;
  };

  if (!D) {
    // fara decodare: lasam imaginea pornita (operatorul vede ca "merge camera")
    // dar codurile vor veni din introducerea manuala
    return { stop, decoding: false };
  }

  const detector = new D({ formats: ['qr_code', 'code_128', 'ean_13', 'code_39', 'pdf417'] });
  let busy = false;

  const tick = async () => {
    if (stopped) return;
    raf = requestAnimationFrame(() => void tick());

    // sarim cadrele cat timp o detectie e in curs: altfel se aduna promisiuni
    if (busy || video.readyState < 2) return;
    busy = true;
    try {
      const found = await detector.detect(video);
      const now = Date.now();
      for (const b of found) {
        const code = (b.rawValue ?? '').trim();
        if (!code) continue;
        const last = lastSeen.get(code) ?? 0;
        if (now - last < debounceMs) continue;
        lastSeen.set(code, now);
        onCode(code);
      }
    } catch (e) {
      onError?.(e instanceof Error ? e : new Error(String(e)));
    } finally {
      busy = false;
    }
  };

  void tick();
  return { stop, decoding: true };
}
