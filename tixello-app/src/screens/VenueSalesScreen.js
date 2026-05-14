import React, { useEffect, useMemo, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
  TextInput,
  ActivityIndicator,
  Alert,
} from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors } from '../theme/colors';
import { listEvents, listEventTicketTypes, createPosOrder } from '../api/venueOwner';

/**
 * Venue-owner POS sales screen. Flow:
 *  1. Pick an event from those hosted at this venue.
 *  2. See ticket types + stock; tap +/- to set quantities.
 *  3. Enter customer name + email (phone optional); choose cash or invitation.
 *  4. Submit — backend creates an order with source='venue_owner_pos'.
 *
 * The order lands in the ORGANIZER's books (their dashboard sees it), tagged
 * via meta.sold_by="Venue: {tenant}" so the organizer can attribute revenue.
 */
export default function VenueSalesScreen() {
  const insets = useSafeAreaInsets();
  const [events, setEvents] = useState([]);
  const [selectedEvent, setSelectedEvent] = useState(null);
  const [ticketTypes, setTicketTypes] = useState([]);
  const [quantities, setQuantities] = useState({});
  const [paymentMethod, setPaymentMethod] = useState('cash'); // cash | invitation
  const [customerFirstName, setCustomerFirstName] = useState('');
  const [customerLastName, setCustomerLastName] = useState('');
  const [customerEmail, setCustomerEmail] = useState('');
  const [customerPhone, setCustomerPhone] = useState('');
  const [loadingEvents, setLoadingEvents] = useState(false);
  const [loadingTypes, setLoadingTypes] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  // ── Load events on mount ───────────────────────────────────────────
  useEffect(() => {
    let mounted = true;
    (async () => {
      setLoadingEvents(true);
      try {
        const resp = await listEvents('upcoming');
        const list = resp.data?.events || resp.data || resp.events || [];
        if (mounted) setEvents(Array.isArray(list) ? list : []);
      } catch (e) {
        console.error('Failed to load venue events:', e);
      }
      if (mounted) setLoadingEvents(false);
    })();
    return () => { mounted = false; };
  }, []);

  // ── Load ticket types when event changes ──────────────────────────
  useEffect(() => {
    if (!selectedEvent?.id) {
      setTicketTypes([]);
      setQuantities({});
      return;
    }
    let mounted = true;
    (async () => {
      setLoadingTypes(true);
      try {
        const resp = await listEventTicketTypes(selectedEvent.id);
        const list = resp.data?.ticket_types || resp.ticket_types || [];
        if (mounted) {
          setTicketTypes(Array.isArray(list) ? list : []);
          setQuantities({});
        }
      } catch (e) {
        console.error('Failed to load ticket types:', e);
        if (mounted) setTicketTypes([]);
      }
      if (mounted) setLoadingTypes(false);
    })();
    return () => { mounted = false; };
  }, [selectedEvent?.id]);

  const totalQty = useMemo(() =>
    Object.values(quantities).reduce((a, b) => a + (b || 0), 0),
    [quantities]
  );

  const totalAmount = useMemo(() => {
    if (paymentMethod === 'invitation') return 0;
    return ticketTypes.reduce(
      (sum, tt) => sum + ((quantities[tt.id] || 0) * (tt.price || 0)),
      0
    );
  }, [ticketTypes, quantities, paymentMethod]);

  const setQty = (id, delta, tt) => {
    setQuantities(prev => {
      const current = prev[id] || 0;
      const next = Math.max(0, current + delta);
      const cap = Math.min(tt.available || 0, tt.max_per_order || 20);
      return { ...prev, [id]: Math.min(next, cap) };
    });
  };

  const canSubmit = totalQty > 0
    && customerFirstName.trim()
    && customerEmail.trim()
    && !submitting;

  const handleSubmit = async () => {
    if (!canSubmit) return;
    const tickets = Object.entries(quantities)
      .filter(([, q]) => q > 0)
      .map(([ticket_type_id, quantity]) => ({
        ticket_type_id: parseInt(ticket_type_id, 10),
        quantity,
      }));

    setSubmitting(true);
    try {
      const resp = await createPosOrder({
        event_id: selectedEvent.id,
        tickets,
        customer: {
          first_name: customerFirstName.trim(),
          last_name: customerLastName.trim() || null,
          email: customerEmail.trim().toLowerCase(),
          phone: customerPhone.trim() || null,
        },
        payment_method: paymentMethod === 'invitation' ? null : paymentMethod,
        is_invitation: paymentMethod === 'invitation',
      });

      if (resp?.success) {
        Alert.alert(
          'Succes',
          `Comanda ${resp.data?.order?.order_number} a fost creată. ${resp.data?.order?.tickets_count || totalQty} bilete emise.`,
          [{ text: 'OK', onPress: resetForm }]
        );
      } else {
        Alert.alert('Eroare', resp?.message || 'Comanda nu a putut fi creată.');
      }
    } catch (e) {
      console.error('Failed to create POS order:', e);
      Alert.alert('Eroare', e?.message || 'Comanda nu a putut fi creată.');
    }
    setSubmitting(false);
  };

  const resetForm = () => {
    setQuantities({});
    setCustomerFirstName('');
    setCustomerLastName('');
    setCustomerEmail('');
    setCustomerPhone('');
    // Refresh ticket type stock after a sale
    if (selectedEvent?.id) {
      listEventTicketTypes(selectedEvent.id)
        .then(resp => setTicketTypes(resp.data?.ticket_types || resp.ticket_types || []))
        .catch(() => {});
    }
  };

  // ── Event picker ───────────────────────────────────────────────────
  if (!selectedEvent) {
    return (
      <View style={[styles.container, { paddingTop: insets.top }]}>
        <View style={styles.header}>
          <Text style={styles.headerTitle}>Vânzare bilete</Text>
          <Text style={styles.headerSubtitle}>Selectează un eveniment</Text>
        </View>
        {loadingEvents ? (
          <ActivityIndicator size="large" color={colors.purple} style={{ marginTop: 40 }} />
        ) : events.length === 0 ? (
          <View style={styles.emptyState}>
            <Text style={styles.emptyText}>Niciun eveniment viitor.</Text>
          </View>
        ) : (
          <ScrollView contentContainerStyle={styles.eventsList}>
            {events.map(ev => {
              const dateLabel = ev.starts_at
                ? new Date(ev.starts_at).toLocaleDateString('ro-RO', { day: '2-digit', month: 'short', year: 'numeric' })
                : '';
              return (
                <TouchableOpacity
                  key={ev.id}
                  style={styles.eventCard}
                  onPress={() => setSelectedEvent(ev)}
                  activeOpacity={0.7}
                >
                  <View style={{ flex: 1 }}>
                    <Text style={styles.eventName} numberOfLines={1}>{ev.name || ev.title}</Text>
                    <Text style={styles.eventDate}>{dateLabel}</Text>
                    {ev.venue_name ? (
                      <Text style={styles.eventVenue} numberOfLines={1}>{ev.venue_name}</Text>
                    ) : null}
                  </View>
                  <Svg width={20} height={20} viewBox="0 0 24 24" fill="none">
                    <Path d="M9 18l6-6-6-6" stroke={colors.textTertiary} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
                  </Svg>
                </TouchableOpacity>
              );
            })}
          </ScrollView>
        )}
      </View>
    );
  }

  // ── Sales form ─────────────────────────────────────────────────────
  return (
    <View style={[styles.container, { paddingTop: insets.top }]}>
      <View style={styles.header}>
        <TouchableOpacity
          style={styles.backRow}
          onPress={() => setSelectedEvent(null)}
          activeOpacity={0.7}
        >
          <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
            <Path d="M15 18l-6-6 6-6" stroke={colors.purple} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
          </Svg>
          <Text style={styles.backText}>Schimbă evenimentul</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle} numberOfLines={1}>{selectedEvent.name || selectedEvent.title}</Text>
      </View>

      <ScrollView contentContainerStyle={styles.formScroll} keyboardShouldPersistTaps="handled">
        {/* Payment method */}
        <Text style={styles.sectionLabel}>Tip vânzare</Text>
        <View style={styles.paymentRow}>
          <TouchableOpacity
            style={[styles.paymentBtn, paymentMethod === 'cash' && styles.paymentBtnActive]}
            onPress={() => setPaymentMethod('cash')}
            activeOpacity={0.7}
          >
            <Text style={[styles.paymentBtnText, paymentMethod === 'cash' && styles.paymentBtnTextActive]}>Numerar</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.paymentBtn, paymentMethod === 'invitation' && styles.paymentBtnActive]}
            onPress={() => setPaymentMethod('invitation')}
            activeOpacity={0.7}
          >
            <Text style={[styles.paymentBtnText, paymentMethod === 'invitation' && styles.paymentBtnTextActive]}>Invitație</Text>
          </TouchableOpacity>
        </View>

        {/* Ticket types */}
        <Text style={styles.sectionLabel}>Bilete</Text>
        {loadingTypes ? (
          <ActivityIndicator size="small" color={colors.purple} style={{ marginVertical: 16 }} />
        ) : ticketTypes.length === 0 ? (
          <Text style={styles.emptyText}>Niciun tip de bilet activ.</Text>
        ) : (
          ticketTypes.map(tt => {
            const qty = quantities[tt.id] || 0;
            const remaining = (tt.available || 0) - qty;
            return (
              <View key={tt.id} style={styles.ticketTypeRow}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.ttName}>{tt.name}</Text>
                  <Text style={styles.ttMeta}>
                    {paymentMethod === 'invitation' ? 'Gratuit' : `${tt.price.toFixed(2)} RON`} · {remaining} disponibile
                  </Text>
                </View>
                <View style={styles.qtyControls}>
                  <TouchableOpacity
                    style={styles.qtyBtn}
                    onPress={() => setQty(tt.id, -1, tt)}
                    activeOpacity={0.7}
                  >
                    <Text style={styles.qtyBtnText}>-</Text>
                  </TouchableOpacity>
                  <Text style={styles.qtyValue}>{qty}</Text>
                  <TouchableOpacity
                    style={styles.qtyBtn}
                    onPress={() => setQty(tt.id, 1, tt)}
                    activeOpacity={0.7}
                  >
                    <Text style={styles.qtyBtnText}>+</Text>
                  </TouchableOpacity>
                </View>
              </View>
            );
          })
        )}

        {/* Customer */}
        <Text style={styles.sectionLabel}>Cumpărător</Text>
        <TextInput
          style={styles.input}
          placeholder="Nume"
          placeholderTextColor={colors.textQuaternary}
          value={customerFirstName}
          onChangeText={setCustomerFirstName}
        />
        <TextInput
          style={styles.input}
          placeholder="Prenume (opțional)"
          placeholderTextColor={colors.textQuaternary}
          value={customerLastName}
          onChangeText={setCustomerLastName}
        />
        <TextInput
          style={styles.input}
          placeholder="Email"
          placeholderTextColor={colors.textQuaternary}
          value={customerEmail}
          onChangeText={setCustomerEmail}
          keyboardType="email-address"
          autoCapitalize="none"
        />
        <TextInput
          style={styles.input}
          placeholder="Telefon (opțional)"
          placeholderTextColor={colors.textQuaternary}
          value={customerPhone}
          onChangeText={setCustomerPhone}
          keyboardType="phone-pad"
        />

        {/* Totals */}
        <View style={styles.totalBox}>
          <View style={styles.totalRow}>
            <Text style={styles.totalLabel}>Bilete</Text>
            <Text style={styles.totalValue}>{totalQty}</Text>
          </View>
          <View style={styles.totalRow}>
            <Text style={styles.totalLabel}>Total</Text>
            <Text style={[styles.totalValue, styles.totalAmount]}>
              {paymentMethod === 'invitation' ? 'Gratuit' : `${totalAmount.toFixed(2)} RON`}
            </Text>
          </View>
        </View>

        <TouchableOpacity
          style={[styles.submitBtn, !canSubmit && styles.submitBtnDisabled]}
          onPress={handleSubmit}
          disabled={!canSubmit}
          activeOpacity={0.8}
        >
          {submitting ? (
            <ActivityIndicator size="small" color={colors.white} />
          ) : (
            <Text style={styles.submitBtnText}>
              {paymentMethod === 'invitation' ? 'Emite invitații' : 'Finalizează vânzarea'}
            </Text>
          )}
        </TouchableOpacity>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  header: {
    paddingHorizontal: 20,
    paddingVertical: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  headerTitle: { fontSize: 18, fontWeight: '700', color: colors.textPrimary },
  headerSubtitle: { fontSize: 12, color: colors.textSecondary, marginTop: 2 },
  backRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginBottom: 6,
  },
  backText: { fontSize: 13, color: colors.purple, fontWeight: '600' },

  eventsList: { padding: 20, gap: 10 },
  eventCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 12,
    padding: 14,
    gap: 10,
  },
  eventName: { fontSize: 15, fontWeight: '600', color: colors.textPrimary },
  eventDate: { fontSize: 12, fontWeight: '600', color: colors.purple, marginTop: 3 },
  eventVenue: { fontSize: 11, color: colors.textTertiary, marginTop: 2 },

  emptyState: { padding: 40, alignItems: 'center' },
  emptyText: { fontSize: 13, color: colors.textTertiary, textAlign: 'center' },

  formScroll: { padding: 20, paddingBottom: 60, gap: 10 },
  sectionLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.textTertiary,
    marginTop: 14,
    marginBottom: 6,
    letterSpacing: 0.3,
  },
  paymentRow: { flexDirection: 'row', gap: 8 },
  paymentBtn: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: 'rgba(255,255,255,0.03)',
    alignItems: 'center',
  },
  paymentBtnActive: {
    backgroundColor: colors.purpleLight,
    borderColor: colors.purpleBorder,
  },
  paymentBtnText: { fontSize: 13, fontWeight: '600', color: colors.textTertiary },
  paymentBtnTextActive: { color: colors.purple },

  ticketTypeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 10,
    padding: 12,
    gap: 10,
  },
  ttName: { fontSize: 14, fontWeight: '600', color: colors.textPrimary },
  ttMeta: { fontSize: 11, color: colors.textTertiary, marginTop: 2 },
  qtyControls: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  qtyBtn: {
    width: 32,
    height: 32,
    borderRadius: 8,
    backgroundColor: colors.purpleLight,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
    alignItems: 'center',
    justifyContent: 'center',
  },
  qtyBtnText: { fontSize: 18, fontWeight: '700', color: colors.purple },
  qtyValue: { fontSize: 16, fontWeight: '700', color: colors.textPrimary, minWidth: 26, textAlign: 'center' },

  input: {
    height: 44,
    backgroundColor: 'rgba(255,255,255,0.04)',
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
    paddingHorizontal: 14,
    fontSize: 14,
    color: colors.textPrimary,
    marginBottom: 4,
  },

  totalBox: {
    marginTop: 14,
    padding: 14,
    backgroundColor: colors.surface,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.border,
    gap: 4,
  },
  totalRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  totalLabel: { fontSize: 13, color: colors.textSecondary },
  totalValue: { fontSize: 14, fontWeight: '700', color: colors.textPrimary },
  totalAmount: { fontSize: 18, color: colors.green },

  submitBtn: {
    backgroundColor: colors.purple,
    paddingVertical: 16,
    borderRadius: 12,
    alignItems: 'center',
    marginTop: 16,
  },
  submitBtnDisabled: { opacity: 0.4 },
  submitBtnText: { fontSize: 15, fontWeight: '700', color: colors.white },
});
