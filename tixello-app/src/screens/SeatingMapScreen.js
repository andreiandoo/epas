import React, { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  ScrollView,
  Dimensions,
  Alert,
} from 'react-native';
import Svg, { Circle, Rect, G, Text as SvgText, Path, Defs, LinearGradient, Stop } from 'react-native-svg';
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

function ZoomInIcon({ size = 22, color = colors.white }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Circle cx={11} cy={11} r={8} stroke={color} strokeWidth={1.8} />
      <Path d="M21 21l-4.35-4.35M11 8v6M8 11h6" stroke={color} strokeWidth={1.8} strokeLinecap="round" />
    </Svg>
  );
}

function ZoomOutIcon({ size = 22, color = colors.white }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Circle cx={11} cy={11} r={8} stroke={color} strokeWidth={1.8} />
      <Path d="M21 21l-4.35-4.35M8 11h6" stroke={color} strokeWidth={1.8} strokeLinecap="round" />
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
  available: '#10B981',    // green
  selected: '#8B5CF6',     // purple
  sold: '#374151',         // dark gray
  held: '#F59E0B',         // amber
  blocked: '#EF4444',      // red
  disabled: '#1F2937',     // very dark
};

// ─── Seat Component ──────────────────────────────────────────────────────────

const SEAT_RADIUS = 6;

function SeatCircle({ seat, isSelected, onPress, scale }) {
  const color = isSelected
    ? SEAT_COLORS.selected
    : seat.status === 'available'
      ? (seat.color || SEAT_COLORS.available)
      : SEAT_COLORS[seat.status] || SEAT_COLORS.disabled;

  const isClickable = seat.status === 'available' || isSelected;
  const strokeColor = isSelected ? '#FFFFFF' : 'transparent';
  const strokeWidth = isSelected ? 1.5 : 0;

  // Increase touch target with a larger invisible circle
  const touchRadius = Math.max(SEAT_RADIUS * 1.8, 10);

  return (
    <G onPress={isClickable ? onPress : undefined}>
      {/* Invisible touch target */}
      <Circle
        cx={seat.cx}
        cy={seat.cy}
        r={touchRadius}
        fill="transparent"
      />
      {/* Visible seat */}
      <Circle
        cx={seat.cx}
        cy={seat.cy}
        r={SEAT_RADIUS}
        fill={color}
        stroke={strokeColor}
        strokeWidth={strokeWidth}
        opacity={seat.status === 'disabled' ? 0.3 : seat.status === 'sold' || seat.status === 'held' || seat.status === 'blocked' ? 0.5 : 1}
      />
    </G>
  );
}

// ─── Main Component ──────────────────────────────────────────────────────────

