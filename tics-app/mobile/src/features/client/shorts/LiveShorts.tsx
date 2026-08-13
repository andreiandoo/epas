/* =========================================================
   Feed vertical LIVE de shorts — sursa: EPAS (docs/plans/shorts.md §7).

   Pager cu scroll-snap vertical, un short pe ecran. Short-ul "activ" se
   determina cu IntersectionObserver (prag .7), nu din calcule de scroll —
   asa ramane corect si cand utilizatorul arunca degetul peste trei ecrane.

   Doar short-ul activ si vecinii lui imediati monteaza un <video>. Fara asta
   ai zeci de elemente media vii in memorie dupa un minut de derulare.

   Telemetria emisa: impression la intrare, view la prag, complete la final,
   watch_ms/watch_ratio la iesire — toate batched (useShortTelemetry).
   ========================================================= */
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Ic, sx } from '../../../design/sx';
import { I } from '../../../mock/prototype';
import {
  isAuthenticated,
  recordDailyWatch,
  remindMe,
  reportCtaClick,
  shareShort,
  toggleShortLike,
  toggleShortSave,
  type ApiShort,
  type ShortFeedSegment,
} from '../../../api/shorts';
import { useNav } from '../nav';
import { useClient } from '../../../store/client';
import { ShortVideo } from './ShortVideo';
import { ShortEmbed, youtubeId } from './ShortEmbed';
import { useShortsFeed } from './useShortsFeed';
import { markSeen } from './seen';
import { useShortTelemetry } from './useShortTelemetry';
import { usePlaybackPreferences } from './usePlaybackPreferences';
import { useHorizontalSwipe } from './useHorizontalSwipe';
import { Blurhash } from './Blurhash';
import { DropCountdown } from './DropCountdown';

/** Cate ecrane in jurul celui activ tin un <video> montat. */
const PRELOAD_RADIUS = 1;

/** Pragurile unui "view" — trebuie sa fie cel putin la fel de stricte ca serverul. */
const VIEW_MIN_MS = 2000;
const VIEW_MIN_RATIO = 0.25;

/** Id-ul de YouTube al unui short extern, sau null cand nu e unul. */
const embedIdOf = (short: ApiShort) =>
  short.source === 'youtube' ? youtubeId(short.source_url, short.embed_html) : null;

const kfmt = (n: number) => (n >= 1000 ? `${(n / 1000).toFixed(1).replace(/\.0$/, '')}k` : String(n));

type Props = {
  feed?: ShortFeedSegment;
  /** Randare alternativa cand feed-ul live e gol sau indisponibil. */
  fallback: ReactNode;
};

