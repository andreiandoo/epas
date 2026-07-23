import React, { useState, useMemo, useRef, useEffect } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  TouchableWithoutFeedback,
  StyleSheet,
  ScrollView,
  TextInput,
  ActivityIndicator,
  Animated,
  Modal,
  Keyboard,
  Alert,
  Linking,
} from 'react-native';
import Svg, { Path, Circle, Rect, Defs, LinearGradient, Stop } from 'react-native-svg';
import QRCode from 'react-native-qrcode-svg';
import { colors } from '../theme/colors';
import { useEvent } from '../context/EventContext';
import { useAuth } from '../context/AuthContext';
import { useApp } from '../context/AppContext';
import { apiPost, apiGet, publicApiGet } from '../api/client';
import { formatCurrency } from '../utils/formatCurrency';
import TicketListScreen from './TicketListScreen';
import SeatingMapScreen from './SeatingMapScreen';

// ─── SVG Icon Components ──────────────────────────────────────────────────────

function CartIcon({ size = 24, color = colors.white }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4zM3 6h18M16 10a4 4 0 01-8 0"
        stroke={color}
        strokeWidth={1.8}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </Svg>
  );
}

function PlusIcon({ size = 20, color = colors.white }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M12 5v14M5 12h14"
        stroke={color}
        strokeWidth={2}
        strokeLinecap="round"
      />
    </Svg>
  );
}

function MinusIcon({ size = 20, color = colors.white }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M5 12h14"
        stroke={color}
        strokeWidth={2}
        strokeLinecap="round"
      />
    </Svg>
  );
}

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

function CheckIcon({ size = 48, color = colors.green }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M22 11.08V12a10 10 0 11-5.93-9.14"
        stroke={color}
        strokeWidth={2}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <Path
        d="M22 4L12 14.01l-3-3"
        stroke={color}
        strokeWidth={2}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </Svg>
  );
}

function CreditCardIcon({ size = 22, color = colors.white }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Rect
        x={1} y={4} width={22} height={16} rx={2}
        stroke={color}
        strokeWidth={1.8}
      />
      <Path
        d="M1 10h22"
        stroke={color}
        strokeWidth={1.8}
      />
    </Svg>
  );
}

function CashIcon({ size = 22, color = colors.white }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Rect
        x={2} y={6} width={20} height={12} rx={2}
        stroke={color}
        strokeWidth={1.8}
      />
      <Circle
        cx={12} cy={12} r={3}
        stroke={color}
        strokeWidth={1.8}
      />
      <Path
        d="M2 9h2M20 9h2M2 15h2M20 15h2"
        stroke={color}
        strokeWidth={1.5}
        strokeLinecap="round"
      />
    </Svg>
  );
}

function ContactlessIcon({ size = 18, color = colors.white }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M8.5 16.5a5 5 0 010-9M5 19a9 9 0 010-14M12 16.5a5 5 0 000-9M15.5 19a9 9 0 000-14"
        stroke={color}
        strokeWidth={1.5}
        strokeLinecap="round"
      />
    </Svg>
  );
}

function MailIcon({ size = 24, color = colors.white }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"
        stroke={color}
        strokeWidth={1.8}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <Path
        d="M22 6l-10 7L2 6"
        stroke={color}
        strokeWidth={1.8}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </Svg>
  );
}

function GiftIcon({ size = 22, color = colors.white }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M20 12v10H4V12M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"
        stroke={color}
        strokeWidth={1.8}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </Svg>
  );
}

function ChartIcon({ size = 24, color = colors.purple }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M18 20V10M12 20V4M6 20v-6"
        stroke={color}
        strokeWidth={2}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </Svg>
  );
}

function ListIcon({ size = 18, color = colors.purple }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"
        stroke={color}
        strokeWidth={2}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </Svg>
  );
}

function QrIcon({ size = 22, color = colors.white }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Rect x="3" y="3" width="7" height="7" rx="1" stroke={color} strokeWidth={1.8} />
      <Rect x="14" y="3" width="7" height="7" rx="1" stroke={color} strokeWidth={1.8} />
      <Rect x="3" y="14" width="7" height="7" rx="1" stroke={color} strokeWidth={1.8} />
      <Rect x="14" y="14" width="3" height="3" stroke={color} strokeWidth={1.8} />
      <Rect x="18" y="18" width="3" height="3" stroke={color} strokeWidth={1.8} />
      <Rect x="14" y="18" width="3" height="3" stroke={color} strokeWidth={1.8} />
      <Rect x="18" y="14" width="3" height="3" stroke={color} strokeWidth={1.8} />
    </Svg>
  );
}

// ─── Reports Only Placeholder ─────────────────────────────────────────────────

function ReportsOnlyPlaceholder({ onViewReports }) {
  return (
    <View style={styles.placeholderContainer}>
      <View style={styles.placeholderIconWrap}>
        <CartIcon size={48} color={colors.textTertiary} />
      </View>
      <Text style={styles.placeholderTitle}>Eveniment Trecut</Text>
      <Text style={styles.placeholderDescription}>
        Vânzarea biletelor nu este disponibilă pentru evenimentele trecute
      </Text>
      <TouchableOpacity
        style={styles.placeholderButton}
        onPress={onViewReports}
        activeOpacity={0.7}
      >
        <ChartIcon size={20} color={colors.white} />
        <Text style={styles.placeholderButtonText}>Vezi Rapoarte</Text>
      </TouchableOpacity>
    </View>
  );
}

// ─── Ticket Type Card ─────────────────────────────────────────────────────────

function TicketTypeCard({ ticket, onAdd }) {
  const isSoldOut = ticket.available <= 0;
  const isSeated = ticket.has_seats === true;
  // Test POS ticket, auto-provisioned per event server-side. We surface
  // it in the same list as the regular POS ticket types with a visible
  // TEST badge — the sell path routes it to source='pos_test' and skips
  // receipt printing.
  const isTestTicket = ticket?.meta?.is_test === true;

  return (
    <TouchableOpacity
      style={[
        styles.ticketCard,
        isSoldOut && styles.ticketCardDisabled,
        isTestTicket && { borderWidth: 1, borderColor: '#8B5CF6' },
      ]}
      onPress={() => !isSoldOut && onAdd(ticket)}
      activeOpacity={isSoldOut ? 1 : 0.7}
    >
      <View style={[styles.ticketColorBar, { backgroundColor: isTestTicket ? '#8B5CF6' : ticket.color }]} />
      <View style={styles.ticketCardContent}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
          <Text
            style={[styles.ticketName, isSoldOut && styles.ticketNameDisabled]}
            numberOfLines={1}
          >
            {ticket.name}
          </Text>
          {isTestTicket && (
            <View style={{ backgroundColor: '#8B5CF6', paddingHorizontal: 6, paddingVertical: 2, borderRadius: 4 }}>
              <Text style={{ color: '#fff', fontSize: 10, fontWeight: '700', letterSpacing: 0.5 }}>TEST</Text>
            </View>
          )}
        </View>
        <Text style={styles.ticketPrice}>{formatCurrency(ticket.price)}</Text>
        {isSoldOut ? (
          <Text style={styles.soldOutText}>Epuizat</Text>
        ) : isSeated ? (
          <Text style={styles.ticketAvailable}>
            {ticket.available} {ticket.available === 1 ? 'loc disponibil' : 'locuri disponibile'} · alege pe hartă
          </Text>
        ) : (
          <Text style={styles.ticketAvailable}>
            {ticket.available} disponibile
            {isTestTicket ? ' · nu se contorizează' : ''}
          </Text>
        )}
      </View>
      {!isSoldOut && (
        <TouchableOpacity
          style={styles.addButton}
          onPress={() => onAdd(ticket)}
          activeOpacity={0.7}
        >
          {isSeated ? (
            <Svg width={18} height={18} viewBox="0 0 24 24" fill="none">
              <Path d="M9 18l6-6-6-6" stroke={colors.white} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
            </Svg>
          ) : (
            <PlusIcon size={18} color={colors.white} />
          )}
        </TouchableOpacity>
      )}
    </TouchableOpacity>
  );
}

