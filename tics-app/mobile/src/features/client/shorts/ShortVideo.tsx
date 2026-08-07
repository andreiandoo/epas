/* =========================================================
   Player HLS pentru un short.

   iOS (WKWebView / Safari) reda HLS nativ — atunci hls.js e inutil si chiar
   daunator. Pe Android WebView nu exista suport nativ, deci incarcam hls.js.
   Verdictul se ia per-dispozitiv, nu prin sniffing de user agent.

   Reguli de redare (docs/plans/shorts.md §7):
     - autoplay MUTED la intrarea in viewport, pauza la iesire;
     - un singur short cu sunet la un moment dat (gestionat de parinte);
     - doar short-ul activ si vecinii lui se monteaza — restul nu tin <video>;
     - `prefers-reduced-motion` => fara autoplay, poster + buton de play (D10).
   ========================================================= */
import { useEffect, useRef, useState } from 'react';

type Props = {
  src: string;
  poster: string | null;
  /** Short-ul e cel din viewport. */
  active: boolean;
  muted: boolean;
  /** Doar preincarca (vecinul urmator) — nu porni redarea. */
  preloadOnly?: boolean;
  /** Utilizatorul a cerut explicit redarea (folosit cand autoplay e dezactivat). */
  forcePlay?: boolean;
  onProgress?: (watchedMs: number, ratio: number) => void;
  onComplete?: () => void;
  onTap?: () => void;
};

/** Respecta setarea de sistem — nu autoplay daca utilizatorul a cerut mai putina miscare. */
function prefersReducedMotion(): boolean {
  return typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
}

export function ShortVideo({
  src,
  poster,
  active,
  muted,
  preloadOnly = false,
  forcePlay = false,
  onProgress,
  onComplete,
  onTap,
}: Props) {
  const videoRef = useRef<HTMLVideoElement | null>(null);
  const hlsRef = useRef<{ destroy: () => void } | null>(null);
  const [reducedMotion] = useState(prefersReducedMotion);
  const [needsTapToPlay, setNeedsTapToPlay] = useState(false);

  /* ---------- atasarea sursei ---------- */
  useEffect(() => {
    const video = videoRef.current;
    if (!video || !src) return;

    let cancelled = false;

    const canPlayNatively = video.canPlayType('application/vnd.apple.mpegurl') !== '';

    if (canPlayNatively || !src.includes('.m3u8')) {
      video.src = src;

      return () => {
        video.removeAttribute('src');
        video.load();
      };
    }

    // Import dinamic: hls.js nu trebuie sa intre in bundle-ul initial al
    // app-ului, doar in chunk-ul feed-ului de shorts.
    void import('hls.js').then(({ default: Hls }) => {
      if (cancelled || !videoRef.current) return;
      if (!Hls.isSupported()) return;

      const hls = new Hls({
        // Feed vertical pe mobil: nu are rost sa buffer-am zeci de secunde
        // dintr-un clip pe care userul il poate parasi in doua secunde.
        maxBufferLength: 12,
        maxMaxBufferLength: 30,
        capLevelToPlayerSize: true,
        startLevel: -1,
        enableWorker: true,
      });

      hls.loadSource(src);
      hls.attachMedia(videoRef.current);
      hlsRef.current = hls;
    });

    return () => {
      cancelled = true;
      hlsRef.current?.destroy();
      hlsRef.current = null;
    };
  }, [src]);

  /* ---------- play / pause dupa starea de viewport ---------- */
  useEffect(() => {
    const video = videoRef.current;
    if (!video) return;

    const shouldPlay = active && !preloadOnly && (!reducedMotion || forcePlay);

    if (!shouldPlay) {
      video.pause();
      if (!active) video.currentTime = 0;

      return;
    }

    video.muted = muted;

    video.play().then(
      () => setNeedsTapToPlay(false),
      () => {
        // Politica de autoplay a browserului a refuzat (se intampla cand
        // pornim nemut). Aratam butonul de play in loc sa esuam tacut.
        setNeedsTapToPlay(true);
      },
    );
  }, [active, preloadOnly, muted, reducedMotion, forcePlay]);

  /* ---------- mute fara sa reporneasca redarea ---------- */
  useEffect(() => {
    if (videoRef.current) videoRef.current.muted = muted;
  }, [muted]);

  /* ---------- raportarea progresului ---------- */
  useEffect(() => {
    const video = videoRef.current;
    if (!video || !active) return;

    const report = () => {
      if (!video.duration || !Number.isFinite(video.duration)) return;
      onProgress?.(Math.round(video.currentTime * 1000), video.currentTime / video.duration);
    };

    const finished = () => {
      report();
      onComplete?.();
    };

    video.addEventListener('timeupdate', report);
    video.addEventListener('ended', finished);

    return () => {
      // Un ultim raport la demontare: asa aflam cat s-a vazut din short-ul
      // parasit prin scroll, nu doar din cele duse pana la capat.
      report();
      video.removeEventListener('timeupdate', report);
      video.removeEventListener('ended', finished);
    };
  }, [active, onProgress, onComplete]);

  return (
    <div className="media" onClick={onTap}>
      <video
        ref={videoRef}
        className="shvideo"
        poster={poster ?? undefined}
        playsInline
        loop
        muted={muted}
        preload={active || preloadOnly ? 'auto' : 'none'}
        // Fara descriere textuala player-ul e invizibil pentru cititoarele de
        // ecran; titlul real vine din overlay, aici ramane rolul.
        aria-label="Video short"
      />

      {(needsTapToPlay || (reducedMotion && !forcePlay)) && active ? (
        <button
          type="button"
          className="shplay"
          aria-label="Redă"
          onClick={(e) => {
            e.stopPropagation();
            videoRef.current?.play().then(() => setNeedsTapToPlay(false), () => undefined);
          }}
        >
          ▶
        </button>
      ) : null}
    </div>
  );
}