export function LiveShorts({ feed = 'for_you', fallback }: Props) {
  const { items, playbackHints, loading, unavailable, onIndexChange, patch } = useShortsFeed(feed);
  const { track } = useShortTelemetry();
  const { go, back } = useNav();
  const showToast = useClient((s) => s.showToast);

  const { autoplayAllowed, dataSaver: localDataSaver, prefetchCount: localPrefetch } = usePlaybackPreferences();

  /* Economia de date porneste daca o cere ORICARE dintre parti: preferinta
     utilizatorului sau guardrail-ul de cost al platformei (D8/D9). Niciuna n-o
     poate anula pe cealalta — platforma nu poate fi obligata sa serveasca
     1080p peste plafonul de banda, iar utilizatorul nu poate fi obligat sa
     consume date. */
  const dataSaver = localDataSaver || playbackHints?.data_saver === true;
  const prefetchCount = dataSaver ? 0 : Math.min(localPrefetch, playbackHints?.prefetch_count ?? localPrefetch);

  const [activeIndex, setActiveIndex] = useState(0);
  const [muted, setMuted] = useState(true);
  const [reminded, setReminded] = useState<Record<number, boolean>>({});
  /* Descrierea sta pliata si se desface la atingerea titlului. Cheia e id-ul
     short-ului, nu un simplu boolean: altfel starea „deschis" ar migra pe
     short-ul urmator la derulare. */
  const [descOpenFor, setDescOpenFor] = useState<number | null>(null);

  /* Volumul de sistem comanda sunetul feed-ului.
     Evenimentul vine din MainActivity (WebView-ul nu vede tastele hardware si
     nu poate citi volumul). Fara asta, dai telefonul pe silentios din butoane
     si short-ul continua sa cante — ceea ce e exact momentul in care nu vrei.
     Necesita APK nou; pe un APK vechi evenimentul nu apare si totul ramane ca
     inainte. */
  useEffect(() => {
    const onVolume = (e: Event) => {
      const volume = (e as CustomEvent<{ volume: number }>).detail?.volume;
      if (typeof volume !== 'number') return;

      setMuted(volume === 0);
    };

    window.addEventListener('tixello:system-volume', onVolume);

    return () => window.removeEventListener('tixello:system-volume', onVolume);
  }, []);

  const containerRef = useRef<HTMLDivElement | null>(null);
  const slideRefs = useRef<Array<HTMLElement | null>>([]);

  /** Ce s-a raportat deja pentru fiecare short, ca sa nu dublam evenimentele. */
  const reported = useRef(new Map<number, { impression: boolean; view: boolean; complete: boolean }>());
  /** Ultimul progres cunoscut per short, folosit la raportarea de iesire. */
  const progress = useRef(new Map<number, { ms: number; ratio: number }>());

  const flagsFor = (id: number) => {
    let flags = reported.current.get(id);
    if (!flags) {
      flags = { impression: false, view: false, complete: false };
      reported.current.set(id, flags);
    }

    return flags;
  };

  /* ---------- cine e activ ---------- */
  useEffect(() => {
    const root = containerRef.current;
    if (!root || items.length === 0) return;

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (!entry.isIntersecting) continue;

          const index = Number((entry.target as HTMLElement).dataset.index);
          if (Number.isNaN(index)) continue;

          setActiveIndex(index);
          onIndexChange(index);
        }
      },
      { root, threshold: 0.7 },
    );

    slideRefs.current.forEach((node) => node && observer.observe(node));

    return () => observer.disconnect();
  }, [items.length, onIndexChange]);

  /* ---------- impression la intrarea in ecran ---------- */
  useEffect(() => {
    const short = items[activeIndex];
    if (!short) return;

    const flags = flagsFor(short.id);
    if (flags.impression) return;

    flags.impression = true;
    track({ short_id: short.id, type: 'impression', feed });

    /* Tinut minte LOCAL, ca la urmatoarea deschidere sa treaca la coada.
       Se marcheaza la impresie, nu la vizionarea completa: daca l-ai avut pe
       ecran si ai derulat mai departe, l-ai vazut destul cat sa nu vrei sa-l
       primesti primul maine. Vezi shorts/seen.ts. */
    markSeen(short.id);
  }, [activeIndex, items, track, feed]);

  /* ---------- raportul de iesire pentru short-ul parasit ---------- */
  const previousActive = useRef<number | null>(null);
  useEffect(() => {
    const leaving = previousActive.current;
    previousActive.current = activeIndex;

    if (leaving === null || leaving === activeIndex) return;

    const short = items[leaving];
    if (!short) return;

    const seen = progress.current.get(short.id);
    if (!seen) return;

    const flags = flagsFor(short.id);
    const counted = seen.ms >= VIEW_MIN_MS || seen.ratio >= VIEW_MIN_RATIO;

    track({
      short_id: short.id,
      type: counted ? 'view' : 'skip',
      watch_ms: seen.ms,
      watch_ratio: Number(seen.ratio.toFixed(3)),
      feed,
    });

    if (counted) flags.view = true;
  }, [activeIndex, items, track, feed]);

  /** Streak-ul zilnic se raporteaza o singura data pe sesiune; ziua o decide serverul. */
  const streakPinged = useRef(false);

  const handleProgress = useCallback(
    (short: ApiShort, ms: number, ratio: number) => {
      progress.current.set(short.id, { ms, ratio });

      // "view" se raporteaza si in timpul redarii, o singura data, ca sa nu
      // depindem de scroll pentru short-urile urmarite lung.
      const flags = flagsFor(short.id);
      if (!flags.view && (ms >= VIEW_MIN_MS || ratio >= VIEW_MIN_RATIO)) {
        flags.view = true;
        track({ short_id: short.id, type: 'view', watch_ms: ms, watch_ratio: Number(ratio.toFixed(3)), feed });

        if (!streakPinged.current && isAuthenticated()) {
          streakPinged.current = true;
          void recordDailyWatch().then((result) => {
            if (result?.awarded) showToast(`+${result.points} puncte · streak ${result.streak}`);
          });
        }
      }
    },
    [track, feed, showToast],
  );

  const handleComplete = useCallback(
    (short: ApiShort) => {
      const flags = flagsFor(short.id);
      if (flags.complete) return;

      flags.complete = true;
      track({ short_id: short.id, type: 'complete', watch_ratio: 1, feed });
    },
    [track, feed],
  );

  /* ---------- interactiuni ---------- */
  const onLike = useCallback(
    async (short: ApiShort) => {
      if (!isAuthenticated()) {
        showToast('Intră în cont ca să salvezi');

        return;
      }

      // Optimist: feed-ul trebuie sa raspunda instant la dublu-tap.
      const next = !short.viewer.liked;
      patch(short.id, {
        viewer: { ...short.viewer, liked: next },
        stats: { ...short.stats, likes: Math.max(0, short.stats.likes + (next ? 1 : -1)) },
      });

      try {
        const result = await toggleShortLike(short.id, feed);
        patch(short.id, {
          viewer: { ...short.viewer, liked: result.active },
          stats: { ...short.stats, likes: result.count },
        });
      } catch {
        // Serverul a refuzat — punem starea inapoi asa cum era.
        patch(short.id, { viewer: short.viewer, stats: short.stats });
        showToast('Nu am putut salva reacția');
      }
    },
    [patch, feed, showToast],
  );

  const onSave = useCallback(
    async (short: ApiShort) => {
      if (!isAuthenticated()) {
        showToast('Intră în cont ca să salvezi');

        return;
      }

      const next = !short.viewer.saved;
      patch(short.id, { viewer: { ...short.viewer, saved: next } });

      try {
        const result = await toggleShortSave(short.id, feed);
        patch(short.id, { viewer: { ...short.viewer, saved: result.active } });
      } catch {
        patch(short.id, { viewer: short.viewer });
        showToast('Nu am putut salva');
      }
    },
    [patch, feed, showToast],
  );

  const onShare = useCallback(
    async (short: ApiShort) => {
      // Cand exista cont, serverul minteste tokenul de share si baga codul de
      // referral in link (D1). Fara cont ramane un link simplu — tot partajabil,
      // doar fara atribuire.
      let url = `${window.location.origin}/s/${short.id}`;

      if (isAuthenticated()) {
        const result = await shareShort(short.id, 'native', feed);
        if (result) url = result.url;
      } else {
        track({ short_id: short.id, type: 'share', feed });
      }

      const title = short.title ?? 'tics';

      // Web Share API cand exista (Capacitor o expune in WebView), altfel
      // clipboard. Fara plugin nativ suplimentar in aceasta faza.
      if (typeof navigator !== 'undefined' && typeof navigator.share === 'function') {
        try {
          await navigator.share({ title, url });

          return;
        } catch {
          // Utilizatorul a inchis dialogul — nu e o eroare.
          return;
        }
      }

      try {
        await navigator.clipboard?.writeText(url);
        showToast('Link copiat');
      } catch {
        showToast('Nu am putut copia linkul');
      }
    },
    [track, feed, showToast],
  );

  const onRemind = useCallback(
    async (short: ApiShort) => {
      if (!isAuthenticated()) {
        showToast('Intră în cont ca să primești anunțul');

        return;
      }

      const ok = await remindMe(short.id);
      if (!ok) {
        showToast('Nu am putut seta memento-ul');

        return;
      }

      setReminded((prev) => ({ ...prev, [short.id]: true }));
      showToast('Te anunțăm când intră biletele');
    },
    [showToast],
  );

  const onCta = useCallback(
    (short: ApiShort) => {
      const cta = short.cta;
      if (!cta) return;

      // Raportam imediat (nu batched) si nu asteptam raspunsul: navigarea spre
      // checkout nu trebuie sa depinda de o cerere de analytics. Pe un slot
      // platit, aici se face si taxarea CPC (D3).
      void reportCtaClick(short.id, feed, short.promoted?.id ?? null);

      if (cta.type === 'external' && cta.url) {
        window.open(cta.url, '_blank', 'noopener');

        return;
      }

      /* Decide TIPUL de CTA, nu prezenta unui eveniment.
         Inainte, orice short care avea `event` mergea la eveniment — deci un
         short de artist cu un concert apropiat ducea la biletele acelui
         concert, desi cadrul era despre artist si butonul spune „Vezi profil". */
      if (cta.type === 'open_artist' && short.owner?.id) {
        go('artist', { id: short.owner.id });

        return;
      }

      if (cta.type === 'open_venue' && short.owner?.id) {
        go('venue', { id: short.owner.id });

        return;
      }

      if (short.event?.id) {
        // sourceShortId + sourceFeed calatoresc pana la crearea comenzii; acolo
        // devin orders.source_short_id / source_feed si inchid bucla de
        // atribuire (docs/plans/shorts.md B1).
        go('event', {
          id: short.event.id,
          ticketTypeId: cta.ticket_type_id ?? undefined,
          promoCode: cta.promo_code ?? undefined,
          sourceShortId: short.id,
          sourceFeed: feed,
        });

        return;
      }

      // Rezerva, pentru randuri vechi fara tip potrivit.
      if (short.owner?.type === 'artist' && short.owner.id) go('artist', { id: short.owner.id });
      else if (short.owner?.type === 'venue' && short.owner.id) go('venue', { id: short.owner.id });
    },
    [feed, go],
  );

  const activeShort = items[activeIndex];

  /**
   * Deschide subiectul short-ului activ, fara sa raporteze un click de CTA.
   *
   * Un swipe NU e o apasare pe buton: daca l-am trece prin `onCta`, o plasare
   * platita ar fi taxata pentru un gest care nu i-a fost destinat.
   */
  const openDetail = useCallback(() => {
    const short = items[activeIndex];
    if (!short) return;

    if (short.event?.id) go('event', { id: short.event.id, sourceShortId: short.id, sourceFeed: feed });
    else if (short.owner?.type === 'artist' && short.owner.id) go('artist', { id: short.owner.id });
    else if (short.owner?.type === 'venue' && short.owner.id) go('venue', { id: short.owner.id });
  }, [items, activeIndex, go, feed]);

  const swipe = useHorizontalSwipe(back, openDetail);

  /* Data, separata de restul detaliilor — se afiseaza altfel. */
  const details = activeShort?.owner?.details ?? [];
  const dateRow = details.find((d) => d.icon === 'cal') ?? null;
  const otherRows = details.filter((d) => d !== dateRow);

  /* La un EVENIMENT, pastila poarta data, nu cuvantul „Eveniment": tipul se
     vede oricum din tot ce urmeaza (titlu, sala, buton de bilete), pe cand
     data e informatia dupa care decizi daca te intereseaza. La artisti si
     locatii nu exista data, deci pastila ramane ce era.
     Randul separat de data dispare cand pastila il duce — aceeasi informatie
     de doua ori, una sub alta, arata a greseala. */
  const isEvent = activeShort?.owner?.type === 'event';
  const pillDate = isEvent ? dateRow : null;
  const pillText = pillDate?.text ?? activeShort?.owner?.label ?? '';

  const descText = activeShort?.caption ?? activeShort?.description ?? '';
  const hasDesc = descText.trim() !== '';
  const descOpen = hasDesc && descOpenFor === activeShort?.id;

  /**
   * Marimea titlului, dupa lungime.
   *
   * Titlurile preluate de pe YouTube sau numele lungi de eveniment ajungeau la
   * trei randuri de 26px, adica jumatate din inaltimea ecranului. Praguri fixe,
   * nu o formula: se pot citi dintr-o privire si dau acelasi rezultat pe orice
   * telefon, spre deosebire de o masuratoare care depinde de latimea reala.
   */
  const titleLen = (activeShort?.title ?? '').length;
  const titleSize = titleLen > 78 ? 17 : titleLen > 56 ? 19 : titleLen > 38 ? 22 : 26;

  // Cu economie de date sau pe retea mobila, prefetchCount e 0: montam doar
  // short-ul activ si nu platim banda pentru vecini (D9).
  const radius = Math.min(PRELOAD_RADIUS, prefetchCount > 0 ? PRELOAD_RADIUS : 0);

  const visibleRange = useMemo(
    () => ({ from: activeIndex - radius, to: activeIndex + radius }),
    [activeIndex, radius],
  );

  if (unavailable) return <>{fallback}</>;

  if (loading && items.length === 0) {
    return (
      <div className="shorts" aria-busy="true">
        <div className="short">
          <div className="media" style={sx('background:#0b0912')} />
        </div>
      </div>
    );
  }

  return (
    /* Doua straturi suprapuse: derularea, si peste ea comenzile.
       Gestul orizontal se asculta pe invelis, ca sa prinda si atingerile care
       incep pe zone fara comenzi. */
    <div className="shwrap" {...swipe}>
      <div className="shorts" ref={containerRef} role="feed" aria-label="Shorts">
        {items.map((short, index) => {
          const isActive = index === activeIndex;
          const mounted = index >= visibleRange.from && index <= visibleRange.to;
          const hls = short.playback.hls_url;

          return (
            <article
              className="short"
              key={short.id}
              data-index={index}
              ref={(node) => {
                slideRefs.current[index] = node;
              }}
              aria-label={short.title ?? 'Short'}
              aria-current={isActive ? 'true' : undefined}
            >
              {/* LQIP sub video: pe retea slaba se vede o culoare plauzibila,
                  nu un dreptunghi negru, pana urca posterul (D9). */}
              <Blurhash hash={short.playback.blurhash} />

              {mounted && hls ? (
                <ShortVideo
                  src={hls}
                  poster={short.playback.poster_url}
                  active={isActive}
                  muted={muted}
                  preloadOnly={!isActive}
                  autoplayAllowed={autoplayAllowed}
                  dataSaver={dataSaver}
                  onProgress={(ms, ratio) => handleProgress(short, ms, ratio)}
                  onComplete={() => handleComplete(short)}
                  onTap={() => setMuted((m) => !m)}
                />
              ) : embedIdOf(short) ? (
                /* Short extern: playerul platformei, montat doar cat e activ.
                   Posterul ramane dedesubt si se vede cat incarca. */
                <>
                  <div
                    className="media"
                    style={
                      short.playback.poster_url
                        ? { background: `#0b0912 center/cover no-repeat url(${short.playback.poster_url})` }
                        : sx('background:transparent')
                    }
                  />
                  <ShortEmbed
                    videoId={embedIdOf(short) as string}
                    active={isActive}
                    muted={muted || !autoplayAllowed}
                    duration={short.duration}
                    onProgress={(ms, ratio) => handleProgress(short, ms, ratio)}
                    onComplete={() => handleComplete(short)}
                  />
                </>
              ) : (
                // Short extern (embed) sau asset inca neincarcat: posterul e
                // suficient ca feed-ul sa nu aiba goluri negre.
                <div
                  className="media"
                  style={
                    short.playback.poster_url
                      ? { background: `#0b0912 center/cover no-repeat url(${short.playback.poster_url})` }
                      : sx('background:transparent')
                  }
                />
              )}
            </article>
          );
        })}
      </div>

      {/* ---------- comenzile, montate O SINGURA DATA ----------
          Cheia e ca nimic de aici sa nu se remonteze la schimbarea short-ului:
          nodurile raman aceleasi si li se schimba doar textul. Asa butonul de
          inapoi, cele laterale si cel de bilete stau perfect nemiscate in timp
          ce continutul din spate deruleaza. */}
      {activeShort ? (
        <div className="shchrome" aria-hidden={false}>
          <div className="grad" />

          <div className="shtop">
            <button type="button" className="icon-btn glass" aria-label="Înapoi" onClick={back}>
              <Ic svg={I.back} />
            </button>
            <div className="row" style={sx('gap:6px;color:#fff;font-weight:600;font-size:14px')}>
              <Ic svg={I.wave} /> Pe val
            </div>
            <button
              type="button"
              className="icon-btn glass"
              aria-label={muted ? 'Pornește sunetul' : 'Oprește sunetul'}
              aria-pressed={!muted}
              onClick={() => setMuted((m) => !m)}
            >
              {muted ? '🔇' : '🔊'}
            </button>
          </div>

          {/* Avertisment de continut inainte de a porni ceva ce poate
              declansa fotosensibilitate (D10). */}
          {activeShort.content_flags.includes('flashing') ? (
            <span className="mtag" role="note">
              ⚠ Lumini intermitente
            </span>
          ) : null}

          <div className="info">
            {/* Dezvaluirea plasarii platite (D3). Eticheta vine de la server —
                „Sponsorizat" pentru un eveniment boostat, „Reclama" pentru un
                brand — si nu are stare de ascundere: o plasare care nu poate fi
                etichetata nu e servita deloc. */}
            {activeShort.promoted ? (
              <span className="short-ad" role="note">
                {activeShort.promoted.label}
                {activeShort.promoted.advertiser ? ` · ${activeShort.promoted.advertiser}` : ''}
              </span>
            ) : null}

            {/* Pastila spune CE e, nu CUM se cheama: numele sta in titlul de
                dedesubt, iar pana acum pastila il repeta pe acelasi. */}
            <span className="gpill solid" hidden={!pillText}>
              {pillText}
            </span>

            {/* Data, scoasa in evidenta: pentru un eveniment conteaza aproape
                cat titlul, iar pierduta printre celelalte detalii se citea
                ultima. Ramane doar un rand de text pentru artisti si locatii,
                unde nu exista. */}
            <span className="shdate" hidden={!dateRow || !!pillDate}>
              <Ic svg={I.cal} /> {dateRow?.text ?? ''}
            </span>

            {/* Randate neconditionat, chiar goale: un `? :` ar scoate si ar
                repune nodul la fiecare short, iar blocul de sub el ar sari.
                Marimea scade cu lungimea: un titlu preluat de pe YouTube poate
                avea 90 de caractere, iar la 26px ar ocupa jumatate de ecran. */}
            <button
              type="button"
              className="shtitle"
              // atingerea titlului desface / plieaza descrierea
              onClick={() => setDescOpenFor((cur) => (cur === activeShort.id ? null : activeShort.id))}
              aria-expanded={descOpen}
              style={{
                fontSize: `${titleSize}px`,
                fontWeight: 700,
                letterSpacing: '-.03em',
                marginTop: '9px',
                lineHeight: 1.08,
                textShadow: '0 2px 18px rgba(0,0,0,.6)',
              }}
            >
              <span>{activeShort.title ?? ''}</span>
              {hasDesc ? <i className={descOpen ? 'shcaret up' : 'shcaret'} aria-hidden="true" /> : null}
            </button>

            {/* Restul detaliilor: adresa, nota, capacitatea, organizatorul. */}
            <div className="cmeta" style={sx('margin-top:8px')}>
              {otherRows.map((d) => (
                <span className="i" key={d.text}>
                  <Ic svg={I[d.icon as keyof typeof I] ?? I.pin} />
                  <span>{d.text}</span>
                </span>
              ))}
            </div>

            {/* Descrierea: pliata implicit, ca sa nu acopere cadrul.
                Animatia foloseste `grid-template-rows: 0fr -> 1fr`, singurul mod
                de a tranzita o inaltime NECUNOSCUTA fara sa o masuram in JS —
                iar tot ce e sub ea se deplaseaza odata cu ea, lin. */}
            <div className={descOpen ? 'shdescwrap open' : 'shdescwrap'}>
              <div className="shdescinner">
                <div className="shdesc" style={sx('font-size:13px;opacity:.9;margin-top:8px;line-height:1.4')}>
                  {descText}
                </div>
              </div>
            </div>

            <div style={sx('font-size:12.5px;opacity:.8;margin-top:6px')}>
              {activeShort.hashtags.map((tag) => `#${tag}`).join(' ')}
            </div>

            <div style={sx('font-size:11.5px;opacity:.7;margin-top:6px')}>
              {activeShort.music_credit ? `♪ ${activeShort.music_credit}` : ''}
            </div>

            {activeShort.cta?.pending && activeShort.cta.on_sale_at ? (
              // Biletele nu sunt inca la vanzare: un buton „Ia bilet" ar fi o
              // fundatura, deci devine countdown + „Amintește-mi" (D2).
              <DropCountdown
                onSaleAt={activeShort.cta.on_sale_at}
                reminded={reminded[activeShort.id] ?? activeShort.viewer.reminded === true}
                onRemind={() => void onRemind(activeShort)}
              />
            ) : (
              <button
                className="shcta"
                style={sx('margin-top:14px')}
                // Fara CTA n-avem unde duce. Butonul se ascunde, dar isi
                // pastreaza locul (vezi shorts.css) ca textul de deasupra sa nu
                // coboare la trecerea pe un short fara actiune.
                hidden={!activeShort.cta}
                onClick={() => onCta(activeShort)}
              >
                <Ic svg={I.ticket} /> {activeShort.cta?.label ?? 'Vezi detalii'}
              </button>
            )}
          </div>

          <div className="rail">
            <button
              type="button"
              className={activeShort.viewer.liked ? 'act liked' : 'act'}
              aria-label="Îmi place"
              aria-pressed={activeShort.viewer.liked}
              onClick={() => void onLike(activeShort)}
            >
              <div className="b">
                <Ic svg={activeShort.viewer.liked ? I.heart : I.heartO} />
              </div>
              <span>{kfmt(activeShort.stats.likes)}</span>
            </button>

            <button
              type="button"
              className={activeShort.viewer.saved ? 'act liked' : 'act'}
              aria-label="Salvează"
              aria-pressed={activeShort.viewer.saved}
              onClick={() => void onSave(activeShort)}
            >
              <div className="b">
                <Ic svg={I.save} />
              </div>
            </button>

            <button type="button" className="act" aria-label="Trimite" onClick={() => void onShare(activeShort)}>
              <div className="b">
                <Ic svg={I.share} />
              </div>
            </button>
          </div>
        </div>
      ) : null}

      {/* Regiune live: cititoarele de ecran anunta schimbarea short-ului (D10). */}
      <div className="sr-only" aria-live="polite">
        {activeShort?.title ?? ''}
      </div>
    </div>
  );
}
