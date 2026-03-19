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
} from 'react-native';
import Svg, { Circle, Rect, G, Text as SvgText, Path } from 'react-native-svg';
import { colors } from '../theme/colors';
import { formatCurrency } from '../utils/formatCurrency';
import { apiGet } from '../api/client';

const { width: SCREEN_WIDTH } = Dimensions.get('window');

// ─── SVG Icon Components ──────────────────────────────────────────────────────

function ArrowLeftIcon({ size = 24, color = colors.white }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path d="M19 12H5M12 19l-7-7 7-7" stroke={color} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" />
    </Svg>
  );
}
function CheckIcon({ size = 20, color = colors.white }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path d="M20 6L9 17l-5-5" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
    </Svg>
  );
}
function ZoomInIcon({ size = 18, color = colors.white }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path d="M11 19a8 8 0 100-16 8 8 0 000 16zM21 21l-4.35-4.35M11 8v6M8 11h6" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
    </Svg>
  );
}
function ZoomOutIcon({ size = 18, color = colors.white }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path d="M11 19a8 8 0 100-16 8 8 0 000 16zM21 21l-4.35-4.35M8 11h6" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
    </Svg>
  );
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function getDistance(t1, t2) {
  const dx = t1.pageX - t2.pageX;
  const dy = t1.pageY - t2.pageY;
  return Math.sqrt(dx * dx + dy * dy);
}

// ─── Main Component ──────────────────────────────────────────────────────────