// ─── Recent Sale Item ─────────────────────────────────────────────────────────

function RecentSaleItem({ sale, onShowQR }) {
  const hasQR = sale.method === 'cash' && !!sale.claimUrl;
  const Wrapper = hasQR ? TouchableOpacity : View;
  return (
    <Wrapper style={styles.saleItem} onPress={hasQR ? () => onShowQR(sale) : undefined} activeOpacity={0.6}>
      <View style={styles.saleIconWrap}>
        {sale.method === 'card' ? (
          <CreditCardIcon size={16} color={colors.purple} />
        ) : (
          <CashIcon size={16} color={colors.green} />
        )}
      </View>
      <View style={styles.saleInfo}>
        <Text style={styles.saleDescription} numberOfLines={1}>
          {sale.description || `${sale.qty || 1}x ${sale.type || 'Ticket'}`}
        </Text>
        <Text style={styles.saleTime}>{sale.time || 'Chiar acum'}</Text>
      </View>
      {hasQR && (
        <View style={{ marginRight: 8 }}>
          <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
            <Rect x="3" y="3" width="7" height="7" stroke={colors.textMuted} strokeWidth={1.5} />
            <Rect x="14" y="3" width="7" height="7" stroke={colors.textMuted} strokeWidth={1.5} />
            <Rect x="3" y="14" width="7" height="7" stroke={colors.textMuted} strokeWidth={1.5} />
            <Rect x="14" y="14" width="3" height="3" fill={colors.textMuted} />
            <Rect x="18" y="18" width="3" height="3" fill={colors.textMuted} />
          </Svg>
        </View>
      )}
      <Text style={styles.saleAmount}>{formatCurrency(sale.total)}</Text>
    </Wrapper>
  );
}

// ─── Cart Item Row ────────────────────────────────────────────────────────────

function CartItemRow({ item, onUpdateQuantity, hideControls, seats }) {
  // When the order has seats, list each seat's section / row / number
  // under the ticket type so the operator knows exactly what's in the
  // cart. `seats` is the subset of selectedSeatDetails for this row's
  // ticket_type_id.
  const seatsForRow = Array.isArray(seats)
    ? seats.filter(s => String(s.ticket_type_id) === String(item.id))
    : [];
  return (
    <View style={styles.cartItem}>
      <View style={[styles.cartItemColorBar, { backgroundColor: item.color }]} />
      <View style={styles.cartItemInfo}>
        <Text style={styles.cartItemName} numberOfLines={1}>{item.name}</Text>
        <Text style={styles.cartItemPrice}>
          {hideControls ? `${item.quantity} ${item.quantity === 1 ? 'loc' : 'locuri'}` : `${formatCurrency(item.price)} fiecare`}
        </Text>
        {seatsForRow.length > 0 && (
          <View style={styles.cartSeatList}>
            {seatsForRow.map((s, idx) => (
              <Text key={s.seat_uid || idx} style={styles.cartSeatText} numberOfLines={1}>
                {[s.section_name, s.row_label ? `Rând ${s.row_label}` : null, s.seat_label ? `Loc ${s.seat_label}` : null]
                  .filter(Boolean)
                  .join(' · ')}
              </Text>
            ))}
          </View>
        )}
      </View>
      {!hideControls && (
        <View style={styles.quantityControls}>
          <TouchableOpacity
            style={styles.quantityButton}
            onPress={() => onUpdateQuantity(item.id, item.quantity - 1)}
            activeOpacity={0.7}
          >
            <MinusIcon size={16} color={colors.textSecondary} />
          </TouchableOpacity>
          <Text style={styles.quantityText}>{item.quantity}</Text>
          <TouchableOpacity
            style={styles.quantityButton}
            onPress={() => onUpdateQuantity(item.id, item.quantity + 1)}
            activeOpacity={0.7}
          >
            <PlusIcon size={16} color={colors.white} />
          </TouchableOpacity>
        </View>
      )}
      <Text style={styles.cartItemTotal}>
        {formatCurrency(item.price * item.quantity)}
      </Text>
    </View>
  );
}

// ─── Main SalesScreen Component ───────────────────────────────────────────────

