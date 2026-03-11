import React, { useState, useEffect, useMemo, useCallback } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  Dimensions,
} from 'react-native';
import Svg, { Circle, Rect, G, Text as SvgText, Path } from 'react-native-svg';
import { GestureHandlerRootView, GestureDetector, Gesture, ScrollView } from 'react-native-gesture-handler';
import Animated, {
  useSharedValue,
  useAnimatedStyle,
  withSpring,
} from 'react-native-reanimated';
import { colors } from '../theme/colors';
import { formatCurrency } from '../utils/formatCurrency';
import { apiGet } from '../api/client';

const { width: SCREEN_WIDTH, height: SCREEN_HEIGHT } = Dimensions.get('window');

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

// ─── Seat Status Colors ──────────────────────────────────────────────────────

const SEAT_COLORS = {
  available: '#10B981',
  selected: '#8B5CF6',
  sold: '#374151',
  held: '#F59E0B',
  blocked: '#EF4444',
  disabled: '#1F2937',
};

// ─── Main Component ──────────────────────────────────────────────────────────

export default function SeatingMapScreen({ eventId, ticketTypeId, onConfirm, onClose }) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [mapData, setMapData] = useState(null);
  const [selectedSeats, setSelectedSeats] = useState([]);

  // Gesture shared values
  const scale = useSharedValue(1);
  const savedScale = useSharedValue(1);
  const translateX = useSharedValue(0);
  const translateY = useSharedValue(0);
  const savedTranslateX = useSharedValue(0);
  const savedTranslateY = useSharedValue(0);

  useEffect(() => {
    loadSeatingMap();
  }, [eventId]);

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

  // Process seats from geometry + statuses
  const processedSeats = useMemo(() => {
    if (!mapData) return [];
    const { sections, seats } = mapData;
    const seatStatusMap = {};
    (seats || []).forEach(s => { seatStatusMap[s.seat_uid] = s; });

    const result = [];
    (sections || []).forEach(section => {
      const sectionX = section.x_position || section.x || 0;
      const sectionY = section.y_position || section.y || 0;
      const sectionMeta = section.metadata || {};
      const seatSize = parseInt(sectionMeta.seat_size) || 15;

      (section.rows || []).forEach(row => {
        (row.seats || []).forEach(seat => {
          const cx = sectionX + (seat.x || 0);
          const cy = sectionY + (row.y || 0) + (seat.y || 0);
          const seatUid = seat.seat_uid || seat.uid;
          const seatInfo = seatStatusMap[seatUid] || {};
          const seatTicketTypeId = seatInfo.ticket_type_id;
          const isAllowed = !ticketTypeId || seatTicketTypeId == ticketTypeId;

          result.push({
            seat_uid: seatUid,
            cx, cy, seatSize,
            status: seatInfo.status || 'available',
            section_name: seatInfo.section_name || section.name || '',
            row_label: seatInfo.row_label || row.label || '',
            seat_label: seatInfo.seat_label || seat.label || '',
            ticket_type_id: seatTicketTypeId,
            ticket_type_name: seatInfo.ticket_type_name,
            price: seatInfo.price || 0,
            color: seatInfo.color || SEAT_COLORS.available,
            isAllowed,
          });
        });
      });
    });
    return result;
  }, [mapData, ticketTypeId]);

  // Canvas dimensions
  const canvas = mapData?.canvas || { width: 1000, height: 800 };

  const baseScale = useMemo(() => {
    const availableWidth = SCREEN_WIDTH - 32;
    const availableHeight = SCREEN_HEIGHT - 280;
    const scaleW = availableWidth / canvas.width;
    const scaleH = availableHeight / canvas.height;
    return Math.min(scaleW, scaleH);
  }, [canvas.width, canvas.height]);

  // Pinch gesture
  const pinchGesture = Gesture.Pinch()
    .onStart(() => { savedScale.value = scale.value; })
    .onUpdate((e) => { scale.value = Math.min(Math.max(savedScale.value * e.scale, 0.5), 5); })
    .onEnd(() => { savedScale.value = scale.value; });

  // Pan gesture
  const panGesture = Gesture.Pan()
    .minPointers(1)
    .onStart(() => {
      savedTranslateX.value = translateX.value;
      savedTranslateY.value = translateY.value;
    })
    .onUpdate((e) => {
      translateX.value = savedTranslateX.value + e.translationX;
      translateY.value = savedTranslateY.value + e.translationY;
    });

  const composedGesture = Gesture.Simultaneous(pinchGesture, panGesture);

  const animatedStyle = useAnimatedStyle(() => ({
    transform: [
      { translateX: translateX.value },
      { translateY: translateY.value },
      { scale: scale.value },
    ],
  }));

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
    scale.value = withSpring(1);
    translateX.value = withSpring(0);
    translateY.value = withSpring(0);
    savedScale.value = 1;
    savedTranslateX.value = 0;
    savedTranslateY.value = 0;
  };

  const ticketTypeLegend = mapData?.ticket_types || [];
  const svgWidth = canvas.width * baseScale;
  const svgHeight = canvas.height * baseScale;

  // ─── Loading / Error States ──────────────────────────────────────────────

  if (loading) {
    return (
      <View style={styles.container}>
        <View style={styles.header}>
          <TouchableOpacity style={styles.backButton} onPress={onClose} activeOpacity={0.7}>
            <ArrowLeftIcon size={20} color={colors.textPrimary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Harta Locurilor</Text>
          <View style={{ width: 40 }} />
        </View>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={colors.purple} />
          <Text style={styles.loadingText}>Se incarca harta...</Text>
        </View>
      </View>
    );
  }

  if (error) {
    return (
      <View style={styles.container}>
        <View style={styles.header}>
          <TouchableOpacity style={styles.backButton} onPress={onClose} activeOpacity={0.7}>
            <ArrowLeftIcon size={20} color={colors.textPrimary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Harta Locurilor</Text>
          <View style={{ width: 40 }} />
        </View>
        <View style={styles.loadingContainer}>
          <Text style={styles.errorText}>{error}</Text>
          <TouchableOpacity style={styles.retryButton} onPress={loadSeatingMap} activeOpacity={0.7}>
            <Text style={styles.retryButtonText}>Reincearca</Text>
          </TouchableOpacity>
        </View>
      </View>
    );
  }

  // ─── Map View ──────────────────────────────────────────────────────

  return (
    <GestureHandlerRootView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity style={styles.backButton} onPress={onClose} activeOpacity={0.7}>
          <ArrowLeftIcon size={20} color={colors.textPrimary} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Selecteaza Locuri</Text>
        {selectedSeats.length > 0 ? (
          <View style={styles.selectedBadge}>
            <Text style={styles.selectedBadgeText}>{selectedSeats.length}</Text>
          </View>
        ) : (
          <View style={{ width: 40 }} />
        )}
      </View>

      {/* Legend */}
      <View style={styles.legendContainer}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.legendScroll}>
          {ticketTypeLegend.map(tt => (
            <View key={tt.id} style={[
              styles.legendItem,
              ticketTypeId && tt.id == ticketTypeId && styles.legendItemActive,
            ]}>
              <View style={[styles.legendDot, { backgroundColor: tt.color || colors.green }]} />
              <Text style={styles.legendText} numberOfLines={1}>{tt.name}</Text>
              <Text style={styles.legendPrice}>{formatCurrency(tt.price)}</Text>
            </View>
          ))}
          <View style={styles.legendItem}>
            <View style={[styles.legendDot, { backgroundColor: SEAT_COLORS.sold, opacity: 0.5 }]} />
            <Text style={styles.legendText}>Vandut</Text>
          </View>
          <View style={styles.legendItem}>
            <View style={[styles.legendDot, { backgroundColor: SEAT_COLORS.selected }]} />
            <Text style={styles.legendText}>Selectat</Text>
          </View>
        </ScrollView>
      </View>

      {/* Map with pinch-to-zoom and pan */}
      <View style={styles.mapContainer}>
        <GestureDetector gesture={composedGesture}>
          <Animated.View style={[styles.mapAnimatedView, animatedStyle]}>
            <Svg width={svgWidth} height={svgHeight} viewBox={`0 0 ${canvas.width} ${canvas.height}`}>
              <Rect x={0} y={0} width={canvas.width} height={canvas.height} fill="#12121e" rx={8} />

              {/* Section labels */}
              {(mapData?.sections || []).map((section, idx) => {
                const sx = section.x_position || section.x || 0;
                const sy = section.y_position || section.y || 0;
                return (
                  <SvgText
                    key={`sl-${idx}`}
                    x={sx + (section.width || 100) / 2}
                    y={sy - 10}
                    fill="rgba(255,255,255,0.4)"
                    fontSize={11}
                    fontWeight="600"
                    textAnchor="middle"
                  >
                    {section.name || ''}
                  </SvgText>
                );
              })}

              {/* Row labels */}
              {(mapData?.sections || []).map((section, sIdx) => {
                const sx = section.x_position || section.x || 0;
                const sy = section.y_position || section.y || 0;
                const seatSize = parseInt((section.metadata || {}).seat_size) || 15;
                const seatRadius = seatSize / 2;
                return (section.rows || []).map((row, rIdx) => {
                  const firstSeat = row.seats?.[0];
                  if (!firstSeat) return null;
                  return (
                    <SvgText
                      key={`r-${sIdx}-${rIdx}`}
                      x={sx + firstSeat.x - seatRadius - 6}
                      y={sy + firstSeat.y + 3}
                      textAnchor="end"
                      fontSize={9}
                      fontWeight="500"
                      fill="rgba(255,255,255,0.4)"
                    >
                      {row.label}
                    </SvgText>
                  );
                });
              })}

              {/* Seats */}
              {processedSeats.map(seat => {
                const isSelected = selectedSeats.some(s => s.seat_uid === seat.seat_uid);
                const seatRadius = (seat.seatSize || 15) / 2;
                const isAvailable = seat.status === 'available';

                let fillColor;
                if (isSelected) fillColor = SEAT_COLORS.selected;
                else if (isAvailable && seat.isAllowed) fillColor = seat.color || SEAT_COLORS.available;
                else if (isAvailable && !seat.isAllowed) fillColor = '#2D2D3D';
                else fillColor = SEAT_COLORS[seat.status] || SEAT_COLORS.disabled;

                const isClickable = (isAvailable && seat.isAllowed) || isSelected;
                const strokeColor = isSelected ? '#FFFFFF' : 'rgba(0,0,0,0.3)';
                const strokeWidth = isSelected ? 1.5 : 0.5;
                const opacity = seat.status === 'disabled' ? 0.3
                  : ['sold', 'held', 'blocked'].includes(seat.status) ? 0.4
                  : (!seat.isAllowed && isAvailable) ? 0.35 : 1;
                const drawRadius = isSelected ? seatRadius * 1.3 : seatRadius;
                const fontSize = Math.round(seatRadius * 0.75 * 10) / 10;

                return (
                  <G key={seat.seat_uid} onPress={isClickable ? () => toggleSeat(seat) : undefined}>
                    <Circle cx={seat.cx} cy={seat.cy} r={Math.max(seatRadius * 1.5, 10)} fill="transparent" />
                    <Circle cx={seat.cx} cy={seat.cy} r={drawRadius} fill={fillColor}
                      stroke={strokeColor} strokeWidth={strokeWidth} opacity={opacity} />
                    {seatRadius >= 6 && (
                      <SvgText x={seat.cx} y={seat.cy + fontSize * 0.35} textAnchor="middle"
                        fontSize={fontSize} fontWeight="600"
                        fill={isSelected ? '#FFFFFF' : 'rgba(0,0,0,0.7)'} opacity={opacity}>
                        {seat.seat_label}
                      </SvgText>
                    )}
                  </G>
                );
              })}
            </Svg>
          </Animated.View>
        </GestureDetector>

        <TouchableOpacity style={styles.resetButton} onPress={resetView} activeOpacity={0.7}>
          <Text style={styles.resetButtonText}>Reset</Text>
        </TouchableOpacity>
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
    </GestureHandlerRootView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  header: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingTop: 12, paddingBottom: 12, borderBottomWidth: 1, borderBottomColor: colors.border, gap: 12 },
  backButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { flex: 1, fontSize: 18, fontWeight: '700', color: colors.textPrimary },
  selectedBadge: { backgroundColor: colors.purple, borderRadius: 12, minWidth: 28, height: 28, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 8 },
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
  mapContainer: { flex: 1, overflow: 'hidden', alignItems: 'center', justifyContent: 'center' },
  mapAnimatedView: { alignItems: 'center', justifyContent: 'center' },
  resetButton: { position: 'absolute', right: 16, top: 16, backgroundColor: 'rgba(10,10,15,0.85)', borderRadius: 10, borderWidth: 1, borderColor: colors.border, paddingHorizontal: 14, paddingVertical: 8 },
  resetButtonText: { fontSize: 12, fontWeight: '600', color: colors.textSecondary },
  bottomBar: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingVertical: 12, borderTopWidth: 1, borderTopColor: colors.border, backgroundColor: 'rgba(10,10,15,0.98)', gap: 12 },
  bottomBarInfo: { flex: 1 },
  bottomBarCount: { fontSize: 14, fontWeight: '600', color: colors.textPrimary },
  bottomBarTotal: { fontSize: 18, fontWeight: '700', color: colors.purple, marginBottom: 4 },
  seatLabelsScroll: { flexGrow: 0, maxHeight: 28 },
  seatLabelChip: { backgroundColor: colors.purpleBg, borderWidth: 1, borderColor: colors.purpleBorder, borderRadius: 6, paddingHorizontal: 6, paddingVertical: 2, marginRight: 4 },
  seatLabelChipText: { fontSize: 11, fontWeight: '600', color: colors.purple },
  confirmButton: { flexDirection: 'row', alignItems: 'center', backgroundColor: colors.purple, paddingHorizontal: 20, paddingVertical: 14, borderRadius: 12, gap: 8 },
  confirmButtonText: { fontSize: 16, fontWeight: '700', color: colors.white },
});
