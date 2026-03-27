import React, { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  Dimensions,
  Animated,
  Modal,
} from 'react-native';
import Svg, { Circle, Rect, G, Text as SvgText, Path } from 'react-native-svg';
import { GestureHandlerRootView, GestureDetector, Gesture, ScrollView } from 'react-native-gesture-handler';
import { colors } from '../theme/colors';
import { formatCurrency } from '../utils/formatCurrency';
import { apiGet } from '../api/client';

const { width: SCREEN_WIDTH } = Dimensions.get('window');

// ─── SVG Icons ───────────────────────────────────────────────────────────────

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

// ─── Main Component ──────────────────────────────────────────────────────────

export default function SeatingMapScreen({ visible, eventId, ticketTypeId, onConfirm, onClose }) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [mapData, setMapData] = useState(null);
  const [selectedSeats, setSelectedSeats] = useState([]);
  const [mapSize, setMapSize] = useState({ width: SCREEN_WIDTH, height: 600 });

  // Animated values (useNativeDriver = zero re-renders during gestures)
  const scaleAnim = useRef(new Animated.Value(1)).current;
  const translateXAnim = useRef(new Animated.Value(0)).current;
  const translateYAnim = useRef(new Animated.Value(0)).current;
  // Mutable gesture state
  const gs = useRef({ scale: 1, savedScale: 1, tx: 0, ty: 0, savedTx: 0, savedTy: 0 });

  // Refs for tap gesture (avoid stale closures)
  const seatsRef = useRef([]);
  const selectedUidsRef = useRef(new Set());
  const lastTapRef = useRef(0);

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
    } catch (e) { setError(e.message || 'Eroare la incarcarea hartii'); }
    setLoading(false);
  };

  const canvas = mapData?.canvas || { width: 1000, height: 800 };

  // Fit zoom based on dynamically measured map area
  const fitZoom = useMemo(() => {
    const availW = mapSize.width - 20;
    const availH = mapSize.height - 20;
    return Math.min(availW / canvas.width, availH / canvas.height);
  }, [canvas.width, canvas.height, mapSize]);

  // Process seats — status is now merged into geometry by API
  const processedData = useMemo(() => {
    if (!mapData) return { seats: [], rowLabels: [], sections: [] };
    const sections = mapData.sections || [];
    const seatList = [];
    const rowLabelList = [];

    sections.forEach(section => {
      const sectionX = section.x || 0;
      const sectionY = section.y || 0;
      const sectionMeta = section.metadata || section.meta || {};
      const seatSize = parseInt(sectionMeta.seat_size) || 15;
      const seatRadius = seatSize / 2;

      (section.rows || []).forEach(row => {
        const firstSeat = row.seats?.[0];
        if (firstSeat) {
          rowLabelList.push({
            key: `${section.name}-${row.label}`,
            x: sectionX + (firstSeat.x || 0) - seatRadius - 6,
            y: sectionY + (firstSeat.y || 0) + seatRadius * 0.35,
            label: row.label,
            fontSize: Math.max(seatRadius * 0.85, 7),
          });
        }

        (row.seats || []).forEach(seat => {
          const cx = sectionX + (seat.x || 0);
          const cy = sectionY + (seat.y || 0);
          const seatUid = seat.seat_uid;
          const seatTicketTypeId = seat.ticket_type_id;
          const isAllowed = !ticketTypeId || seatTicketTypeId == ticketTypeId;

          seatList.push({
            seat_uid: seatUid,
            cx, cy, seatSize, seatRadius,
            status: seat.status || 'available',
            section_name: section.name || '',
            row_label: row.label || '',
            seat_label: seat.label || '',
            ticket_type_id: seatTicketTypeId,
            ticket_type_name: seat.ticket_type_name,
            price: seat.price || 0,
            color: seat.color || '#10B981',
            isAllowed,
          });
        });
      });
    });
    seatsRef.current = seatList;
    return { seats: seatList, rowLabels: rowLabelList, sections };
  }, [mapData, ticketTypeId]);

  // ─── Centering & FIT ───────────────────────────────────────────────────────
  // transformOrigin '0% 0%': screenX = tx + canvasX * scale

  const centerAndFit = useCallback((zoom, mw, mh) => {
    const panX = ((mw || mapSize.width) - canvas.width * zoom) / 2;
    const panY = ((mh || mapSize.height) - canvas.height * zoom) / 2;
    gs.current = { scale: zoom, savedScale: zoom, tx: panX, ty: panY, savedTx: panX, savedTy: panY };
    scaleAnim.setValue(zoom);
    translateXAnim.setValue(panX);
    translateYAnim.setValue(panY);
  }, [canvas.width, canvas.height, mapSize]);

  useEffect(() => {
    if (mapData && mapSize.width > 0) centerAndFit(fitZoom);
  }, [mapData, fitZoom, mapSize]);

  const onMapLayout = useCallback((e) => {
    const { width, height } = e.nativeEvent.layout;
    if (width > 0 && height > 0) setMapSize({ width, height });
  }, []);

  // ─── Selected seats ────────────────────────────────────────────────────────

  const selectedUids = useMemo(() => {
    const set = new Set();
    selectedSeats.forEach(s => set.add(s.seat_uid));
    selectedUidsRef.current = set;
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

  const toggleSeatRef = useRef(toggleSeat);
  useEffect(() => { toggleSeatRef.current = toggleSeat; }, [toggleSeat]);

  // ─── RNGH Gestures (from v1.3.6 + fixes) ──────────────────────────────────

  // TAP gesture for seat selection (replaces SVG onPress)
  const tapGesture = Gesture.Tap()
    .maxDuration(300)
    .maxDistance(10)
    .onEnd((e) => {
      // Debounce: ignore taps within 400ms
      const now = Date.now();
      if (now - lastTapRef.current < 400) return;
      lastTapRef.current = now;

      // e.x, e.y are in the Animated.View's local space = canvas coordinates
      // (RNGH inverse-transforms touch coords for the attached view)
      const canvasX = e.x;
      const canvasY = e.y;

      // Fallback: if coords seem to be in screen space, convert manually
      // With transformOrigin 0% 0%: canvasX = (screenX - tx) / scale
      const g = gs.current;
      let cx = canvasX, cy = canvasY;
      // Heuristic: if coordinates are within screen range (0..500) and canvas is large (1000+),
      // they're likely screen coords, not canvas coords
      if (canvas.width > 500 && canvasX < mapSize.width && canvasY < mapSize.height) {
        cx = (canvasX - g.tx) / g.scale;
        cy = (canvasY - g.ty) / g.scale;
      }

      const seats = seatsRef.current;
      const uids = selectedUidsRef.current;
      let best = null, bestD = Infinity;
      for (let i = 0; i < seats.length; i++) {
        const s = seats[i];
        const clickable = ((s.status === 'available') && s.isAllowed) || uids.has(s.seat_uid);
        if (!clickable) continue;
        const dx = cx - s.cx, dy = cy - s.cy;
        const d = dx * dx + dy * dy;
        const hr = Math.max(s.seatRadius * 2, 12);
        if (d < hr * hr && d < bestD) { bestD = d; best = s; }
      }
      if (best) toggleSeatRef.current(best);
    });

  // PINCH gesture — zoom toward focal point
  const pinchGesture = Gesture.Pinch()
    .onStart((e) => {
      const g = gs.current;
      g.savedScale = g.scale;
      g.savedTx = g.tx;
      g.savedTy = g.ty;
      // Save the canvas point under the focal (for focal-point zoom)
      // With transformOrigin 0% 0%: canvasX = (screenX - tx) / scale
      // e.focalX is in view's local space; same heuristic as tap
      g.focalCanvasX = (e.focalX - g.tx) / g.scale;
      g.focalCanvasY = (e.focalY - g.ty) / g.scale;
      // Save the screen position of that canvas point
      g.focalScreenX = g.tx + g.focalCanvasX * g.scale;
      g.focalScreenY = g.ty + g.focalCanvasY * g.scale;
    })
    .onUpdate((e) => {
      const g = gs.current;
      const minScale = fitZoom * 0.5;
      const maxScale = fitZoom * 10;
      const newScale = Math.min(Math.max(g.savedScale * e.scale, minScale), maxScale);

      // Keep focal canvas point at same screen position
      const newTx = g.focalScreenX - g.focalCanvasX * newScale;
      const newTy = g.focalScreenY - g.focalCanvasY * newScale;

      g.scale = newScale;
      g.tx = newTx;
      g.ty = newTy;
      scaleAnim.setValue(newScale);
      translateXAnim.setValue(newTx);
      translateYAnim.setValue(newTy);
    })
    .onEnd(() => {
      const g = gs.current;
      g.savedScale = g.scale;
      g.savedTx = g.tx;
      g.savedTy = g.ty;
    });

  // PAN gesture — single finger drag
  const panGesture = Gesture.Pan()
    .minPointers(1)
    .minDistance(10)
    .onStart(() => {
      gs.current.savedTx = gs.current.tx;
      gs.current.savedTy = gs.current.ty;
    })
    .onUpdate((e) => {
      const g = gs.current;
      g.tx = g.savedTx + e.translationX;
      g.ty = g.savedTy + e.translationY;
      translateXAnim.setValue(g.tx);
      translateYAnim.setValue(g.ty);
    });

  // Compose: Tap first (exclusive), then pan+pinch simultaneous
  const composedGesture = Gesture.Exclusive(
    tapGesture,
    Gesture.Simultaneous(pinchGesture, panGesture)
  );

  // ─── Zoom controls ────────────────────────────────────────────────────────

  const zoomToCenter = useCallback((newScale) => {
    const g = gs.current;
    // Canvas point at screen center
    const centerCanvasX = (mapSize.width / 2 - g.tx) / g.scale;
    const centerCanvasY = (mapSize.height / 2 - g.ty) / g.scale;
    // New translate keeping center stationary
    const newTx = mapSize.width / 2 - centerCanvasX * newScale;
    const newTy = mapSize.height / 2 - centerCanvasY * newScale;
    g.scale = newScale; g.savedScale = newScale;
    g.tx = newTx; g.ty = newTy; g.savedTx = newTx; g.savedTy = newTy;
    Animated.parallel([
      Animated.spring(scaleAnim, { toValue: newScale, useNativeDriver: true, friction: 8 }),
      Animated.spring(translateXAnim, { toValue: newTx, useNativeDriver: true, friction: 8 }),
      Animated.spring(translateYAnim, { toValue: newTy, useNativeDriver: true, friction: 8 }),
    ]).start();
  }, [mapSize]);

  const resetView = useCallback(() => {
    centerAndFit(fitZoom);
  }, [fitZoom, centerAndFit]);

  const zoomIn = useCallback(() => {
    zoomToCenter(Math.min(gs.current.scale * 1.8, fitZoom * 10));
  }, [fitZoom, zoomToCenter]);

  const zoomOut = useCallback(() => {
    zoomToCenter(Math.max(gs.current.scale / 1.8, fitZoom * 0.5));
  }, [fitZoom, zoomToCenter]);

  // ─── Derived values ────────────────────────────────────────────────────────

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

  // Filter legend: only entry tickets
  const ticketTypeLegend = useMemo(() => {
    const types = mapData?.ticket_types || [];
    return types.filter(t => t.is_entry_ticket !== false);
  }, [mapData]);

  // ─── Render ────────────────────────────────────────────────────────────────

  const renderContent = () => {
    if (loading) return <View style={styles.loadingContainer}><ActivityIndicator size="large" color={colors.purple} /><Text style={styles.loadingText}>Se incarca harta...</Text></View>;
    if (error) return <View style={styles.loadingContainer}><Text style={styles.errorText}>{error}</Text><TouchableOpacity style={styles.retryButton} onPress={loadSeatingMap} activeOpacity={0.7}><Text style={styles.retryButtonText}>Reincearca</Text></TouchableOpacity></View>;

    return (
      <>
        {/* Legend */}
        <View style={styles.legendContainer}>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.legendScroll}>
            {ticketTypeLegend.map(tt => (
              <View key={tt.id} style={[styles.legendItem, ticketTypeId && tt.id == ticketTypeId && styles.legendItemActive]}>
                <View style={[styles.legendDot, { backgroundColor: tt.color || '#10B981' }]} />
                <Text style={styles.legendText} numberOfLines={1}>{tt.name}</Text>
                <Text style={styles.legendPrice}>{formatCurrency(tt.price)}</Text>
              </View>
            ))}
            <View style={styles.legendItem}><View style={[styles.legendDot, { backgroundColor: '#9CA3AF' }]} /><Text style={styles.legendText}>Vandut</Text></View>
            <View style={styles.legendItem}><View style={[styles.legendDot, { backgroundColor: '#a51c30' }]} /><Text style={styles.legendText}>Selectat</Text></View>
          </ScrollView>
        </View>

        {/* Map */}
        <View style={styles.mapContainer} onLayout={onMapLayout}>
          <GestureDetector gesture={composedGesture}>
            <Animated.View style={[
              styles.mapAnimatedView,
              { transform: [{ translateX: translateXAnim }, { translateY: translateYAnim }, { scale: scaleAnim }] },
            ]}>
              <Svg width={canvas.width} height={canvas.height} viewBox={`0 0 ${canvas.width} ${canvas.height}`}>
                <Rect x={0} y={0} width={canvas.width} height={canvas.height} fill="#0f0f1a" rx={8} />

                {/* Section labels */}
                {processedData.sections.map((section, idx) => {
                  const sx = section.x || 0, sy = section.y || 0;
                  const rotation = section.rotation || 0;
                  const sW = section.width || 100, sH = section.height || 100;
                  const rcx = sx + sW / 2, rcy = sy + sH / 2;
                  return (
                    <SvgText key={`sl-${idx}`} x={sx + sW / 2} y={sy - 12}
                      fill="rgba(255,255,255,0.5)" fontSize={12} fontWeight="700" textAnchor="middle"
                      transform={rotation ? `rotate(${rotation} ${rcx} ${rcy})` : undefined}>
                      {section.name || ''}
                    </SvgText>
                  );
                })}

                {/* Row labels */}
                {processedData.rowLabels.map(rl => (
                  <SvgText key={rl.key} x={rl.x} y={rl.y} textAnchor="end" fontSize={rl.fontSize} fontWeight="500" fill="rgba(255,255,255,0.45)">{rl.label}</SvgText>
                ))}

                {/* Seats — NO onPress, taps handled via Gesture.Tap */}
                {processedData.seats.map(seat => {
                  const isSelected = selectedUids.has(seat.seat_uid);
                  const r = seat.seatRadius;
                  const avail = seat.status === 'available';
                  let fill, stroke, sw, op;
                  if (isSelected) { fill = '#a51c30'; stroke = '#7a141f'; sw = 1.5; op = 1; }
                  else if (avail && seat.isAllowed) { fill = seat.color; stroke = '#ffffff'; sw = 0.8; op = 1; }
                  else if (avail && !seat.isAllowed) { fill = '#2D2D3D'; stroke = 'rgba(255,255,255,0.1)'; sw = 0.5; op = 0.4; }
                  else { fill = '#9CA3AF'; stroke = 'rgba(255,255,255,0.15)'; sw = 0.5; op = seat.status === 'disabled' ? 0.25 : 0.45; }
                  const dr = isSelected ? r * 1.15 : r;
                  const fs = Math.round(r * 0.85 * 10) / 10;
                  return (
                    <G key={seat.seat_uid}>
                      <Circle cx={seat.cx} cy={seat.cy} r={dr} fill={fill} stroke={stroke} strokeWidth={sw} opacity={op} />
                      {r >= 5 && <SvgText x={seat.cx} y={seat.cy + fs * 0.35} textAnchor="middle" fontSize={fs} fontWeight="700" fill={isSelected ? '#fff' : 'rgba(255,255,255,0.85)'} opacity={op}>{seat.seat_label}</SvgText>}
                    </G>
                  );
                })}
              </Svg>
            </Animated.View>
          </GestureDetector>

          {/* Zoom controls */}
          <View style={styles.zoomControls}>
            <TouchableOpacity style={styles.zoomButton} onPress={zoomIn} activeOpacity={0.7}><ZoomInIcon size={18} color={colors.textPrimary} /></TouchableOpacity>
            <TouchableOpacity style={styles.zoomButton} onPress={zoomOut} activeOpacity={0.7}><ZoomOutIcon size={18} color={colors.textPrimary} /></TouchableOpacity>
            <TouchableOpacity style={styles.zoomButton} onPress={resetView} activeOpacity={0.7}><Text style={styles.resetLabel}>FIT</Text></TouchableOpacity>
          </View>
        </View>

        {/* Bottom Bar */}
        {selectedSeats.length > 0 && (
          <View style={styles.bottomBar}>
            <View style={styles.bottomBarInfo}>
              <Text style={styles.bottomBarCount}>{selectedSeats.length} {selectedSeats.length === 1 ? 'loc' : 'locuri'}</Text>
              <Text style={styles.bottomBarTotal}>{formatCurrency(selectedTotal)}</Text>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.seatLabelsScroll}>
                {selectedSeats.map(s => <View key={s.seat_uid} style={styles.seatLabelChip}><Text style={styles.seatLabelChipText}>R{s.row_label}-{s.seat_label}</Text></View>)}
              </ScrollView>
            </View>
            <TouchableOpacity style={styles.confirmButton} onPress={handleConfirm} activeOpacity={0.8}>
              <CheckIcon size={20} color={colors.white} /><Text style={styles.confirmButtonText}>Confirma</Text>
            </TouchableOpacity>
          </View>
        )}
      </>
    );
  };

  return (
    <Modal visible={visible} animationType="slide" statusBarTranslucent onRequestClose={onClose}>
      <GestureHandlerRootView style={styles.container}>
        <View style={styles.header}>
          <TouchableOpacity style={styles.backButton} onPress={onClose} activeOpacity={0.7}><ArrowLeftIcon size={20} color={colors.textPrimary} /></TouchableOpacity>
          <Text style={styles.headerTitle}>{loading ? 'Harta Locurilor' : 'Selecteaza Locuri'}</Text>
          {selectedSeats.length > 0 ? <View style={styles.selectedBadge}><Text style={styles.selectedBadgeText}>{selectedSeats.length}</Text></View> : <View style={{ width: 40 }} />}
        </View>
        {renderContent()}
      </GestureHandlerRootView>
    </Modal>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  header: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingTop: 48, paddingBottom: 8, borderBottomWidth: 1, borderBottomColor: colors.border, gap: 12, backgroundColor: colors.background },
  backButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { flex: 1, fontSize: 18, fontWeight: '700', color: colors.textPrimary },
  selectedBadge: { backgroundColor: '#a51c30', borderRadius: 12, minWidth: 28, height: 28, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 8 },
  selectedBadgeText: { fontSize: 14, fontWeight: '700', color: colors.white },
  loadingContainer: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 16 },
  loadingText: { fontSize: 15, color: colors.textSecondary },
  errorText: { fontSize: 15, color: colors.red, textAlign: 'center', marginBottom: 16 },
  retryButton: { backgroundColor: colors.purple, paddingHorizontal: 24, paddingVertical: 12, borderRadius: 12 },
  retryButtonText: { fontSize: 15, fontWeight: '600', color: colors.white },
  legendContainer: { borderBottomWidth: 1, borderBottomColor: colors.border, paddingVertical: 8 },
  legendScroll: { paddingHorizontal: 16, gap: 12 },
  legendItem: { flexDirection: 'row', alignItems: 'center', gap: 5, marginRight: 4 },
  legendItemActive: { backgroundColor: colors.purpleBg, borderRadius: 8, paddingHorizontal: 8, paddingVertical: 4, borderWidth: 1, borderColor: colors.purpleBorder },
  legendDot: { width: 10, height: 10, borderRadius: 5 },
  legendText: { fontSize: 11, color: colors.textSecondary },
  legendPrice: { fontSize: 11, fontWeight: '600', color: colors.textTertiary },
  mapContainer: { flex: 1, overflow: 'hidden' },
  mapAnimatedView: { transformOrigin: '0% 0%' },
  zoomControls: { position: 'absolute', right: 12, top: 12, gap: 6 },
  zoomButton: { width: 40, height: 40, borderRadius: 10, backgroundColor: 'rgba(10,10,15,0.9)', borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  resetLabel: { fontSize: 11, fontWeight: '700', color: colors.textSecondary },
  bottomBar: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingVertical: 12, paddingBottom: 28, borderTopWidth: 1, borderTopColor: colors.border, backgroundColor: 'rgba(10,10,15,0.98)', gap: 12 },
  bottomBarInfo: { flex: 1 },
  bottomBarCount: { fontSize: 14, fontWeight: '600', color: colors.textPrimary },
  bottomBarTotal: { fontSize: 18, fontWeight: '700', color: '#a51c30', marginBottom: 4 },
  seatLabelsScroll: { flexGrow: 0, maxHeight: 28 },
  seatLabelChip: { backgroundColor: 'rgba(165,28,48,0.15)', borderWidth: 1, borderColor: 'rgba(165,28,48,0.3)', borderRadius: 6, paddingHorizontal: 6, paddingVertical: 2, marginRight: 4 },
  seatLabelChipText: { fontSize: 11, fontWeight: '600', color: '#a51c30' },
  confirmButton: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#a51c30', paddingHorizontal: 20, paddingVertical: 14, borderRadius: 12, gap: 8 },
  confirmButtonText: { fontSize: 16, fontWeight: '700', color: colors.white },
});
