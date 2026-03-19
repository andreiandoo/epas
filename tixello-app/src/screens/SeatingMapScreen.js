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

// ─── SVG Icon Components ──────────────────────────────────────────────────────

function ArrowLeftIcon({ size = 24, color = colors.white }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M19 12H5M12 19l-7-7 7-7"
        stroke={color}
        strokeWidth={1.8}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </Svg>
  );
}

function CheckIcon({ size = 20, color = colors.white }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M20 6L9 17l-5-5"
        stroke={color}
        strokeWidth={2}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
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

// ─── Main Component ──────────────────────────────────────────────────────────

export default function SeatingMapScreen({ visible, eventId, ticketTypeId, onConfirm, onClose }) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [mapData, setMapData] = useState(null);
  const [selectedSeats, setSelectedSeats] = useState([]);
  // Dynamically measured map area size
  const [mapSize, setMapSize] = useState({ width: SCREEN_WIDTH, height: 500 });

  // Gesture values
  const scaleAnim = useRef(new Animated.Value(1)).current;
  const translateXAnim = useRef(new Animated.Value(0)).current;
  const translateYAnim = useRef(new Animated.Value(0)).current;
  const gestureState = useRef({ scale: 1, savedScale: 1, tx: 0, ty: 0, savedTx: 0, savedTy: 0 });

  // Refs for gesture callbacks (avoid stale closures)
  const processedDataRef = useRef({ seats: [], rowLabels: [], sections: [] });
  const selectedUidsRef = useRef(new Set());

  useEffect(() => {
    if (visible && eventId) {
      loadSeatingMap();
    }
    if (!visible) {
      setSelectedSeats([]);
      setError(null);
    }
  }, [visible, eventId]);

  const loadSeatingMap = async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await apiGet(`/organizer/events/${eventId}/seating-map`);
      if (response.success && response.data) {
        setMapData(response.data);
      } else {
        setError(response.message || 'Nu s-a putut incarca harta');
      }
    } catch (e) {
      console.error('Failed to load seating map:', e);
      setError(e.message || 'Eroare la incarcarea hartii');
    }
    setLoading(false);
  };

  const canvas = mapData?.canvas || { width: 1000, height: 800 };

  // Fit zoom based on dynamically measured map area
  const fitZoom = useMemo(() => {
    const padX = 20;
    const padY = 20;
    const availW = mapSize.width - padX;
    const availH = mapSize.height - padY;
    const zoomW = availW / canvas.width;
    const zoomH = availH / canvas.height;
    return Math.min(zoomW, zoomH);
  }, [canvas.width, canvas.height, mapSize.width, mapSize.height]);

  // Process seats from geometry + statuses
  const processedData = useMemo(() => {
    if (!mapData) return { seats: [], rowLabels: [], sections: [] };
    const { sections, seats } = mapData;
    const seatStatusMap = {};
    (seats || []).forEach(s => { seatStatusMap[s.seat_uid] = s; });

    const seatList = [];
    const rowLabelList = [];

    (sections || []).forEach(section => {
      const sectionX = section.x_position || section.x || 0;
      const sectionY = section.y_position || section.y || 0;
      const sectionMeta = section.metadata || {};
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
          const seatUid = seat.seat_uid || seat.uid;
          const seatInfo = seatStatusMap[seatUid] || {};
          const seatTicketTypeId = seatInfo.ticket_type_id;
          const isAllowed = !ticketTypeId || seatTicketTypeId == ticketTypeId;

          seatList.push({
            seat_uid: seatUid,
            cx, cy, seatSize, seatRadius,
            status: seatInfo.status || 'available',
            section_name: seatInfo.section_name || section.name || '',
            row_label: seatInfo.row_label || row.label || '',
            seat_label: seatInfo.seat_label || seat.label || '',
            ticket_type_id: seatTicketTypeId,
            ticket_type_name: seatInfo.ticket_type_name,
            price: seatInfo.price || 0,
            color: seatInfo.color || '#10B981',
            isAllowed,
          });
        });
      });
    });
    const result = { seats: seatList, rowLabels: rowLabelList, sections: sections || [] };
    processedDataRef.current = result;
    return result;
  }, [mapData, ticketTypeId]);

  // Centering helper
  const getCenterPan = useCallback((zoom, size) => {
    const w = size?.width || mapSize.width;
    const h = size?.height || mapSize.height;
    return {
      panX: (w - canvas.width * zoom) / 2,
      panY: (h - canvas.height * zoom) / 2,
    };
  }, [canvas.width, canvas.height, mapSize]);

  // Center map on load and when mapSize changes
  useEffect(() => {
    if (mapData && mapSize.width > 0 && mapSize.height > 0) {
      const { panX, panY } = getCenterPan(fitZoom);
      scaleAnim.setValue(fitZoom);
      translateXAnim.setValue(panX);
      translateYAnim.setValue(panY);
      gestureState.current = {
        scale: fitZoom, savedScale: fitZoom,
        tx: panX, ty: panY, savedTx: panX, savedTy: panY,
      };
    }
  }, [mapData, fitZoom, mapSize]);

  // Measure actual map container size
  const onMapLayout = useCallback((e) => {
    const { width, height } = e.nativeEvent.layout;
    if (width > 0 && height > 0) {
      setMapSize({ width, height });
    }
  }, []);

  // Selected seats set for O(1) lookup
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

  // Ref for toggleSeat so gesture callbacks always have the latest
  const toggleSeatRef = useRef(toggleSeat);
  useEffect(() => { toggleSeatRef.current = toggleSeat; }, [toggleSeat]);

  // ─── Gesture handlers ──────────────────────────────────────────────────────

  // Tap gesture: convert screen coords → canvas coords → find seat
  const tapGesture = Gesture.Tap()
    .maxDuration(300)
    .maxDistance(10)
    .onEnd((e) => {
      const gs = gestureState.current;
      // Convert tap position (in mapContainer coords) to canvas coords
      const canvasX = (e.x - gs.tx) / gs.scale;
      const canvasY = (e.y - gs.ty) / gs.scale;

      // Find nearest clickable seat
      const seats = processedDataRef.current.seats;
      const uids = selectedUidsRef.current;
      let bestSeat = null;
      let bestDist = Infinity;

      for (let i = 0; i < seats.length; i++) {
        const s = seats[i];
        const isAvailable = s.status === 'available';
        const isSelected = uids.has(s.seat_uid);
        const isClickable = (isAvailable && s.isAllowed) || isSelected;
        if (!isClickable) continue;

        const dx = canvasX - s.cx;
        const dy = canvasY - s.cy;
        const dist = Math.sqrt(dx * dx + dy * dy);
        const hitRadius = Math.max(s.seatRadius * 2, 14);

        if (dist < hitRadius && dist < bestDist) {
          bestDist = dist;
          bestSeat = s;
        }
      }

      if (bestSeat) {
        toggleSeatRef.current(bestSeat);
      }
    });

  // Pinch gesture: zoom toward focal point
  const pinchGesture = Gesture.Pinch()
    .onStart((e) => {
      const gs = gestureState.current;
      gs.savedScale = gs.scale;
      gs.savedTx = gs.tx;
      gs.savedTy = gs.ty;
      gs.pinchFocalX = e.focalX;
      gs.pinchFocalY = e.focalY;
    })
    .onUpdate((e) => {
      const gs = gestureState.current;
      const minScale = fitZoom * 0.5;
      const maxScale = fitZoom * 8;
      const newScale = Math.min(Math.max(gs.savedScale * e.scale, minScale), maxScale);

      // Zoom toward focal point:
      // The canvas point under the initial focal should move to the current focal
      const scaleRatio = newScale / gs.savedScale;
      const newTx = e.focalX - (gs.pinchFocalX - gs.savedTx) * scaleRatio;
      const newTy = e.focalY - (gs.pinchFocalY - gs.savedTy) * scaleRatio;

      gs.scale = newScale;
      gs.tx = newTx;
      gs.ty = newTy;
      scaleAnim.setValue(newScale);
      translateXAnim.setValue(newTx);
      translateYAnim.setValue(newTy);
    })
    .onEnd(() => {
      const gs = gestureState.current;
      gs.savedScale = gs.scale;
      gs.savedTx = gs.tx;
      gs.savedTy = gs.ty;
    });

  // Pan gesture: 1-finger drag only
  const panGesture = Gesture.Pan()
    .minPointers(1)
    .maxPointers(1)
    .minDistance(10)
    .onStart(() => {
      const gs = gestureState.current;
      gs.savedTx = gs.tx;
      gs.savedTy = gs.ty;
    })
    .onUpdate((e) => {
      const gs = gestureState.current;
      gs.tx = gs.savedTx + e.translationX;
      gs.ty = gs.savedTy + e.translationY;
      translateXAnim.setValue(gs.tx);
      translateYAnim.setValue(gs.ty);
    })
    .onEnd(() => {
      const gs = gestureState.current;
      gs.savedTx = gs.tx;
      gs.savedTy = gs.ty;
    });

  // Compose: pinch runs independently; 1-finger is Race(pan, tap)
  const composedGesture = Gesture.Simultaneous(
    pinchGesture,
    Gesture.Race(panGesture, tapGesture)
  );

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
    const cartItems = selectedByType.map(group => ({
      id: group.ticket_type_id, name: group.name, price: group.price, color: group.color, quantity: group.seats.length,
    }));
    const seatUids = selectedSeats.map(s => s.seat_uid);
    onConfirm({
      cartItems, seatUids,
      selectedSeats: selectedSeats.map(s => ({
        seat_uid: s.seat_uid, section_name: s.section_name, row_label: s.row_label,
        seat_label: s.seat_label, ticket_type_id: s.ticket_type_id, price: s.price,
      })),
    });
  };

  // Zoom toward center of screen
  const zoomToCenter = useCallback((newScale) => {
    const gs = gestureState.current;
    const centerX = mapSize.width / 2;
    const centerY = mapSize.height / 2;
    const scaleRatio = newScale / gs.scale;
    const newTx = centerX - (centerX - gs.tx) * scaleRatio;
    const newTy = centerY - (centerY - gs.ty) * scaleRatio;

    gs.scale = newScale;
    gs.savedScale = newScale;
    gs.tx = newTx;
    gs.ty = newTy;
    gs.savedTx = newTx;
    gs.savedTy = newTy;

    Animated.parallel([
      Animated.spring(scaleAnim, { toValue: newScale, useNativeDriver: true }),
      Animated.spring(translateXAnim, { toValue: newTx, useNativeDriver: true }),
      Animated.spring(translateYAnim, { toValue: newTy, useNativeDriver: true }),
    ]).start();
  }, [mapSize]);

  const resetView = useCallback(() => {
    const { panX, panY } = getCenterPan(fitZoom);
    Animated.parallel([
      Animated.spring(scaleAnim, { toValue: fitZoom, useNativeDriver: true }),
      Animated.spring(translateXAnim, { toValue: panX, useNativeDriver: true }),
      Animated.spring(translateYAnim, { toValue: panY, useNativeDriver: true }),
    ]).start();
    gestureState.current = {
      scale: fitZoom, savedScale: fitZoom,
      tx: panX, ty: panY, savedTx: panX, savedTy: panY,
    };
  }, [fitZoom, getCenterPan]);

  const zoomIn = useCallback(() => {
    const newScale = Math.min(gestureState.current.scale * 1.5, fitZoom * 8);
    zoomToCenter(newScale);
  }, [fitZoom, zoomToCenter]);

  const zoomOut = useCallback(() => {
    const newScale = Math.max(gestureState.current.scale / 1.5, fitZoom * 0.5);
    zoomToCenter(newScale);
  }, [fitZoom, zoomToCenter]);

  const ticketTypeLegend = mapData?.ticket_types || [];

  // ─── Render ──────────────────────────────────────────────────────

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
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.legendScroll}>
            {ticketTypeLegend.map(tt => (
              <View key={tt.id} style={[
                styles.legendItem,
                ticketTypeId && tt.id == ticketTypeId && styles.legendItemActive,
              ]}>
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
          </ScrollView>
        </View>

        {/* Map — GestureDetector on a stable container, not the animated view */}
        <View style={styles.mapContainer} onLayout={onMapLayout}>
          <GestureDetector gesture={composedGesture}>
            <View style={styles.gestureArea}>
              <Animated.View style={[
                styles.mapAnimatedView,
                {
                  transform: [
                    { translateX: translateXAnim },
                    { translateY: translateYAnim },
                    { scale: scaleAnim },
                  ],
                },
              ]}>
                {/* SVG at FULL canvas resolution — stays crisp at any zoom */}
                <Svg
                  width={canvas.width}
                  height={canvas.height}
                  viewBox={`0 0 ${canvas.width} ${canvas.height}`}
                >
                  {/* Background */}
                  <Rect x={0} y={0} width={canvas.width} height={canvas.height} fill="#0f0f1a" rx={8} />

                  {/* Section labels */}
                  {processedData.sections.map((section, idx) => {
                    const sx = section.x_position || section.x || 0;
                    const sy = section.y_position || section.y || 0;
                    const rotation = section.rotation || 0;
                    const sectionW = section.width || 100;
                    const sectionH = section.height || 100;
                    const rcx = sx + sectionW / 2;
                    const rcy = sy + sectionH / 2;
                    const transform = rotation !== 0
                      ? `rotate(${rotation} ${rcx} ${rcy})`
                      : undefined;

                    return (
                      <SvgText
                        key={`sl-${idx}`}
                        x={sx + sectionW / 2}
                        y={sy - 12}
                        fill="rgba(255,255,255,0.5)"
                        fontSize={12}
                        fontWeight="700"
                        textAnchor="middle"
                        transform={transform}
                      >
                        {section.name || ''}
                      </SvgText>
                    );
                  })}

                  {/* Row labels */}
                  {processedData.rowLabels.map(rl => (
                    <SvgText
                      key={rl.key}
                      x={rl.x}
                      y={rl.y}
                      textAnchor="end"
                      fontSize={rl.fontSize}
                      fontWeight="500"
                      fill="rgba(255,255,255,0.45)"
                    >
                      {rl.label}
                    </SvgText>
                  ))}

                  {/* Seats — NO onPress, taps handled via gesture system */}
                  {processedData.seats.map(seat => {
                    const isSelected = selectedUids.has(seat.seat_uid);
                    const seatRadius = seat.seatRadius;
                    const isAvailable = seat.status === 'available';

                    let fillColor, strokeColor, strokeWidth, opacity;

                    if (isSelected) {
                      fillColor = '#a51c30';
                      strokeColor = '#7a141f';
                      strokeWidth = 1.5;
                      opacity = 1;
                    } else if (isAvailable && seat.isAllowed) {
                      fillColor = seat.color;
                      strokeColor = '#ffffff';
                      strokeWidth = 0.8;
                      opacity = 1;
                    } else if (isAvailable && !seat.isAllowed) {
                      fillColor = '#2D2D3D';
                      strokeColor = 'rgba(255,255,255,0.1)';
                      strokeWidth = 0.5;
                      opacity = 0.4;
                    } else {
                      fillColor = '#9CA3AF';
                      strokeColor = 'rgba(255,255,255,0.15)';
                      strokeWidth = 0.5;
                      opacity = seat.status === 'disabled' ? 0.25 : 0.45;
                    }

                    const drawRadius = isSelected ? seatRadius * 1.4 : seatRadius;
                    const fontSize = Math.round(seatRadius * 0.85 * 10) / 10;

                    return (
                      <G key={seat.seat_uid}>
                        {/* Visible seat */}
                        <Circle
                          cx={seat.cx} cy={seat.cy} r={drawRadius}
                          fill={fillColor}
                          stroke={strokeColor}
                          strokeWidth={strokeWidth}
                          opacity={opacity}
                        />
                        {/* Seat label */}
                        {seatRadius >= 5 && (
                          <SvgText
                            x={seat.cx}
                            y={seat.cy + fontSize * 0.35}
                            textAnchor="middle"
                            fontSize={fontSize}
                            fontWeight="700"
                            fill={isSelected ? '#ffffff' : 'rgba(255,255,255,0.85)'}
                            opacity={opacity}
                          >
                            {seat.seat_label}
                          </SvgText>
                        )}
                      </G>
                    );
                  })}
                </Svg>
              </Animated.View>
            </View>
          </GestureDetector>

          {/* Zoom controls — outside GestureDetector so they stay tappable */}
          <View style={styles.zoomControls} pointerEvents="box-none">
            <TouchableOpacity style={styles.zoomButton} onPress={zoomIn} activeOpacity={0.7}>
              <ZoomInIcon size={18} color={colors.textPrimary} />
            </TouchableOpacity>
            <TouchableOpacity style={styles.zoomButton} onPress={zoomOut} activeOpacity={0.7}>
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
              <Text style={styles.bottomBarCount}>
                {selectedSeats.length} {selectedSeats.length === 1 ? 'loc' : 'locuri'}
              </Text>
              <Text style={styles.bottomBarTotal}>{formatCurrency(selectedTotal)}</Text>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.seatLabelsScroll}>
                {selectedSeats.map(s => (
                  <View key={s.seat_uid} style={styles.seatLabelChip}>
                    <Text style={styles.seatLabelChipText}>R{s.row_label}-{s.seat_label}</Text>
                  </View>
                ))}
              </ScrollView>
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
    <Modal
      visible={visible}
      animationType="slide"
      statusBarTranslucent
      onRequestClose={onClose}
    >
      <GestureHandlerRootView style={styles.container}>
        <View style={styles.header}>
          <TouchableOpacity style={styles.backButton} onPress={onClose} activeOpacity={0.7}>
            <ArrowLeftIcon size={20} color={colors.textPrimary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>
            {loading ? 'Harta Locurilor' : 'Selecteaza Locuri'}
          </Text>
          {selectedSeats.length > 0 ? (
            <View style={styles.selectedBadge}>
              <Text style={styles.selectedBadgeText}>{selectedSeats.length}</Text>
            </View>
          ) : (
            <View style={{ width: 40 }} />
          )}
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
  gestureArea: { flex: 1 },
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
