<!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="color-scheme" content="dark">
<title>Hartă locuri</title>
<style>
  *, *::before, *::after { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
  html, body {
    margin: 0; padding: 0; height: 100%; background: #0a0a14; color: #fff;
    font-family: -apple-system, Roboto, system-ui, sans-serif; overflow: hidden;
    user-select: none; -webkit-user-select: none; touch-action: none;
    overscroll-behavior: none;
  }
  #wrap { display: flex; flex-direction: column; height: 100vh; }
  #legend {
    flex-shrink: 0; display: flex; gap: 8px; padding: 10px 12px;
    overflow-x: auto; background: #0f0f1f; border-bottom: 1px solid rgba(255,255,255,0.08);
    scrollbar-width: none;
  }
  #legend::-webkit-scrollbar { display: none; }
  .leg-item {
    flex-shrink: 0; display: flex; align-items: center; gap: 6px;
    padding: 6px 10px; border-radius: 8px; background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08); font-size: 11px; font-weight: 600;
  }
  .leg-item.active { background: rgba(139,92,246,0.18); border-color: rgba(139,92,246,0.5); }
  .leg-dot { width: 10px; height: 10px; border-radius: 5px; }
  .leg-price { color: #a78bfa; font-weight: 700; }
  #canvas-wrap {
    flex: 1; position: relative; overflow: hidden;
    background: linear-gradient(180deg, #0a0a14 0%, #0f0f1f 100%);
  }
  /* Explicit 100% size so the canvas exists in the DOM with non-zero
     bounds even before fitToContainer() runs — Android WebView ignores
     pointer events on zero-sized elements. touch-action:none stops the
     browser from interpreting drags as scrolls. */
  #seat-canvas {
    position: absolute; top: 0; left: 0;
    width: 100%; height: 100%;
    transform-origin: 0 0;
    touch-action: none;
    -webkit-tap-highlight-color: transparent;
  }
  #footer {
    flex-shrink: 0; background: #0f0f1f;
    border-top: 1px solid rgba(255,255,255,0.08);
    display: flex; flex-direction: column;
  }
  #selected-list {
    max-height: 38vh; overflow-y: auto;
    padding: 8px 16px;
  }
  #selected-list.hidden { display: none; }
  .sel-row {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.04);
  }
  .sel-row:last-child { border-bottom: 0; }
  .sel-dot { width: 10px; height: 10px; border-radius: 5px; flex-shrink: 0; }
  .sel-info { flex: 1; min-width: 0; }
  .sel-name { font-size: 13px; font-weight: 700; color: #fff; }
  .sel-pos { font-size: 11px; color: rgba(255,255,255,0.55); margin-top: 2px; }
  .sel-price { font-size: 14px; font-weight: 700; color: #a78bfa; flex-shrink: 0; }
  .sel-remove {
    width: 28px; height: 28px; border-radius: 14px;
    background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.6);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; font-weight: 700; cursor: pointer; flex-shrink: 0;
  }
  #totals-row {
    flex-shrink: 0;
    padding: 12px 16px;
    /* Phone gesture nav / home indicator inset. env() returns the OS
       inset on iOS / modern Android; max() ensures a baseline 12px so
       the buttons don't sit flush against the bottom edge. The WebView
       container in SeatingMapScreen.js also pads the bottom by
       insets.bottom as a belt-and-braces fallback. */
    padding-bottom: max(12px, env(safe-area-inset-bottom));
    border-top: 1px solid rgba(255,255,255,0.08);
    display: flex; gap: 10px; align-items: center;
  }
  #summary { flex: 1; font-size: 13px; color: rgba(255,255,255,0.7); }
  #summary strong { color: #fff; }
  button {
    background: #8b5cf6; color: #fff; border: 0; border-radius: 10px;
    padding: 14px 18px; font-size: 14px; font-weight: 700; cursor: pointer;
    transition: opacity 0.15s, background 0.15s;
  }
  button:disabled { opacity: 0.35; cursor: not-allowed; }
  #cancel-btn { background: rgba(255,255,255,0.06); }
  #status-line {
    position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.6);
    padding: 4px 8px; border-radius: 6px; font-size: 10px; color: rgba(255,255,255,0.5);
    pointer-events: none; z-index: 10;
  }
  #status-line.connected { color: #10b981; }
</style>
</head>
<body>
<div id="wrap">
  <div id="legend"></div>
  <div id="canvas-wrap">
    <canvas id="seat-canvas"></canvas>
    <div id="status-line">offline</div>
  </div>
  <div id="footer">
    <div id="selected-list" class="hidden"></div>
    <div id="totals-row">
      <div id="summary">Selectează locurile dorite din hartă.</div>
      <button id="cancel-btn">Anulează</button>
      <button id="confirm-btn" disabled>Confirmă</button>
    </div>
  </div>