export default function SalesScreen({ navigation }) {
  const { ticketTypes, allTicketTypes, isReportsOnlyMode, selectedEvent, refreshStats, refreshTicketTypes, eventCommission } = useEvent();
  const { user } = useAuth();
  const { recentSales, addSale, addScan, loadSaleHistory, autoConfirmValid } = useApp();

  // State
  const [showTicketList, setShowTicketList] = useState(false);
  const [activeView, setActiveView] = useState('tickets'); // 'tickets' | 'cart'
  const [cartItems, setCartItems] = useState([]);
  const [paymentMethod, setPaymentMethod] = useState(null); // 'tap' | 'card' | 'cash'
  const [isProcessing, setIsProcessing] = useState(false);
  const [showPaymentSuccess, setShowPaymentSuccess] = useState(false);
  const [showEmailCapture, setShowEmailCapture] = useState(false);
  // Pending payment method — kept for the styled Card modal (see below).
  // Cash uses a native Alert instead so it works regardless of which
  // ScrollView tree is currently mounted, avoiding a subtle bug where the
  // cart-view modal wasn't firing on some builds after the seating flow.
  const [pendingPayment, setPendingPayment] = useState(null); // 'cash' | 'card' | null

  // Native confirm — swap to Alert.alert (works across all platforms and
  // isn't affected by React tree mount ordering). Called on Cash tap AND
  // Card tap; both go through the same path now. Anulează = wipe cart.
  const confirmPayment = (method) => {
    if (isProcessing) return;
    const cancelAndClearCart = () => {
      setPendingPayment(null);
      setCartItems([]);
      setPaymentMethod(null);
      setSelectedSeatUids([]);
      setSelectedSeatDetails([]);
      setSeatingMapTicketTypeId?.(null);
    };
    const body = method === 'cash'
      ? `Ai primit banii în numerar (${formatCurrency(total)})? Confirmă doar dacă suma e în mână.`
      : `Suma de ${formatCurrency(total)} a fost încasată pe terminalul POS? Confirmă doar după ce tranzacția e aprobată.`;
    Alert.alert(
      'Confirmă încasarea banilor',
      body,
      [
        { text: 'Anulează', style: 'cancel', onPress: cancelAndClearCart },
        { text: 'Confirmă', onPress: () => processPayment(method) },
      ],
      { cancelable: true, onDismiss: () => {} }
    );
  };
  const [buyerEmail, setBuyerEmail] = useState('');
  const [sendingEmail, setSendingEmail] = useState(false);
  const [lastPaymentAmount, setLastPaymentAmount] = useState(0);
  const [lastOrderData, setLastOrderData] = useState(null);
  const [claimUrl, setClaimUrl] = useState(null);
  const [claimToken, setClaimToken] = useState(null);
  const [showSeatingMap, setShowSeatingMap] = useState(false);
  const [seatingMapTicketTypeId, setSeatingMapTicketTypeId] = useState(null);
  const [selectedSeatUids, setSelectedSeatUids] = useState([]);
  // Full seat metadata (section / row / seat label / price / ticket type).
  // Used by the cart row renderer to show "Secțiunea X · Rând Y · Loc Z"
  // under each ticket type group.
  const [selectedSeatDetails, setSelectedSeatDetails] = useState([]);
  const [qrReplaySale, setQrReplaySale] = useState(null); // sale object for QR re-display

  // 'Card prin NFC' (legacy Stripe Tap) — only surface when the organizer
  // has enabled it from Filament (service_settings.mobile_card_nfc_enabled).
  const cardNfcEnabled = !!user?.mobile_settings?.card_nfc_enabled;

  // Check if current event has seating
  const hasSeating = selectedEvent?.has_seating === true;

  // Load persisted sale history when event changes
  useEffect(() => {
    if (selectedEvent?.id) {
      loadSaleHistory(selectedEvent.id);
    }
  }, [selectedEvent?.id]);

  // Auto-close QR overlay: 30s timeout + poll claim status every 5s
  useEffect(() => {
    if (!showPaymentSuccess || !claimToken) return;

    // Auto-close after 30s
    const timeout = setTimeout(() => {
      finishPayment(true);
    }, 30000);

    // Poll claim status every 5s
    const interval = setInterval(async () => {
      try {
        const res = await fetch(`https://core.tixello.com/claim/${claimToken}/status`);
        const data = await res.json();
        if (data.success && data.data?.has_email) {
          // Customer completed step 1 — refresh stats and close
          refreshStats();
          refreshTicketTypes();
          clearTimeout(timeout);
          finishPayment(false);
        }
      } catch (e) {
        // Ignore polling errors
      }
    }, 5000);

    return () => {
      clearTimeout(timeout);
      clearInterval(interval);
    };
  }, [showPaymentSuccess, claimToken]);

  // FAB animation
  const fabScale = useRef(new Animated.Value(0)).current;

  // Computed values
  const cartTotal = useMemo(
    () => cartItems.reduce((sum, item) => sum + item.price * item.quantity, 0),
    [cartItems]
  );

  const cartCount = useMemo(
    () => cartItems.reduce((sum, item) => sum + item.quantity, 0),
    [cartItems]
  );

  const salesTodayTotal = useMemo(
    () => recentSales.reduce((sum, sale) => sum + (sale.total || 0), 0),
    [recentSales]
  );

  const subtotal = cartTotal;
  const commissionRate = eventCommission?.rate || 0;
  const commissionMode = eventCommission?.mode || 'included';
  const commissionAmount = commissionMode === 'added_on_top' ? Math.round(subtotal * commissionRate) / 100 : 0;
  const total = subtotal + commissionAmount;

  // Animate FAB in/out
  useEffect(() => {
    Animated.spring(fabScale, {
      toValue: cartCount > 0 && activeView !== 'cart' ? 1 : 0,
      friction: 6,
      tension: 100,
      useNativeDriver: true,
    }).start();
  }, [cartCount, activeView]);

  // Cart actions
  const addToCart = (ticket) => {
    // For seated ticket types, open the seating map filtered by this ticket type
    // For non-seated ticket types (even in events with seating), add directly to cart
    if (hasSeating && ticket.has_seats) {
      setSeatingMapTicketTypeId(ticket.id);
      setShowSeatingMap(true);
      return;
    }
    const isTestTicket = ticket?.meta?.is_test === true;
    // Refuse to mix test + real POS tickets in the same cart — the
    // downstream sale routes them through separate order sources
    // (pos_app vs pos_test) and any mixed cart would land on one side
    // or the other in a confusing way for the operator + reports.
    if (cartItems.length > 0) {
      const cartHasTest = cartItems.some(it => it?.meta?.is_test === true);
      if (cartHasTest !== isTestTicket) {
        Alert.alert(
          'Nu poți amesteca',
          'Biletele TEST se vând separat de restul. Golește coșul dacă vrei să adaugi acest bilet.',
        );
        return;
      }
    }
    setCartItems((prev) => {
      const existing = prev.find((item) => item.id === ticket.id);
      if (existing) {
        return prev.map((item) =>
          item.id === ticket.id
            ? { ...item, quantity: item.quantity + 1 }
            : item
        );
      }
      return [
        ...prev,
        {
          id: ticket.id,
          name: ticket.name,
          price: ticket.price,
          color: ticket.color,
          quantity: 1,
          // Preserve the meta so processPayment can flip source to
          // 'pos_test'. Ticket types without meta.is_test just get the
          // ticket.meta pass-through as-is (undefined ≠ true).
          meta: ticket.meta,
        },
      ];
    });
  };

  const updateQuantity = (ticketId, newQuantity) => {
    if (newQuantity <= 0) {
      setCartItems((prev) => prev.filter((item) => item.id !== ticketId));
    } else {
      setCartItems((prev) =>
        prev.map((item) =>
          item.id === ticketId ? { ...item, quantity: newQuantity } : item
        )
      );
    }
  };

  const processPayment = async (method) => {
    setPaymentMethod(method);
    setIsProcessing(true);

    try {
      // Detect a test-only cart. Test POS ticket type carries
      // meta.is_test = true and the backend routes source = 'pos_test'
      // to the exclusion path so it never touches revenue / decont /
      // admin_new_order email. We refuse to mix test + real tickets in
      // the same cart at addToCart time; here we just derive the
      // source from what's actually in the cart.
      const isTestCart = cartItems.length > 0 && cartItems.every(it => it?.meta?.is_test === true);

      // Create order via API
      const orderPayload = {
        event_id: selectedEvent?.id,
        tickets: cartItems.map(item => ({
          ticket_type_id: item.id,
          quantity: item.quantity,
        })),
        customer: {
          email: isTestCart ? 'test-pos@ambilet.ro' : 'pos@ambilet.ro',
          first_name: isTestCart ? 'TEST' : 'POS',
          last_name: method === 'cash' ? 'Numerar' : 'Card',
        },
        payment_method: method,
        source: isTestCart ? 'pos_test' : 'pos_app',
        sold_by: user?.name || 'POS',
        ...(selectedSeatUids.length > 0 && { seat_uids: selectedSeatUids }),
      };

      const response = await apiPost('/orders', orderPayload);

      if (response.success) {
        const orderData = response.data?.order || null;

        // Stripe Tap (NFC) needs payment_url; card / cash do not (those
        // are auto-confirmed server-side).
        if (method === 'tap' && !orderData?.payment_url) {
          alert('Plata prin Stripe nu este disponibilă momentan. Folosiți plata cu numerar.');
          setIsProcessing(false);
          setPaymentMethod(null);
          return;
        }

        setLastPaymentAmount(cartTotal);
        setShowPaymentSuccess(true);
        setLastOrderData(orderData);

        const saleDescription = cartItems
          .map((item) => `${item.quantity}x ${item.name}`)
          .join(', ');

        const saleRecord = {
          id: Date.now(),
          method: method,
          total: cartTotal,
          description: saleDescription,
          qty: cartCount,
          type: cartItems.length === 1 ? cartItems[0].name : 'Mixt',
          time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
          eventId: selectedEvent?.id,
        };

        // Cash + Card (POS terminal confirm) both qualify for the QR
        // claim flow — the customer scans to receive tickets by email.
        const isOfflinePosSale = method === 'cash' || method === 'card';
        if (isOfflinePosSale && orderData?.id) {
          try {
            const claimResponse = await apiPost(`/orders/${orderData.id}/generate-claim-url`);
            if (claimResponse.success && claimResponse.data?.claim_url) {
              setClaimUrl(claimResponse.data.claim_url);
              setClaimToken(claimResponse.data.token);
              saleRecord.claimUrl = claimResponse.data.claim_url;
            }
          } catch (e) {
            console.warn('Failed to generate claim URL:', e);
          }
        }

        addSale(saleRecord);

        // Auto check-in records — only when autoConfirmValid is on and
        // the sale was a cash / card (offline) POS one. Stripe tap
        // tickets are confirmed by the payment webhook, not here.
        if (isOfflinePosSale && autoConfirmValid) {
          const methodLabel = method === 'cash' ? 'Numerar' : 'Card';
          cartItems.forEach((item) => {
            for (let i = 0; i < item.quantity; i++) {
              addScan({
                id: Date.now() + Math.random(),
                type: 'valid',
                name: `POS ${methodLabel}`,
                ticketType: item.name,
                time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                code: `pos-${method}-${orderData?.id || Date.now()}-${i}`,
                eventId: selectedEvent?.id,
                source: `pos_${method}`,
              });
            }
          });
        }

        refreshStats();
        refreshTicketTypes();
      } else {
        throw new Error(response.message || 'Eroare la crearea comenzii');
      }
    } catch (error) {
      alert(error.message || 'Eroare la procesarea plății');
    } finally {
      setIsProcessing(false);
    }
  };

  const finishPayment = async (skipEmail = false) => {
    // Auto check-in tickets only when autoConfirmValid is enabled. Both
    // cash and card (POS-terminal-confirmed) qualify — same flow.
    const isOfflinePos = paymentMethod === 'cash' || paymentMethod === 'card';
    if (skipEmail && lastOrderData?.id && isOfflinePos && autoConfirmValid) {
      try {
        await apiPost(`/orders/${lastOrderData.id}/pos-complete`, {
          auto_checkin: true,
          checked_in_by: user?.name || 'POS',
        });
      } catch (e) {
        console.error('Failed to auto check-in:', e);
      }
    }
    setShowPaymentSuccess(false);
    setShowEmailCapture(false);
    setClaimUrl(null);
    setClaimToken(null);
    setCartItems([]);
    setPaymentMethod(null);
    setBuyerEmail('');
    setLastOrderData(null);
    setSelectedSeatUids([]);
    setSelectedSeatDetails([]);
    setSeatingMapTicketTypeId(null);
    setActiveView('tickets');
  };

  const sendTicketsEmail = async () => {
    if (!buyerEmail.trim() || !lastOrderData) return;
    Keyboard.dismiss();
    setSendingEmail(true);
    try {
      await apiPost(`/orders/${lastOrderData.id}/send-tickets`, {
        email: buyerEmail.trim(),
      });
      setSendingEmail(false);
      finishPayment(false);
    } catch (e) {
      console.error('Failed to send ticket email:', e);
      setSendingEmail(false);
      Alert.alert('Eroare', e.message || 'Nu s-au putut trimite biletele. Încercați din nou.');
    }
  };

  // ─── Seating Map View ────────────────────────────────────────────────────

  const handleSeatingConfirm = (seatingResult) => {
    // seatingResult: { cartItems, seatUids, selectedSeats }
    // Merge with existing cart items (user may select from multiple ticket types)
    setCartItems(prev => {
      const merged = [...prev];
      seatingResult.cartItems.forEach(newItem => {
        const existing = merged.find(m => m.id === newItem.id);
        if (existing) {
          existing.quantity += newItem.quantity;
        } else {
          merged.push(newItem);
        }
      });
      return merged;
    });
    setSelectedSeatUids(prev => [...prev, ...seatingResult.seatUids]);
    setSelectedSeatDetails(prev => [...prev, ...(seatingResult.selectedSeats || [])]);
    setShowSeatingMap(false);
    setSeatingMapTicketTypeId(null);
    setActiveView('cart');
  };

  // ─── Ticket List View (inline, keeps tab bar visible) ──────────────────────

  if (showTicketList) {
    return (
      <View style={styles.container}>
        <TicketListScreen onClose={() => setShowTicketList(false)} />
      </View>
    );
  }

  // ─── Reports Only Mode ────────────────────────────────────────────────────

  if (isReportsOnlyMode) {
    return (
      <View style={styles.container}>
        <View style={{ paddingHorizontal: 16, paddingTop: 20 }}>
          <TouchableOpacity
            style={styles.ticketListBar}
            onPress={() => setShowTicketList(true)}
            activeOpacity={0.7}
          >
            <ListIcon size={18} color={colors.purple} />
            <Text style={styles.ticketListBarText}>Bilete eveniment</Text>
            <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
              <Path d="M9 18l6-6-6-6" stroke={colors.textTertiary} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
            </Svg>
          </TouchableOpacity>
        </View>
        <ReportsOnlyPlaceholder
          onViewReports={() => navigation?.navigate?.('Reports')}
        />
      </View>
    );
  }

  // ─── Cart View ────────────────────────────────────────────────────────────

  if (activeView === 'cart') {
    return (
      <View style={styles.container}>
        {/* Cart Header */}
        <View style={styles.cartHeader}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => {
              if (selectedSeatUids.length > 0) {
                // Go back to seating map for seated events
                setCartItems([]);
                setSelectedSeatUids([]);
                setSelectedSeatDetails([]);
                setShowSeatingMap(true);
              }
              setActiveView('tickets');
            }}
            activeOpacity={0.7}
          >
            <ArrowLeftIcon size={22} color={colors.white} />
          </TouchableOpacity>
          <Text style={styles.cartTitle}>
            {selectedSeatUids.length > 0 ? 'Locuri Selectate' : 'Coș'}
          </Text>
          <View style={styles.cartCountBadge}>
            <Text style={styles.cartCountBadgeText}>{cartCount}</Text>
          </View>
        </View>

        <ScrollView
          style={styles.scrollView}
          contentContainerStyle={styles.cartScrollContent}
          showsVerticalScrollIndicator={false}
        >
          {/* Cart Items */}
          <View style={styles.section}>
            {cartItems.map((item) => (
              <CartItemRow
                key={item.id}
                item={item}
                onUpdateQuantity={selectedSeatUids.length > 0 ? () => {} : updateQuantity}
                hideControls={selectedSeatUids.length > 0}
                seats={selectedSeatDetails}
              />
            ))}
          </View>

          {/* Cart Summary */}
          <View style={styles.summarySection}>
            <View style={styles.summaryRow}>
              <Text style={styles.summaryLabel}>Subtotal</Text>
              <Text style={styles.summaryValue}>{formatCurrency(subtotal)}</Text>
            </View>
            {commissionRate > 0 && commissionMode === 'added_on_top' && (
              <>
                <View style={styles.summaryRow}>
                  <Text style={styles.summaryLabel}>Comision {commissionRate}%</Text>
                  <Text style={styles.summaryValue}>{formatCurrency(commissionAmount)}</Text>
                </View>
              </>
            )}
            {commissionRate > 0 && commissionMode === 'included' && (
              <Text style={[styles.summaryLabel, { fontSize: 11, marginTop: 4 }]}>
                Include comision {commissionRate}%
              </Text>
            )}
            <View style={styles.summaryDivider} />
            <View style={styles.summaryRow}>
              <Text style={styles.summaryTotalLabel}>Total</Text>
              <Text style={styles.summaryTotalValue}>
                {formatCurrency(total)}
              </Text>
            </View>
          </View>

          {/* Payment Methods */}
          <View style={styles.paymentSection}>
            <Text style={styles.paymentSectionTitle}>
              Metodă de Plată
            </Text>

            {/* Card (POS card terminal — operator confirms the money was
                collected via the intermediate modal). Same backend flow as
                cash: order auto-confirmed, tickets valid, QR claim. */}
            <TouchableOpacity
              style={[
                styles.paymentButton,
                paymentMethod === 'card' && styles.paymentButtonActive,
              ]}
              onPress={() => confirmPayment('card')}
              activeOpacity={0.7}
              disabled={isProcessing}
            >
              {isProcessing && paymentMethod === 'card' ? (
                <ActivityIndicator size="small" color={colors.purple} />
              ) : (
                <View style={styles.paymentButtonContent}>
                  <CreditCardIcon
                    size={22}
                    color={paymentMethod === 'card' ? colors.purple : colors.textSecondary}
                  />
                  <View style={styles.paymentButtonText}>
                    <Text
                      style={[
                        styles.paymentMethodName,
                        paymentMethod === 'card' && styles.paymentMethodNameActive,
                      ]}
                    >
                      Card
                    </Text>
                    <Text style={styles.paymentPoweredBy}>Terminal POS card</Text>
                  </View>
                </View>
              )}
            </TouchableOpacity>

            {/* Cash */}
            <TouchableOpacity
              style={[
                styles.paymentButton,
                paymentMethod === 'cash' && styles.paymentButtonActive,
              ]}
              onPress={() => confirmPayment('cash')}
              activeOpacity={0.7}
              disabled={isProcessing}
            >
              {isProcessing && paymentMethod === 'cash' ? (
                <ActivityIndicator size="small" color={colors.purple} />
              ) : (
                <View style={styles.paymentButtonContent}>
                  <CashIcon
                    size={22}
                    color={paymentMethod === 'cash' ? colors.purple : colors.textSecondary}
                  />
                  <View style={styles.paymentButtonText}>
                    <Text
                      style={[
                        styles.paymentMethodName,
                        paymentMethod === 'cash' && styles.paymentMethodNameActive,
                      ]}
                    >
                      Numerar
                    </Text>
                  </View>
                </View>
              )}
            </TouchableOpacity>

            {/* Card prin NFC — legacy Stripe Tap flow. Hidden by default
                and only surfaces when the organizer toggles it on from
                Filament (service_settings.mobile_card_nfc_enabled). */}
            {cardNfcEnabled && (
              <TouchableOpacity
                style={[
                  styles.paymentButton,
                  paymentMethod === 'tap' && styles.paymentButtonActive,
                ]}
                onPress={() => !isProcessing && processPayment('tap')}
                activeOpacity={0.7}
                disabled={isProcessing}
              >
                {isProcessing && paymentMethod === 'tap' ? (
                  <ActivityIndicator size="small" color={colors.purple} />
                ) : (
                  <View style={styles.paymentButtonContent}>
                    <View style={styles.paymentButtonLeft}>
                      <CreditCardIcon
                        size={22}
                        color={paymentMethod === 'tap' ? colors.purple : colors.textSecondary}
                      />
                      <ContactlessIcon
                        size={16}
                        color={paymentMethod === 'tap' ? colors.purple : colors.textTertiary}
                      />
                    </View>
                    <View style={styles.paymentButtonText}>
                      <Text
                        style={[
                          styles.paymentMethodName,
                          paymentMethod === 'tap' && styles.paymentMethodNameActive,
                        ]}
                      >
                        Card prin NFC
                      </Text>
                      <Text style={styles.paymentPoweredBy}>Furnizat de Stripe</Text>
                    </View>
                  </View>
                )}
              </TouchableOpacity>
            )}

          </View>
        </ScrollView>

        {/* Confirmation dialog is now handled via Alert.alert (see
            confirmPayment above) so it works uniformly regardless of
            which view branch is currently mounted. */}

        {/* Payment Success Overlay */}
        {showPaymentSuccess && (
          <View style={styles.successOverlay}>
            <View style={styles.successContent}>
              {claimUrl ? (
                <>
                  <View style={styles.qrCodeContainer}>
                    <View style={styles.qrCodeWhiteBg}>
                      <QRCode
                        value={claimUrl}
                        size={200}
                        backgroundColor="#FFFFFF"
                        color="#000000"
                      />
                    </View>
                  </View>
                  <Text style={styles.successTitle}>
                    Plată Reușită!
                  </Text>
                  <Text style={styles.qrCodeDescription}>
                    Clientul scanează codul QR pentru a primi biletele pe email.
                  </Text>
                </>
              ) : (
                <>
                  <View style={styles.successIconWrap}>
                    <CheckIcon size={64} color={colors.green} />
                  </View>
                  <Text style={styles.successTitle}>
                    Plată Reușită!
                  </Text>
                  <Text style={styles.successAmount}>
                    {formatCurrency(lastPaymentAmount)}
                  </Text>
                </>
              )}

              <TouchableOpacity
                style={styles.sendEmailButton}
                onPress={() => finishPayment(true)}
                activeOpacity={0.7}
              >
                <Text style={styles.sendEmailButtonText}>Finalizează</Text>
              </TouchableOpacity>
            </View>
          </View>
        )}

        {/* Email Capture Modal */}
        <Modal
          visible={showEmailCapture}
          transparent
          animationType="fade"
          onRequestClose={() => {
            Keyboard.dismiss();
            setShowEmailCapture(false);
            finishPayment(true);
          }}
        >
          <TouchableWithoutFeedback onPress={Keyboard.dismiss}>
            <View style={styles.modalOverlay}>
              <View style={styles.emailModal}>
                <View style={styles.emailModalIconWrap}>
                  <MailIcon size={32} color={colors.purple} />
                </View>
                <Text style={styles.emailModalTitle}>Trimite Biletele pe Email</Text>
                <Text style={styles.emailModalDescription}>
                  Introduceți adresa de email a cumpărătorului pentru a trimite biletele digital.
                </Text>

                <TextInput
                  style={styles.emailInput}
                  placeholder="buyer@example.com"
                  placeholderTextColor={colors.textQuaternary}
                  value={buyerEmail}
                  onChangeText={setBuyerEmail}
                  keyboardType="email-address"
                  autoCapitalize="none"
                  autoCorrect={false}
                  autoFocus={true}
                />

                <TouchableOpacity
                  style={[
                    styles.sendTicketsButton,
                    !buyerEmail.trim() && styles.sendTicketsButtonDisabled,
                  ]}
                  onPress={sendTicketsEmail}
                  activeOpacity={0.7}
                  disabled={!buyerEmail.trim() || sendingEmail}
                >
                  {sendingEmail ? (
                    <ActivityIndicator size="small" color={colors.white} />
                  ) : (
                    <Text style={styles.sendTicketsButtonText}>Trimite Biletele</Text>
                  )}
                </TouchableOpacity>

                <TouchableOpacity
                  style={styles.emailSkipButton}
                  onPress={() => {
                    Keyboard.dismiss();
                    finishPayment(true);
                  }}
                  activeOpacity={0.7}
                >
                  <Text style={styles.emailSkipButtonText}>Omite</Text>
                </TouchableOpacity>
              </View>
            </View>
          </TouchableWithoutFeedback>
        </Modal>
      </View>
    );
  }

  // ─── Ticket Selection View (Default) ─────────────────────────────────────

  return (
    <View style={styles.container}>
      <ScrollView
        style={styles.scrollView}
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        {/* Ticket List Bar */}
        <TouchableOpacity
          style={styles.ticketListBar}
          onPress={() => setShowTicketList(true)}
          activeOpacity={0.7}
        >
          <ListIcon size={18} color={colors.purple} />
          <Text style={styles.ticketListBarText}>Bilete eveniment</Text>
          <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
            <Path d="M9 18l6-6-6-6" stroke={colors.textTertiary} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
          </Svg>
        </TouchableOpacity>

        {/* Select Tickets Heading */}
        <Text style={styles.sectionHeading}>Selectează Bilete</Text>

        {/* Ticket Types Grid */}
        <View style={styles.ticketGrid}>
          {ticketTypes.map((ticket) => (
            <TicketTypeCard
              key={ticket.id}
              ticket={ticket}
              onAdd={addToCart}
            />
          ))}
          {ticketTypes.length === 0 && (
            <View style={styles.emptyState}>
              <Text style={styles.emptyStateTitle}>
                Acest eveniment nu are bilete configurate pentru vânzare prin aplicație
              </Text>
              <Text style={styles.emptyStateText}>
                {(allTicketTypes && allTicketTypes.length > 0)
                  ? 'Există tipuri de bilete pentru acest eveniment, dar niciunul nu este marcat ca bilet de intrare (POS). Pentru a permite vânzarea la intrare, cere echipei AmBilet să configureze tipurile de bilete corespunzătoare.'
                  : 'Pentru a vinde bilete la intrare prin aplicație, cere echipei AmBilet să adauge tipuri de bilete pentru POS.'}
              </Text>
              <View style={styles.emptyStateButtons}>
                <TouchableOpacity
                  style={[styles.emptyStateBtn, styles.emptyStateBtnPrimary]}
                  onPress={() => Linking.openURL('https://ambilet.ro/organizator/login')}
                  activeOpacity={0.8}
                >
                  <Text style={styles.emptyStateBtnPrimaryText}>Login cont organizator</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.emptyStateBtn, styles.emptyStateBtnSecondary]}
                  onPress={() => Linking.openURL('https://ambilet.ro/contact')}
                  activeOpacity={0.8}
                >
                  <Text style={styles.emptyStateBtnSecondaryText}>Contact AmBilet</Text>
                </TouchableOpacity>
              </View>
            </View>
          )}
        </View>

        {/* Today's Sales Section */}
        {recentSales.length > 0 && (
          <View style={styles.salesSection}>
            <View style={styles.salesHeader}>
              <Text style={styles.sectionHeading}>Vânzări Azi</Text>
              <View style={styles.salesTotalBadge}>
                <Text style={styles.salesTotalText}>
                  {formatCurrency(salesTodayTotal)}
                </Text>
              </View>
            </View>

            {recentSales.map((sale, index) => (
              <RecentSaleItem key={sale.id || index} sale={sale} onShowQR={setQrReplaySale} />
            ))}
          </View>
        )}

        {/* Bottom spacer for FAB */}
        {cartCount > 0 && <View style={{ height: 100 }} />}
      </ScrollView>

      {/* Cart FAB */}
      <Animated.View
        style={[
          styles.fab,
          {
            transform: [{ scale: fabScale }],
            opacity: fabScale,
          },
        ]}
        pointerEvents={cartCount > 0 ? 'auto' : 'none'}
      >
        <TouchableOpacity
          style={styles.fabTouchable}
          onPress={() => setActiveView('cart')}
          activeOpacity={0.8}
        >
          <Svg width={56} height={56} viewBox="0 0 56 56" style={styles.fabBg}>
            <Defs>
              <LinearGradient id="fabGrad" x1="0" y1="0" x2="1" y2="1">
                <Stop offset="0" stopColor="#8B5CF6" />
                <Stop offset="1" stopColor="#6366F1" />
              </LinearGradient>
            </Defs>
            <Rect width={56} height={56} rx={28} fill="url(#fabGrad)" />
          </Svg>
          <View style={styles.fabContent}>
            <View style={styles.fabBadge}>
              <Text style={styles.fabBadgeText}>{cartCount}</Text>
            </View>
            <CartIcon size={22} color={colors.white} />
            <Text style={styles.fabAmountText}>{formatCurrency(cartTotal)}</Text>
          </View>
        </TouchableOpacity>
      </Animated.View>

      {/* QR Replay Modal */}
      <Modal
        visible={!!qrReplaySale}
        transparent
        animationType="fade"
        onRequestClose={() => setQrReplaySale(null)}
      >
        <TouchableWithoutFeedback onPress={() => setQrReplaySale(null)}>
          <View style={styles.successOverlay}>
            <TouchableWithoutFeedback>
              <View style={styles.successContent}>
                <View style={styles.qrCodeContainer}>
                  <View style={styles.qrCodeWhiteBg}>
                    {qrReplaySale?.claimUrl && (
                      <QRCode
                        value={qrReplaySale.claimUrl}
                        size={200}
                        backgroundColor="#FFFFFF"
                        color="#000000"
                      />
                    )}
                  </View>
                </View>
                <Text style={styles.successTitle}>
                  {qrReplaySale?.description || 'Vânzare'}
                </Text>
                <Text style={styles.qrCodeDescription}>
                  Clientul scanează codul QR pentru a primi biletele pe email.
                </Text>
                <TouchableOpacity
                  style={styles.sendEmailButton}
                  onPress={() => setQrReplaySale(null)}
                  activeOpacity={0.7}
                >
                  <Text style={styles.sendEmailButtonText}>Închide</Text>
                </TouchableOpacity>
              </View>
            </TouchableWithoutFeedback>
          </View>
        </TouchableWithoutFeedback>
      </Modal>

      <SeatingMapScreen
        visible={showSeatingMap}
        eventId={selectedEvent?.id}
        ticketTypeId={seatingMapTicketTypeId}
        onConfirm={handleSeatingConfirm}
        onClose={() => { setShowSeatingMap(false); setSeatingMapTicketTypeId(null); }}
      />
    </View>
  );
}

