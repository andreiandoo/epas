/* =============================================================================
 * Scan App — camera barcode scanner
 * -----------------------------------------------------------------------------
 * Replicates tixello-app/src/screens/CheckInScreen.js scanner behavior in the
 * browser. Strategy:
 *
 *   1. Try the native `BarcodeDetector` API (Chrome / Edge / Android Chrome /
 *      Safari 16.4+). Best perf, lowest battery.
 *   2. Fall back to jsQR for older iOS Safari. Lazy-loaded from CDN only when
 *      BarcodeDetector is unavailable, so modern devices never pay the cost.
 *   3. Fall back to manual entry input if camera permission is denied.
 *
 * Public API (exposed as window.ScanScanner):
 *   const s = ScanScanner.create({ videoEl, onScan, onError, onPermission });
 *   await s.start();          // requests camera permission, starts stream
 *   s.stop();                 // releases camera + cancels animation frame
 *   s.pause(); s.resume();    // soft pause (keeps stream alive, stops scanning)
 *   s.destroy();              // permanent teardown
 *
 *   onScan({ code, format, time })  fired once per unique code (60s dedup)
 *   onError({ message, recoverable }) fired on permission/track failures
 *   onPermission({ granted })        fired after the initial getUserMedia
 *
 * Settings honored from AppContext on each successful scan (NOT on each frame):
 *   - vibrationFeedback → navigator.vibrate()
 *   - soundEffects      → Web Audio API (no external mp3 files)
 *
 * Wake Lock API is requested while the stream is active to prevent screen
 * sleep during long scanning sessions; quietly no-ops on browsers without it.
 * ============================================================================= */