</div>

<script>
// ────────────────────────────────────────────────────────────────────────
// Server-rendered data — zero network round-trips on initial paint.
// ────────────────────────────────────────────────────────────────────────
const SEATING = @json($seating);
const EVENT_ID = @json($eventId);
const PRESELECTED_TT = @json($ticketTypeId);
const REVERB = @json($reverbConfig);

// ────────────────────────────────────────────────────────────────────────
// Bridge to host page.
//   - React Native WebView: window.ReactNativeWebView.postMessage (mobile app)
//   - Browser iframe:       window.parent.postMessage (Aplicație Scan web)
//   - Standalone:           console.log (debugging)
// All three are tried in order so the same embed page works in every host.
// ────────────────────────────────────────────────────────────────────────
const bridge = {
  post(type, data = {}) {
    const payload = { type, ...data };
    const msg = JSON.stringify(payload);
    if (window.ReactNativeWebView && window.ReactNativeWebView.postMessage) {
      window.ReactNativeWebView.postMessage(msg);
      return;
    }
    if (window.parent && window.parent !== window) {
      // Browser iframe — post to the parent window. We send the object form
      // (not the JSON string) so the parent listener can read event.data.type
      // directly without parsing, matching modern postMessage best practice.
      // Target origin '*' is OK here because the message contains no secrets
      // and the parent listener filters by event.source === iframe.contentWindow.
      try { window.parent.postMessage(payload, '*'); return; } catch (e) {}
    }
    console.log('[bridge]', msg);
  },
};

// (The 'ready' post is dispatched at the bottom of this script once
// fitToContainer() has run — line ~925. Don't double-post it here.)

// ────────────────────────────────────────────────────────────────────────
// Render state.
// ────────────────────────────────────────────────────────────────────────
const canvas = document.getElementById('seat-canvas');
const ctx = canvas.getContext('2d');
const wrap = document.getElementById('canvas-wrap');

const view = {
  scale: 1,
  minScale: 0.3,
  maxScale: 6,
  tx: 0, ty: 0,
  // Initialized in fitToContainer
};

const seatIndex = new Map(); // seat_uid → { seat, section, row }
const selectedSeats = new Set();

// Build the seat index once for O(1) lookup on status updates and taps.
(function buildIndex() {
  for (const section of SEATING.sections) {
    for (const row of (section.rows || [])) {
      for (const seat of (row.seats || [])) {
        // Absolute canvas coordinates: section origin + seat offset.
        const absX = (section.x || 0) + (seat.x || 0);
        const absY = (section.y || 0) + (seat.y || 0);
        const r = (section.seat_size || 14) / 2;
        seatIndex.set(seat.seat_uid, {
          seat, section, row, absX, absY, r,
        });
      }
    }
  }
})();

// ────────────────────────────────────────────────────────────────────────
// Color rules — match the legacy SeatingMapScreen palette closely so the
// visual stays consistent with what the user expects.
// ────────────────────────────────────────────────────────────────────────
function seatColor(seat) {
  if (selectedSeats.has(seat.seat_uid)) return '#a51c30'; // selected
  if (seat.status === 'sold' || seat.status === 'held') return '#9CA3AF';
  if (seat.status === 'blocked' || seat.status === 'disabled') return '#374151';
  // Available — color by ticket type (or default green). Locked-out
  // (non-POS) tiers get a muted slate so they read as 'not for sale here'.
  const ttId = seat.ticket_type_id != null ? Number(seat.ticket_type_id) : null;
  if (ttId == null || !POS_TT_IDS.has(ttId)) return '#475569';
  return seat.ticket_type_color || '#10B981';
}

// Set of POS-allowed ticket type ids (is_entry_ticket = true). Only seats
// whose ticket type is in this set can be sold from the mobile app —
// online-only / imported tiers stay locked out. Built once at boot.
const POS_TT_IDS = new Set(
  (SEATING.ticket_types || [])
    .filter(t => t.is_entry_ticket)
    .map(t => Number(t.id))
);

function isSeatSelectable(seat) {
  if (seat.status !== 'available') return false;
  // Lock out seats whose ticket type isn't marked as POS / entry. The
  // mobile app must never sell non-POS tiers (online-only / imported)
  // — that was the urgent bug the operator reported.
  const ttId = seat.ticket_type_id != null ? Number(seat.ticket_type_id) : null;
  if (ttId == null) return false; // no assignment → not sellable
  if (!POS_TT_IDS.has(ttId)) return false;
  return true;
}

