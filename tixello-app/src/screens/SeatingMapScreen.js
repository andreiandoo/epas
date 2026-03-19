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

// ─── Helpers ──────────────────────────────────────────────────────────────────

function getDistance(touches) {
  const [t1, t2] = touches;
  const dx = t1.pageX - t2.pageX;
  const dy = t1.pageY - t2.pageY;
  return Math.sqrt(dx * dx + dy * dy);
}

function getMidpoint(touches) {
  const [t1, t2] = touches;
  return {
    x: (t1.pageX + t2.pageX) / 2,
    y: (t1.pageY + t2.pageY) / 2,
  };
}

// ─── Main Component ──────────────────────────────────────────────────────────

export default function SeatingMapScreen({ visible, eventId, ticketTypeId, onConfirm, onClose }) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [mapData, setMapData] = useState(null);
  const [selectedSeats, setSelectedSeats] = useState([]);
  const [mapSize, setMapSize] = useState({ width: SCREEN_WIDTH, height: 500 });
  // viewBox state: what portion of the canvas is visible
  const [viewBox, setViewBox] = useState({ x: 0, y: 0, w: 1000, h: 800 });

  // Gesture tracking refs
  const gestureRef = useRef({
    isPinching: false,
    isPanning: false,
    startDist: 0,
    startMid: { x: 0, y: 0 },
    startVB: { x: 0, y: 0, w: 0, h: 0 },
    lastTouchTime: 0,
    touchStartPos: { x: 0, y: 0 },
    // Map container position on screen
    containerOffset: { x: 0, y: 0 },
  });

  const containerRef = useRef(null);

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

  // Calculate initial viewBox to fit canvas in map area
  const fitViewBox = useCallback((size) => {
    const w = size?.width || mapSize.width;
    const h = size?.height || mapSize.height;
    const canvasAspect = canvas.width / canvas.height;
    const screenAspect = w / h;

    let vbW, vbH;
    if (canvasAspect > screenAspect) {
      // Canvas is wider — fit by width
      vbW = canvas.width;
      vbH = canvas.width / screenAspect;
    } else {
      // Canvas is taller — fit by height
      vbH = canvas.height;
      vbW = canvas.height * screenAspect;
    }
    const vbX = (canvas.width - vbW) / 2;
    const vbY = (canvas.height - vbH) / 2;
    return { x: vbX, y: vbY, w: vbW, h: vbH };
  }, [canvas.width, canvas.height, mapSize]);

  // Set initial viewBox when map loads or size changes
  useEffect(() => {
    if (mapData && mapSize.width > 0 && mapSize.height > 0) {
      setViewBox(fitViewBox());
    }
  }, [mapData, mapSize]);

  const onMapLayout = useCallback((e) => {
    const { width, height } = e.nativeEvent.layout;
    if (width > 0 && height > 0) {
      setMapSize({ width, height });
    }
    // Measure container position on screen
    e.target.measureInWindow((x, y) => {
      gestureRef.current.containerOffset = { x: x || 0, y: y || 0 };
    });
  }, []);

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
    return { seats: seatList, rowLabels: rowLabelList, sections: sections || [] };
  }, [mapData, ticketTypeId]);

  // Selected seats set
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

  // Convert screen tap position to canvas coordinates using current viewBox
  const screenToCanvas = useCallback((screenX, screenY) => {
    const offset = gestureRef.current.containerOffset;
    const localX = screenX - offset.x;
    const localY = screenY - offset.y;
    return {
      x: viewBox.x + (localX / mapSize.width) * viewBox.w,
      y: viewBox.y + (localY / mapSize.height) * viewBox.h,
    };
  }, [viewBox, mapSize]);

  // Find seat at canvas coordinates
  const findSeatAt = useCallback((canvasX, canvasY) => {
    const seats = processedData.seats;
    let bestSeat = null;
    let bestDist = Infinity;

    // Hit radius in canvas units — scale-aware
    const currentZoom = canvas.width / viewBox.w;

    for (let i = 0; i < seats.length; i++) {
      const s = seats[i];
      const isAvailable = s.status === 'available';
      const isSelected = selectedUids.has(s.seat_uid);
      const isClickable = (isAvailable && s.isAllowed) || isSelected;
      if (!isClickable) continue;

      const dx = canvasX - s.cx;
      const dy = canvasY - s.cy;
      const dist = Math.sqrt(dx * dx + dy * dy);
      // Hit radius adapts to zoom level
      const hitRadius = Math.max(s.seatRadius * 2.5, 20 / currentZoom);

      if (dist < hitRadius && dist < bestDist) {
        bestDist = dist;
        bestSeat = s;
      }
    }
    return bestSeat;
  }, [processedData.seats, selectedUids, viewBox.w, canvas.width]);

  // ─── PanResponder for gestures (replaces RNGH) ──────────────────────────────

  const panResponder = useRef(
    PanResponder.create({
      onStartShouldSetPanResponder: () => true,
      onMoveShouldSetPanResponder: () => true,
      onPanResponderGrant: (evt) => {
        const touches = evt.nativeEvent.touches;
        const g = gestureRef.current;

        g.lastTouchTime = Date.now();
        g.touchStartPos = { x: touches[0].pageX, y: touches[0].pageY };
        g.startVB = { ...viewBoxRef.current };

        if (touches.length === 2) {
          g.isPinching = true;
          g.startDist = getDistance(touches);
          g.startMid = getMidpoint(touches);
        } else {
          g.isPinching = false;
        }
        g.isPanning = false;
      },
      onPanResponderMove: (evt, gestureState) => {
        const touches = evt.nativeEvent.touches;
        const g = gestureRef.current;
        const vb = g.startVB;

        if (touches.length >= 2) {
          // ── Pinch zoom + pan ──
          g.isPinching = true;
          const newDist = getDistance(touches);
          const newMid = getMidpoint(touches);
          const scaleRatio = g.startDist / newDist; // > 1 = zoom out

          // New viewBox size
          const newW = Math.max(canvas.width * 0.1, Math.min(canvas.width * 3, vb.w * scaleRatio));
          const newH = Math.max(canvas.height * 0.1, Math.min(canvas.height * 3, vb.h * scaleRatio));

          // Canvas point under original midpoint
          const offset = gestureRef.current.containerOffset;
          const midLocalX = g.startMid.x - offset.x;
          const midLocalY = g.startMid.y - offset.y;
          const canvasMidX = vb.x + (midLocalX / mapSizeRef.current.width) * vb.w;
          const canvasMidY = vb.y + (midLocalY / mapSizeRef.current.height) * vb.h;

          // New midpoint position on screen
          const newMidLocalX = newMid.x - offset.x;
          const newMidLocalY = newMid.y - offset.y;

          // Adjust viewBox so canvas point stays under new midpoint
          const newX = canvasMidX - (newMidLocalX / mapSizeRef.current.width) * newW;
          const newY = canvasMidY - (newMidLocalY / mapSizeRef.current.height) * newH;

          const newVB = { x: newX, y: newY, w: newW, h: newH };
          viewBoxRef.current = newVB;
          setViewBox(newVB);
        } else if (touches.length === 1 && !g.isPinching) {
          // ── Single finger pan ──
          g.isPanning = true;
          const dx = gestureState.dx;
          const dy = gestureState.dy;

          // Convert screen pixels to canvas units
          const canvasDx = -(dx / mapSizeRef.current.width) * vb.w;
          const canvasDy = -(dy / mapSizeRef.current.height) * vb.h;

          const newVB = { x: vb.x + canvasDx, y: vb.y + canvasDy, w: vb.w, h: vb.h };
          viewBoxRef.current = newVB;
          setViewBox(newVB);
        }
      },
      onPanResponderRelease: (evt) => {
        const g = gestureRef.current;

        // Detect tap (short duration, small movement)
        const elapsed = Date.now() - g.lastTouchTime;
        const touch = evt.nativeEvent.changedTouches[0];
        const dx = touch.pageX - g.touchStartPos.x;
        const dy = touch.pageY - g.touchStartPos.y;
        const dist = Math.sqrt(dx * dx + dy * dy);

        if (elapsed < 300 && dist < 15 && !g.isPinching) {
          // It's a tap — find seat
          const offset = g.containerOffset;
          const localX = touch.pageX - offset.x;
          const localY = touch.pageY - offset.y;
          const vb = viewBoxRef.current;
          const canvasX = vb.x + (localX / mapSizeRef.current.width) * vb.w;
          const canvasY = vb.y + (localY / mapSizeRef.current.height) * vb.h;

          // Find nearest seat
          const seats = processedDataRef.current.seats;
          const uids = selectedUidsRef.current;
          const currentZoom = canvas.width / vb.w;
          let bestSeat = null;
          let bestDistSq = Infinity;

          for (let i = 0; i < seats.length; i++) {
            const s = seats[i];
            const isAvailable = s.status === 'available';
            const isSelected = uids.has(s.seat_uid);
            const isClickable = (isAvailable && s.isAllowed) || isSelected;
            if (!isClickable) continue;

            const sdx = canvasX - s.cx;
            const sdy = canvasY - s.cy;
            const distSq = sdx * sdx + sdy * sdy;
            const hitRadius = Math.max(s.seatRadius * 2.5, 20 / currentZoom);

            if (distSq < hitRadius * hitRadius && distSq < bestDistSq) {
              bestDistSq = distSq;
              bestSeat = s;
            }
          }

          if (bestSeat) {
            toggleSeatRef.current(bestSeat);
          }
        }

        g.isPinching = false;
        g.isPanning = false;
      },
    })
  ).current;

  // Refs to avoid stale closures in PanResponder
  const viewBoxRef = useRef(viewBox);
  useEffect(() => { viewBoxRef.current = viewBox; }, [viewBox]);
  const mapSizeRef = useRef(mapSize);
  useEffect(() => { mapSizeRef.current = mapSize; }, [mapSize]);
  const processedDataRef = useRef(processedData);
  useEffect(() => { processedDataRef.current = processedData; }, [processedData]);
  const selectedUidsRef = useRef(selectedUids);
  useEffect(() => { selectedUidsRef.current = selectedUids; }, [selectedUids]);
  const toggleSeatRef = useRef(toggleSeat);
  useEffect(() => { toggleSeatRef.current = toggleSeat; }, [toggleSeat]);

  // ─── Zoom controls ──────────────────────────────────────────────────────────

  const resetView = useCallback(() => {
    const vb = fitViewBox();
    viewBoxRef.current = vb;
    setViewBox(vb);
  }, [fitViewBox]);

  const zoomIn = useCallback(() => {
    setViewBox(prev => {
      const newW = prev.w / 1.5;
      const newH = prev.h / 1.5;
      const newX = prev.x + (prev.w - newW) / 2;
      const newY = prev.y + (prev.h - newH) / 2;
      const vb = { x: newX, y: newY, w: newW, h: newH };
      viewBoxRef.current = vb;
      return vb;
    });
  }, []);

  const zoomOut = useCallback(() => {
    setViewBox(prev => {
      const newW = Math.min(prev.w * 1.5, canvas.width * 3);
      const newH = Math.min(prev.h * 1.5, canvas.height * 3);
      const newX = prev.x + (prev.w - newW) / 2;
      const newY = prev.y + (prev.h - newH) / 2;
      const vb = { x: newX, y: newY, w: newW, h: newH };
      viewBoxRef.current = vb;
      return vb;
    });
  }, [canvas.width, canvas.height]);

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
          <View style={styles.legendScroll}>
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
          </View>
        </View>

        {/* Map with PanResponder gestures */}
        <View
          ref={containerRef}
          style={styles.mapContainer}
          onLayout={onMapLayout}
          {...panResponder.panHandlers}
        >
          <Svg
            width="100%"
            height="100%"
            viewBox={`${viewBox.x} ${viewBox.y} ${viewBox.w} ${viewBox.h}`}
            preserveAspectRatio="xMidYMid meet"
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

            {/* Seats */}
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
                  <Circle
                    cx={seat.cx} cy={seat.cy} r={drawRadius}
                    fill={fillColor}
                    stroke={strokeColor}
                    strokeWidth={strokeWidth}
                    opacity={opacity}
                  />
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

          {/* Zoom controls */}
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
    <Modal
      visible={visible}
      animationType="slide"
      statusBarTranslucent
      onRequestClose={onClose}
    >
      <View style={styles.container}>
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
