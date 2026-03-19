import React, { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  Dimensions,
  Modal,
  PanResponder,
  Animated,
} from 'react-native';
import Svg, { Circle, Rect, G, Text as SvgText, Path } from 'react-native-svg';
import { colors } from '../theme/colors';
import { formatCurrency } from '../utils/formatCurrency';
import { apiGet } from '../api/client';

const { width: SCREEN_WIDTH } = Dimensions.get('window');

// ─── Icons ───────────────────────────────────────────────────────────────────
function ArrowLeftIcon({ size = 24, color = colors.white }) {
  return <Svg width={size} height={size} viewBox="0 0 24 24" fill="none"><Path d="M19 12H5M12 19l-7-7 7-7" stroke={color} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" /></Svg>;
}
function CheckIcon({ size = 20, color = colors.white }) {
  return <Svg width={size} height={size} viewBox="0 0 24 24" fill="none"><Path d="M20 6L9 17l-5-5" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" /></Svg>;
}
function ZoomInIcon({ size = 18, color = colors.white }) {
  return <Svg width={size} height={size} viewBox="0 0 24 24" fill="none"><Path d="M11 19a8 8 0 100-16 8 8 0 000 16zM21 21l-4.35-4.35M11 8v6M8 11h6" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" /></Svg>;
}
function ZoomOutIcon({ size = 18, color = colors.white }) {
  return <Svg width={size} height={size} viewBox="0 0 24 24" fill="none"><Path d="M11 19a8 8 0 100-16 8 8 0 000 16zM21 21l-4.35-4.35M8 11h6" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" /></Svg>;
}

// ─── Coordinate helpers (center transform origin) ────────────────────────────
// With default RN transform origin at view center (cw/2, ch/2):
//   screenX = tx + cw/2 + (canvasX - cw/2) * scale
//   screenY = ty + ch/2 + (canvasY - ch/2) * scale

function screenToCanvas(scrX, scrY, tx, ty, s, cw, ch) {
  return {
    x: cw / 2 + (scrX - tx - cw / 2) / s,
    y: ch / 2 + (scrY - ty - ch / 2) / s,
  };
}

// ─── Main Component ──────────────────────────────────────────────────────────