// ────────────────────────────────────────────────────────────────────────
// Drawing — single full repaint per frame. Even 10k seats redraw in a
// few ms on a modern device; we don't bother with dirty rects.
// ────────────────────────────────────────────────────────────────────────
let rafScheduled = false;
function requestPaint() {
  if (rafScheduled) return;
  rafScheduled = true;
  requestAnimationFrame(paint);
}

function paint() {
  rafScheduled = false;
  const dpr = window.devicePixelRatio || 1;
  const w = canvas.clientWidth, h = canvas.clientHeight;
  if (canvas.width !== w * dpr || canvas.height !== h * dpr) {
    canvas.width = w * dpr; canvas.height = h * dpr;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }
  ctx.clearRect(0, 0, w, h);

  ctx.save();
  ctx.translate(view.tx, view.ty);
  ctx.scale(view.scale, view.scale);

  // Backdrop matching the SVG version
  ctx.fillStyle = '#0f0f1a';
  const cw = SEATING.canvas.width, ch = SEATING.canvas.height;
  roundedRect(ctx, 0, 0, cw, ch, 8);
  ctx.fill();

  // Section pass — three rendering modes:
  //   1. Decorative `shape === 'text'`: styled free text, no outline.
  //   2. Empty section (no rows/seats): outline + name (structural labels
  //      like Scenă, Bar, Intrare).
  //   3. Seated section: nothing extra — seats themselves are the visual.
  //      No outline, no name. Names overlap the seats which is ugly.
  for (const section of SEATING.sections) {
    const sx = section.x || 0, sy = section.y || 0;
    const sw = section.width || 100, sh = section.height || 100;
    const meta = section.metadata || {};
    const hasSeats = (section.rows || []).some(r => (r.seats || []).length > 0);

    // Decorative text element — admin-styled font/color/size/rotation.
    if (meta.shape === 'text' && meta.text) {
      ctx.save();
      ctx.globalAlpha = meta.opacity != null ? Number(meta.opacity) : 1;
      ctx.fillStyle = meta.color || section.color || '#ffffff';
      const fSize = Number(meta.fontSize || 14);
      const fWeight = meta.fontWeight || '600';
      const fFam = meta.fontFamily || 'system-ui';
      ctx.font = `${fWeight} ${fSize}px ${fFam}`;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      if (section.rotation) {
        ctx.translate(sx + sw / 2, sy + sh / 2);
        ctx.rotate((section.rotation * Math.PI) / 180);
        ctx.fillText(meta.text, 0, 0);
      } else {
        ctx.fillText(meta.text, sx + sw / 2, sy + sh / 2);
      }
      ctx.restore();
      continue;
    }

    // Seated section — render seats only (handled in the seat pass below).
    // Skip outline and name entirely.
    if (hasSeats) continue;

    // Empty section — structural label (Scenă, Bar, Intrare). Outline +
    // name centered. Respects metadata.show_label === false to fully hide
    // even an empty section if the admin asked for it.
    ctx.strokeStyle = 'rgba(139,92,246,0.18)';
    ctx.lineWidth = 1;
    ctx.strokeRect(sx, sy, sw, sh);

    if (meta.show_label !== false && section.name) {
      ctx.fillStyle = 'rgba(255,255,255,0.75)';
      ctx.font = 'bold 14px system-ui';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(section.name, sx + sw / 2, sy + sh / 2);
    }
  }

  // Row labels — respect both per-section auto_show_row_labels and
  // per-row metadata.show_label. Position from row.metadata.label_position
  // (default 'left'). Generous clearance (seatR + 14) so the text doesn't
  // bleed into the leftmost/rightmost seat of the row.
  const zoomLetsLabelsShow = view.scale > 0.35;
  if (zoomLetsLabelsShow) {
    ctx.fillStyle = 'rgba(255,255,255,0.75)';
    ctx.font = '700 10px system-ui';
    ctx.textBaseline = 'middle';
    for (const section of SEATING.sections) {
      const secMeta = section.metadata || {};
      if (secMeta.shape === 'text') continue; // decorative — no rows
      if (secMeta.auto_show_row_labels === false) continue;
      const sx = section.x || 0, sy = section.y || 0;
      const seatR = (section.seat_size || 14) / 2;
      for (const row of section.rows || []) {
        if (!row.seats || row.seats.length === 0) continue;
        const rowMeta = row.metadata || {};
        if (rowMeta.show_label === false) continue;
        // Leftmost/rightmost seat anchors the label.
        let left = row.seats[0], right = row.seats[0];
        for (const s of row.seats) {
          if ((s.x || 0) < (left.x || 0)) left = s;
          if ((s.x || 0) > (right.x || 0)) right = s;
        }
        const pos = rowMeta.label_position || 'left';
        const gap = seatR + 14; // big enough that "Rând A" sits clear
        let lx, ly;
        if (pos === 'right') {
          lx = sx + (right.x || 0) + gap;
          ly = sy + (right.y || 0);
          ctx.textAlign = 'left';
        } else {
          lx = sx + (left.x || 0) - gap;
          ly = sy + (left.y || 0);
          ctx.textAlign = 'right';
        }
        ctx.fillText(String(row.label || ''), lx, ly);
      }
    }
  }

  // Seat numbers — always drawn. They're tiny at low zoom (naturally
  // hard to read) and crisp once the user pinches in, but they're
  // always visible so the operator can pick a specific seat without
  // guessing. Performance is fine: even 5000 fillText calls per paint
  // are <10ms on modern devices.
  const showSeatNumbers = true;
  // Two-pass render: draw unselected seats first, then selected ones on top
  // (so selected halos don't get covered by neighbouring seat fills). Selected
  // seats are rendered ~25% bigger and with a glow + white outline so the
  // operator can pick them out at a glance.
  const selectedEntries = [];
  for (const entry of seatIndex.values()) {
    const { seat, absX, absY, r } = entry;
    if (selectedSeats.has(seat.seat_uid)) {
      selectedEntries.push(entry);
      continue;
    }
    ctx.beginPath();
    ctx.arc(absX, absY, r, 0, Math.PI * 2);
    ctx.fillStyle = seatColor(seat);
    ctx.fill();

    const seatNum = seat.label ?? seat.seat_number ?? seat.seat_label;
    if (showSeatNumbers && seatNum != null && seatNum !== '') {
      ctx.fillStyle = '#fff';
      ctx.font = `700 ${Math.max(7, r * 0.95)}px system-ui`;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(String(seatNum), absX, absY);
    }
  }
  // Second pass — selected seats, enlarged 25% with glow + outline.
  for (const entry of selectedEntries) {
    const { seat, absX, absY, r } = entry;
    const bigR = r * 1.25;
    ctx.save();
    ctx.shadowColor = 'rgba(165, 28, 48, 0.85)';
    ctx.shadowBlur = Math.max(4, r * 0.8);
    ctx.beginPath();
    ctx.arc(absX, absY, bigR, 0, Math.PI * 2);
    ctx.fillStyle = seatColor(seat);
    ctx.fill();
    ctx.restore();

    ctx.strokeStyle = '#fff';
    ctx.lineWidth = Math.max(1.2, 2 / view.scale);
    ctx.beginPath();
    ctx.arc(absX, absY, bigR, 0, Math.PI * 2);
    ctx.stroke();

    const seatNum = seat.label ?? seat.seat_number ?? seat.seat_label;
    if (showSeatNumbers && seatNum != null && seatNum !== '') {
      ctx.fillStyle = '#fff';
      // Font scales with the enlarged radius so the number stays readable.
      ctx.font = `800 ${Math.max(8, bigR * 0.95)}px system-ui`;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(String(seatNum), absX, absY);
    }
  }

  ctx.restore();
}

