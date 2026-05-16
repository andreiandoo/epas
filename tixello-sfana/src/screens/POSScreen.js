// POSScreen — vânzare on-site rapidă cu pos_price.
// Versiune mobilă a /organizator/leisure-pos.
import React, { useEffect, useState, useCallback } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, ActivityIndicator, Alert } from 'react-native';
import { useShift } from '../context/ShiftContext';
import { colors } from '../theme/colors';
import { fetchLeisureConfig, posSale } from '../api/leisure';

const CAT_LABELS = {
  access: '🎟️ Acces', parking: '🅿️ Parcare', rental: '🛶 Închiriere',
  activity: '🎯 Activitate', extra: '➕ Extra', package: '🎁 Pachet',
};

export default function POSScreen({ navigation }) {
  const { activeEvent } = useShift();
  const eventId = activeEvent?.id;
  const [types, setTypes] = useState([]);
  const [cart, setCart] = useState({}); // key -> { qty, price, name, ttId, variantId }
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [payment, setPayment] = useState('cash');

  const load = useCallback(async () => {
    if (!eventId) return;
    try {
      const cfg = await fetchLeisureConfig(eventId);
      const all = cfg.data?.ticket_types || [];
      // Filtreaza: doar produse marcate "Doar pentru vanzare POS" sau cu pret POS setat.
      const posTypes = all.filter(t => {
        const meta = t.meta || {};
        const isPosOnly = !!meta.pos_only;
        const hasPosPrice = (t.pos_price !== null && t.pos_price !== undefined && t.pos_price !== '');
        return isPosOnly || hasPosPrice;
      });
      setTypes(posTypes);
    } catch (e) {
      console.warn('[POS] load', e);
    } finally {
      setLoading(false);
    }
  }, [eventId]);
  useEffect(() => { load(); }, [load]);

  function priceFor(t) {
    if (t.pos_price !== null && t.pos_price !== undefined && t.pos_price !== '') {
      return parseFloat(t.pos_price);
    }
    return parseFloat(t.price_max ?? t.price ?? 0);
  }

  function addToCart(t, variant) {
    const vid = variant?.id || null;
    const key = vid ? `${t.id}|${vid}` : String(t.id);
    const price = variant ? parseFloat(variant.price) : priceFor(t);
    setCart(prev => {
      const next = { ...prev };
      if (!next[key]) {
        next[key] = {
          qty: 0, price,
          name: labelOf(t.name) + (variant ? ` — ${variant.label}` : ''),
          ttId: t.id, variantId: vid,
        };
      }
      next[key].qty++;
      return next;
    });
  }

  function decrement(key) {
    setCart(prev => {
      const next = { ...prev };
      if (!next[key]) return prev;
      next[key].qty--;
      if (next[key].qty <= 0) delete next[key];
      return next;
    });
  }

  function clearCart() { setCart({}); }

  const cartItems = Object.entries(cart);
  const subtotal = cartItems.reduce((s, [, it]) => s + it.qty * it.price, 0);

  async function checkout() {
    if (cartItems.length === 0) return;
    setSubmitting(true);
    try {
      const items = cartItems.map(([, it]) => ({
        ticket_type_id: it.ttId,
        qty: it.qty,
        variant_id: it.variantId,
      }));
      const res = await posSale(eventId, {
        date: new Date().toISOString().slice(0, 10),
        items,
        customer: { name: 'POS — vânzare on-site' },
        payment_method: payment,
      });
      const total = res.data?.order?.total ?? 0;
      Alert.alert('✓ Vânzare finalizată', `Total: ${parseFloat(total).toFixed(2)} RON\nBilete: ${res.data?.tickets?.length || 0}`, [
        { text: 'OK', onPress: clearCart },
      ]);
    } catch (e) {
      Alert.alert('Eroare', e.message || 'Vânzare eșuată');
    } finally {
      setSubmitting(false);
    }
  }

  function tapProduct(t) {
    if (Array.isArray(t.variants) && t.variants.length > 0) {
      Alert.alert(
        labelOf(t.name),
        'Alege varianta:',
        t.variants.map(v => ({
          text: `${v.label} (${parseFloat(v.price).toFixed(2)} RON)`,
          onPress: () => addToCart(t, v),
        })).concat([{ text: 'Anulează', style: 'cancel' }])
      );
    } else {
      addToCart(t, null);
    }
  }

  if (loading) {
    return <View style={styles.center}><ActivityIndicator color={colors.primary} /></View>;
  }

  return (
    <View style={styles.container}>
      <ScrollView style={styles.grid} contentContainerStyle={{ padding: 16, paddingTop: 60 }}>
        <Text style={styles.title}>💳 POS — Vânzare on-site</Text>
        {types.map(t => (
          <TouchableOpacity key={t.id} style={styles.product} onPress={() => tapProduct(t)}>
            <View style={{ flex: 1 }}>
              <Text style={styles.productCat}>{CAT_LABELS[t.service_category] || t.service_category}</Text>
              <Text style={styles.productName}>{labelOf(t.name)}</Text>
            </View>
            <Text style={styles.productPrice}>{priceFor(t).toFixed(2)} RON</Text>
          </TouchableOpacity>
        ))}
      </ScrollView>

      {/* Cart */}
      <View style={styles.cart}>
        <View style={styles.cartHeader}>
          <Text style={styles.cartTitle}>Coș ({cartItems.length})</Text>
          {cartItems.length > 0 && (
            <TouchableOpacity onPress={clearCart}>
              <Text style={styles.cartClear}>Golește</Text>
            </TouchableOpacity>
          )}
        </View>
        {cartItems.length === 0 ? (
          <Text style={styles.cartEmpty}>Apasă pe un produs pentru a-l adăuga.</Text>
        ) : (
          <ScrollView style={{ maxHeight: 180 }}>
            {cartItems.map(([key, it]) => (
              <View key={key} style={styles.cartLine}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.cartLineName}>{it.name}</Text>
                  <Text style={styles.cartLinePrice}>{it.price.toFixed(2)} × {it.qty}</Text>
                </View>
                <TouchableOpacity style={styles.qtyBtn} onPress={() => decrement(key)}><Text style={styles.qtyBtnText}>−</Text></TouchableOpacity>
                <Text style={styles.cartLineQty}>{it.qty}</Text>
                <TouchableOpacity style={styles.qtyBtn} onPress={() => addToCart({ id: it.ttId, name: it.name, price: it.price, pos_price: it.price, variants: [] }, it.variantId ? { id: it.variantId, label: '', price: it.price } : null)}>
                  <Text style={styles.qtyBtnText}>+</Text>
                </TouchableOpacity>
              </View>
            ))}
          </ScrollView>
        )}

        {cartItems.length > 0 && (
          <>
            <View style={styles.totalRow}>
              <Text style={styles.totalLabel}>Total</Text>
              <Text style={styles.totalValue}>{subtotal.toFixed(2)} RON</Text>
            </View>
            <View style={styles.paymentRow}>
              {['cash', 'card', 'invoice'].map(m => (
                <TouchableOpacity
                  key={m}
                  style={[styles.payBtn, payment === m && styles.payBtnActive]}
                  onPress={() => setPayment(m)}
                >
                  <Text style={[styles.payBtnText, payment === m && styles.payBtnTextActive]}>
                    {m === 'cash' ? '💵 Cash' : m === 'card' ? '💳 Card' : '📧 Email'}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
            <TouchableOpacity style={styles.checkoutBtn} onPress={checkout} disabled={submitting}>
              <Text style={styles.checkoutBtnText}>{submitting ? 'Se procesează...' : 'Finalizează vânzare'}</Text>
            </TouchableOpacity>
          </>
        )}
      </View>

      <TouchableOpacity style={styles.backBtn} onPress={() => navigation.navigate('Hub')}>
        <Text style={styles.backBtnText}>← Înapoi la Hub</Text>
      </TouchableOpacity>
    </View>
  );
}

function labelOf(name) {
  if (typeof name === 'string') return name;
  if (name && typeof name === 'object') return name.ro || Object.values(name)[0] || '—';
  return '—';
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  grid: { flex: 1 },
  title: { fontSize: 22, fontWeight: '800', color: colors.textPrimary, marginBottom: 16 },
  product: { flexDirection: 'row', backgroundColor: colors.surface, padding: 14, borderRadius: 12, marginBottom: 8, alignItems: 'center', borderWidth: 1, borderColor: colors.border },
  productCat: { fontSize: 10, color: colors.textTertiary, textTransform: 'uppercase' },
  productName: { fontSize: 15, fontWeight: '700', color: colors.textPrimary, marginTop: 2 },
  productPrice: { fontSize: 16, fontWeight: '800', color: colors.accent },
  cart: { backgroundColor: '#0A1F15', padding: 12, borderTopWidth: 1, borderTopColor: colors.border },
  cartHeader: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 8 },
  cartTitle: { fontSize: 13, fontWeight: '700', color: colors.textPrimary },
  cartClear: { fontSize: 12, color: colors.danger },
  cartEmpty: { fontSize: 12, color: colors.textSecondary, textAlign: 'center', paddingVertical: 8 },
  cartLine: { flexDirection: 'row', alignItems: 'center', paddingVertical: 6 },
  cartLineName: { fontSize: 13, color: colors.textPrimary, fontWeight: '600' },
  cartLinePrice: { fontSize: 11, color: colors.textSecondary },
  cartLineQty: { width: 24, textAlign: 'center', color: colors.textPrimary, fontWeight: '700', fontSize: 14 },
  qtyBtn: { width: 28, height: 28, borderRadius: 8, backgroundColor: colors.surface, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: colors.border },
  qtyBtnText: { color: colors.textPrimary, fontSize: 16, fontWeight: '800' },
  totalRow: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 8, paddingTop: 8, borderTopWidth: 1, borderTopColor: colors.border },
  totalLabel: { fontSize: 14, color: colors.textPrimary, fontWeight: '700' },
  totalValue: { fontSize: 18, color: colors.accent, fontWeight: '900' },
  paymentRow: { flexDirection: 'row', gap: 6, marginTop: 10 },
  payBtn: { flex: 1, padding: 10, backgroundColor: colors.surface, borderRadius: 10, alignItems: 'center', borderWidth: 1, borderColor: colors.border, marginRight: 4 },
  payBtnActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  payBtnText: { color: colors.textSecondary, fontSize: 11, fontWeight: '600' },
  payBtnTextActive: { color: '#0F2C20' },
  checkoutBtn: { marginTop: 10, padding: 14, backgroundColor: colors.primary, borderRadius: 12, alignItems: 'center' },
  checkoutBtnText: { color: '#0F2C20', fontWeight: '800', fontSize: 15 },
  backBtn: { padding: 12, alignItems: 'center' },
  backBtnText: { color: colors.muted, fontSize: 13 },
});
