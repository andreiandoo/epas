import { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Modal,
  Alert,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { Card, Button, Input } from '../../../src/components/ui';
import { useEventStore } from '../../../src/stores/eventStore';
import { useCartStore } from '../../../src/stores/cartStore';
import { useAppStore } from '../../../src/stores/appStore';
import { eventsApi, doorSalesApi } from '../../../src/api';
import { TicketType } from '../../../src/types';
import { colors, spacing, typography, borderRadius } from '../../../src/utils/theme';

export default function SalesScreen() {
  const { selectedEvent, ticketTypes, setTicketTypes } = useEventStore();
  const {
    items,
    addItem,
    updateQuantity,
    clearCart,
    cartTotal,
    cartCount,
    customerName,
    setCustomerName,
    customerEmail,
    setCustomerEmail,
    isProcessingPayment,
    setProcessingPayment,
    addSaleToHistory,
    addToShiftTotal,
    salesHistory,
  } = useCartStore();
  const { isShiftPaused, addNotification } = useAppStore();

  const [showCart, setShowCart] = useState(false);
  const [showPaymentSuccess, setShowPaymentSuccess] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState<'card' | 'cash' | null>(null);
  const [lastSaleTotal, setLastSaleTotal] = useState(0);

  useEffect(() => {
    if (selectedEvent) {
      loadTicketTypes();
    }
  }, [selectedEvent]);

  const loadTicketTypes = async () => {
    if (!selectedEvent) return;
    try {
      const response = await eventsApi.getTicketTypes(selectedEvent.id);
      if (response.data) {
        setTicketTypes(response.data);
      }
    } catch (error) {
      console.error('Error loading ticket types:', error);
    }
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('ro-RO').format(amount) + ' lei';
  };

  const handleAddToCart = (ticket: TicketType) => {
    if (isShiftPaused || ticket.available_quantity === 0) return;
    addItem(ticket);
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
  };

  const processPayment = async (method: 'card' | 'cash') => {
    if (!selectedEvent) return;

    setPaymentMethod(method);
    setProcessingPayment(true);

    try {
      // For card payments, we would integrate Stripe Terminal here
      // For now, simulate the payment process

      const response = await doorSalesApi.process({
        tenant_id: selectedEvent.tenant_id,
        event_id: selectedEvent.id,
        user_id: 1, // Would come from auth store
        items: items.map(item => ({
          ticket_type_id: item.ticket_type.id,
          quantity: item.quantity,
        })),
        payment_method: method === 'card' ? 'card_tap' : 'cash',
        customer_name: customerName || undefined,
        customer_email: customerEmail || undefined,
      });

      if (response.success) {
        const total = cartTotal();

        // Add to history
        addSaleToHistory({
          id: Date.now(),
          tickets: cartCount(),
          ticket_type: items.map(i => i.ticket_type.name).join(', '),
          total: total,
          payment_method: method,
          time: new Date().toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
          }),
        });

        // Add to shift totals
        addToShiftTotal(total, method);

        // Show success
        setLastSaleTotal(total);
        setShowPaymentSuccess(true);
        Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);

        // Add notification
        addNotification({
          type: 'success',
          message: `Sale completed: ${formatCurrency(total)}`,
          time: 'Just now',
          unread: true,
        });

        // Clear cart after delay
        setTimeout(() => {
          setShowPaymentSuccess(false);
          setPaymentMethod(null);
          clearCart();
          setShowCart(false);
        }, 2500);
      }
    } catch (error: any) {
      console.error('Payment error:', error);
      Alert.alert('Payment Failed', error.response?.data?.message || 'Please try again');
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
    } finally {
      setProcessingPayment(false);
    }
  };

  const ticketColors = ['#8B5CF6', '#F59E0B', '#10B981', '#06B6D4', '#EF4444', '#EC4899'];

  return (
    <View style={styles.container}>
      {/* Shift Paused Overlay */}
      {isShiftPaused && (
        <View style={styles.pausedOverlay}>
          <View style={styles.pausedContent}>
            <Ionicons name="pause" size={64} color={colors.textPrimary} />
            <Text style={styles.pausedText}>Shift Paused</Text>
          </View>
        </View>
      )}

      <ScrollView style={styles.content} showsVerticalScrollIndicator={false}>
        {/* Ticket Selection */}
        <Text style={styles.sectionTitle}>Select Tickets</Text>
        <View style={styles.ticketsGrid}>
          {ticketTypes.map((ticket, index) => {
            const ticketColor = ticketColors[index % ticketColors.length];
            const isSoldOut = ticket.available_quantity === 0;

            return (
              <TouchableOpacity
                key={ticket.id}
                style={[styles.ticketCard, isSoldOut && styles.ticketSoldOut]}
                onPress={() => handleAddToCart(ticket)}
                disabled={isSoldOut || isShiftPaused}
              >
                <View style={[styles.ticketBadge, { backgroundColor: ticketColor }]} />
                <View style={styles.ticketInfo}>
                  <Text style={styles.ticketName}>{ticket.name}</Text>
                  <Text style={styles.ticketPrice}>
                    {formatCurrency((ticket.sale_price_cents || ticket.price_cents) / 100)}
                  </Text>
                </View>
                <Text
                  style={[
                    styles.ticketAvailable,
                    isSoldOut && { color: colors.error },
                  ]}
                >
                  {isSoldOut ? 'Sold Out' : `${ticket.available_quantity} left`}
                </Text>
                {!isSoldOut && (
                  <View style={styles.addButton}>
                    <Ionicons name="add" size={18} color={colors.primary} />
                  </View>
                )}
              </TouchableOpacity>
            );
          })}
        </View>

        {/* Sales History */}
        <View style={styles.historySection}>
          <Text style={styles.sectionTitle}>My Sales Today</Text>
          {salesHistory.slice(0, 5).map((sale) => (
            <View key={sale.id} style={styles.historyItem}>
              <View style={styles.historyIcon}>
                <Ionicons
                  name={sale.payment_method === 'card' ? 'card' : 'cash'}
                  size={18}
                  color={colors.textMuted}
                />
              </View>
              <View style={styles.historyInfo}>
                <Text style={styles.historyDesc}>
                  {sale.tickets}x {sale.ticket_type}
                </Text>
                <Text style={styles.historyTime}>{sale.time}</Text>
              </View>
              <Text style={styles.historyAmount}>{formatCurrency(sale.total)}</Text>
            </View>
          ))}
          {salesHistory.length === 0 && (
            <Text style={styles.emptyText}>No sales yet</Text>
          )}
        </View>

        <View style={styles.bottomPadding} />
      </ScrollView>

      {/* Cart FAB */}
      {cartCount() > 0 && (
        <TouchableOpacity
          style={styles.cartFab}
          onPress={() => setShowCart(true)}
        >
          <View style={styles.fabBadge}>
            <Text style={styles.fabBadgeText}>{cartCount()}</Text>
          </View>
          <Ionicons name="cart" size={22} color={colors.textPrimary} />
          <Text style={styles.fabTotal}>{formatCurrency(cartTotal())}</Text>
        </TouchableOpacity>
      )}

      {/* Cart Modal */}
      <Modal visible={showCart} animationType="slide" transparent>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            {/* Payment Success Overlay */}
            {showPaymentSuccess && (
              <View style={styles.successOverlay}>
                <Ionicons name="checkmark-circle" size={80} color={colors.success} />
                <Text style={styles.successText}>Payment Successful!</Text>
                <Text style={styles.successAmount}>{formatCurrency(lastSaleTotal)}</Text>
              </View>
            )}

            <View style={styles.cartHeader}>
              <TouchableOpacity style={styles.backButton} onPress={() => setShowCart(false)}>
                <Ionicons name="arrow-back" size={20} color={colors.textPrimary} />
              </TouchableOpacity>
              <Text style={styles.cartTitle}>Cart ({cartCount()})</Text>
            </View>

            <ScrollView style={styles.cartItems}>
              {items.map((item) => (
                <View key={item.ticket_type.id} style={styles.cartItem}>
                  <View
                    style={[
                      styles.cartItemBadge,
                      { backgroundColor: ticketColors[0] },
                    ]}
                  />
                  <View style={styles.cartItemInfo}>
                    <Text style={styles.cartItemName}>{item.ticket_type.name}</Text>
                    <Text style={styles.cartItemPrice}>
                      {formatCurrency((item.ticket_type.sale_price_cents || item.ticket_type.price_cents) / 100)}
                    </Text>
                  </View>
                  <View style={styles.qtyControls}>
                    <TouchableOpacity
                      style={styles.qtyButton}
                      onPress={() => updateQuantity(item.ticket_type.id, -1)}
                    >
                      <Text style={styles.qtyButtonText}>−</Text>
                    </TouchableOpacity>
                    <Text style={styles.qtyValue}>{item.quantity}</Text>
                    <TouchableOpacity
                      style={styles.qtyButton}
                      onPress={() => updateQuantity(item.ticket_type.id, 1)}
                    >
                      <Text style={styles.qtyButtonText}>+</Text>
                    </TouchableOpacity>
                  </View>
                  <Text style={styles.cartItemTotal}>
                    {formatCurrency(
                      ((item.ticket_type.sale_price_cents || item.ticket_type.price_cents) / 100) *
                        item.quantity
                    )}
                  </Text>
                </View>
              ))}
            </ScrollView>

            {/* Customer Info */}
            <View style={styles.customerInfo}>
              <Input
                value={customerName}
                onChangeText={setCustomerName}
                placeholder="Customer name (optional)"
                style={styles.customerInput}
              />
              <Input
                value={customerEmail}
                onChangeText={setCustomerEmail}
                placeholder="Email for tickets (optional)"
                keyboardType="email-address"
                style={styles.customerInput}
              />
            </View>

            {/* Cart Total */}
            <View style={styles.cartTotal}>
              <Text style={styles.cartTotalLabel}>Total</Text>
              <Text style={styles.cartTotalValue}>{formatCurrency(cartTotal())}</Text>
            </View>

            {/* Payment Methods */}
            <Text style={styles.paymentTitle}>Payment Method</Text>
            <View style={styles.paymentGrid}>
              <TouchableOpacity
                style={[
                  styles.paymentButton,
                  paymentMethod === 'card' && styles.paymentButtonActive,
                ]}
                onPress={() => processPayment('card')}
                disabled={isProcessingPayment}
              >
                <View style={styles.tapIcon}>
                  <Ionicons name="card" size={32} color={colors.textPrimary} />
                  <View style={styles.contactlessWaves}>
                    <View style={styles.wave} />
                    <View style={[styles.wave, styles.wave2]} />
                    <View style={[styles.wave, styles.wave3]} />
                  </View>
                </View>
                <Text style={styles.paymentButtonText}>Tap to Pay</Text>
                <Text style={styles.poweredBy}>Card / Apple Pay / Google Pay</Text>
                {paymentMethod === 'card' && isProcessingPayment && (
                  <View style={styles.processingSpinner} />
                )}
              </TouchableOpacity>

              <TouchableOpacity
                style={[
                  styles.paymentButton,
                  paymentMethod === 'cash' && styles.paymentButtonActive,
                ]}
                onPress={() => processPayment('cash')}
                disabled={isProcessingPayment}
              >
                <Ionicons name="cash" size={32} color={colors.textPrimary} />
                <Text style={styles.paymentButtonText}>Cash</Text>
                {paymentMethod === 'cash' && isProcessingPayment && (
                  <View style={styles.processingSpinner} />
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
    paddingTop: 60,
  },
  content: {
    flex: 1,
    padding: spacing.xl,
  },
  pausedOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(0, 0, 0, 0.8)',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 100,
  },
  pausedContent: {
    alignItems: 'center',
  },
  pausedText: {
    fontSize: typography.fontSize.xxl,
    fontWeight: '600',
    color: colors.textPrimary,
    marginTop: spacing.lg,
  },
  sectionTitle: {
    fontSize: typography.fontSize.lg,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: spacing.lg,
  },
  ticketsGrid: {
    gap: spacing.md,
    marginBottom: spacing.xxl,
  },
  ticketCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: spacing.lg,
    backgroundColor: colors.backgroundCard,
    borderRadius: borderRadius.xl,
    borderWidth: 1,
    borderColor: colors.borderLight,
    gap: spacing.md,
  },
  ticketSoldOut: {
    opacity: 0.5,
  },
  ticketBadge: {
    width: 6,
    height: 40,
    borderRadius: 3,
  },
  ticketInfo: {
    flex: 1,
  },
  ticketName: {
    fontSize: typography.fontSize.md,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: spacing.xs,
  },
  ticketPrice: {
    fontSize: typography.fontSize.md,
    color: colors.textSecondary,
    fontFamily: typography.fontFamily.mono,
  },
  ticketAvailable: {
    fontSize: typography.fontSize.sm,
    color: colors.success,
  },
  addButton: {
    width: 36,
    height: 36,
    borderRadius: borderRadius.md,
    backgroundColor: colors.primaryLight,
    justifyContent: 'center',
    alignItems: 'center',
  },
  historySection: {
    marginTop: spacing.lg,
  },
  historyItem: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: spacing.md,
  },
  historyIcon: {
    width: 36,
    height: 36,
    borderRadius: borderRadius.md,
    backgroundColor: colors.backgroundCard,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: spacing.md,
  },
  historyInfo: {
    flex: 1,
  },
  historyDesc: {
    fontSize: typography.fontSize.md,
    fontWeight: '500',
    color: colors.textPrimary,
  },
  historyTime: {
    fontSize: typography.fontSize.sm,
    color: colors.textMuted,
  },
  historyAmount: {
    fontSize: typography.fontSize.md,
    fontWeight: '600',
    color: colors.success,
    fontFamily: typography.fontFamily.mono,
  },
  emptyText: {
    fontSize: typography.fontSize.md,
    color: colors.textMuted,
    textAlign: 'center',
    paddingVertical: spacing.xxl,
  },
  bottomPadding: {
    height: 150,
  },
  cartFab: {
    position: 'absolute',
    bottom: 110,
    alignSelf: 'center',
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.xl,
    backgroundColor: colors.primary,
    borderRadius: borderRadius.xxl,
    gap: spacing.md,
    shadowColor: colors.primary,
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.4,
    shadowRadius: 20,
    elevation: 10,
  },
  fabBadge: {
    position: 'absolute',
    top: -8,
    left: -8,
    width: 24,
    height: 24,
    borderRadius: 12,
    backgroundColor: colors.error,
    justifyContent: 'center',
    alignItems: 'center',
  },
  fabBadgeText: {
    fontSize: 12,
    fontWeight: '700',
    color: colors.textPrimary,
  },
  fabTotal: {
    fontSize: typography.fontSize.lg,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.8)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: colors.backgroundLight,
    borderTopLeftRadius: borderRadius.xxl,
    borderTopRightRadius: borderRadius.xxl,
    padding: spacing.xl,
    maxHeight: '90%',
  },
  successOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(0, 0, 0, 0.9)',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 100,
    borderRadius: borderRadius.xxl,
  },
  successText: {
    fontSize: typography.fontSize.xl,
    fontWeight: '600',
    color: colors.textPrimary,
    marginTop: spacing.lg,
  },
  successAmount: {
    fontSize: 32,
    fontWeight: '700',
    color: colors.success,
    fontFamily: typography.fontFamily.mono,
    marginTop: spacing.sm,
  },
  cartHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.lg,
    marginBottom: spacing.xl,
  },
  backButton: {
    width: 40,
    height: 40,
    borderRadius: borderRadius.md,
    backgroundColor: colors.backgroundCard,
    borderWidth: 1,
    borderColor: colors.border,
    justifyContent: 'center',
    alignItems: 'center',
  },
  cartTitle: {
    fontSize: typography.fontSize.xl,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  cartItems: {
    maxHeight: 200,
    marginBottom: spacing.lg,
  },
  cartItem: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: spacing.lg,
    backgroundColor: colors.backgroundCard,
    borderRadius: borderRadius.xl,
    marginBottom: spacing.md,
  },
  cartItemBadge: {
    width: 4,
    height: 40,
    borderRadius: 2,
    marginRight: spacing.md,
  },
  cartItemInfo: {
    flex: 1,
  },
  cartItemName: {
    fontSize: typography.fontSize.md,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  cartItemPrice: {
    fontSize: typography.fontSize.sm,
    color: colors.textMuted,
  },
  qtyControls: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
  },
  qtyButton: {
    width: 32,
    height: 32,
    borderRadius: 8,
    backgroundColor: colors.border,
    justifyContent: 'center',
    alignItems: 'center',
  },
  qtyButtonText: {
    fontSize: 18,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  qtyValue: {
    fontSize: typography.fontSize.lg,
    fontWeight: '600',
    color: colors.textPrimary,
    minWidth: 24,
    textAlign: 'center',
  },
  cartItemTotal: {
    fontSize: typography.fontSize.md,
    fontWeight: '600',
    color: colors.textPrimary,
    fontFamily: typography.fontFamily.mono,
    marginLeft: spacing.md,
  },
  customerInfo: {
    marginBottom: spacing.lg,
  },
  customerInput: {
    marginBottom: spacing.sm,
  },
  cartTotal: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: spacing.lg,
    borderTopWidth: 1,
    borderTopColor: colors.border,
    marginBottom: spacing.lg,
  },
  cartTotalLabel: {
    fontSize: typography.fontSize.lg,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  cartTotalValue: {
    fontSize: typography.fontSize.xl,
    fontWeight: '700',
    color: colors.textPrimary,
    fontFamily: typography.fontFamily.mono,
  },
  paymentTitle: {
    fontSize: typography.fontSize.md,
    fontWeight: '600',
    color: colors.textSecondary,
    marginBottom: spacing.md,
  },
  paymentGrid: {
    flexDirection: 'row',
    gap: spacing.md,
  },
  paymentButton: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: spacing.xl,
    backgroundColor: colors.backgroundCard,
    borderRadius: borderRadius.xl,
    borderWidth: 2,
    borderColor: colors.border,
    gap: spacing.sm,
  },
  paymentButtonActive: {
    borderColor: colors.primary,
    backgroundColor: colors.primaryLight,
  },
  paymentButtonText: {
    fontSize: typography.fontSize.lg,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  tapIcon: {
    position: 'relative',
  },
  contactlessWaves: {
    position: 'absolute',
    top: -4,
    right: -8,
    gap: 2,
  },
  wave: {
    width: 8,
    height: 8,
    borderWidth: 2,
    borderColor: colors.primary,
    borderRadius: 4,
    borderLeftColor: 'transparent',
    borderBottomColor: 'transparent',
    transform: [{ rotate: '45deg' }],
    opacity: 0.6,
  },
  wave2: {
    width: 12,
    height: 12,
    borderRadius: 6,
    marginLeft: -2,
    marginTop: -2,
  },
  wave3: {
    width: 16,
    height: 16,
    borderRadius: 8,
    marginLeft: -4,
    marginTop: -4,
  },
  poweredBy: {
    fontSize: typography.fontSize.xs,
    color: colors.textMuted,
  },
  processingSpinner: {
    position: 'absolute',
    top: 12,
    right: 12,
    width: 16,
    height: 16,
    borderWidth: 2,
    borderColor: colors.primaryLight,
    borderTopColor: colors.primary,
    borderRadius: 8,
  },
});