function roundedRect(c, x, y, w, h, r) {
  c.beginPath();
  c.moveTo(x + r, y);
  c.arcTo(x + w, y, x + w, y + h, r);
  c.arcTo(x + w, y + h, x, y + h, r);
  c.arcTo(x, y + h, x, y, r);
  c.arcTo(x, y, x + w, y, r);
  c.closePath();
}

// ────────────────────────────────────────────────────────────────────────
// Fit to container on first paint + on resize.
// ────────────────────────────────────────────────────────────────────────
function fitToContainer() {
  const w = wrap.clientWidth, h = wrap.clientHeight;
  canvas.style.width = w + 'px';
  canvas.style.height = h + 'px';
  const sx = w / SEATING.canvas.width;
  const sy = h / SEATING.canvas.height;
  view.scale = Math.min(sx, sy) * 0.95;
  view.minScale = view.scale * 0.5;
  // Guarantee at least 4× absolute scale even on huge canvases — needed
  // so the user can always zoom enough to read seat numbers (threshold
  // 0.5). Otherwise on a 4000-px canvas in a 400-px viewport the
  // initial fit is 0.095 and 8× of that is still only 0.76.
  view.maxScale = Math.max(4.0, view.scale * 10);
  // Center
  view.tx = (w - SEATING.canvas.width * view.scale) / 2;
  view.ty = (h - SEATING.canvas.height * view.scale) / 2;
  requestPaint();
}