// ─── Styles ─────────────────────────────────────────────────────────────────

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  scrollView: {
    flex: 1,
  },
  scrollContent: {
    paddingHorizontal: 16,
    paddingTop: 20,
    paddingBottom: 24,
  },
  cartScrollContent: {
    paddingHorizontal: 16,
    paddingTop: 8,
    paddingBottom: 32,
  },

  // ── Placeholder (Reports Only) ────────────────────────────────────────────
  placeholderContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 32,
  },
  placeholderIconWrap: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 20,
  },
  placeholderTitle: {
    fontSize: 22,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: 8,
  },
  placeholderDescription: {
    fontSize: 15,
    color: colors.textSecondary,
    textAlign: 'center',
    lineHeight: 22,
    marginBottom: 28,
  },
  placeholderButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.purple,
    paddingHorizontal: 24,
    paddingVertical: 14,
    borderRadius: 12,
    gap: 8,
  },
  placeholderButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.white,
  },

  // ── Ticket List Bar ──────────────────────────────────────────────────────
  ticketListBar: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.purpleBg,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 12,
    marginBottom: 16,
    gap: 10,
  },
  ticketListBarText: {
    flex: 1,
    fontSize: 14,
    fontWeight: '600',
    color: colors.purple,
  },

  // ── Mode Toggle (Vânzare / Invitații) ────────────────────────────────────
  modeToggleRow: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: 16,
  },
  modeToggleBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 12,
    paddingVertical: 12,
  },
  modeToggleBtnActive: {
    borderColor: colors.purpleBorder,
    backgroundColor: colors.purpleBg,
  },
  modeToggleText: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textTertiary,
  },
  modeToggleTextActive: {
    color: colors.purple,
  },

  // ── Section Heading ───────────────────────────────────────────────────────
  sectionHeading: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: 16,
  },

  // ── Seating Map Button ──────────────────────────────────────────────────
  seatingMapButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.purpleBg,
    borderWidth: 1.5,
    borderColor: colors.purpleBorder,
    borderRadius: 16,
    padding: 16,
    marginBottom: 24,
    gap: 14,
  },
  seatingMapIconWrap: {
    width: 52,
    height: 52,
    borderRadius: 14,
    backgroundColor: 'rgba(139,92,246,0.15)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  seatingMapButtonContent: {
    flex: 1,
  },
  seatingMapButtonTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: colors.purple,
    marginBottom: 3,
  },
  seatingMapButtonSubtitle: {
    fontSize: 13,
    color: colors.textTertiary,
  },

  // ── Ticket Grid ───────────────────────────────────────────────────────────
  ticketGrid: {
    gap: 10,
    marginBottom: 28,
  },
  ticketCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 12,
    overflow: 'hidden',
  },
  ticketCardDisabled: {
    opacity: 0.5,
  },
  ticketColorBar: {
    width: 4,
    alignSelf: 'stretch',
  },
  ticketCardContent: {
    flex: 1,
    paddingVertical: 14,
    paddingHorizontal: 14,
  },
  ticketName: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 4,
  },
  ticketNameDisabled: {
    color: colors.textTertiary,
  },
  ticketPrice: {
    fontSize: 14,
    fontWeight: '700',
    color: colors.purple,
    marginBottom: 2,
  },
  ticketAvailable: {
    fontSize: 12,
    color: colors.textTertiary,
  },
  soldOutText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.red,
  },
  addButton: {
    width: 40,
    height: 40,
    borderRadius: 10,
    backgroundColor: colors.purpleLight,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },

  // ── Empty State ───────────────────────────────────────────────────────────
  emptyState: {
    paddingVertical: 32,
    paddingHorizontal: 20,
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.border,
  },
  emptyStateTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: colors.textPrimary,
    textAlign: 'center',
    marginBottom: 10,
  },
  emptyStateText: {
    fontSize: 13,
    color: colors.textSecondary,
    textAlign: 'center',
    lineHeight: 19,
  },
  emptyStateButtons: {
    width: '100%',
    gap: 10,
    marginTop: 18,
  },
  emptyStateBtn: {
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 10,
    alignItems: 'center',
  },
  emptyStateBtnPrimary: {
    backgroundColor: colors.purple,
  },
  emptyStateBtnPrimaryText: {
    color: '#fff',
    fontWeight: '700',
    fontSize: 14,
  },
  emptyStateBtnSecondary: {
    backgroundColor: 'rgba(20,10,10,0.04)',
    borderWidth: 1,
    borderColor: colors.border,
  },
  emptyStateBtnSecondaryText: {
    color: colors.textPrimary,
    fontWeight: '600',
    fontSize: 14,
  },

  // ── Today's Sales ─────────────────────────────────────────────────────────
  salesSection: {
    marginBottom: 16,
  },
  salesHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  salesTotalBadge: {
    backgroundColor: colors.greenBg,
    borderWidth: 1,
    borderColor: colors.greenBorder,
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 20,
  },
  salesTotalText: {
    fontSize: 13,
    fontWeight: '700',
    color: colors.green,
  },
  saleItem: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 10,
    paddingVertical: 12,
    paddingHorizontal: 14,
    marginBottom: 6,
  },
  saleIconWrap: {
    width: 32,
    height: 32,
    borderRadius: 8,
    backgroundColor: colors.purpleBg,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  saleInfo: {
    flex: 1,
  },
  saleDescription: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.textPrimary,
    marginBottom: 2,
  },
  saleTime: {
    fontSize: 12,
    color: colors.textTertiary,
  },
  saleAmount: {
    fontSize: 14,
    fontWeight: '700',
    color: colors.textPrimary,
    marginLeft: 8,
  },

  // ── Cart FAB ──────────────────────────────────────────────────────────────
  fab: {
    position: 'absolute',
    bottom: 24,
    right: 20,
    alignItems: 'center',
  },
  fabTouchable: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  fabBg: {
    position: 'absolute',
  },
  fabContent: {
    width: 56,
    height: 56,
    alignItems: 'center',
    justifyContent: 'center',
  },
  fabBadge: {
    position: 'absolute',
    top: -4,
    right: -4,
    backgroundColor: colors.red,
    borderRadius: 10,
    minWidth: 20,
    height: 20,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 4,
    borderWidth: 2,
    borderColor: colors.background,
    zIndex: 1,
  },
  fabBadgeText: {
    fontSize: 11,
    fontWeight: '700',
    color: colors.white,
  },
  fabAmountText: {
    fontSize: 9,
    fontWeight: '700',
    color: colors.white,
    marginTop: 1,
  },

  // ── Cart View ─────────────────────────────────────────────────────────────
  cartHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingTop: 16,
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
  cartTitle: {
    flex: 1,
    fontSize: 20,
    fontWeight: '700',
    color: colors.textPrimary,
  },
  cartCountBadge: {
    backgroundColor: colors.purpleLight,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
    paddingHorizontal: 10,
    paddingVertical: 3,
    borderRadius: 12,
  },
  cartCountBadgeText: {
    fontSize: 13,
    fontWeight: '700',
    color: colors.purple,
  },

  // ── Cart Items ────────────────────────────────────────────────────────────
  section: {
    marginTop: 16,
    gap: 8,
  },
  cartItem: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 12,
    overflow: 'hidden',
    paddingRight: 14,
  },
  cartItemColorBar: {
    width: 4,
    alignSelf: 'stretch',
  },
  cartItemInfo: {
    flex: 1,
    paddingVertical: 14,
    paddingHorizontal: 12,
  },
  cartItemName: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 2,
  },
  cartItemPrice: {
    fontSize: 12,
    color: colors.textTertiary,
  },
  cartSeatList: {
    marginTop: 6,
    gap: 2,
  },
  cartSeatText: {
    fontSize: 11,
    color: colors.purple,
    fontWeight: '600',
    letterSpacing: 0.2,
  },
  quantityControls: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  quantityButton: {
    width: 32,
    height: 32,
    borderRadius: 8,
    backgroundColor: colors.surfaceHover,
    borderWidth: 1,
    borderColor: colors.borderMedium,
    alignItems: 'center',
    justifyContent: 'center',
  },
  quantityText: {
    fontSize: 16,
    fontWeight: '700',
    color: colors.textPrimary,
    minWidth: 24,
    textAlign: 'center',
  },
  cartItemTotal: {
    fontSize: 15,
    fontWeight: '700',
    color: colors.purple,
    marginLeft: 12,
    minWidth: 60,
    textAlign: 'right',
  },

  // ── Cart Summary ──────────────────────────────────────────────────────────
  summarySection: {
    marginTop: 20,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 12,
    padding: 16,
  },
  summaryRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  summaryLabel: {
    fontSize: 14,
    color: colors.textSecondary,
  },
  summaryValue: {
    fontSize: 14,
    color: colors.textSecondary,
  },
  summaryDivider: {
    height: 1,
    backgroundColor: colors.border,
    marginVertical: 8,
  },
  summaryTotalLabel: {
    fontSize: 16,
    fontWeight: '700',
    color: colors.textPrimary,
  },
  summaryTotalValue: {
    fontSize: 18,
    fontWeight: '700',
    color: colors.textPrimary,
  },

  // ── Payment Methods ───────────────────────────────────────────────────────
  paymentSection: {
    marginTop: 24,
  },
  paymentSectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 12,
  },
  paymentButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 12,
    paddingVertical: 16,
    paddingHorizontal: 16,
    marginBottom: 10,
    minHeight: 60,
    justifyContent: 'center',
  },
  paymentButtonActive: {
    borderColor: colors.purpleBorder,
    backgroundColor: colors.purpleBg,
  },
  // Card-confirm modal (POS card terminal "money collected" confirmation).
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.7)',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  cardConfirmCard: {
    backgroundColor: colors.surface,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: colors.border,
    padding: 24,
    width: '100%',
    maxWidth: 380,
  },
  cardConfirmTitle: {
    fontSize: 17,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: 8,
  },
  cardConfirmText: {
    fontSize: 14,
    color: colors.textSecondary,
    lineHeight: 20,
    marginBottom: 14,
  },
  cardConfirmAmount: {
    fontSize: 26,
    fontWeight: '800',
    color: colors.green,
    textAlign: 'center',
    marginBottom: 18,
  },
  cardConfirmButtons: {
    flexDirection: 'row',
    gap: 10,
  },
  cardConfirmCancel: {
    flex: 1,
    paddingVertical: 13,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
  },
  cardConfirmCancelText: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textSecondary,
  },
  cardConfirmOk: {
    flex: 1.4,
    paddingVertical: 13,
    borderRadius: 10,
    backgroundColor: colors.green,
    alignItems: 'center',
  },
  cardConfirmOkText: {
    fontSize: 14,
    fontWeight: '700',
    color: colors.white,
  },
  paymentButtonContent: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  paymentButtonLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  paymentButtonText: {
    marginLeft: 14,
  },
  paymentMethodName: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  paymentMethodNameActive: {
    color: colors.purple,
  },
  paymentPoweredBy: {
    fontSize: 11,
    color: colors.textQuaternary,
    marginTop: 2,
  },

  // ── Payment Success Overlay ───────────────────────────────────────────────
  successOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(10, 10, 15, 0.95)',
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 200,
  },
  successContent: {
    alignItems: 'center',
    paddingHorizontal: 32,
  },
  successIconWrap: {
    width: 100,
    height: 100,
    borderRadius: 50,
    backgroundColor: colors.greenBg,
    borderWidth: 1,
    borderColor: colors.greenBorder,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 24,
  },
  successTitle: {
    fontSize: 24,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: 8,
  },
  successAmount: {
    fontSize: 32,
    fontWeight: '700',
    color: colors.green,
    marginBottom: 40,
  },
  sendEmailButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.purple,
    paddingHorizontal: 24,
    paddingVertical: 14,
    borderRadius: 12,
    gap: 10,
    width: '100%',
    justifyContent: 'center',
    marginBottom: 12,
  },
  sendEmailButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.white,
  },
  skipButton: {
    paddingVertical: 12,
    paddingHorizontal: 24,
  },
  skipButtonText: {
    fontSize: 15,
    color: colors.textSecondary,
    fontWeight: '500',
  },

  // ── QR Code ────────────────────────────────────────────────────────────────
  qrCodeContainer: {
    marginBottom: 16,
    alignItems: 'center',
  },
  qrCodeWhiteBg: {
    padding: 16,
    backgroundColor: colors.surface,
    borderRadius: 16,
  },
  qrCodeDescription: {
    fontSize: 14,
    color: colors.textSecondary,
    textAlign: 'center',
    marginBottom: 28,
    lineHeight: 20,
    paddingHorizontal: 8,
  },

  // ── Email Capture Modal ───────────────────────────────────────────────────
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.7)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  emailModal: {
    backgroundColor: colors.surface,
    borderRadius: 20,
    padding: 28,
    marginHorizontal: 24,
    width: '90%',
    maxWidth: 400,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: colors.border,
  },
  emailModalIconWrap: {
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: colors.purpleBg,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 16,
  },
  emailModalTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: 8,
    textAlign: 'center',
  },
  emailModalDescription: {
    fontSize: 14,
    color: colors.textSecondary,
    textAlign: 'center',
    lineHeight: 20,
    marginBottom: 24,
  },
  emailInput: {
    width: '100%',
    height: 48,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.borderMedium,
    borderRadius: 12,
    paddingHorizontal: 16,
    fontSize: 15,
    color: colors.textPrimary,
    marginBottom: 16,
  },
  sendTicketsButton: {
    width: '100%',
    height: 48,
    backgroundColor: colors.purple,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 8,
  },
  sendTicketsButtonDisabled: {
    opacity: 0.5,
  },
  sendTicketsButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.white,
  },
  emailSkipButton: {
    paddingVertical: 10,
  },
  emailSkipButtonText: {
    fontSize: 14,
    color: colors.textSecondary,
    fontWeight: '500',
  },
});
