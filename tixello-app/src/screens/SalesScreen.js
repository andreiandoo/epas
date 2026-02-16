import React, { useState, useMemo, useRef, useEffect } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ScrollView,
  TextInput,
  ActivityIndicator,
  Animated,
  Modal,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import Svg, { Path, Circle, Rect, Defs, LinearGradient, Stop } from 'react-native-svg';
import { colors } from '../theme/colors';
import { useEvent } from '../context/EventContext';
import { useAuth } from '../context/AuthContext';
import { useApp } from '../context/AppContext';
import { apiPost } from '../api/client';
import { formatCurrency } from '../utils/formatCurrency';

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

  return (
    <TouchableOpacity
      style={[styles.ticketCard, isSoldOut && styles.ticketCardDisabled]}
      onPress={() => !isSoldOut && onAdd(ticket)}
      activeOpacity={isSoldOut ? 1 : 0.7}
    >
      <View style={[styles.ticketColorBar, { backgroundColor: ticket.color }]} />
      <View style={styles.ticketCardContent}>
        <Text
          style={[styles.ticketName, isSoldOut && styles.ticketNameDisabled]}
          numberOfLines={1}
        >
          {ticket.name}
        </Text>
        <Text style={styles.ticketPrice}>{formatCurrency(ticket.price)}</Text>
        {isSoldOut ? (
          <Text style={styles.soldOutText}>Epuizat</Text>
        ) : (
          <Text style={styles.ticketAvailable}>{ticket.available} disponibile</Text>
        )}
      </View>
      {!isSoldOut && (
        <TouchableOpacity
          style={styles.addButton}
          onPress={() => onAdd(ticket)}
          activeOpacity={0.7}
        >
          <PlusIcon size={18} color={colors.white} />
        </TouchableOpacity>
      )}
    </TouchableOpacity>
  );
}

// ─── Recent Sale Item ─────────────────────────────────────────────────────────

function RecentSaleItem({ sale }) {
  return (
    <View style={styles.saleItem}>
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
      <Text style={styles.saleAmount}>{formatCurrency(sale.total)}</Text>
    </View>
  );
}

// ─── Cart Item Row ────────────────────────────────────────────────────────────

function CartItemRow({ item, onUpdateQuantity }) {
  return (
    <View style={styles.cartItem}>
      <View style={[styles.cartItemColorBar, { backgroundColor: item.color }]} />
      <View style={styles.cartItemInfo}>
        <Text style={styles.cartItemName} numberOfLines={1}>{item.name}</Text>
        <Text style={styles.cartItemPrice}>{formatCurrency(item.price)} fiecare</Text>
      </View>
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
      <Text style={styles.cartItemTotal}>
        {formatCurrency(item.price * item.quantity)}
      </Text>
    </View>
  );
}

// ─── Main SalesScreen Component ───────────────────────────────────────────────