window.addEventListener('resize', fitToContainer);

// ────────────────────────────────────────────────────────────────────────
// Gestures — touch + mouse + wheel. We use touch events (not pointer
// events) because Android WebView delivers touches reliably while
// pointer-event capture flakes on some devices (the user reported taps
// not registering at all, while raw pinch worked). Mouse events handle
// desktop. Wheel handles desktop zoom (added in the next listener).
// ────────────────────────────────────────────────────────────────────────
const touches = new Map(); // identifier → {x, y}
let lastDist = 0;
let lastCenter = null;
let lastSingle = null;
let movedDuringTap = false;
let mouseDown = false;

// Touch-event diagnostic overlay was here during debugging; removed
// once the cross-ticket-type selection issue was fixed. Re-enable by
// pasting back the createElement + debugFlash helper if needed.
function debugFlash(_msg) { /* no-op */ }

function pageXYFromTouch(t) {
  // clientX/Y is what we want — relative to the viewport (canvas is
  // position: absolute filling the wrap).
  return { x: t.clientX, y: t.clientY };
}

function onTouchStart(e) {
  // Prevent the WebView from interpreting this as a scroll candidate.
  if (e.cancelable) e.preventDefault();
  debugFlash('touchstart ' + e.changedTouches.length);
  for (const t of e.changedTouches) {
    touches.set(t.identifier, pageXYFromTouch(t));
  }
  if (touches.size === 1) {
    lastSingle = [...touches.values()][0];
    movedDuringTap = false;
  } else if (touches.size === 2) {
    const [a, b] = [...touches.values()];
    lastDist = Math.hypot(a.x - b.x, a.y - b.y);
    lastCenter = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
  }
}

function onTouchMove(e) {
  if (e.cancelable) e.preventDefault();
  for (const t of e.changedTouches) {
    if (touches.has(t.identifier)) {
      touches.set(t.identifier, pageXYFromTouch(t));
    }
  }
  if (touches.size === 1 && lastSingle) {
    const t = [...touches.values()][0];
    const dx = t.x - lastSingle.x;
    const dy = t.y - lastSingle.y;
    if (Math.abs(dx) > 4 || Math.abs(dy) > 4) movedDuringTap = true;
    view.tx += dx; view.ty += dy;
    lastSingle = t;
    requestPaint();
  } else if (touches.size === 2) {
    const [a, b] = [...touches.values()];
    const dist = Math.hypot(a.x - b.x, a.y - b.y);
    const center = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
    const factor = dist / (lastDist || dist);
    const newScale = Math.max(view.minScale, Math.min(view.maxScale, view.scale * factor));
    const k = newScale / view.scale;
    view.tx = center.x - (center.x - view.tx) * k;
    view.ty = center.y - (center.y - view.ty) * k;
    view.scale = newScale;
    lastDist = dist;
    lastCenter = center;
    requestPaint();
  }
}

function onTouchEnd(e) {
  if (e.cancelable) e.preventDefault();
  debugFlash('touchend (drag=' + movedDuringTap + ')');
  for (const t of e.changedTouches) {
    touches.delete(t.identifier);
  }
  if (touches.size === 0 && !movedDuringTap && lastSingle) {
    handleTap(lastSingle.x, lastSingle.y);
  }
  if (touches.size < 2) lastDist = 0;
  lastSingle = touches.size === 1 ? [...touches.values()][0] : null;
}

// Register on both canvas and wrap so even if pointer-events somehow
// reroutes off the canvas, the wrap still catches the touch.
canvas.addEventListener('touchstart', onTouchStart, { passive: false });
canvas.addEventListener('touchmove', onTouchMove, { passive: false });
canvas.addEventListener('touchend', onTouchEnd, { passive: false });
canvas.addEventListener('touchcancel', onTouchEnd, { passive: false });

// Mouse events for desktop (no touch).
canvas.addEventListener('mousedown', (e) => {
  mouseDown = true;
  lastSingle = { x: e.clientX, y: e.clientY };
  movedDuringTap = false;
});
canvas.addEventListener('mousemove', (e) => {
  if (!mouseDown || !lastSingle) return;
  const dx = e.clientX - lastSingle.x;
  const dy = e.clientY - lastSingle.y;
  if (Math.abs(dx) > 4 || Math.abs(dy) > 4) movedDuringTap = true;
  view.tx += dx; view.ty += dy;
  lastSingle = { x: e.clientX, y: e.clientY };
  requestPaint();
});
canvas.addEventListener('mouseup', (e) => {
  if (mouseDown && !movedDuringTap) {
    handleTap(e.clientX, e.clientY);
  }
  mouseDown = false;
  lastSingle = null;
});
canvas.addEventListener('mouseleave', () => { mouseDown = false; lastSingle = null; });