export default function SeatingMapScreen({ eventId, onConfirm, onClose }) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [mapData, setMapData] = useState(null);
  const [selectedSeats, setSelectedSeats] = useState([]);
  const [zoom, setZoom] = useState(1);
  const scrollViewRef = useRef(null);

  // Load seating map data
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

    // Build seat status lookup from seats array
    const seatStatusMap = {};
    (seats || []).forEach(s => {
      seatStatusMap[s.seat_uid] = s;
    });

    const result = [];

    // Process geometry sections to get seat positions
    (sections || []).forEach(section => {
      const sectionX = section.x_position || section.x || 0;
      const sectionY = section.y_position || section.y || 0;

      (section.rows || []).forEach(row => {
        (row.seats || []).forEach(seat => {
          const cx = sectionX + (seat.x || 0);
          const cy = sectionY + (row.y || 0) + (seat.y || 0);
          const seatUid = seat.seat_uid || seat.uid;

          // Get status and ticket info from seat data
          const seatInfo = seatStatusMap[seatUid] || {};

          result.push({
            seat_uid: seatUid,
            cx,
            cy,
            status: seatInfo.status || 'available',
            section_name: seatInfo.section_name || section.name || '',
            row_label: seatInfo.row_label || row.label || '',
            seat_label: seatInfo.seat_label || seat.label || '',
            ticket_type_id: seatInfo.ticket_type_id,
            ticket_type_name: seatInfo.ticket_type_name,
            price: seatInfo.price || 0,
            color: seatInfo.color || SEAT_COLORS.available,
          });
        });
      });
    });

    return result;
  }, [mapData]);

  // Canvas dimensions
  const canvas = mapData?.canvas || { width: 1000, height: 800 };

  // Calculate initial scale to fit screen width with padding
  const baseScale = useMemo(() => {
    const availableWidth = SCREEN_WIDTH - 32; // 16px padding each side
    return availableWidth / canvas.width;
  }, [canvas.width]);

  const effectiveScale = baseScale * zoom;
  const svgWidth = canvas.width * effectiveScale;
  const svgHeight = canvas.height * effectiveScale;

  // Toggle seat selection
  const toggleSeat = useCallback((seat) => {
    setSelectedSeats(prev => {
      const exists = prev.find(s => s.seat_uid === seat.seat_uid);
      if (exists) {
        return prev.filter(s => s.seat_uid !== seat.seat_uid);
      }
      return [...prev, seat];
    });
  }, []);

  // Selected seats summary
  const selectedTotal = useMemo(
    () => selectedSeats.reduce((sum, s) => sum + (s.price || 0), 0),
    [selectedSeats]
  );

  // Group selected seats by ticket type for display
  const selectedByType = useMemo(() => {
    const groups = {};
    selectedSeats.forEach(s => {
      const key = s.ticket_type_id || 'unknown';
      if (!groups[key]) {
        groups[key] = {
          ticket_type_id: s.ticket_type_id,
          name: s.ticket_type_name || 'Bilet',
          price: s.price,
          color: s.color,
          seats: [],
        };
      }
      groups[key].seats.push(s);
    });
    return Object.values(groups);
  }, [selectedSeats]);

  // Confirm selection
  const handleConfirm = () => {
    if (selectedSeats.length === 0) return;

    // Build cart items grouped by ticket_type_id
    const cartItems = selectedByType.map(group => ({
      id: group.ticket_type_id,
      name: group.name,
      price: group.price,
      color: group.color,
      quantity: group.seats.length,
    }));

    const seatUids = selectedSeats.map(s => s.seat_uid);

    onConfirm({
      cartItems,
      seatUids,
      selectedSeats: selectedSeats.map(s => ({
        seat_uid: s.seat_uid,
        section_name: s.section_name,
        row_label: s.row_label,
        seat_label: s.seat_label,
        ticket_type_id: s.ticket_type_id,
        price: s.price,
      })),
    });
  };

  // Zoom controls
  const zoomIn = () => setZoom(z => Math.min(z * 1.5, 5));
  const zoomOut = () => setZoom(z => Math.max(z / 1.5, 0.5));
  const zoomReset = () => setZoom(1);

  // Ticket type legend
  const ticketTypeLegend = mapData?.ticket_types || [];

  // ─── Loading State ──────────────────────────────────────────────────────

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

  // ─── Error State ──────────────────────────────────────────────────────

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
    <View style={styles.container}>
      {/* Header */}
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
            <View key={tt.id} style={styles.legendItem}>
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

      {/* Map */}
      <ScrollView
        ref={scrollViewRef}
        style={styles.mapScrollView}
        contentContainerStyle={[
          styles.mapContent,
          { width: Math.max(svgWidth, SCREEN_WIDTH - 32), minHeight: svgHeight + 20 },
        ]}
        horizontal={false}
        showsVerticalScrollIndicator={false}
        showsHorizontalScrollIndicator={false}
        nestedScrollEnabled
      >
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={{ paddingHorizontal: 16 }}
          nestedScrollEnabled
        >
          <Svg
            width={svgWidth}
            height={svgHeight}
            viewBox={`0 0 ${canvas.width} ${canvas.height}`}
          >
            {/* Background */}
            <Rect x={0} y={0} width={canvas.width} height={canvas.height} fill="#1a1a2e" rx={8} />

            {/* Section labels */}
            {(mapData?.sections || []).map((section, idx) => {
              const sx = section.x_position || section.x || 0;
              const sy = section.y_position || section.y || 0;
              return (
                <SvgText
                  key={`section-label-${idx}`}
                  x={sx + (section.width || 100) / 2}
                  y={sy - 8}
                  fill="rgba(255,255,255,0.3)"
                  fontSize={10}
                  fontWeight="600"
                  textAnchor="middle"
                >
                  {section.name || ''}
                </SvgText>
              );
            })}

            {/* Seats */}
            {processedSeats.map(seat => (
              <SeatCircle
                key={seat.seat_uid}
                seat={seat}
                isSelected={selectedSeats.some(s => s.seat_uid === seat.seat_uid)}
                onPress={() => toggleSeat(seat)}
                scale={effectiveScale}
              />
            ))}
          </Svg>
        </ScrollView>
      </ScrollView>

      {/* Zoom Controls */}
      <View style={styles.zoomControls}>
        <TouchableOpacity style={styles.zoomButton} onPress={zoomIn} activeOpacity={0.7}>
          <ZoomInIcon size={20} color={colors.textPrimary} />
        </TouchableOpacity>
        <TouchableOpacity style={styles.zoomButton} onPress={zoomReset} activeOpacity={0.7}>
          <Text style={styles.zoomResetText}>{Math.round(zoom * 100)}%</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.zoomButton} onPress={zoomOut} activeOpacity={0.7}>
          <ZoomOutIcon size={20} color={colors.textPrimary} />
        </TouchableOpacity>
      </View>

      {/* Bottom Bar - Selected Seats Summary */}
      {selectedSeats.length > 0 && (
        <View style={styles.bottomBar}>
          <View style={styles.bottomBarInfo}>
            <Text style={styles.bottomBarCount}>
              {selectedSeats.length} {selectedSeats.length === 1 ? 'loc' : 'locuri'}
            </Text>
            <Text style={styles.bottomBarTotal}>{formatCurrency(selectedTotal)}</Text>
            {/* Show selected seat labels */}
            <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.seatLabelsScroll}>
              {selectedSeats.map(s => (
                <View key={s.seat_uid} style={styles.seatLabelChip}>
                  <Text style={styles.seatLabelChipText}>
                    {s.row_label}{s.seat_label}
                  </Text>
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
    </View>
  );
}

// ─── Styles ──────────────────────────────────────────────────────────────────

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
    gap: 12,
  },
  backButton: {
    width: 40,
    height: 40,
    borderRadius: 12,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTitle: {
    flex: 1,
    fontSize: 18,
    fontWeight: '700',
    color: colors.textPrimary,
  },
  selectedBadge: {
    backgroundColor: colors.purple,
    borderRadius: 12,
    minWidth: 28,
    height: 28,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 8,
  },
  selectedBadgeText: {
    fontSize: 14,
    fontWeight: '700',
    color: colors.white,
  },

  // Loading / Error
  loadingContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 16,
  },
  loadingText: {
    fontSize: 15,
    color: colors.textSecondary,
  },
  errorText: {
    fontSize: 15,
    color: colors.red,
    textAlign: 'center',
    marginBottom: 16,
  },
  retryButton: {
    backgroundColor: colors.purple,
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 12,
  },
  retryButtonText: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.white,
  },

  // Legend
  legendContainer: {
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
    paddingVertical: 8,
  },
  legendScroll: {
    paddingHorizontal: 16,
    gap: 12,
  },
  legendItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    marginRight: 4,
  },
  legendDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  legendText: {
    fontSize: 11,
    color: colors.textSecondary,
  },
  legendPrice: {
    fontSize: 11,
    fontWeight: '600',
    color: colors.textTertiary,
  },

  // Map
  mapScrollView: {
    flex: 1,
  },
  mapContent: {
    alignItems: 'center',
    paddingVertical: 10,
  },

  // Zoom
  zoomControls: {
    position: 'absolute',
    right: 16,
    top: 160,
    backgroundColor: 'rgba(10,10,15,0.85)',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.border,
    overflow: 'hidden',
  },
  zoomButton: {
    width: 44,
    height: 44,
    alignItems: 'center',
    justifyContent: 'center',
  },
  zoomResetText: {
    fontSize: 11,
    fontWeight: '600',
    color: colors.textSecondary,
  },

  // Bottom Bar
  bottomBar: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderTopWidth: 1,
    borderTopColor: colors.border,
    backgroundColor: 'rgba(10,10,15,0.98)',
    gap: 12,
  },
  bottomBarInfo: {
    flex: 1,
  },
  bottomBarCount: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  bottomBarTotal: {
    fontSize: 18,
    fontWeight: '700',
    color: colors.purple,
    marginBottom: 4,
  },
  seatLabelsScroll: {
    flexGrow: 0,
    maxHeight: 28,
  },
  seatLabelChip: {
    backgroundColor: colors.purpleBg,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
    borderRadius: 6,
    paddingHorizontal: 6,
    paddingVertical: 2,
    marginRight: 4,
  },
  seatLabelChipText: {
    fontSize: 11,
    fontWeight: '600',
    color: colors.purple,
  },
  confirmButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.purple,
    paddingHorizontal: 20,
    paddingVertical: 14,
    borderRadius: 12,
    gap: 8,
  },
  confirmButtonText: {
    fontSize: 16,
    fontWeight: '700',
    color: colors.white,
  },
});