export default function SalesScreen({ navigation }) {
  const { ticketTypes, isReportsOnlyMode, selectedEvent, refreshStats, eventCommission } = useEvent();
  const { user } = useAuth();
  const { recentSales, addSale } = useApp();

  // State
  const [activeView, setActiveView] = useState('tickets'); // 'tickets' | 'cart'
  const [cartItems, setCartItems] = useState([]);
  const [paymentMethod, setPaymentMethod] = useState(null); // 'tap' | 'cash'
  const [isProcessing, setIsProcessing] = useState(false);
  const [showPaymentSuccess, setShowPaymentSuccess] = useState(false);
  const [showEmailCapture, setShowEmailCapture] = useState(false);
  const [buyerEmail, setBuyerEmail] = useState('');
  const [sendingEmail, setSendingEmail] = useState(false);
  const [lastPaymentAmount, setLastPaymentAmount] = useState(0);
  const [lastOrderData, setLastOrderData] = useState(null);

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
      // Create order via API
      const orderPayload = {
        event_id: selectedEvent?.id,
        tickets: cartItems.map(item => ({
          ticket_type_id: item.id,
          quantity: item.quantity,
        })),
        customer: {
          email: 'pos@ambilet.ro',
          first_name: 'POS',
          last_name: method === 'cash' ? 'Numerar' : 'Card',
        },
        payment_method: method,
        source: 'pos_app',
      };

      const response = await apiPost('/orders', orderPayload);

      if (response.success) {
        const orderData = response.data?.order || null;

        // For card payments, check if Stripe payment_url is available
        if (method === 'tap' && !orderData?.payment_url) {
          alert('Plata prin Stripe nu este disponibilă momentan. Folosiți plata cu numerar.');
          setIsProcessing(false);
          setPaymentMethod(null);
          return;
        }

        setLastPaymentAmount(cartTotal);
        setShowPaymentSuccess(true);
        setLastOrderData(orderData);

        // Record the sale locally
        const saleDescription = cartItems
          .map((item) => `${item.quantity}x ${item.name}`)
          .join(', ');

        addSale({
          method,
          total: cartTotal,
          description: saleDescription,
          qty: cartCount,
          type: cartItems.length === 1 ? cartItems[0].name : 'Mixt',
          time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        });

        refreshStats();
      } else {
        throw new Error(response.message || 'Eroare la crearea comenzii');
      }
    } catch (error) {
      alert(error.message || 'Eroare la procesarea plății');
    } finally {
      setIsProcessing(false);
    }
  };

  const finishPayment = () => {
    setShowPaymentSuccess(false);
    setShowEmailCapture(false);
    setCartItems([]);
    setPaymentMethod(null);
    setBuyerEmail('');
    setLastOrderData(null);
    setActiveView('tickets');
  };

  const sendTicketsEmail = async () => {
    if (!buyerEmail.trim() || !lastOrderData) return;
    setSendingEmail(true);
    try {
      await apiPost(`/orders/${lastOrderData.id}/send-tickets`, {
        email: buyerEmail.trim(),
      });
    } catch (e) {
      // Silently fail - best effort
      console.error('Failed to send ticket email:', e);
    }
    setSendingEmail(false);
    finishPayment();
  };

  // ─── Reports Only Mode ────────────────────────────────────────────────────

  if (isReportsOnlyMode) {
    return (
      <View style={styles.container}>
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
            onPress={() => setActiveView('tickets')}
            activeOpacity={0.7}
          >
            <ArrowLeftIcon size={22} color={colors.white} />
          </TouchableOpacity>
          <Text style={styles.cartTitle}>Coș</Text>
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
                onUpdateQuantity={updateQuantity}
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
            <Text style={styles.paymentSectionTitle}>Metodă de Plată</Text>

            {/* Plată Card */}
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
                      Plată Card
                    </Text>
                    <Text style={styles.paymentPoweredBy}>Furnizat de Stripe</Text>
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
              onPress={() => !isProcessing && processPayment('cash')}
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
          </View>
        </ScrollView>

        {/* Payment Success Overlay */}
        {showPaymentSuccess && (
          <View style={styles.successOverlay}>
            <View style={styles.successContent}>
              <View style={styles.successIconWrap}>
                <CheckIcon size={64} color={colors.green} />
              </View>
              <Text style={styles.successTitle}>Plată Reușită!</Text>
              <Text style={styles.successAmount}>
                {formatCurrency(lastPaymentAmount)}
              </Text>

              <TouchableOpacity
                style={styles.sendEmailButton}
                onPress={() => {
                  setShowPaymentSuccess(false);
                  setShowEmailCapture(true);
                }}
                activeOpacity={0.7}
              >
                <MailIcon size={20} color={colors.white} />
                <Text style={styles.sendEmailButtonText}>
                  Trimite Biletele pe Email
                </Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={styles.skipButton}
                onPress={finishPayment}
                activeOpacity={0.7}
              >
                <Text style={styles.skipButtonText}>Omite & Finalizează</Text>
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
            setShowEmailCapture(false);
            finishPayment();
          }}
        >
          <KeyboardAvoidingView
            style={styles.modalOverlay}
            behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
          >
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
                onPress={finishPayment}
                activeOpacity={0.7}
              >
                <Text style={styles.emailSkipButtonText}>Omite</Text>
              </TouchableOpacity>
            </View>
          </KeyboardAvoidingView>
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
              <Text style={styles.emptyStateText}>
                Niciun tip de bilet disponibil
              </Text>
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
              <RecentSaleItem key={sale.id || index} sale={sale} />
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

  // ── Section Heading ───────────────────────────────────────────────────────
  sectionHeading: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: 16,
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
    paddingVertical: 40,
    alignItems: 'center',
  },
  emptyStateText: {
    fontSize: 14,
    color: colors.textTertiary,
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

  // ── Email Capture Modal ───────────────────────────────────────────────────
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.7)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  emailModal: {
    backgroundColor: '#16161F',
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