// Desktop wheel-zoom (and mac trackpad pinch, which fires wheel with
// ctrlKey). Anchored on the cursor so users zoom where they're looking.
canvas.addEventListener('wheel', (e) => {
  e.preventDefault();
  const rect = canvas.getBoundingClientRect();
  const cx = e.clientX - rect.left;
  const cy = e.clientY - rect.top;
  // Mac pinch sends large deltaY with ctrlKey; treat both the same way
  // but tone down the per-event step to keep zoom feeling smooth.
  const step = e.ctrlKey ? 0.05 : 0.15;
  const factor = e.deltaY < 0 ? (1 + step) : 1 / (1 + step);
  const newScale = Math.max(view.minScale, Math.min(view.maxScale, view.scale * factor));
  const k = newScale / view.scale;
  view.tx = cx - (cx - view.tx) * k;
  view.ty = cy - (cy - view.ty) * k;
  view.scale = newScale;
  requestPaint();
}, { passive: false });

function handleTap(clientX, clientY) {
  const rect = canvas.getBoundingClientRect();
  // Convert viewport-CSS-pixel coords into the layout's own coordinate
  // system (the one seats were drawn in via ctx.translate + ctx.scale).
  const x = (clientX - rect.left - view.tx) / view.scale;
  const y = (clientY - rect.top - view.ty) / view.scale;

  // Linear scan — fine up to ~10k seats. For larger venues, swap to a
  // simple grid spatial index keyed by (floor(x/cellSize), floor(y/cellSize)).
  let hit = null;
  let bestDist = Infinity;
  let nearestDist = Infinity;
  let nearestUid = null;
  for (const entry of seatIndex.values()) {
    const dx = x - entry.absX;
    const dy = y - entry.absY;
    const d2 = dx * dx + dy * dy;
    const r = entry.r * 1.6; // generous hit radius for thumbs
    if (d2 < nearestDist) {
      nearestDist = d2;
      nearestUid = entry.seat.seat_uid;
    }
    if (d2 < r * r && d2 < bestDist) {
      hit = entry;
      bestDist = d2;
    }
  }
  // Diagnostic: show tap math + nearest-seat distance. Lets the user
  // tell at a glance whether the conversion is wildly off (e.g. drift
  // factor of ~10 means dpr handling is off; consistent small offset
  // means rect.top is wrong).
  debugFlash(
    'tap ' + Math.round(x) + ',' + Math.round(y) +
    ' near=' + (nearestUid || '?') +
    ' d=' + Math.round(Math.sqrt(nearestDist))
  );
  if (!hit) return;
  toggleSeat(hit);
}

function toggleSeat(entry) {
  const { seat, section, row } = entry;
  if (selectedSeats.has(seat.seat_uid)) {
    selectedSeats.delete(seat.seat_uid);
    debugFlash('deselect ' + seat.seat_uid);
  } else {
    if (!isSeatSelectable(seat)) {
      // Most common reasons a hit gets rejected:
      //   - seat.status !== 'available' (held / sold / blocked)
      //   - PRESELECTED_TT mismatches seat.ticket_type_id
      debugFlash(
        'blocked ' + seat.seat_uid +
        ' status=' + (seat.status || '?') +
        ' tt=' + (seat.ticket_type_id || 'none') +
        ' need=' + (PRESELECTED_TT || 'any')
      );
      return;
    }
    selectedSeats.add(seat.seat_uid);
    debugFlash('select ' + seat.seat_uid);
  }
  requestPaint();
  updateFooter();
}

// ────────────────────────────────────────────────────────────────────────
// Footer / cart summary.
// ────────────────────────────────────────────────────────────────────────
const summaryEl = document.getElementById('summary');
const confirmBtn = document.getElementById('confirm-btn');
const cancelBtn = document.getElementById('cancel-btn');
const selectedListEl = document.getElementById('selected-list');

function findTicketType(id) {
  return SEATING.ticket_types.find(t => Number(t.id) === Number(id));
}

