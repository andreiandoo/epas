import React, { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  Dimensions,
  Animated,
} from 'react-native';
import Svg, { Circle, Rect, G, Text as SvgText, Path } from 'react-native-svg';
import { GestureHandlerRootView, GestureDetector, Gesture, ScrollView } from 'react-native-gesture-handler';
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

export default function SeatingMapScreen({ eventId, ticketTypeId, onConfirm, onClose }) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [mapData, setMapData] = useState(null);
  const [selectedSeats, setSelectedSeats] = useState([]);

  // Gesture values using Animated API
  const scaleAnim = useRef(new Animated.Value(1)).current;
  const translateXAnim = useRef(new Animated.Value(0)).current;
  const translateYAnim = useRef(new Animated.Value(0)).current;
  const gestureState = useRef({ scale: 1, savedScale: 1, tx: 0, ty: 0, savedTx: 0, savedTy: 0 });

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

  // Canvas dimensions
  const canvas = mapData?.canvas || { width: 1000, height: 800 };

  // Base scale: how much to shrink the full-res SVG to fit the screen
  const baseScale = useMemo(() => {
    const availableWidth = SCREEN_WIDTH - 16;
    const availableHeight = SCREEN_HEIGHT - 260;
    const scaleW = availableWidth / canvas.width;
    const scaleH = availableHeight / canvas.height;
    return Math.min(scaleW, scaleH);
  }, [canvas.width, canvas.height]);

  // Process seats from geometry + statuses
  // IMPORTANT: seat.x/seat.y are absolute within the section (same as website)
  // Do NOT add row.y — it's already baked into seat.y
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

  // Center map on load — match website's centerMapToContent()
  useEffect(() => {
    if (mapData) {
      const availableWidth = SCREEN_WIDTH - 16;
      const availableHeight = SCREEN_HEIGHT - 260;
      const scaledW = canvas.width * baseScale;
      const scaledH = canvas.height * baseScale;
      const centerTx = (availableWidth - scaledW) / 2;
      const centerTy = (availableHeight - scaledH) / 2;
      scaleAnim.setValue(baseScale);
      translateXAnim.setValue(centerTx);
      translateYAnim.setValue(centerTy);
      gestureState.current = { scale: baseScale, savedScale: baseScale, tx: centerTx, ty: centerTy, savedTx: centerTx, savedTy: centerTy };
    }
  }, [mapData, baseScale]);

  // Pinch gesture — scale range: baseScale * 0.5 to baseScale * 8
  const pinchGesture = Gesture.Pinch()
    .onStart(() => { gestureState.current.savedScale = gestureState.current.scale; })
    .onUpdate((e) => {
      const minScale = baseScale * 0.5;
      const maxScale = baseScale * 8;
      const newScale = Math.min(Math.max(gestureState.current.savedScale * e.scale, minScale), maxScale);
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
    const availableWidth = SCREEN_WIDTH - 16;
    const availableHeight = SCREEN_HEIGHT - 260;
    const scaledW = canvas.width * baseScale;
    const scaledH = canvas.height * baseScale;
    const centerTx = (availableWidth - scaledW) / 2;
    const centerTy = (availableHeight - scaledH) / 2;
    Animated.parallel([
      Animated.spring(scaleAnim, { toValue: baseScale, useNativeDriver: true }),
      Animated.spring(translateXAnim, { toValue: centerTx, useNativeDriver: true }),
      Animated.spring(translateYAnim, { toValue: centerTy, useNativeDriver: true }),
    ]).start();
    gestureState.current = { scale: baseScale, savedScale: baseScale, tx: centerTx, ty: centerTy, savedTx: centerTx, savedTy: centerTy };
  };

  const zoomIn = () => {
    const newScale = Math.min(gestureState.current.scale * 1.5, baseScale * 8);
    gestureState.current.scale = newScale;
    gestureState.current.savedScale = newScale;
    Animated.spring(scaleAnim, { toValue: newScale, useNativeDriver: true }).start();
  };

  const zoomOut = () => {
    const newScale = Math.max(gestureState.current.scale / 1.5, baseScale * 0.5);
    gestureState.current.scale = newScale;
    gestureState.current.savedScale = newScale;
    Animated.spring(scaleAnim, { toValue: newScale, useNativeDriver: true }).start();
  };

  const ticketTypeLegend = mapData?.ticket_types || [];

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
              transform: [
                { translateX: translateXAnim },
                { translateY: translateYAnim },
                { scale: scaleAnim },
              ],
            },
          ]}>
            {/* SVG at FULL canvas resolution for crisp vector rendering at any zoom */}
            <Svg
              width={canvas.width}
              height={canvas.height}
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

              {/* Seats */}
              {processedData.seats.map(seat => {
                const isSelected = selectedSeats.some(s => s.seat_uid === seat.seat_uid);
                const seatRadius = seat.seatRadius;
                const isAvailable = seat.status === 'available';

                // Colors matching website: ticket type color + white stroke, selected = #a51c30
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
                  // sold, held, blocked, disabled
                  fillColor = '#9CA3AF';
                  strokeColor = 'rgba(255,255,255,0.15)';
                  strokeWidth = 0.5;
                  opacity = seat.status === 'disabled' ? 0.25 : 0.45;
                }

                const isClickable = (isAvailable && seat.isAllowed) || isSelected;
                const drawRadius = isSelected ? seatRadius * 1.4 : seatRadius;
                const fontSize = Math.round(seatRadius * 0.85 * 10) / 10;
                // Larger invisible hit target for easier tapping
                const hitRadius = Math.max(seatRadius * 1.8, 12);

                return (
                  <G key={seat.seat_uid} onPress={isClickable ? () => toggleSeat(seat) : undefined}>
                    {/* Invisible hit target */}
                    <Circle cx={seat.cx} cy={seat.cy} r={hitRadius} fill="transparent" />
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
            <SvgText style={{ fontSize: 11, fontWeight: '700', color: colors.textSecondary }}>
              FIT
            </SvgText>
            <Text style={styles.resetLabel}>Reset</Text>
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
    </GestureHandlerRootView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  header: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingTop: 12, paddingBottom: 12, borderBottomWidth: 1, borderBottomColor: colors.border, gap: 12 },
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
  mapAnimatedView: {},
  zoomControls: { position: 'absolute', right: 12, top: 12, gap: 6 },
  zoomButton: { width: 40, height: 40, borderRadius: 10, backgroundColor: 'rgba(10,10,15,0.9)', borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  resetLabel: { fontSize: 9, fontWeight: '600', color: colors.textTertiary, marginTop: -1 },
  bottomBar: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingVertical: 12, borderTopWidth: 1, borderTopColor: colors.border, backgroundColor: 'rgba(10,10,15,0.98)', gap: 12 },
  bottomBarInfo: { flex: 1 },
  bottomBarCount: { fontSize: 14, fontWeight: '600', color: colors.textPrimary },
  bottomBarTotal: { fontSize: 18, fontWeight: '700', color: '#a51c30', marginBottom: 4 },
  seatLabelsScroll: { flexGrow: 0, maxHeight: 28 },
  seatLabelChip: { backgroundColor: 'rgba(165,28,48,0.15)', borderWidth: 1, borderColor: 'rgba(165,28,48,0.3)', borderRadius: 6, paddingHorizontal: 6, paddingVertical: 2, marginRight: 4 },
  seatLabelChipText: { fontSize: 11, fontWeight: '600', color: '#a51c30' },
  confirmButton: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#a51c30', paddingHorizontal: 20, paddingVertical: 14, borderRadius: 12, gap: 8 },
  confirmButtonText: { fontSize: 16, fontWeight: '700', color: colors.white },
});
