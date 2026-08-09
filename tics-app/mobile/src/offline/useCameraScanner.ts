/* =========================================================
   Hook-ul de scanare pentru ecranul de Check-in.

   Leaga camera (camera.ts) de motorul offline (scanEngine) si de starea
   vizuala din store (flash-ul colorat, cardul de rezultat).

   Cand camera sau decodarea lipsesc, `decoding` e false si ecranul ofera
   introducerea manuala — un scanner fara alternativa blocheaza poarta.
   ========================================================= */
import { useCallback, useEffect, useRef, useState } from 'react';
import { cameraSupported, decoderSupported, startScanner, type ScannerHandle } from './camera';
import { scanCode } from './scanEngine';
import type { ScanResult } from './db';
import { useSession } from '../store/session';

/** Rezultatul motorului -> starea vizuala din prototip. */
const TO_STATE: Record<ScanResult, 'valid' | 'duplicate' | 'banned' | 'invalid'> = {
  valid: 'valid',
  duplicate: 'duplicate',
  void: 'banned',
  unknown: 'invalid',
  'wrong-event': 'invalid',
};

export function useCameraScanner(eventId: string | number | undefined) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const handle = useRef<ScannerHandle | null>(null);
  const [live, setLive] = useState(false);
  const [decoding, setDecoding] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [manual, setManual] = useState('');

  const ev = eventId === undefined ? '' : String(eventId);

  /** Trece rezultatul in store, ca ecranul sa reactioneze ca inainte. */
  const apply = useCallback(async (code: string) => {
    if (!ev || !code.trim()) return;
    const out = await scanCode(code.trim(), { eventId: ev });
    const state = TO_STATE[out.result];
    useSession.setState({ scan: state, flash: state });
  }, [ev]);

  useEffect(() => {
    let cancelled = false;

    if (!cameraSupported()) {
      setError('Camera nu este disponibilă — folosește introducerea manuală.');
      setDecoding(false);
      return;
    }

    startScanner({
      video: videoRef.current!,
      onCode: (code) => void apply(code),
      onError: () => {
        /* o eroare de cadru nu opreste scanarea */
      },
    })
      .then((h) => {
        if (cancelled) {
          h.stop();
          return;
        }
        handle.current = h;
        setLive(true);
        setDecoding(h.decoding);
        if (!h.decoding) {
          setError('Decodarea automată nu e disponibilă pe acest dispozitiv — introdu codul manual.');
        }
      })
      .catch((e: Error) => {
        if (cancelled) return;
        // permisiune refuzata sau camera ocupata de alta aplicatie
        setError(
          /denied|NotAllowed/i.test(e.message)
            ? 'Acces la cameră refuzat. Poți introduce codul manual.'
            : 'Camera nu a pornit. Poți introduce codul manual.',
        );
        setDecoding(false);
      });

    return () => {
      cancelled = true;
      handle.current?.stop();
      handle.current = null;
      setLive(false);
    };
  }, [apply]);

  const submitManual = useCallback(() => {
    const code = manual.trim();
    if (!code) return;
    setManual('');
    void apply(code);
  }, [manual, apply]);

  const hint = live
    ? decoding
      ? 'Adu codul QR în cadru'
      : 'Cameră pornită · decodare indisponibilă'
    : decoderSupported()
      ? 'Apasă pentru a simula o scanare'
      : 'Apasă pentru a simula o scanare';

  return { videoRef, live, decoding, error, hint, manual, setManual, submitManual };
}