function updateFooter() {
  const list = [...selectedSeats].map(uid => seatIndex.get(uid)).filter(Boolean);
  if (list.length === 0) {
    selectedListEl.classList.add('hidden');
    selectedListEl.innerHTML = '';
    summaryEl.textContent = 'Selectează locurile dorite din hartă.';
    confirmBtn.disabled = true;
    return;
  }

  // Build the per-seat detail list. One row per seat: type name +
  // position (section + row + seat number) + price + delete affordance.
  selectedListEl.classList.remove('hidden');
  selectedListEl.innerHTML = '';
  let total = 0;
  for (const e of list) {
    const { seat, row, section } = e;
    const tt = findTicketType(seat.ticket_type_id || PRESELECTED_TT);
    const ttName = tt?.name || 'Bilet';
    const ttColor = tt?.color || seat.ticket_type_color || '#10B981';
    const price = Number(seat.price || 0);
    total += price;

    const seatNum = seat.label ?? seat.seat_number ?? seat.seat_label ?? '';
    const sectionName = section?.name || '';
    const div = document.createElement('div');
    div.className = 'sel-row';
    div.innerHTML =
      '<span class="sel-dot" style="background:' + ttColor + '"></span>' +
      '<div class="sel-info">' +
        '<div class="sel-name">' + escapeHtml(ttName) + '</div>' +
        '<div class="sel-pos">' +
          (sectionName ? escapeHtml(sectionName) + ' · ' : '') +
          'Rând ' + escapeHtml(String(row.label || '')) +
          ' · Loc ' + escapeHtml(String(seatNum)) +
        '</div>' +
      '</div>' +
      '<span class="sel-price">' + price.toFixed(2) + ' RON</span>' +
      '<span class="sel-remove" data-uid="' + escapeHtml(seat.seat_uid) + '">×</span>';
    selectedListEl.appendChild(div);
  }

  // Wire up the × buttons after innerHTML rewrite.
  selectedListEl.querySelectorAll('.sel-remove').forEach(el => {
    el.addEventListener('click', () => {
      selectedSeats.delete(el.dataset.uid);
      requestPaint();
      updateFooter();
    });
  });

  summaryEl.innerHTML = '<strong>' + list.length + '</strong> locuri · <strong>' + total.toFixed(2) + ' RON</strong>';
  confirmBtn.disabled = false;
}

confirmBtn.addEventListener('click', () => {
  const list = [...selectedSeats].map(uid => seatIndex.get(uid)).filter(Boolean);
  const ttMap = new Map();
  const selectedOut = [];
  for (const e of list) {
    const { seat, section, row } = e;
    const ttId = seat.ticket_type_id || PRESELECTED_TT || null;
    const ttDef = SEATING.ticket_types.find(t => Number(t.id) === Number(ttId));
    if (ttId) {
      if (!ttMap.has(ttId)) {
        ttMap.set(ttId, {
          id: ttId,
          name: ttDef?.name || 'Bilet',
          price: ttDef?.price || seat.price || 0,
          color: ttDef?.color || seat.ticket_type_color || null,
          quantity: 0,
        });
      }
      ttMap.get(ttId).quantity++;
    }
    selectedOut.push({
      seat_uid: seat.seat_uid,
      section_name: section.name,
      row_label: row.label,
      // Geometry stores the seat number as `label`. Older payloads may
      // have used seat_number / seat_label — keep them as fallbacks.
      seat_label: seat.label ?? seat.seat_number ?? seat.seat_label ?? '',
      ticket_type_id: ttId,
      price: seat.price || 0,
    });
  }
  bridge.post('confirm', {
    cartItems: [...ttMap.values()],
    seatUids: selectedOut.map(s => s.seat_uid),
    selectedSeats: selectedOut,
  });
});

cancelBtn.addEventListener('click', () => bridge.post('cancel'));

// ────────────────────────────────────────────────────────────────────────
// Legend.
// ────────────────────────────────────────────────────────────────────────
(function renderLegend() {
  const el = document.getElementById('legend');
  for (const tt of SEATING.ticket_types) {
    if (tt.is_entry_ticket === false) continue;
    const active = PRESELECTED_TT && Number(tt.id) === Number(PRESELECTED_TT);
    const div = document.createElement('div');
    div.className = 'leg-item' + (active ? ' active' : '');
    div.innerHTML = `
      <span class="leg-dot" style="background:${tt.color || '#10B981'}"></span>
      <span>${escapeHtml(tt.name)}</span>
      <span class="leg-price">${Number(tt.price).toFixed(2)} RON</span>`;
    el.appendChild(div);
  }
  for (const [label, color] of [['Vândut', '#9CA3AF'], ['Selectat', '#a51c30']]) {
    const div = document.createElement('div');
    div.className = 'leg-item';
    div.innerHTML = `<span class="leg-dot" style="background:${color}"></span><span>${label}</span>`;
    el.appendChild(div);
  }
})();

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

