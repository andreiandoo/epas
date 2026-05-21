import React, { useEffect, useRef, useState, useCallback } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  Modal,
} from 'react-native';
import { WebView } from 'react-native-webview';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors } from '../theme/colors';
import { issueSeatingEmbedToken } from '../api/seating';

/**
 * Seating map for POS sales — fast canvas-based widget rendered inside a
 * WebView. Replaces the previous react-native-svg implementation which
 * stalled for ~1 minute on layouts with 1000+ seats.
 *
 * Architecture:
 *   1. Mobile calls /seating/embed-token → backend returns a signed URL
 *      (HMAC, 30-min TTL).
 *   2. WebView loads that URL. The page (resources/views/seating/embed)
 *      paints a `<canvas>` with all geometry + statuses inlined as JSON
 *      so the first frame is interactive immediately.
 *   3. The page subscribes to Laravel Reverb on `event.{id}.seats` —
 *      seats sold on the website or by another POS scanner turn red
 *      live, without polling.
 *   4. User confirms inside the page → JS posts {type:'confirm', ...}
 *      to React Native via window.ReactNativeWebView.postMessage. We
 *      forward the payload to SalesScreen's onConfirm — identical shape
 *      to the legacy SVG screen, so SalesScreen needs no changes.
 *
 * Props (kept identical to the legacy SVG screen):
 *   visible        — modal visibility
 *   eventId        — event whose seating layout to render
 *   ticketTypeId   — optional preselection (filter selectable seats)
 *   onConfirm      — ({ cartItems, seatUids, selectedSeats }) => void
 *   onClose        — () => void
 */
export default function SeatingMapScreen({ visible, eventId, ticketTypeId, onConfirm, onClose }) {
  const insets = useSafeAreaInsets();
  const [embedUrl, setEmbedUrl] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [pageReady, setPageReady] = useState(false);
  const webRef = useRef(null);

  useEffect(() => {
    if (!visible || !eventId) {
      setEmbedUrl(null);
      setPageReady(false);
      setError(null);
      return;
    }
    let cancelled = false;
    (async () => {
      setLoading(true);
      setError(null);
      try {
        const resp = await issueSeatingEmbedToken({ eventId, ticketTypeId });
        if (cancelled) return;
        const url = resp?.data?.url || resp?.url;
        if (!url) throw new Error(resp?.message || 'Token invalid');
        setEmbedUrl(url);
      } catch (e) {
        if (!cancelled) {
          setError(e?.message || 'Nu s-a putut obține token-ul de seating');
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, [visible, eventId, ticketTypeId]);

  const handleMessage = useCallback((event) => {
    let msg;
    try {
      msg = JSON.parse(event.nativeEvent.data || '{}');
    } catch {
      return;
    }
    switch (msg.type) {
      case 'ready':
        setPageReady(true);
        break;
      case 'confirm':
        onConfirm?.({
          cartItems: msg.cartItems || [],
          seatUids: msg.seatUids || [],
          selectedSeats: msg.selectedSeats || [],
        });
        break;
      case 'cancel':
        onClose?.();
        break;
      default:
        // Ignore unknown messages — forward-compatible with future
        // embed-page events (e.g. analytics, error reporting).
        break;
    }
  }, [onConfirm, onClose]);

  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose}>
      {/* paddingBottom = insets.bottom so the WebView's footer (Anulează /
          Confirmă) sits above the phone's gesture nav. Without this the
          buttons land under the home indicator on Android 10+ and any
          tap is consumed by the system back gesture instead. */}
      <View style={[styles.container, { paddingBottom: insets.bottom, paddingTop: insets.top }]}>
        {/* Native header — keep the back button outside the WebView so the
            user can always escape even if the page is mid-load. */}
        <View style={styles.header}>
          <TouchableOpacity onPress={onClose} style={styles.headerBtn} activeOpacity={0.7}>
            <Text style={styles.headerBtnText}>‹ Înapoi</Text>
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Selectează locuri</Text>
          <View style={styles.headerBtn} />
        </View>

        {error ? (
          <View style={styles.center}>
            <Text style={styles.errorText}>{error}</Text>
            <TouchableOpacity style={styles.retry} onPress={onClose}>
              <Text style={styles.retryText}>Închide</Text>
            </TouchableOpacity>
          </View>
        ) : !embedUrl ? (
          <View style={styles.center}>
            <ActivityIndicator size="large" color={colors.purple} />
            <Text style={styles.loadingText}>Se pregătește harta...</Text>
          </View>
        ) : (
          <View style={{ flex: 1 }}>
            <WebView
              ref={webRef}
              source={{ uri: embedUrl }}
              onMessage={handleMessage}
              originWhitelist={['*']}
              javaScriptEnabled
              domStorageEnabled
              startInLoadingState
              setSupportMultipleWindows={false}
              androidLayerType="hardware"
              // Critical for canvas touches on Android — stop the WebView
              // from interpreting drags as native scrolls and swallowing
              // pointer events before the canvas sees them.
              scrollEnabled={false}
              nestedScrollEnabled={false}
              overScrollMode="never"
              scalesPageToFit={false}
              renderLoading={() => (
                <View style={styles.center}>
                  <ActivityIndicator size="large" color={colors.purple} />
                  <Text style={styles.loadingText}>Se încarcă harta...</Text>
                </View>
              )}
              onError={(e) => setError(e.nativeEvent?.description || 'Eroare WebView')}
            />
          </View>
        )}
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#0a0a14' },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 8,
    paddingVertical: 10,
    backgroundColor: '#0f0f1f',
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(255,255,255,0.08)',
  },
  headerBtn: { minWidth: 80, paddingHorizontal: 12, paddingVertical: 6 },
  headerBtnText: { color: colors.purple, fontSize: 15, fontWeight: '600' },
  headerTitle: { flex: 1, textAlign: 'center', color: colors.textPrimary, fontSize: 16, fontWeight: '700' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24, gap: 12 },
  loadingText: { color: colors.textSecondary, fontSize: 13 },
  errorText: { color: colors.red, fontSize: 14, textAlign: 'center' },
  retry: { backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.border, borderRadius: 10, paddingVertical: 12, paddingHorizontal: 24 },
  retryText: { color: colors.textPrimary, fontWeight: '600' },
});
