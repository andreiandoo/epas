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
import Svg, { Circle, Rect, Text as SvgText, Path } from 'react-native-svg';
import { GestureHandlerRootView, GestureDetector, Gesture, ScrollView } from 'react-native-gesture-handler';
import { colors } from '../theme/colors';
import { formatCurrency } from '../utils/formatCurrency';
import { apiGet } from '../api/client';

const { width: SCREEN_WIDTH, height: SCREEN_HEIGHT } = Dimensions.get('window');
const AVAILABLE_WIDTH = SCREEN_WIDTH;
const HEADER_HEIGHT = 56;
const LEGEND_HEIGHT = 44;
const BOTTOM_BAR_HEIGHT = 90;
const MAP_HEIGHT = SCREEN_HEIGHT - HEADER_HEIGHT - LEGEND_HEIGHT - 40;

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

// ─── Seat Component (memoized for performance) ─────────────────────────────

const SeatCircle = React.memo(function SeatCircle({ seat, isSelected, onPress }) {
  const seatRadius = seat.seatRadius;
  const isAvailable = seat.status === 'available';

  let fillColor, strokeColor, strokeW, opacity;

  if (isSelected) {
    fillColor = '#a51c30';
    strokeColor = '#7a141f';
    strokeW = 1.5;
    opacity = 1;
  } else if (isAvailable && seat.isAllowed) {
    fillColor = seat.color;
    strokeColor = '#ffffff';
    strokeW = 0.8;
    opacity = 1;
  } else if (isAvailable && !seat.isAllowed) {
    fillColor = '#2D2D3D';
    strokeColor = 'rgba(255,255,255,0.1)';
    strokeW = 0.5;
    opacity = 0.4;
  } else {
    fillColor = '#9CA3AF';
    strokeColor = 'rgba(255,255,255,0.15)';
    strokeW = 0.5;
    opacity = seat.status === 'disabled' ? 0.25 : 0.45;
  }

  const isClickable = (isAvailable && seat.isAllowed) || isSelected;
  const drawRadius = isSelected ? seatRadius * 1.4 : seatRadius;

  return (
    <Circle
      cx={seat.cx}
      cy={seat.cy}
      r={drawRadius}
      fill={fillColor}
      stroke={strokeColor}
      strokeWidth={strokeW}
      opacity={opacity}
      onPress={isClickable ? onPress : undefined}
    />
  );
});

// ─── Main Component ──────────────────────────────────────────────────────────