// ────────────────────────────────────────────────────────────────────────
// Real-time updates — minimal Pusher protocol over a raw WebSocket.
// pusher-js 8.x had a sync 'initialized → failed' bug with our config; the
// underlying Pusher wire protocol is trivial (JSON over WS) so we just
// speak it directly. ~30 LOC instead of a 100KB CDN dependency.
// Protocol reference: https://pusher.com/docs/channels/library_auth_reference/pusher-websockets-protocol/
// ────────────────────────────────────────────────────────────────────────
const statusEl = document.getElementById('status-line');

(function connectReverb() {
  if (!REVERB || REVERB.driver !== 'reverb' || !REVERB.app_key || !REVERB.host) {
    statusEl.textContent = 'real-time off';
    return;
  }

  const channelName = 'event.' + EVENT_ID + '.seats';
  let ws = null;
  let reconnectTimer = null;
  let pingTimer = null;
  let reconnectDelay = 1000; // start at 1s, exponential back-off capped at 30s

  function buildWsUrl() {
    const scheme = REVERB.scheme === 'https' ? 'wss' : 'ws';
    const portPart = (REVERB.scheme === 'https' && REVERB.port === 443)
      || (REVERB.scheme !== 'https' && REVERB.port === 80)
      ? '' : ':' + REVERB.port;
    const path = REVERB.path || '';
    return `${scheme}://${REVERB.host}${portPart}${path}/app/${REVERB.app_key}?protocol=7&client=js&version=8.4.0&flash=false`;
  }

  function setStatus(text, connected) {
    statusEl.textContent = text;
    statusEl.classList.toggle('connected', !!connected);
  }

  function applySeatChange(payload) {
    if (!payload || !Array.isArray(payload.seats)) return;
    for (const u of payload.seats) {
      const entry = seatIndex.get(u.seat_uid);
      if (entry) {
        entry.seat.status = u.status;
        // If we held a seat that someone else just sold, clear it.
        if (u.status !== 'available' && u.status !== 'held' && selectedSeats.has(u.seat_uid)) {
          selectedSeats.delete(u.seat_uid);
        }
      }
    }
    updateFooter();
    requestPaint();
  }

  function connect() {
    try {
      ws = new WebSocket(buildWsUrl());
    } catch (e) {
      setStatus('real-time error');
      scheduleReconnect();
      return;
    }
    setStatus('connecting…');

    ws.onopen = () => {
      // No-op — wait for pusher:connection_established before subscribing.
    };

    ws.onmessage = (evt) => {
      let frame;
      try { frame = JSON.parse(evt.data); } catch { return; }
      // Pusher frames double-encode `data` for application events.
      const data = typeof frame.data === 'string'
        ? (frame.data ? safeParse(frame.data) : null)
        : frame.data;

      switch (frame.event) {
        case 'pusher:connection_established':
          setStatus('● real-time', true);
          reconnectDelay = 1000;
          // Subscribe to the public seat channel.
          ws.send(JSON.stringify({
            event: 'pusher:subscribe',
            data: { channel: channelName },
          }));
          // Start a keep-alive ping every 30s (Reverb expects activity).
          clearInterval(pingTimer);
          pingTimer = setInterval(() => {
            if (ws && ws.readyState === WebSocket.OPEN) {
              ws.send(JSON.stringify({ event: 'pusher:ping', data: {} }));
            }
          }, 30000);
          break;
        case 'pusher:error':
          console.warn('reverb error', data);
          break;
        case 'pusher:pong':
          // Reverb is alive — nothing to do.
          break;
        case 'pusher_internal:subscription_succeeded':
          // Subscribed — no-op.
          break;
        case 'seat.status.changed':
          applySeatChange(data);
          break;
        default:
          // Other internal events (subscription_count etc) — ignore.
          break;
      }
    };

    ws.onclose = () => {
      clearInterval(pingTimer);
      pingTimer = null;
      setStatus('reconnecting…');
      scheduleReconnect();
    };

    ws.onerror = () => {
      // onclose fires right after; reconnect logic lives there.
    };
  }

  function scheduleReconnect() {
    clearTimeout(reconnectTimer);
    reconnectTimer = setTimeout(connect, reconnectDelay);
    reconnectDelay = Math.min(reconnectDelay * 2, 30000);
  }

  function safeParse(s) { try { return JSON.parse(s); } catch { return null; } }

  connect();
})();

// ────────────────────────────────────────────────────────────────────────
// Boot.
// ────────────────────────────────────────────────────────────────────────
fitToContainer();
bridge.post('ready', { event_id: EVENT_ID, seat_count: seatIndex.size });
</script>
</body>
</html>
