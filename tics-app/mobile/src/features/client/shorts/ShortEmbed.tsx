/* =========================================================
   Redarea unui short EXTERN (YouTube deocamdata).

   Short-urile aduse din canalul unui artist nu au fisier video la noi — n-avem
   dreptul sa-l rehostam — ci doar linkul si un `embed_html`. Feed-ul le arata
   pana acum ca poster static, fara redare si fara buton: un clip care nu porneste.

   Se randeaza un iframe DOAR pentru short-ul activ. Doua motive:
     - un iframe YouTube inseamna un player intreg; trei montate simultan
       inseamna trei playere care ruleaza in fundal si consuma date;
     - demontarea e si singurul mod sigur de a OPRI redarea la derulare.

   Sunetul se comanda prin postMessage catre API-ul din iframe (`enablejsapi`),
   ca butonul de mute al feed-ului sa functioneze la fel pentru clipuri proprii
   si pentru cele externe.
   ========================================================= */
import { useEffect, useRef } from 'react';
import { sx } from '../../../design/sx';

/** Id-ul videoclipului, din link sau din embed-ul trimis de server. */
export function youtubeId(sourceUrl: string | null, embedHtml: string | null): string | null {
  const from = (s: string | null, re: RegExp) => (s ? (s.match(re)?.[1] ?? null) : null);

  return (
    from(sourceUrl, /youtube\.com\/shorts\/([A-Za-z0-9_-]{6,})/) ??
    from(sourceUrl, /[?&]v=([A-Za-z0-9_-]{6,})/) ??
    from(sourceUrl, /youtu\.be\/([A-Za-z0-9_-]{6,})/) ??
    from(embedHtml, /embed\/([A-Za-z0-9_-]{6,})/)
  );
}

type Props = {
  videoId: string;
  active: boolean;
  muted: boolean;
  /** Durata declarata, ca sa putem estima progresul (vezi mai jos). */
  duration: number | null;
  onProgress?: (ms: number, ratio: number) => void;
  onComplete?: () => void;
};

export function ShortEmbed({ videoId, active, muted, duration, onProgress, onComplete }: Props) {
  const frame = useRef<HTMLIFrameElement | null>(null);

  /* Comanda sunetul in player. `mute=1` in URL e obligatoriu la pornire —
     nicio platforma nu lasa un video sa porneasca singur cu sunet — deci
     dezactivarea vine dupa, prin mesaj. */
  useEffect(() => {
    const el = frame.current;
    if (!el || !active) return;

    const send = () => {
      const post = (func: string, args: unknown[] = []) =>
        el.contentWindow?.postMessage(JSON.stringify({ event: 'command', func, args }), '*');

      post(muted ? 'mute' : 'unMute');

      /* Si PORNIREA, nu doar sunetul.

         `autoplay=1` esueaza in destule WebView-uri (politica de gest al
         utilizatorului, economie de date, revenirea din fundal). Cand esueaza,
         YouTube deseneaza peste clip toata carcasa lui — poster, buton mare de
         redare, titlu, „Watch on YouTube". Asta se vedea drept „controale":
         nu erau comenzi ramase aprinse, era un player oprit.

         Comanda e idempotenta: pe un clip care ruleaza deja nu face nimic. */
      post('playVideo');
    };

    // Playerul nu asculta imediat dupa montare; reincercam de cateva ori.
    const timers = [200, 600, 1500].map((t) => setTimeout(send, t));

    return () => timers.forEach(clearTimeout);
  }, [active, muted]);

  /* Progresul.

     ATENTIE la ce masoara: fara scriptul IFrame API incarcat, nu putem citi
     pozitia reala din player. Ceasul de aici numara cat timp short-ul a fost
     ACTIV pe ecran, ceea ce e o aproximare buna a vizionarii pe un feed
     vertical (nu poti vedea doua deodata), dar NU e acelasi lucru: un clip pus
     pe pauza ar continua sa numere. Telemetria de „view" se bazeaza pe el, deci
     merita stiut cand se compara cifrele cu cele din YouTube Studio. */
  useEffect(() => {
    if (!active || !onProgress) return;

    const started = performance.now();
    const total = (duration ?? 30) * 1000;
    let done = false;

    const tick = setInterval(() => {
      const ms = performance.now() - started;
      onProgress(ms, Math.min(ms / total, 1));

      if (!done && ms >= total) {
        done = true;
        onComplete?.();
      }
    }, 500);

    return () => clearInterval(tick);
  }, [active, duration, onProgress, onComplete]);

  if (!active) return null;

  const params = new URLSearchParams({
    autoplay: '1',
    // pornim mereu fara sunet: altfel politica de autoplay refuza redarea
    mute: '1',
    playsinline: '1',
    controls: '0',
    modestbranding: '1',
    rel: '0',
    /* Tot ce mai poate fi stins din player. Nu se pot folosi oricum —
       iframe-ul are `pointer-events:none`, ca atingerea sa ajunga la feed —
       deci orice comanda vizibila e doar zgomot peste imagine. */
    fs: '0',
    disablekb: '1',
    iv_load_policy: '3',
    cc_load_policy: '0',
    color: 'white',
    loop: '1',
    // `loop` are efect doar impreuna cu o lista care contine acelasi clip
    playlist: videoId,
    enablejsapi: '1',
  });

  return (
    <>
      <iframe
        ref={frame}
        className="shembed"
        // remontare la schimbarea clipului, ca playerul sa nu ramana pe cel vechi
        key={videoId}
        src={`https://www.youtube-nocookie.com/embed/${videoId}?${params.toString()}`}
        title="Short"
        frameBorder="0"
        allow="autoplay; encrypted-media; picture-in-picture"
        allowFullScreen
        style={sx('border:0')}
      />

      {/* Benzile care acopera ce ramane din player.

          `controls=0` stinge bara de comenzi, dar YouTube tot deseneaza, la
          pornire si la bucla, titlul sus si linia de progres jos. Parametrii nu
          le pot opri (`modestbranding` e ignorat din 2023), iar taierea prin
          `scale` ar trebui dusa atat de departe incat s-ar pierde din imagine.
          Doua benzi de degrade catre negru le acopera fara sa se vada: sus si
          jos, cadrul e oricum sub scrimul feedului. */}
      <div className="shembed-mask top" aria-hidden="true" />
      <div className="shembed-mask bottom" aria-hidden="true" />
    </>
  );
}