(function () {
  'use strict';

  var JSQR_CDN = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js';
  var DEDUP_WINDOW_MS = 60000;   // ignore the same code if seen within 60s
  var SCAN_LOOP_INTERVAL_MS = 150; // ~6.5fps decode — easy on battery, fast enough for users

  function lazyLoadJSQR() {
    return new Promise(function (resolve, reject) {
      if (window.jsQR) return resolve(window.jsQR);
      var script = document.createElement('script');
      script.src = JSQR_CDN;
      script.async = true;
      script.onload = function () { resolve(window.jsQR); };
      script.onerror = function () { reject(new Error('Failed to load jsQR')); };
      document.head.appendChild(script);
    });
  }

  // ── Web Audio API beep generator (no mp3 dependencies) ───────────────────
  var audioCtx = null;
  function getAudioCtx() {
    if (audioCtx) return audioCtx;
    var Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return null;
    try { audioCtx = new Ctx(); } catch (e) { return null; }
    return audioCtx;
  }
  function beep(freq, durationMs, type) {
    var ctx = getAudioCtx();
    if (!ctx) return;
    // iOS Safari requires the AudioContext to be resumed inside a user gesture
    // at least once. We resume on every beep — no-op if already running.
    if (ctx.state === 'suspended') { try { ctx.resume(); } catch (e) {} }
    var osc = ctx.createOscillator();
    var gain = ctx.createGain();
    osc.type = type || 'sine';
    osc.frequency.value = freq;
    gain.gain.value = 0.15;
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    setTimeout(function () {
      try { osc.stop(); osc.disconnect(); gain.disconnect(); } catch (e) {}
    }, durationMs);
  }
  function feedbackSuccess() { beep(1000, 120, 'sine'); }
  function feedbackWarning() { beep(600, 200, 'sine'); setTimeout(function () { beep(600, 200, 'sine'); }, 220); }
  function feedbackError()   { beep(220, 400, 'square'); }

  function vibratePattern(pattern) {
    if (!navigator.vibrate) return;
    try { navigator.vibrate(pattern); } catch (e) {}
  }

  // ── Wake Lock ────────────────────────────────────────────────────────────
  function requestWakeLock() {
    if (!('wakeLock' in navigator)) return Promise.resolve(null);
    try {
      return navigator.wakeLock.request('screen').catch(function (e) {
        console.warn('[scanner] wakeLock request failed:', e);
        return null;
      });
    } catch (e) { return Promise.resolve(null); }
  }

  // ── Scanner instance factory ─────────────────────────────────────────────
  function create(opts) {
    opts = opts || {};
    var videoEl     = opts.videoEl;
    var onScan      = typeof opts.onScan       === 'function' ? opts.onScan       : function () {};
    var onError     = typeof opts.onError      === 'function' ? opts.onError      : function () {};
    var onPermission= typeof opts.onPermission === 'function' ? opts.onPermission : function () {};
    var formats     = opts.formats || ['qr_code', 'code_128', 'code_39', 'ean_13', 'ean_8'];

    if (!videoEl) throw new Error('scanner.create: videoEl is required');

    var stream     = null;
    var detector   = null;   // BarcodeDetector instance (or null when using jsQR)
    var useJsqr    = false;  // true if BarcodeDetector unavailable
    var jsqr       = null;
    var canvas     = null;   // offscreen canvas for jsQR
    var ctx2d      = null;
    var loopTimer  = null;
    var isPaused   = false;
    var lastCodes  = new Map(); // code → expiresAt
    var wakeLock   = null;

    function purgeExpiredCodes() {
      var now = Date.now();
      lastCodes.forEach(function (expiresAt, code) {
        if (expiresAt < now) lastCodes.delete(code);
      });
    }

    function isDuplicate(code) {
      purgeExpiredCodes();
      if (lastCodes.has(code)) return true;
      lastCodes.set(code, Date.now() + DEDUP_WINDOW_MS);
      return false;
    }

    async function detectFrame() {
      if (isPaused || !videoEl || videoEl.readyState < 2) return;

      try {
        if (detector) {
          var results = await detector.detect(videoEl);
          if (results && results.length) {
            handleDetection(results[0].rawValue, results[0].format);
          }
        } else if (useJsqr && jsqr) {
          var w = videoEl.videoWidth;
          var h = videoEl.videoHeight;
          if (!w || !h) return;
          if (!canvas) {
            canvas = document.createElement('canvas');
            ctx2d = canvas.getContext('2d', { willReadFrequently: true });
          }
          canvas.width = w;
          canvas.height = h;
          ctx2d.drawImage(videoEl, 0, 0, w, h);
          var imgData = ctx2d.getImageData(0, 0, w, h);
          var result = jsqr(imgData.data, w, h, { inversionAttempts: 'dontInvert' });
          if (result && result.data) {
            handleDetection(result.data, 'qr_code');
          }
        }
      } catch (e) {
        // Detection errors are noisy and non-fatal — log once at debug level.
        if (!detectFrame._loggedError) {
          console.debug('[scanner] detect error:', e);
          detectFrame._loggedError = true;
        }
      }
    }

    function handleDetection(code, format) {
      if (!code) return;
      var trimmed = String(code).trim();
      if (!trimmed) return;
      if (isDuplicate(trimmed)) return;

      var settings = (window.AppContext && AppContext.getSettings()) || {};
      if (settings.soundEffects !== false)      feedbackSuccess();
      if (settings.vibrationFeedback !== false) vibratePattern(200);

      try { onScan({ code: trimmed, format: format, time: Date.now() }); }
      catch (e) { console.error('[scanner] onScan handler threw:', e); }
    }

    function startLoop() {
      if (loopTimer) return;
      loopTimer = setInterval(detectFrame, SCAN_LOOP_INTERVAL_MS);
    }
    function stopLoop() {
      if (loopTimer) { clearInterval(loopTimer); loopTimer = null; }
    }

    async function setupDetector() {
      if ('BarcodeDetector' in window) {
        try {
          detector = new window.BarcodeDetector({ formats: formats });
          return;
        } catch (e) {
          console.warn('[scanner] BarcodeDetector creation failed, falling back to jsQR:', e);
        }
      }
      useJsqr = true;
      try {
        jsqr = await lazyLoadJSQR();
      } catch (e) {
        onError({ message: 'Nu am putut încărca biblioteca de decodare QR. Folosește introducere manuală.', recoverable: true });
      }
    }

    async function start() {
      if (stream) return; // already running

      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        onError({
          message: 'Acest browser nu suportă accesul la cameră. Folosește Safari 14+ pe iOS sau Chrome 60+ pe Android.',
          recoverable: false
        });
        return;
      }

      try {
        stream = await navigator.mediaDevices.getUserMedia({
          video: {
            facingMode: { ideal: 'environment' },
            width:  { ideal: 1280 },
            height: { ideal: 720 }
          },
          audio: false
        });
      } catch (e) {
        var msg = 'Acces refuzat la cameră.';
        if (e && e.name === 'NotAllowedError') {
          msg = 'Acces la cameră refuzat. Activează permisiunea în Setări → Safari/Chrome → Camera, apoi reîncarcă pagina.';
        } else if (e && e.name === 'NotFoundError') {
          msg = 'Nu am găsit nicio cameră pe acest dispozitiv.';
        } else if (e && e.name === 'NotReadableError') {
          msg = 'Camera este folosită de o altă aplicație. Închide-o și încearcă din nou.';
        }
        onError({ message: msg, recoverable: false });
        onPermission({ granted: false });
        return;
      }

      onPermission({ granted: true });

      videoEl.srcObject = stream;
      videoEl.setAttribute('playsinline', 'true');
      videoEl.setAttribute('muted', 'true');
      videoEl.muted = true;
      try { await videoEl.play(); } catch (e) { /* will retry on user gesture */ }

      await setupDetector();

      // Try to keep screen awake during scanning sessions.
      requestWakeLock().then(function (wl) { wakeLock = wl; });

      isPaused = false;
      startLoop();
    }

    function pause() {
      isPaused = true;
      stopLoop();
    }
    function resume() {
      if (!stream) return;
      isPaused = false;
      startLoop();
    }

    function stop() {
      stopLoop();
      isPaused = true;
      if (stream) {
        stream.getTracks().forEach(function (t) { try { t.stop(); } catch (e) {} });
        stream = null;
      }
      if (videoEl) {
        try { videoEl.srcObject = null; } catch (e) {}
      }
      if (wakeLock) {
        try { wakeLock.release(); } catch (e) {}
        wakeLock = null;
      }
    }

    function destroy() {
      stop();
      lastCodes.clear();
      detector = null;
      jsqr = null;
      canvas = null;
      ctx2d = null;
    }

    function clearDedup() { lastCodes.clear(); }

    return {
      start: start,
      stop: stop,
      pause: pause,
      resume: resume,
      destroy: destroy,
      clearDedup: clearDedup
    };
  }

  window.ScanScanner = {
    create: create,
    feedbackSuccess: feedbackSuccess,
    feedbackWarning: feedbackWarning,
    feedbackError:   feedbackError,
    vibratePattern:  vibratePattern
  };
})();