export default function SeatingMapScreen({ visible, eventId, ticketTypeId, onConfirm, onClose }) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [mapData, setMapData] = useState(null);
  const [selectedSeats, setSelectedSeats] = useState([]);
  const [mapSize, setMapSize] = useState({ width: SCREEN_WIDTH, height: 500 });

  // Animated values — useNativeDriver means NO re-renders during gestures
  const scaleAnim = useRef(new Animated.Value(1)).current;
  const txAnim = useRef(new Animated.Value(0)).current;
  const tyAnim = useRef(new Animated.Value(0)).current;

  // Mutable gesture state (all accessed via ref, never stale)
  const gs = useRef({ s: 1, tx: 0, ty: 0 });
  const sizeRef = useRef({ width: SCREEN_WIDTH, height: 500 });
  const canvasRef = useRef({ width: 1000, height: 800 });
  const offsetRef = useRef({ x: 0, y: 0 }); // mapContainer screen position
  const seatsRef = useRef([]);
  const selectedRef = useRef(new Set());
  const toggleRef = useRef(null);
  const lastTapTime = useRef(0);

  useEffect(() => {
    if (visible && eventId) loadSeatingMap();
    if (!visible) { setSelectedSeats([]); setError(null); }
  }, [visible, eventId]);

  const loadSeatingMap = async () => {
    setLoading(true); setError(null);
    try {
      const r = await apiGet(`/organizer/events/${eventId}/seating-map`);
      if (r.success && r.data) setMapData(r.data);
      else setError(r.message || 'Nu s-a putut incarca harta');
    } catch (e) { setError(e.message || 'Eroare'); }
    setLoading(false);
  };

  const canvas = mapData?.canvas || { width: 1000, height: 800 };
  const cw = canvas.width;
  const ch = canvas.height;
  useEffect(() => { canvasRef.current = { width: cw, height: ch }; }, [cw, ch]);

  // Fit scale
  const fitZoom = useMemo(() => {
    const sw = sizeRef.current.width || mapSize.width;
    const sh = sizeRef.current.height || mapSize.height;
    return Math.min(sw / cw, sh / ch) * 0.95; // 95% to add small padding
  }, [cw, ch, mapSize]);

  // Center + fit on load
  const centerMap = useCallback((s) => {
    const sw = sizeRef.current.width;
    const sh = sizeRef.current.height;
    const cv = canvasRef.current;
    const tx = (sw - cv.width) / 2;
    const ty = (sh - cv.height) / 2;
    gs.current = { s, tx, ty };
    scaleAnim.setValue(s);
    txAnim.setValue(tx);
    tyAnim.setValue(ty);
  }, []);

  useEffect(() => {
    if (mapData && mapSize.width > 0 && mapSize.height > 0) {
      centerMap(fitZoom);
    }
  }, [mapData, fitZoom, mapSize]);

  const onMapLayout = useCallback((e) => {
    const { width, height } = e.nativeEvent.layout;
    if (width > 0 && height > 0) {
      sizeRef.current = { width, height };
      setMapSize({ width, height });
    }
    if (e.target?.measureInWindow) {
      e.target.measureInWindow((x, y) => { offsetRef.current = { x: x || 0, y: y || 0 }; });
    }
  }, []);

  // Process seats
  const processedData = useMemo(() => {
    if (!mapData) return { seats: [], rowLabels: [], sections: [] };
    const { sections, seats } = mapData;
    const statusMap = {};
    (seats || []).forEach(s => { statusMap[s.seat_uid] = s; });
    const seatList = [], rowLabels = [];

    (sections || []).forEach(sec => {
      const sx = sec.x_position || sec.x || 0;
      const sy = sec.y_position || sec.y || 0;
      const meta = sec.metadata || {};
      const seatSize = parseInt(meta.seat_size) || 15;
      const r = seatSize / 2;

      (sec.rows || []).forEach(row => {
        const f = row.seats?.[0];
        if (f) rowLabels.push({ key: `${sec.name}-${row.label}`, x: sx + (f.x || 0) - r - 6, y: sy + (f.y || 0) + r * 0.35, label: row.label, fontSize: Math.max(r * 0.85, 7) });
        (row.seats || []).forEach(seat => {
          const uid = seat.seat_uid || seat.uid;
          const info = statusMap[uid] || {};
          seatList.push({
            seat_uid: uid, cx: sx + (seat.x || 0), cy: sy + (seat.y || 0), seatSize, seatRadius: r,
            status: info.status || 'available', section_name: info.section_name || sec.name || '',
            row_label: info.row_label || row.label || '', seat_label: info.seat_label || seat.label || '',
            ticket_type_id: info.ticket_type_id, ticket_type_name: info.ticket_type_name,
            price: info.price || 0, color: info.color || '#10B981',
            isAllowed: !ticketTypeId || info.ticket_type_id == ticketTypeId,
          });
        });
      });
    });
    seatsRef.current = seatList;
    return { seats: seatList, rowLabels, sections: sections || [] };
  }, [mapData, ticketTypeId]);

  const selectedUids = useMemo(() => {
    const set = new Set();
    selectedSeats.forEach(s => set.add(s.seat_uid));
    selectedRef.current = set;
    return set;
  }, [selectedSeats]);

  const toggleSeat = useCallback((seat) => {
    if (!seat.isAllowed) return;
    setSelectedSeats(prev => {
      const exists = prev.find(s => s.seat_uid === seat.seat_uid);
      if (exists) return prev.filter(s => s.seat_uid !== seat.seat_uid);
      return [...prev, seat];
    });
  }, []);
  useEffect(() => { toggleRef.current = toggleSeat; }, [toggleSeat]);

  // ─── PanResponder (gesture handling) ───────────────────────────────────────

  const panResponder = useMemo(() => {
    let isPinching = false;
    let moved = false;
    let startDist = 0;
    let startFocalX = 0, startFocalY = 0;
    let savedS = 1, savedTx = 0, savedTy = 0;
    let touchStartX = 0, touchStartY = 0, touchStartTime = 0;
    // Canvas point under initial focal (computed once at pinch start)
    let focalCanvasX = 0, focalCanvasY = 0;

    function set(s, tx, ty) {
      gs.current = { s, tx, ty };
      scaleAnim.setValue(s);
      txAnim.setValue(tx);
      tyAnim.setValue(ty);
    }

    return PanResponder.create({
      onStartShouldSetPanResponder: () => true,
      onMoveShouldSetPanResponder: () => true,
      onStartShouldSetPanResponderCapture: () => true,
      onMoveShouldSetPanResponderCapture: () => true,

      onPanResponderGrant: (evt) => {
        const t = evt.nativeEvent.touches;
        const g = gs.current;
        savedS = g.s; savedTx = g.tx; savedTy = g.ty;
        touchStartX = t[0].pageX; touchStartY = t[0].pageY;
        touchStartTime = Date.now();
        moved = false;
        isPinching = false;

        if (t.length >= 2) {
          isPinching = true;
          const dx = t[0].pageX - t[1].pageX;
          const dy = t[0].pageY - t[1].pageY;
          startDist = Math.sqrt(dx * dx + dy * dy);
          // Focal point in mapContainer coordinates
          const off = offsetRef.current;
          startFocalX = (t[0].pageX + t[1].pageX) / 2 - off.x;
          startFocalY = (t[0].pageY + t[1].pageY) / 2 - off.y;
          // Compute canvas point under focal
          const cv = canvasRef.current;
          const cp = screenToCanvas(startFocalX, startFocalY, savedTx, savedTy, savedS, cv.width, cv.height);
          focalCanvasX = cp.x;
          focalCanvasY = cp.y;
        }
      },

      onPanResponderMove: (evt) => {
        const t = evt.nativeEvent.touches;
        if (!t || t.length === 0) return;
        const cv = canvasRef.current;
        const off = offsetRef.current;

        if (t.length >= 2) {
          // ── PINCH + PAN ──
          isPinching = true; moved = true;
          const dx = t[0].pageX - t[1].pageX;
          const dy = t[0].pageY - t[1].pageY;
          const newDist = Math.sqrt(dx * dx + dy * dy);
          if (newDist < 5) return;

          const minS = fitZoom * 0.5;
          const maxS = fitZoom * 10;
          const newS = Math.min(maxS, Math.max(minS, savedS * (newDist / startDist)));

          // Current focal in mapContainer coords
          const curFocalX = (t[0].pageX + t[1].pageX) / 2 - off.x;
          const curFocalY = (t[0].pageY + t[1].pageY) / 2 - off.y;

          // Adjust tx/ty so focalCanvasPoint stays under curFocal
          // screenX = tx + cw/2 + (cx - cw/2) * s
          // tx = screenX - cw/2 - (cx - cw/2) * s
          const newTx = curFocalX - cv.width / 2 - (focalCanvasX - cv.width / 2) * newS;
          const newTy = curFocalY - cv.height / 2 - (focalCanvasY - cv.height / 2) * newS;

          set(newS, newTx, newTy);
        } else if (!isPinching) {
          // ── SINGLE FINGER PAN ──
          const dx = t[0].pageX - touchStartX;
          const dy = t[0].pageY - touchStartY;
          if (Math.abs(dx) > 5 || Math.abs(dy) > 5) moved = true;
          set(savedS, savedTx + dx, savedTy + dy);
        }
      },

      onPanResponderRelease: (evt) => {
        if (isPinching || moved) { isPinching = false; return; }

        // ── TAP ──
        const elapsed = Date.now() - touchStartTime;
        if (elapsed > 500) return;
        const now = Date.now();
        if (now - lastTapTime.current < 400) return; // debounce
        lastTapTime.current = now;

        const t = evt.nativeEvent.changedTouches?.[0] || evt.nativeEvent;
        const off = offsetRef.current;
        const cv = canvasRef.current;
        const g = gs.current;
        const localX = (t.pageX || touchStartX) - off.x;
        const localY = (t.pageY || touchStartY) - off.y;
        const cp = screenToCanvas(localX, localY, g.tx, g.ty, g.s, cv.width, cv.height);

        // Find nearest clickable seat
        const seats = seatsRef.current;
        const uids = selectedRef.current;
        let best = null, bestD = Infinity;
        for (let i = 0; i < seats.length; i++) {
          const s = seats[i];
          const clickable = ((s.status === 'available') && s.isAllowed) || uids.has(s.seat_uid);
          if (!clickable) continue;
          const ddx = cp.x - s.cx, ddy = cp.y - s.cy;
          const d = ddx * ddx + ddy * ddy;
          const hr = Math.max(s.seatRadius * 2, 15);
          if (d < hr * hr && d < bestD) { bestD = d; best = s; }
        }
        if (best && toggleRef.current) toggleRef.current(best);
        isPinching = false;
      },
      onPanResponderTerminate: () => { isPinching = false; },
    });
  }, [fitZoom]); // recreate when fitZoom changes (after map loads)

  // ─── Zoom buttons ──────────────────────────────────────────────────────────

  const zoomCenter = useCallback((factor) => {
    const g = gs.current;
    const cv = canvasRef.current;
    const sz = sizeRef.current;
    const newS = Math.min(fitZoom * 10, Math.max(fitZoom * 0.5, g.s * factor));
    // Zoom toward screen center
    const centerX = sz.width / 2;
    const centerY = sz.height / 2;
    const cp = screenToCanvas(centerX, centerY, g.tx, g.ty, g.s, cv.width, cv.height);
    const newTx = centerX - cv.width / 2 - (cp.x - cv.width / 2) * newS;
    const newTy = centerY - cv.height / 2 - (cp.y - cv.height / 2) * newS;
    gs.current = { s: newS, tx: newTx, ty: newTy };
    Animated.parallel([
      Animated.spring(scaleAnim, { toValue: newS, useNativeDriver: true, friction: 8 }),
      Animated.spring(txAnim, { toValue: newTx, useNativeDriver: true, friction: 8 }),
      Animated.spring(tyAnim, { toValue: newTy, useNativeDriver: true, friction: 8 }),
    ]).start();
  }, [fitZoom]);

  const resetView = useCallback(() => {
    centerMap(fitZoom);
  }, [fitZoom, centerMap]);

  const selectedTotal = useMemo(() => selectedSeats.reduce((sum, s) => sum + (s.price || 0), 0), [selectedSeats]);
  const selectedByType = useMemo(() => {
    const g = {};
    selectedSeats.forEach(s => {
      const k = s.ticket_type_id || '?';
      if (!g[k]) g[k] = { ticket_type_id: s.ticket_type_id, name: s.ticket_type_name || 'Bilet', price: s.price, color: s.color, seats: [] };
      g[k].seats.push(s);
    });
    return Object.values(g);
  }, [selectedSeats]);

  const handleConfirm = () => {
    if (!selectedSeats.length) return;
    onConfirm({
      cartItems: selectedByType.map(g => ({ id: g.ticket_type_id, name: g.name, price: g.price, color: g.color, quantity: g.seats.length })),
      seatUids: selectedSeats.map(s => s.seat_uid),
      selectedSeats: selectedSeats.map(s => ({ seat_uid: s.seat_uid, section_name: s.section_name, row_label: s.row_label, seat_label: s.seat_label, ticket_type_id: s.ticket_type_id, price: s.price })),
    });
  };

  const ticketTypeLegend = useMemo(() => {
    const types = mapData?.ticket_types || [];
    if (ticketTypeId) return types.filter(t => t.id == ticketTypeId);
    return types;
  }, [mapData, ticketTypeId]);

  // ─── Render ────────────────────────────────────────────────────────────────

  if (!visible) return null;

  const renderMap = () => {
    if (loading) return <View style={styles.center}><ActivityIndicator size="large" color={colors.purple} /><Text style={styles.loadingText}>Se incarca harta...</Text></View>;
    if (error) return <View style={styles.center}><Text style={styles.errorText}>{error}</Text><TouchableOpacity style={styles.retryBtn} onPress={loadSeatingMap}><Text style={styles.retryText}>Reincearca</Text></TouchableOpacity></View>;

    return (
      <>
        {/* Legend */}
        <View style={styles.legend}>
          <View style={styles.legendRow}>
            {ticketTypeLegend.map(tt => (
              <View key={tt.id} style={[styles.legendItem, ticketTypeId && tt.id == ticketTypeId && styles.legendActive]}>
                <View style={[styles.legendDot, { backgroundColor: tt.color || '#10B981' }]} />
                <Text style={styles.legendText}>{tt.name}</Text>
                <Text style={styles.legendPrice}>{formatCurrency(tt.price)}</Text>
              </View>
            ))}
            <View style={styles.legendItem}><View style={[styles.legendDot, { backgroundColor: '#9CA3AF' }]} /><Text style={styles.legendText}>Vandut</Text></View>
            <View style={styles.legendItem}><View style={[styles.legendDot, { backgroundColor: '#a51c30' }]} /><Text style={styles.legendText}>Selectat</Text></View>
          </View>
        </View>

        {/* Map — PanResponder on container, Animated.View inside */}
        <View style={styles.mapContainer} onLayout={onMapLayout} {...panResponder.panHandlers}>
          <Animated.View
            pointerEvents="none"
            style={{
              position: 'absolute',
              left: 0,
              top: 0,
              width: cw,
              height: ch,
              transform: [
                { translateX: txAnim },
                { translateY: tyAnim },
                { scale: scaleAnim },
              ],
            }}
          >
            <Svg width={cw} height={ch} viewBox={`0 0 ${cw} ${ch}`}>
              <Rect x={0} y={0} width={cw} height={ch} fill="#0f0f1a" rx={8} />

              {/* Section labels */}
              {processedData.sections.map((sec, i) => {
                const sx = sec.x_position || sec.x || 0;
                const sy = sec.y_position || sec.y || 0;
                const rot = sec.rotation || 0;
                const sw = sec.width || 100, sh = sec.height || 100;
                const rcx = sx + sw / 2, rcy = sy + sh / 2;
                return (
                  <SvgText key={`s${i}`} x={sx + sw / 2} y={sy - 12} fill="rgba(255,255,255,0.5)" fontSize={12} fontWeight="700" textAnchor="middle"
                    transform={rot ? `rotate(${rot} ${rcx} ${rcy})` : undefined}>
                    {sec.name || ''}
                  </SvgText>
                );
              })}

              {/* Row labels */}
              {processedData.rowLabels.map(rl => (
                <SvgText key={rl.key} x={rl.x} y={rl.y} textAnchor="end" fontSize={rl.fontSize} fontWeight="500" fill="rgba(255,255,255,0.45)">{rl.label}</SvgText>
              ))}

              {/* Seats — STATIC, no re-renders during gestures */}
              {processedData.seats.map(seat => {
                const sel = selectedUids.has(seat.seat_uid);
                const r = seat.seatRadius;
                const avail = seat.status === 'available';
                let fill, stroke, sw, op;
                if (sel) { fill = '#a51c30'; stroke = '#7a141f'; sw = 1.5; op = 1; }
                else if (avail && seat.isAllowed) { fill = seat.color; stroke = '#ffffff'; sw = 0.8; op = 1; }
                else if (avail) { fill = '#2D2D3D'; stroke = 'rgba(255,255,255,0.1)'; sw = 0.5; op = 0.4; }
                else { fill = '#9CA3AF'; stroke = 'rgba(255,255,255,0.15)'; sw = 0.5; op = seat.status === 'disabled' ? 0.25 : 0.45; }
                const dr = sel ? r * 1.15 : r;
                const fs = Math.round(r * 0.85 * 10) / 10;
                return (
                  <G key={seat.seat_uid}>
                    <Circle cx={seat.cx} cy={seat.cy} r={dr} fill={fill} stroke={stroke} strokeWidth={sw} opacity={op} />
                    {r >= 5 && <SvgText x={seat.cx} y={seat.cy + fs * 0.35} textAnchor="middle" fontSize={fs} fontWeight="700" fill={sel ? '#fff' : 'rgba(255,255,255,0.85)'} opacity={op}>{seat.seat_label}</SvgText>}
                  </G>
                );
              })}
            </Svg>
          </Animated.View>

          {/* Zoom controls */}
          <View style={styles.zoomBtns}>
            <TouchableOpacity style={styles.zoomBtn} onPress={() => zoomCenter(1.8)} activeOpacity={0.7}><ZoomInIcon size={18} color={colors.textPrimary} /></TouchableOpacity>
            <TouchableOpacity style={styles.zoomBtn} onPress={() => zoomCenter(1 / 1.8)} activeOpacity={0.7}><ZoomOutIcon size={18} color={colors.textPrimary} /></TouchableOpacity>
            <TouchableOpacity style={styles.zoomBtn} onPress={resetView} activeOpacity={0.7}><Text style={styles.resetLabel}>FIT</Text></TouchableOpacity>
          </View>
        </View>

        {/* Bottom bar */}
        {selectedSeats.length > 0 && (
          <View style={styles.bottomBar}>
            <View style={{ flex: 1 }}>
              <Text style={styles.bbCount}>{selectedSeats.length} {selectedSeats.length === 1 ? 'loc' : 'locuri'}</Text>
              <Text style={styles.bbTotal}>{formatCurrency(selectedTotal)}</Text>
              <View style={styles.chipRow}>
                {selectedSeats.map(s => <View key={s.seat_uid} style={styles.chip}><Text style={styles.chipText}>R{s.row_label}-{s.seat_label}</Text></View>)}
              </View>
            </View>
            <TouchableOpacity style={styles.confirmBtn} onPress={handleConfirm} activeOpacity={0.8}>
              <CheckIcon size={20} color={colors.white} /><Text style={styles.confirmText}>Confirma</Text>
            </TouchableOpacity>
          </View>
        )}
      </>
    );
  };

  return (
    <Modal visible={visible} animationType="slide" statusBarTranslucent onRequestClose={onClose}>
      <View style={styles.container}>
        <View style={styles.header}>
          <TouchableOpacity style={styles.backBtn} onPress={onClose} activeOpacity={0.7}><ArrowLeftIcon size={20} color={colors.textPrimary} /></TouchableOpacity>
          <Text style={styles.headerTitle}>{loading ? 'Harta Locurilor' : 'Selecteaza Locuri'}</Text>
          {selectedSeats.length > 0 ? <View style={styles.badge}><Text style={styles.badgeText}>{selectedSeats.length}</Text></View> : <View style={{ width: 40 }} />}
        </View>
        {renderMap()}
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  header: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingTop: 48, paddingBottom: 8, borderBottomWidth: 1, borderBottomColor: colors.border, gap: 12, backgroundColor: colors.background },
  backBtn: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { flex: 1, fontSize: 18, fontWeight: '700', color: colors.textPrimary },
  badge: { backgroundColor: '#a51c30', borderRadius: 12, minWidth: 28, height: 28, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 8 },
  badgeText: { fontSize: 14, fontWeight: '700', color: colors.white },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 16 },
  loadingText: { fontSize: 15, color: colors.textSecondary },
  errorText: { fontSize: 15, color: colors.red, textAlign: 'center', marginBottom: 16 },
  retryBtn: { backgroundColor: colors.purple, paddingHorizontal: 24, paddingVertical: 12, borderRadius: 12 },
  retryText: { fontSize: 15, fontWeight: '600', color: colors.white },
  legend: { borderBottomWidth: 1, borderBottomColor: colors.border, paddingVertical: 8, paddingHorizontal: 16 },
  legendRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  legendItem: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  legendActive: { backgroundColor: colors.purpleBg, borderRadius: 8, paddingHorizontal: 8, paddingVertical: 4, borderWidth: 1, borderColor: colors.purpleBorder },
  legendDot: { width: 10, height: 10, borderRadius: 5 },
  legendText: { fontSize: 11, color: colors.textSecondary },
  legendPrice: { fontSize: 11, fontWeight: '600', color: colors.textTertiary },
  mapContainer: { flex: 1, overflow: 'hidden', backgroundColor: '#0f0f1a' },
  zoomBtns: { position: 'absolute', right: 12, top: 12, gap: 6 },
  zoomBtn: { width: 40, height: 40, borderRadius: 10, backgroundColor: 'rgba(10,10,15,0.9)', borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  resetLabel: { fontSize: 11, fontWeight: '700', color: colors.textSecondary },
  bottomBar: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingVertical: 12, paddingBottom: 28, borderTopWidth: 1, borderTopColor: colors.border, backgroundColor: 'rgba(10,10,15,0.98)', gap: 12 },
  bbCount: { fontSize: 14, fontWeight: '600', color: colors.textPrimary },
  bbTotal: { fontSize: 18, fontWeight: '700', color: '#a51c30', marginBottom: 4 },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 4 },
  chip: { backgroundColor: 'rgba(165,28,48,0.15)', borderWidth: 1, borderColor: 'rgba(165,28,48,0.3)', borderRadius: 6, paddingHorizontal: 6, paddingVertical: 2 },
  chipText: { fontSize: 11, fontWeight: '600', color: '#a51c30' },
  confirmBtn: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#a51c30', paddingHorizontal: 20, paddingVertical: 14, borderRadius: 12, gap: 8 },
  confirmText: { fontSize: 16, fontWeight: '700', color: colors.white },
});