export default function SeatingMapScreen({ visible, eventId, ticketTypeId, onConfirm, onClose }) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [mapData, setMapData] = useState(null);
  const [selectedSeats, setSelectedSeats] = useState([]);
  const [mapSize, setMapSize] = useState({ width: SCREEN_WIDTH, height: 500 });
  const [viewBox, setViewBox] = useState({ x: 0, y: 0, w: 1000, h: 800 });

  // ALL mutable values accessed in PanResponder go through refs
  const vbRef = useRef({ x: 0, y: 0, w: 1000, h: 800 });
  const sizeRef = useRef({ width: SCREEN_WIDTH, height: 500 });
  const canvasRef = useRef({ width: 1000, height: 800 });
  const offsetRef = useRef({ x: 0, y: 0 });
  const seatsRef = useRef([]);
  const selectedRef = useRef(new Set());
  const toggleRef = useRef(null);
  const lastTapRef = useRef(0); // debounce taps

  useEffect(() => {
    if (visible && eventId) loadSeatingMap();
    if (!visible) { setSelectedSeats([]); setError(null); }
  }, [visible, eventId]);

  const loadSeatingMap = async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await apiGet(`/organizer/events/${eventId}/seating-map`);
      if (response.success && response.data) setMapData(response.data);
      else setError(response.message || 'Nu s-a putut incarca harta');
    } catch (e) {
      setError(e.message || 'Eroare la incarcarea hartii');
    }
    setLoading(false);
  };

  const canvas = mapData?.canvas || { width: 1000, height: 800 };
  useEffect(() => { canvasRef.current = canvas; }, [canvas.width, canvas.height]);

  // Compute viewBox that fits the canvas while matching container aspect ratio
  const computeFitVB = useCallback((cv, sz) => {
    const cw = cv?.width || canvasRef.current.width;
    const ch = cv?.height || canvasRef.current.height;
    const sw = sz?.width || sizeRef.current.width;
    const sh = sz?.height || sizeRef.current.height;
    const canvasAR = cw / ch;
    const screenAR = sw / sh;
    let vbW, vbH;
    if (canvasAR > screenAR) {
      vbW = cw;
      vbH = cw / screenAR;
    } else {
      vbH = ch;
      vbW = ch * screenAR;
    }
    return { x: (cw - vbW) / 2, y: (ch - vbH) / 2, w: vbW, h: vbH };
  }, []);

  // Set initial viewBox on load / size change
  useEffect(() => {
    if (mapData && mapSize.width > 0 && mapSize.height > 0) {
      const vb = computeFitVB(canvas, mapSize);
      vbRef.current = vb;
      setViewBox(vb);
    }
  }, [mapData, mapSize]);

  const onMapLayout = useCallback((e) => {
    const { width, height } = e.nativeEvent.layout;
    if (width > 0 && height > 0) {
      sizeRef.current = { width, height };
      setMapSize({ width, height });
    }
    // Measure absolute position on screen for coordinate conversion
    if (e.target?.measureInWindow) {
      e.target.measureInWindow((x, y) => {
        offsetRef.current = { x: x || 0, y: y || 0 };
      });
    }
  }, []);

  // Process seats
  const processedData = useMemo(() => {
    if (!mapData) return { seats: [], rowLabels: [], sections: [] };
    const { sections, seats } = mapData;
    const statusMap = {};
    (seats || []).forEach(s => { statusMap[s.seat_uid] = s; });
    const seatList = [];
    const rowLabelList = [];

    (sections || []).forEach(section => {
      const sx = section.x_position || section.x || 0;
      const sy = section.y_position || section.y || 0;
      const meta = section.metadata || {};
      const seatSize = parseInt(meta.seat_size) || 15;
      const seatRadius = seatSize / 2;

      (section.rows || []).forEach(row => {
        const first = row.seats?.[0];
        if (first) {
          rowLabelList.push({
            key: `${section.name}-${row.label}`,
            x: sx + (first.x || 0) - seatRadius - 6,
            y: sy + (first.y || 0) + seatRadius * 0.35,
            label: row.label,
            fontSize: Math.max(seatRadius * 0.85, 7),
          });
        }
        (row.seats || []).forEach(seat => {
          const uid = seat.seat_uid || seat.uid;
          const info = statusMap[uid] || {};
          const ttId = info.ticket_type_id;
          seatList.push({
            seat_uid: uid,
            cx: sx + (seat.x || 0),
            cy: sy + (seat.y || 0),
            seatSize, seatRadius,
            status: info.status || 'available',
            section_name: info.section_name || section.name || '',
            row_label: info.row_label || row.label || '',
            seat_label: info.seat_label || seat.label || '',
            ticket_type_id: ttId,
            ticket_type_name: info.ticket_type_name,
            price: info.price || 0,
            color: info.color || '#10B981',
            isAllowed: !ticketTypeId || ttId == ticketTypeId,
          });
        });
      });
    });
    seatsRef.current = seatList;
    return { seats: seatList, rowLabels: rowLabelList, sections: sections || [] };
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

  // ─── PanResponder ──────────────────────────────────────────────────────────
  // Recreated via useMemo so closure never goes stale (refs always fresh)

  const panResponder = useMemo(() => {
    // Local gesture state
    let isPinching = false;
    let startDist = 0;
    let startMidX = 0, startMidY = 0;
    let startVB = { x: 0, y: 0, w: 0, h: 0 };
    let touchStart = { x: 0, y: 0, time: 0 };
    let moved = false;

    function applyVB(vb) {
      vbRef.current = vb;
      setViewBox(vb);
    }

    return PanResponder.create({
      onStartShouldSetPanResponder: () => true,
      onMoveShouldSetPanResponder: () => true,
      // Capture phase — grab gestures before children
      onStartShouldSetPanResponderCapture: () => true,
      onMoveShouldSetPanResponderCapture: () => true,

      onPanResponderGrant: (evt) => {
        const t = evt.nativeEvent.touches;
        touchStart = { x: t[0].pageX, y: t[0].pageY, time: Date.now() };
        startVB = { ...vbRef.current };
        moved = false;

        if (t.length >= 2) {
          isPinching = true;
          startDist = getDistance(t[0], t[1]);
          startMidX = (t[0].pageX + t[1].pageX) / 2;
          startMidY = (t[0].pageY + t[1].pageY) / 2;
        } else {
          isPinching = false;
        }
      },

      onPanResponderMove: (evt) => {
        const t = evt.nativeEvent.touches;
        if (!t || t.length === 0) return;

        if (t.length >= 2) {
          // ── PINCH ZOOM + PAN ──
          isPinching = true;
          moved = true;
          const newDist = getDistance(t[0], t[1]);
          if (newDist < 10) return; // fingers too close
          const scaleRatio = startDist / newDist; // >1 = zoom out

          const sz = sizeRef.current;
          const cv = canvasRef.current;
          const vb = startVB;

          // New viewBox size (maintain aspect ratio of container)
          const aspect = sz.width / sz.height;
          let newW = vb.w * scaleRatio;
          // Clamp: max zoom out = 2x canvas, max zoom in = 10% canvas
          newW = Math.max(cv.width * 0.1, Math.min(cv.width * 2.5, newW));
          const newH = newW / aspect;

          // Canvas point under original midpoint
          const off = offsetRef.current;
          const localMidX = startMidX - off.x;
          const localMidY = startMidY - off.y;
          const canvasMidX = vb.x + (localMidX / sz.width) * vb.w;
          const canvasMidY = vb.y + (localMidY / sz.height) * vb.h;

          // Current midpoint
          const curMidX = (t[0].pageX + t[1].pageX) / 2;
          const curMidY = (t[0].pageY + t[1].pageY) / 2;
          const curLocalX = curMidX - off.x;
          const curLocalY = curMidY - off.y;

          // Adjust so canvas point under old mid moves to new mid
          const newX = canvasMidX - (curLocalX / sz.width) * newW;
          const newY = canvasMidY - (curLocalY / sz.height) * newH;

          applyVB({ x: newX, y: newY, w: newW, h: newH });
        } else if (!isPinching) {
          // ── SINGLE FINGER PAN ──
          const dx = t[0].pageX - touchStart.x;
          const dy = t[0].pageY - touchStart.y;
          if (Math.abs(dx) > 5 || Math.abs(dy) > 5) moved = true;

          const sz = sizeRef.current;
          const vb = startVB;
          const canvasDx = -(dx / sz.width) * vb.w;
          const canvasDy = -(dy / sz.height) * vb.h;

          applyVB({ x: vb.x + canvasDx, y: vb.y + canvasDy, w: vb.w, h: vb.h });
        }
      },

      onPanResponderRelease: (evt) => {
        if (isPinching || moved) {
          isPinching = false;
          return;
        }

        // ── TAP DETECTION ──
        const elapsed = Date.now() - touchStart.time;
        if (elapsed > 500) return; // too slow

        // Debounce: ignore taps within 400ms of each other
        const now = Date.now();
        if (now - lastTapRef.current < 400) return;
        lastTapRef.current = now;

        const touch = evt.nativeEvent.changedTouches?.[0] || evt.nativeEvent;
        const off = offsetRef.current;
        const sz = sizeRef.current;
        const vb = vbRef.current;

        const localX = (touch.pageX || touchStart.x) - off.x;
        const localY = (touch.pageY || touchStart.y) - off.y;
        const canvasX = vb.x + (localX / sz.width) * vb.w;
        const canvasY = vb.y + (localY / sz.height) * vb.h;

        // Find nearest clickable seat
        const seats = seatsRef.current;
        const uids = selectedRef.current;
        const zoom = canvasRef.current.width / vb.w;
        let best = null;
        let bestD = Infinity;

        for (let i = 0; i < seats.length; i++) {
          const s = seats[i];
          const clickable = ((s.status === 'available') && s.isAllowed) || uids.has(s.seat_uid);
          if (!clickable) continue;
          const dx = canvasX - s.cx;
          const dy = canvasY - s.cy;
          const d = dx * dx + dy * dy;
          const hr = Math.max(s.seatRadius * 2.5, 18 / zoom);
          if (d < hr * hr && d < bestD) {
            bestD = d;
            best = s;
          }
        }

        if (best && toggleRef.current) {
          toggleRef.current(best);
        }

        isPinching = false;
      },

      onPanResponderTerminate: () => {
        isPinching = false;
      },
    });
  }, []); // No deps — all values accessed via refs

  // ─── Zoom controls ──────────────────────────────────────────────────────────

  const resetView = useCallback(() => {
    const vb = computeFitVB();
    vbRef.current = vb;
    setViewBox(vb);
  }, [computeFitVB]);

  const zoomCenter = useCallback((factor) => {
    const vb = vbRef.current;
    const sz = sizeRef.current;
    const aspect = sz.width / sz.height;
    const cv = canvasRef.current;
    let newW = vb.w * factor;
    newW = Math.max(cv.width * 0.1, Math.min(cv.width * 2.5, newW));
    const newH = newW / aspect;
    const newX = vb.x + (vb.w - newW) / 2;
    const newY = vb.y + (vb.h - newH) / 2;
    const newVB = { x: newX, y: newY, w: newW, h: newH };
    vbRef.current = newVB;
    setViewBox(newVB);
  }, []);

  const selectedTotal = useMemo(
    () => selectedSeats.reduce((sum, s) => sum + (s.price || 0), 0),
    [selectedSeats]
  );

  const selectedByType = useMemo(() => {
    const groups = {};
    selectedSeats.forEach(s => {
      const key = s.ticket_type_id || 'unknown';
      if (!groups[key]) {
        groups[key] = { ticket_type_id: s.ticket_type_id, name: s.ticket_type_name || 'Bilet', price: s.price, color: s.color, seats: [] };
      }
      groups[key].seats.push(s);
    });
    return Object.values(groups);
  }, [selectedSeats]);

  const handleConfirm = () => {
    if (selectedSeats.length === 0) return;
    const cartItems = selectedByType.map(g => ({
      id: g.ticket_type_id, name: g.name, price: g.price, color: g.color, quantity: g.seats.length,
    }));
    onConfirm({
      cartItems,
      seatUids: selectedSeats.map(s => s.seat_uid),
      selectedSeats: selectedSeats.map(s => ({
        seat_uid: s.seat_uid, section_name: s.section_name, row_label: s.row_label,
        seat_label: s.seat_label, ticket_type_id: s.ticket_type_id, price: s.price,
      })),
    });
  };

  // Filter legend: only show ticket types that have seats with is_entry_ticket
  const ticketTypeLegend = useMemo(() => {
    const types = mapData?.ticket_types || [];
    // If ticketTypeId is set, only show that type; otherwise show all from API
    if (ticketTypeId) return types.filter(t => t.id == ticketTypeId);
    return types;
  }, [mapData, ticketTypeId]);

  // ─── Render ──────────────────────────────────────────────────────

  if (!visible) return null;

  const renderContent = () => {
    if (loading) {
      return (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={colors.purple} />
          <Text style={styles.loadingText}>Se incarca harta...</Text>
        </View>
      );
    }
    if (error) {
      return (
        <View style={styles.loadingContainer}>
          <Text style={styles.errorText}>{error}</Text>
          <TouchableOpacity style={styles.retryButton} onPress={loadSeatingMap} activeOpacity={0.7}>
            <Text style={styles.retryButtonText}>Reincearca</Text>
          </TouchableOpacity>
        </View>
      );
    }

    return (
      <>
        {/* Legend */}
        <View style={styles.legendContainer}>
          <View style={styles.legendScroll}>
            {ticketTypeLegend.map(tt => (
              <View key={tt.id} style={[styles.legendItem, ticketTypeId && tt.id == ticketTypeId && styles.legendItemActive]}>
                <View style={[styles.legendDot, { backgroundColor: tt.color || '#10B981' }]} />
                <Text style={styles.legendText} numberOfLines={1}>{tt.name}</Text>
                <Text style={styles.legendPrice}>{formatCurrency(tt.price)}</Text>
              </View>
            ))}
            <View style={styles.legendItem}>
              <View style={[styles.legendDot, { backgroundColor: '#9CA3AF' }]} />
              <Text style={styles.legendText}>Vandut</Text>
            </View>
            <View style={styles.legendItem}>
              <View style={[styles.legendDot, { backgroundColor: '#a51c30' }]} />
              <Text style={styles.legendText}>Selectat</Text>
            </View>
          </View>
        </View>

        {/* Map */}
        <View style={styles.mapContainer} onLayout={onMapLayout} {...panResponder.panHandlers}>
          <Svg
            width={mapSize.width}
            height={mapSize.height}
            viewBox={`${viewBox.x} ${viewBox.y} ${viewBox.w} ${viewBox.h}`}
            preserveAspectRatio="none"
          >
            <Rect x={viewBox.x} y={viewBox.y} width={viewBox.w} height={viewBox.h} fill="#0f0f1a" />
            <Rect x={0} y={0} width={canvas.width} height={canvas.height} fill="#0f0f1a" rx={8} />

            {/* Section labels */}
            {processedData.sections.map((section, idx) => {
              const sx = section.x_position || section.x || 0;
              const sy = section.y_position || section.y || 0;
              const rotation = section.rotation || 0;
              const sW = section.width || 100;
              const sH = section.height || 100;
              const rcx = sx + sW / 2;
              const rcy = sy + sH / 2;
              const transform = rotation !== 0 ? `rotate(${rotation} ${rcx} ${rcy})` : undefined;
              return (
                <SvgText key={`sl-${idx}`} x={sx + sW / 2} y={sy - 12} fill="rgba(255,255,255,0.5)" fontSize={12} fontWeight="700" textAnchor="middle" transform={transform}>
                  {section.name || ''}
                </SvgText>
              );
            })}

            {/* Row labels */}
            {processedData.rowLabels.map(rl => (
              <SvgText key={rl.key} x={rl.x} y={rl.y} textAnchor="end" fontSize={rl.fontSize} fontWeight="500" fill="rgba(255,255,255,0.45)">
                {rl.label}
              </SvgText>
            ))}

            {/* Seats */}
            {processedData.seats.map(seat => {
              const isSelected = selectedUids.has(seat.seat_uid);
              const r = seat.seatRadius;
              const avail = seat.status === 'available';
              let fill, stroke, sw, op;
              if (isSelected) { fill = '#a51c30'; stroke = '#7a141f'; sw = 1.5; op = 1; }
              else if (avail && seat.isAllowed) { fill = seat.color; stroke = '#ffffff'; sw = 0.8; op = 1; }
              else if (avail && !seat.isAllowed) { fill = '#2D2D3D'; stroke = 'rgba(255,255,255,0.1)'; sw = 0.5; op = 0.4; }
              else { fill = '#9CA3AF'; stroke = 'rgba(255,255,255,0.15)'; sw = 0.5; op = seat.status === 'disabled' ? 0.25 : 0.45; }
              const dr = isSelected ? r * 1.4 : r;
              const fs = Math.round(r * 0.85 * 10) / 10;
              return (
                <G key={seat.seat_uid}>
                  <Circle cx={seat.cx} cy={seat.cy} r={dr} fill={fill} stroke={stroke} strokeWidth={sw} opacity={op} />
                  {r >= 5 && (
                    <SvgText x={seat.cx} y={seat.cy + fs * 0.35} textAnchor="middle" fontSize={fs} fontWeight="700" fill={isSelected ? '#ffffff' : 'rgba(255,255,255,0.85)'} opacity={op}>
                      {seat.seat_label}
                    </SvgText>
                  )}
                </G>
              );
            })}
          </Svg>

          {/* Zoom controls */}
          <View style={styles.zoomControls}>
            <TouchableOpacity style={styles.zoomButton} onPress={() => zoomCenter(1 / 1.5)} activeOpacity={0.7}>
              <ZoomInIcon size={18} color={colors.textPrimary} />
            </TouchableOpacity>
            <TouchableOpacity style={styles.zoomButton} onPress={() => zoomCenter(1.5)} activeOpacity={0.7}>
              <ZoomOutIcon size={18} color={colors.textPrimary} />
            </TouchableOpacity>
            <TouchableOpacity style={styles.zoomButton} onPress={resetView} activeOpacity={0.7}>
              <Text style={styles.resetLabel}>FIT</Text>
            </TouchableOpacity>
          </View>
        </View>

        {/* Bottom Bar */}
        {selectedSeats.length > 0 && (
          <View style={styles.bottomBar}>
            <View style={styles.bottomBarInfo}>
              <Text style={styles.bottomBarCount}>{selectedSeats.length} {selectedSeats.length === 1 ? 'loc' : 'locuri'}</Text>
              <Text style={styles.bottomBarTotal}>{formatCurrency(selectedTotal)}</Text>
              <View style={styles.seatLabelsRow}>
                {selectedSeats.map(s => (
                  <View key={s.seat_uid} style={styles.seatLabelChip}>
                    <Text style={styles.seatLabelChipText}>R{s.row_label}-{s.seat_label}</Text>
                  </View>
                ))}
              </View>
            </View>
            <TouchableOpacity style={styles.confirmButton} onPress={handleConfirm} activeOpacity={0.8}>
              <CheckIcon size={20} color={colors.white} />
              <Text style={styles.confirmButtonText}>Confirma</Text>
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
          <TouchableOpacity style={styles.backButton} onPress={onClose} activeOpacity={0.7}>
            <ArrowLeftIcon size={20} color={colors.textPrimary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>{loading ? 'Harta Locurilor' : 'Selecteaza Locuri'}</Text>
          {selectedSeats.length > 0 ? (
            <View style={styles.selectedBadge}><Text style={styles.selectedBadgeText}>{selectedSeats.length}</Text></View>
          ) : <View style={{ width: 40 }} />}
        </View>
        {renderContent()}
      </View>
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
  legendContainer: { borderBottomWidth: 1, borderBottomColor: colors.border, paddingVertical: 8, paddingHorizontal: 16 },
  legendScroll: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  legendItem: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  legendItemActive: { backgroundColor: colors.purpleBg, borderRadius: 8, paddingHorizontal: 8, paddingVertical: 4, borderWidth: 1, borderColor: colors.purpleBorder },
  legendDot: { width: 10, height: 10, borderRadius: 5 },
  legendText: { fontSize: 11, color: colors.textSecondary },
  legendPrice: { fontSize: 11, fontWeight: '600', color: colors.textTertiary },
  mapContainer: { flex: 1, backgroundColor: '#0f0f1a' },
  zoomControls: { position: 'absolute', right: 12, top: 12, gap: 6 },
  zoomButton: { width: 40, height: 40, borderRadius: 10, backgroundColor: 'rgba(10,10,15,0.9)', borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  resetLabel: { fontSize: 11, fontWeight: '700', color: colors.textSecondary },
  bottomBar: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingVertical: 12, paddingBottom: 28, borderTopWidth: 1, borderTopColor: colors.border, backgroundColor: 'rgba(10,10,15,0.98)', gap: 12 },
  bottomBarInfo: { flex: 1 },
  bottomBarCount: { fontSize: 14, fontWeight: '600', color: colors.textPrimary },
  bottomBarTotal: { fontSize: 18, fontWeight: '700', color: '#a51c30', marginBottom: 4 },
  seatLabelsRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 4 },
  seatLabelChip: { backgroundColor: 'rgba(165,28,48,0.15)', borderWidth: 1, borderColor: 'rgba(165,28,48,0.3)', borderRadius: 6, paddingHorizontal: 6, paddingVertical: 2 },
  seatLabelChipText: { fontSize: 11, fontWeight: '600', color: '#a51c30' },
  confirmButton: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#a51c30', paddingHorizontal: 20, paddingVertical: 14, borderRadius: 12, gap: 8 },
  confirmButtonText: { fontSize: 16, fontWeight: '700', color: colors.white },
});