export default function SeatingMapScreen({ visible, eventId, ticketTypeId, onConfirm, onClose }) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [mapData, setMapData] = useState(null);
  const [selectedSeats, setSelectedSeats] = useState([]);

  // Gesture: start at scale=1 (SVG viewBox handles base fit), translate=(0,0)
  const scaleAnim = useRef(new Animated.Value(1)).current;
  const translateXAnim = useRef(new Animated.Value(0)).current;
  const translateYAnim = useRef(new Animated.Value(0)).current;
  const gestureState = useRef({ scale: 1, savedScale: 1, tx: 0, ty: 0, savedTx: 0, savedTy: 0 });

  useEffect(() => {
    if (visible && eventId) {
      loadSeatingMap();
    }
    if (!visible) {
      // Reset state when closing
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

  // Canvas dimensions
  const canvas = mapData?.canvas || { width: 1000, height: 800 };

  // Process seats from geometry + statuses
  const processedData = useMemo(() => {
    if (!mapData) return { seats: [], rowLabels: [] };
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
    return { seats: seatList, rowLabels: rowLabelList };
  }, [mapData, ticketTypeId]);

  // Reset view on new map data
  useEffect(() => {
    if (mapData) {
      scaleAnim.setValue(1);
      translateXAnim.setValue(0);
      translateYAnim.setValue(0);
      gestureState.current = { scale: 1, savedScale: 1, tx: 0, ty: 0, savedTx: 0, savedTy: 0 };
    }
  }, [mapData]);

  // Pinch gesture — scale range: 0.5x to 8x of fitted view
  const pinchGesture = Gesture.Pinch()
    .onStart(() => { gestureState.current.savedScale = gestureState.current.scale; })
    .onUpdate((e) => {
      const newScale = Math.min(Math.max(gestureState.current.savedScale * e.scale, 0.5), 8);
      gestureState.current.scale = newScale;
      scaleAnim.setValue(newScale);
    })
    .onEnd(() => { gestureState.current.savedScale = gestureState.current.scale; });

  // Pan gesture
  const panGesture = Gesture.Pan()
    .minPointers(1)
    .onStart(() => {
      gestureState.current.savedTx = gestureState.current.tx;
      gestureState.current.savedTy = gestureState.current.ty;
    })
    .onUpdate((e) => {
      const newTx = gestureState.current.savedTx + e.translationX;
      const newTy = gestureState.current.savedTy + e.translationY;
      gestureState.current.tx = newTx;
      gestureState.current.ty = newTy;
      translateXAnim.setValue(newTx);
      translateYAnim.setValue(newTy);
    });

  const composedGesture = Gesture.Simultaneous(pinchGesture, panGesture);

  // Selected seats set for O(1) lookup
  const selectedUids = useMemo(() => {
    const set = new Set();
    selectedSeats.forEach(s => set.add(s.seat_uid));
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

  const resetView = () => {
    Animated.parallel([
      Animated.spring(scaleAnim, { toValue: 1, useNativeDriver: true }),
      Animated.spring(translateXAnim, { toValue: 0, useNativeDriver: true }),
      Animated.spring(translateYAnim, { toValue: 0, useNativeDriver: true }),
    ]).start();
    gestureState.current = { scale: 1, savedScale: 1, tx: 0, ty: 0, savedTx: 0, savedTy: 0 };
  };

  const zoomIn = () => {
    const newScale = Math.min(gestureState.current.scale * 1.5, 8);
    gestureState.current.scale = newScale;
    gestureState.current.savedScale = newScale;
    Animated.spring(scaleAnim, { toValue: newScale, useNativeDriver: true }).start();
  };

  const zoomOut = () => {
    const newScale = Math.max(gestureState.current.scale / 1.5, 0.5);
    gestureState.current.scale = newScale;
    gestureState.current.savedScale = newScale;
    Animated.spring(scaleAnim, { toValue: newScale, useNativeDriver: true }).start();
  };

  const ticketTypeLegend = mapData?.ticket_types || [];

  // ─── Render content based on state ──────────────────────────────────────

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

        {/* Map with pinch-to-zoom and pan */}
        <View style={styles.mapContainer}>
          <GestureDetector gesture={composedGesture}>
            <Animated.View style={[
              styles.mapAnimatedView,
              {
                width: AVAILABLE_WIDTH,
                height: MAP_HEIGHT,
                transform: [
                  { scale: scaleAnim },
                  { translateX: translateXAnim },
                  { translateY: translateYAnim },
                ],
              },
            ]}>
              {/* SVG uses viewBox for auto-fit — no manual scale math needed */}
              <Svg
                width={AVAILABLE_WIDTH}
                height={MAP_HEIGHT}
                viewBox={`0 0 ${canvas.width} ${canvas.height}`}
              >
                {/* Background */}
                <Rect x={0} y={0} width={canvas.width} height={canvas.height} fill="#0f0f1a" rx={8} />

                {/* Section labels */}
                {(mapData?.sections || []).map((section, idx) => {
                  const sx = section.x_position || section.x || 0;
                  const sy = section.y_position || section.y || 0;
                  return (
                    <SvgText
                      key={`sl-${idx}`}
                      x={sx + (section.width || 100) / 2}
                      y={sy - 12}
                      fill="rgba(255,255,255,0.5)"
                      fontSize={12}
                      fontWeight="700"
                      textAnchor="middle"
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

                {/* Seats — single Circle per seat for performance */}
                {processedData.seats.map(seat => (
                  <SeatCircle
                    key={seat.seat_uid}
                    seat={seat}
                    isSelected={selectedUids.has(seat.seat_uid)}
                    onPress={() => toggleSeat(seat)}
                  />
                ))}
              </Svg>
            </Animated.View>
          </GestureDetector>

          {/* Zoom controls */}
          <View style={styles.zoomControls}>
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
  mapAnimatedView: { transformOrigin: 'center center' },
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
