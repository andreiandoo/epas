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
  #seat-canvas { position: absolute; top: 0; left: 0; transform-origin: 0 0; }
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
    flex-shrink: 0; padding: 12px 16px;
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
// Bridge to React Native WebView. window.ReactNativeWebView.postMessage
// is injected when running inside the mobile WebView. Wrapped to also
// work in a regular browser for debugging.
// ────────────────────────────────────────────────────────────────────────
const bridge = {
  post(type, data = {}) {
    const msg = JSON.stringify({ type, ...data });
    if (window.ReactNativeWebView && window.ReactNativeWebView.postMessage) {
      window.ReactNativeWebView.postMessage(msg);
    } else {
      console.log('[bridge]', msg);
    }
  },
};

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
  // Available — color by ticket type (or default green)
  return seat.ticket_type_color || '#10B981';
}

function isSeatSelectable(seat) {
  if (seat.status !== 'available') return false;
  // If a ticket type was pre-selected, only allow seats of that type. POS
  // operators picking one ticket type at a time keep things unambiguous.
  if (PRESELECTED_TT && seat.ticket_type_id && Number(seat.ticket_type_id) !== Number(PRESELECTED_TT)) {
    return false;
  }
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

  // Section container outlines — faint. Section names are hidden by
  // default (visual noise on seated sections), BUT sections without any
  // seats are structural / decorative labels (stage, bar, entrance, etc.)
  // and need their name visible at every zoom level.
  for (const section of SEATING.sections) {
    const sx = section.x || 0, sy = section.y || 0;
    const sw = section.width || 100, sh = section.height || 100;
    ctx.strokeStyle = 'rgba(139,92,246,0.18)';
    ctx.lineWidth = 1;
    ctx.strokeRect(sx, sy, sw, sh);

    const hasSeats = (section.rows || []).some(r => (r.seats || []).length > 0);
    if (!hasSeats && section.name) {
      ctx.fillStyle = 'rgba(255,255,255,0.75)';
      ctx.font = 'bold 14px system-ui';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(section.name, sx + sw / 2, sy + sh / 2);
    }
  }

  // Row labels — drawn to the left of the first seat in each row when
  // zoom is high enough to make text legible.
  const showRowLabels = view.scale > 0.5;
  if (showRowLabels) {
    ctx.fillStyle = 'rgba(255,255,255,0.65)';
    ctx.font = '700 9px system-ui';
    ctx.textAlign = 'right';
    ctx.textBaseline = 'middle';
    for (const section of SEATING.sections) {
      const sx = section.x || 0, sy = section.y || 0;
      const seatR = (section.seat_size || 14) / 2;
      for (const row of section.rows || []) {
        if (!row.seats || row.seats.length === 0) continue;
        // Leftmost seat in the row anchors the label position.
        let left = row.seats[0];
        for (const s of row.seats) {
          if ((s.x || 0) < (left.x || 0)) left = s;
        }
        const lx = sx + (left.x || 0) - seatR - 4;
        const ly = sy + (left.y || 0);
        ctx.fillText(String(row.label || ''), lx, ly);
      }
    }
  }

  // Seats + seat numbers. Lower threshold than row labels — numbers
  // need a slightly larger zoom to stay legible inside the seat circle.
  const showSeatNumbers = view.scale > 0.9;
  for (const entry of seatIndex.values()) {
    const { seat, absX, absY, r } = entry;
    ctx.beginPath();
    ctx.arc(absX, absY, r, 0, Math.PI * 2);
    ctx.fillStyle = seatColor(seat);
    ctx.fill();

    if (selectedSeats.has(seat.seat_uid)) {
      ctx.strokeStyle = '#fff';
      ctx.lineWidth = 2 / view.scale;
      ctx.stroke();
    }

    if (showSeatNumbers && seat.seat_number != null) {
      ctx.fillStyle = '#fff';
      ctx.font = `700 ${Math.max(7, r * 0.95)}px system-ui`;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(String(seat.seat_number), absX, absY);
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
  view.maxScale = view.scale * 8;
  // Center
  view.tx = (w - SEATING.canvas.width * view.scale) / 2;
  view.ty = (h - SEATING.canvas.height * view.scale) / 2;
  requestPaint();
}

window.addEventListener('resize', fitToContainer);

// ────────────────────────────────────────────────────────────────────────
// Gestures — pinch + pan + tap. Pure pointer events, no library.
// ────────────────────────────────────────────────────────────────────────
const pointers = new Map();
let lastDist = 0;
let lastCenter = null;
let lastSingle = null;
let movedDuringTap = false;

canvas.addEventListener('pointerdown', (e) => {
  canvas.setPointerCapture(e.pointerId);
  pointers.set(e.pointerId, { x: e.clientX, y: e.clientY, startX: e.clientX, startY: e.clientY });
  if (pointers.size === 1) {
    lastSingle = { x: e.clientX, y: e.clientY };
    movedDuringTap = false;
  } else if (pointers.size === 2) {
    const [a, b] = [...pointers.values()];
    lastDist = Math.hypot(a.x - b.x, a.y - b.y);
    lastCenter = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
  }
});

canvas.addEventListener('pointermove', (e) => {
  const p = pointers.get(e.pointerId);
  if (!p) return;
  p.x = e.clientX; p.y = e.clientY;

  if (pointers.size === 1 && lastSingle) {
    const dx = e.clientX - lastSingle.x;
    const dy = e.clientY - lastSingle.y;
    if (Math.abs(dx) > 4 || Math.abs(dy) > 4) movedDuringTap = true;
    view.tx += dx; view.ty += dy;
    lastSingle = { x: e.clientX, y: e.clientY };
    requestPaint();
  } else if (pointers.size === 2) {
    const [a, b] = [...pointers.values()];
    const dist = Math.hypot(a.x - b.x, a.y - b.y);
    const center = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
    const factor = dist / lastDist;
    const newScale = Math.max(view.minScale, Math.min(view.maxScale, view.scale * factor));
    // Zoom around the pinch center
    const k = newScale / view.scale;
    view.tx = center.x - (center.x - view.tx) * k;
    view.ty = center.y - (center.y - view.ty) * k;
    view.scale = newScale;
    lastDist = dist;
    lastCenter = center;
    requestPaint();
  }
});

canvas.addEventListener('pointerup', (e) => {
  const p = pointers.get(e.pointerId);
  if (p && pointers.size === 1 && !movedDuringTap) {
    handleTap(e.clientX, e.clientY);
  }
  pointers.delete(e.pointerId);
  if (pointers.size < 2) lastDist = 0;
  lastSingle = pointers.size === 1 ? [...pointers.values()][0] : null;
});

canvas.addEventListener('pointercancel', (e) => {
  pointers.delete(e.pointerId);
});

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
  const x = (clientX - rect.left - view.tx) / view.scale;
  const y = (clientY - rect.top - view.ty) / view.scale;

  // Linear scan — fine up to ~10k seats. For larger venues, swap to a
  // simple grid spatial index keyed by (floor(x/cellSize), floor(y/cellSize)).
  let hit = null;
  let bestDist = Infinity;
  for (const entry of seatIndex.values()) {
    const dx = x - entry.absX;
    const dy = y - entry.absY;
    const d2 = dx * dx + dy * dy;
    const r = entry.r * 1.6; // generous hit radius for thumbs
    if (d2 < r * r && d2 < bestDist) {
      hit = entry;
      bestDist = d2;
    }
  }
  if (!hit) return;
  toggleSeat(hit);
}

function toggleSeat(entry) {
  const { seat, section, row } = entry;
  if (selectedSeats.has(seat.seat_uid)) {
    selectedSeats.delete(seat.seat_uid);
  } else {
    if (!isSeatSelectable(seat)) return;
    selectedSeats.add(seat.seat_uid);
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
  // position (row + seat number) + price + delete affordance.
  selectedListEl.classList.remove('hidden');
  selectedListEl.innerHTML = '';
  let total = 0;
  for (const e of list) {
    const { seat, row } = e;
    const tt = findTicketType(seat.ticket_type_id || PRESELECTED_TT);
    const ttName = tt?.name || 'Bilet';
    const ttColor = tt?.color || seat.ticket_type_color || '#10B981';
    const price = Number(seat.price || 0);
    total += price;

    const div = document.createElement('div');
    div.className = 'sel-row';
    div.innerHTML =
      '<span class="sel-dot" style="background:' + ttColor + '"></span>' +
      '<div class="sel-info">' +
        '<div class="sel-name">' + escapeHtml(ttName) + '</div>' +
        '<div class="sel-pos">Rând ' + escapeHtml(String(row.label || '')) +
          ' · Loc ' + escapeHtml(String(seat.seat_number || '')) + '</div>' +
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
      seat_label: seat.seat_number,
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
