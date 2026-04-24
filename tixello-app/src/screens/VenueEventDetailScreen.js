import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  FlatList,
  TextInput,
  ActivityIndicator,
  RefreshControl,
  Modal,
  Alert,
  Linking,
  Platform,
  KeyboardAvoidingView,
} from 'react-native';
import Svg, { Path, Circle, Rect } from 'react-native-svg';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors } from '../theme/colors';
import { getEvent, listAttendees, exportAttendees } from '../api/venueOwner';

function BackIcon({ size = 22, color }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color || colors.textPrimary} strokeWidth={2}>
      <Path d="M19 12H5M12 19l-7-7 7-7" />
    </Svg>
  );
}

function SearchIcon({ size = 16, color }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color || colors.textSecondary} strokeWidth={2}>
      <Circle cx="11" cy="11" r="8" />
      <Path d="M21 21l-4.35-4.35" />
    </Svg>
  );
}

function ChevronIcon({ size = 16, color }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color || colors.textTertiary} strokeWidth={2}>
      <Path d="M9 18l6-6-6-6" />
    </Svg>
  );
}

function formatDate(iso) {
  if (!iso) return '—';
  try {
    const d = new Date(iso);
    return d.toLocaleDateString('ro-RO', { day: '2-digit', month: 'short', year: 'numeric' });
  } catch { return iso; }
}

function statusColor(status) {
  switch (status) {
    case 'used': return colors.green;
    case 'valid': return colors.cyan;
    case 'cancelled':
    case 'refunded':
      return colors.red;
    case 'pending': return colors.amber;
    default: return colors.textSecondary;
  }
}

function statusLabel(status) {
  switch (status) {
    case 'used': return 'Utilizat';
    case 'valid': return 'Valid';
    case 'cancelled': return 'Anulat';
    case 'refunded': return 'Rambursat';
    case 'pending': return 'În așteptare';
    default: return status || '—';
  }
}

function NoteIcon({ size = 14, color }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color || colors.amber} strokeWidth={2}>
      <Path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" strokeLinecap="round" strokeLinejoin="round" />
    </Svg>
  );
}

function AttendeeRow({ ticket, onPress }) {
  const customer = ticket.customer || {};
  const customerName = customer.full_name || '—';
  const seat = ticket.seat;
  const seatText = seat
    ? [seat.section_name, seat.row_label && `rând ${seat.row_label}`, seat.seat_number && `loc ${seat.seat_number}`]
        .filter(Boolean).join(' · ')
    : null;

  return (
    <TouchableOpacity style={styles.row} onPress={onPress} activeOpacity={0.7}>
      <View style={{ flex: 1 }}>
        <View style={styles.rowHeader}>
          <View style={styles.rowNameWrap}>
            {ticket.has_notes && <NoteIcon />}
            <Text style={styles.rowName} numberOfLines={1}>{customerName}</Text>
          </View>
          <Text style={[styles.rowStatus, { color: statusColor(ticket.status) }]}>
            {statusLabel(ticket.status)}
          </Text>
        </View>
        <Text style={styles.rowMeta} numberOfLines={1}>
          {ticket.ticket_type?.name || 'Bilet'} · #{ticket.id}
          {ticket.order?.order_number ? ` · ${ticket.order.order_number}` : ''}
        </Text>
        {seatText && <Text style={styles.rowSeat}>{seatText}</Text>}
        <Text style={styles.rowDate}>{formatDate(ticket.order?.placed_at)}</Text>
      </View>
      <ChevronIcon />
    </TouchableOpacity>
  );
}

export default function VenueEventDetailScreen({ route, navigation }) {
  const insets = useSafeAreaInsets();
  const { eventId, title } = route.params || {};

  const [event, setEvent] = useState(null);
  const [tickets, setTickets] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [error, setError] = useState('');

  const [showExport, setShowExport] = useState(false);
  const [exportMode, setExportMode] = useState(null); // 'download' | 'email' | null (chooser)
  const [exportEmail, setExportEmail] = useState('');
  const [isExporting, setIsExporting] = useState(false);
  const [exportMessage, setExportMessage] = useState('');

  const searchDebounce = useRef(null);

  const fetchEvent = useCallback(async () => {
    try {
      const data = await getEvent(eventId);
      if (data?.success) setEvent(data.data?.event || null);
    } catch (e) {}
  }, [eventId]);

  const fetchAttendees = useCallback(async ({ query = search, nextPage = 1, append = false } = {}) => {
    try {
      if (!append) setIsLoading(true);
      setError('');
      const data = await listAttendees(eventId, { search: query, page: nextPage, perPage: 25 });
      if (data?.success) {
        const items = data.data || [];
        setTickets(prev => append ? [...prev, ...items] : items);
        setPage(data.meta?.current_page || 1);
        setLastPage(data.meta?.last_page || 1);
        setTotal(data.meta?.total || 0);
      } else {
        setError(data?.message || 'Nu am putut încărca lista');
      }
    } catch (err) {
      setError(err?.message || 'Eroare de conexiune');
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
      setIsRefreshing(false);
    }
  }, [eventId, search]);

  useEffect(() => {
    fetchEvent();
    fetchAttendees({ query: '', nextPage: 1 });
  }, [eventId]);

  // Debounced search
  useEffect(() => {
    if (searchDebounce.current) clearTimeout(searchDebounce.current);
    searchDebounce.current = setTimeout(() => {
      fetchAttendees({ query: search, nextPage: 1 });
    }, 350);
    return () => clearTimeout(searchDebounce.current);
  }, [search]);

  const onEndReached = () => {
    if (isLoadingMore || page >= lastPage) return;
    setIsLoadingMore(true);
    fetchAttendees({ query: search, nextPage: page + 1, append: true });
  };

  const openExport = () => {
    setExportMode(null);
    setExportEmail('');
    setExportMessage('');
    setShowExport(true);
  };

  const closeExport = () => {
    if (isExporting) return;
    setShowExport(false);
    setExportMode(null);
    setExportEmail('');
    setExportMessage('');
  };

  const handleExportDownload = async () => {
    if (isExporting) return;
    setIsExporting(true);
    setExportMessage('');
    try {
      const data = await exportAttendees(eventId, { destination: 'download' });
      if (data?.success && data.data?.download_url) {
        const url = data.data.download_url;
        setShowExport(false);
        // Open the signed URL in the system browser — browser downloads the file.
        try {
          await Linking.openURL(url);
        } catch (e) {
          Alert.alert('Eroare', 'Nu am putut deschide linkul de descărcare.');
        }
      } else {
        setExportMessage(data?.message || 'Nu am putut genera exportul');
      }
    } catch (err) {
      setExportMessage(err?.message || 'Eroare de conexiune');
    } finally {
      setIsExporting(false);
    }
  };

  const handleExportEmail = async () => {
    const email = exportEmail.trim();
    if (!email) {
      setExportMessage('Introdu o adresă de email.');
      return;
    }
    if (isExporting) return;
    setIsExporting(true);
    setExportMessage('');
    try {
      const data = await exportAttendees(eventId, { destination: 'email', email });
      if (data?.success) {
        setExportMessage(data.message || `Exportul a fost trimis la ${email}`);
        setTimeout(() => { closeExport(); }, 1800);
      } else {
        setExportMessage(data?.message || 'Nu am putut trimite exportul');
      }
    } catch (err) {
      setExportMessage(err?.message || 'Eroare de conexiune');
    } finally {
      setIsExporting(false);
    }
  };

  const stats = event?.stats || {};

  return (
    <View style={[styles.container, { paddingTop: insets.top }]}>
      <View style={styles.topBar}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
          <BackIcon />
        </TouchableOpacity>
        <Text style={styles.topTitle} numberOfLines={1}>{event?.title || title || 'Eveniment'}</Text>
      </View>

      {event && (
        <View style={styles.summary}>
          <Text style={styles.summaryDate}>
            {formatDate(event.start_date)}
            {event.start_time ? ` · ${event.start_time.slice(0, 5)}` : ''}
          </Text>
          {event.marketplace_organizer?.name && (
            <Text style={styles.summaryOrganizer}>Organizator: {event.marketplace_organizer.name}</Text>
          )}
          <View style={styles.summaryStats}>
            <View style={styles.statBox}>
              <Text style={styles.statValue}>
                {stats.stock_total > 0 ? `${stats.tickets_sold || 0}/${stats.stock_total}` : (stats.tickets_sold || 0)}
              </Text>
              <Text style={styles.statLabel}>Vândute</Text>
            </View>
            <View style={styles.statBox}>
              <Text style={styles.statValue}>{stats.checked_in_count || 0}</Text>
              <Text style={styles.statLabel}>Check-in</Text>
            </View>
            <TouchableOpacity style={styles.exportBox} onPress={openExport} activeOpacity={0.7}>
              <Svg width={20} height={20} viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth={2}>
                <Path d="M12 3v13m0 0l-4-4m4 4l4-4M4 21h16" strokeLinecap="round" strokeLinejoin="round" />
              </Svg>
              <Text style={styles.exportLabel}>Export CSV</Text>
            </TouchableOpacity>
          </View>
        </View>
      )}

      <View style={styles.searchWrap}>
        <SearchIcon />
        <TextInput
          style={styles.searchInput}
          placeholder="Caută după nume, telefon sau nr. comandă"
          placeholderTextColor={colors.textTertiary}
          value={search}
          onChangeText={setSearch}
          autoCorrect={false}
          autoCapitalize="none"
        />
      </View>

      {error !== '' && (
        <View style={styles.errorBox}>
          <Text style={styles.errorText}>{error}</Text>
        </View>
      )}

      {isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={colors.purple} />
        </View>
      ) : (
        <FlatList
          data={tickets}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => (
            <AttendeeRow
              ticket={item}
              onPress={() => navigation.navigate('VenueTicketDetail', { ticketId: item.id })}
            />
          )}
          contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 40 }}
          onEndReached={onEndReached}
          onEndReachedThreshold={0.5}
          refreshControl={
            <RefreshControl
              refreshing={isRefreshing}
              onRefresh={() => {
                setIsRefreshing(true);
                fetchEvent();
                fetchAttendees({ query: search, nextPage: 1 });
              }}
              tintColor={colors.textPrimary}
            />
          }
          ListFooterComponent={isLoadingMore ? (
            <ActivityIndicator color={colors.textSecondary} style={{ marginVertical: 16 }} />
          ) : null}
          ListEmptyComponent={
            <View style={styles.empty}>
              <Text style={styles.emptyText}>
                {search ? 'Niciun rezultat pentru căutarea ta.' : 'Nu sunt bilete pentru acest eveniment.'}
              </Text>
            </View>
          }
        />
      )}

      {/* Export modal — chooser + email input */}
      <Modal visible={showExport} transparent animationType="fade" statusBarTranslucent onRequestClose={closeExport}>
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
          style={styles.modalBackdrop}
        >
          <View style={styles.modalCard}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Export bilete</Text>
              <TouchableOpacity onPress={closeExport} style={styles.modalClose} disabled={isExporting}>
                <Svg width={18} height={18} viewBox="0 0 24 24" fill="none" stroke={colors.textPrimary} strokeWidth={2.5}>
                  <Path d="M18 6L6 18M6 6l12 12" strokeLinecap="round" strokeLinejoin="round" />
                </Svg>
              </TouchableOpacity>
            </View>

            {exportMode === null && (
              <>
                <Text style={styles.modalHint}>
                  CSV cu biletele valide: id comandă, id bilet, tip bilet, nume + telefon client, data comenzii, mențiuni.
                </Text>
                <TouchableOpacity
                  style={styles.modalOption}
                  onPress={() => setExportMode('download')}
                  disabled={isExporting}
                  activeOpacity={0.7}
                >
                  <Svg width={22} height={22} viewBox="0 0 24 24" fill="none" stroke={colors.purple} strokeWidth={2}>
                    <Path d="M12 3v13m0 0l-4-4m4 4l4-4M4 21h16" strokeLinecap="round" strokeLinejoin="round" />
                  </Svg>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.modalOptionTitle}>Descarcă în telefon</Text>
                    <Text style={styles.modalOptionHint}>Se deschide în browser și se descarcă fișierul</Text>
                  </View>
                </TouchableOpacity>
                <TouchableOpacity
                  style={styles.modalOption}
                  onPress={() => setExportMode('email')}
                  disabled={isExporting}
                  activeOpacity={0.7}
                >
                  <Svg width={22} height={22} viewBox="0 0 24 24" fill="none" stroke={colors.purple} strokeWidth={2}>
                    <Path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                    <Path d="M22 6l-10 7L2 6" />
                  </Svg>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.modalOptionTitle}>Trimite pe email</Text>
                    <Text style={styles.modalOptionHint}>Primești CSV ca atașament</Text>
                  </View>
                </TouchableOpacity>
              </>
            )}

            {exportMode === 'download' && (
              <>
                <Text style={styles.modalHint}>
                  Se generează un link de descărcare valid 30 de minute. După ce apeși Descarcă, se deschide în browser.
                </Text>
                {exportMessage !== '' && (
                  <Text style={styles.errorLine}>{exportMessage}</Text>
                )}
                <View style={styles.modalRow}>
                  <TouchableOpacity onPress={() => setExportMode(null)} style={styles.cancelBtn} disabled={isExporting}>
                    <Text style={styles.cancelBtnText}>Înapoi</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.saveBtn, isExporting && styles.saveBtnDisabled]}
                    onPress={handleExportDownload}
                    disabled={isExporting}
                  >
                    {isExporting ? (
                      <ActivityIndicator color="#fff" size="small" />
                    ) : (
                      <Text style={styles.saveBtnText}>Descarcă</Text>
                    )}
                  </TouchableOpacity>
                </View>
              </>
            )}

            {exportMode === 'email' && (
              <>
                <Text style={styles.modalHint}>
                  Introdu adresa la care vrei să primești exportul. Fișierul ajunge ca atașament .csv.
                </Text>
                <TextInput
                  style={styles.emailInput}
                  placeholder="exemplu@domeniu.ro"
                  placeholderTextColor={colors.textTertiary}
                  value={exportEmail}
                  onChangeText={setExportEmail}
                  keyboardType="email-address"
                  autoCapitalize="none"
                  autoCorrect={false}
                  editable={!isExporting}
                />
                {exportMessage !== '' && (
                  <Text style={styles.errorLine}>{exportMessage}</Text>
                )}
                <View style={styles.modalRow}>
                  <TouchableOpacity onPress={() => setExportMode(null)} style={styles.cancelBtn} disabled={isExporting}>
                    <Text style={styles.cancelBtnText}>Înapoi</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.saveBtn, (isExporting || !exportEmail.trim()) && styles.saveBtnDisabled]}
                    onPress={handleExportEmail}
                    disabled={isExporting || !exportEmail.trim()}
                  >
                    {isExporting ? (
                      <ActivityIndicator color="#fff" size="small" />
                    ) : (
                      <Text style={styles.saveBtnText}>Trimite</Text>
                    )}
                  </TouchableOpacity>
                </View>
              </>
            )}
          </View>
        </KeyboardAvoidingView>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  topBar: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    gap: 10,
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(255,255,255,0.05)',
  },
  backBtn: { padding: 4 },
  topTitle: { flex: 1, color: colors.textPrimary, fontSize: 17, fontWeight: '700' },
  summary: { padding: 16, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
  summaryDate: { color: colors.textSecondary, fontSize: 13 },
  summaryOrganizer: { color: colors.textTertiary, fontSize: 12, marginTop: 2 },
  summaryStats: { flexDirection: 'row', gap: 10, marginTop: 12 },
  statBox: { flex: 1, backgroundColor: colors.surface, padding: 12, borderRadius: 10, borderWidth: 1, borderColor: colors.border },
  statValue: { color: colors.textPrimary, fontSize: 18, fontWeight: '700' },
  statLabel: { color: colors.textSecondary, fontSize: 11, marginTop: 2 },
  searchWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginHorizontal: 16,
    marginVertical: 12,
    paddingHorizontal: 12,
    backgroundColor: colors.surface,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
  },
  searchInput: { flex: 1, color: colors.textPrimary, paddingVertical: 10, fontSize: 14 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 14,
    backgroundColor: colors.surface,
    borderRadius: 12,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: colors.border,
  },
  rowHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  rowNameWrap: { flexDirection: 'row', alignItems: 'center', gap: 6, flex: 1, marginRight: 8 },
  rowName: { color: colors.textPrimary, fontSize: 15, fontWeight: '600', flexShrink: 1 },
  rowStatus: { fontSize: 11, fontWeight: '700', textTransform: 'uppercase' },
  rowMeta: { color: colors.textSecondary, fontSize: 12, marginTop: 3 },
  rowSeat: { color: colors.textTertiary, fontSize: 12, marginTop: 2 },
  rowDate: { color: colors.textQuaternary, fontSize: 11, marginTop: 4 },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  empty: { alignItems: 'center', paddingVertical: 40 },
  emptyText: { color: colors.textTertiary, fontSize: 14, textAlign: 'center', paddingHorizontal: 24 },
  errorBox: {
    marginHorizontal: 16,
    marginBottom: 8,
    padding: 10,
    backgroundColor: colors.redBg,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.redBorder,
  },
  errorText: { color: colors.red, fontSize: 13 },

  // Export
  exportBox: {
    flex: 1,
    backgroundColor: colors.purple,
    padding: 12,
    borderRadius: 10,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  exportLabel: { color: '#fff', fontWeight: '700', fontSize: 13 },
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.7)',
    justifyContent: 'center',
    padding: 20,
  },
  modalCard: {
    backgroundColor: colors.background,
    borderRadius: 16,
    padding: 18,
    borderWidth: 1,
    borderColor: colors.border,
  },
  modalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
  modalTitle: { color: colors.textPrimary, fontSize: 17, fontWeight: '700' },
  modalClose: { padding: 6, borderRadius: 16, backgroundColor: 'rgba(255,255,255,0.05)' },
  modalHint: { color: colors.textSecondary, fontSize: 13, lineHeight: 18, marginBottom: 14 },
  modalOption: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    padding: 14,
    borderRadius: 12,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    marginBottom: 10,
  },
  modalOptionTitle: { color: colors.textPrimary, fontSize: 14, fontWeight: '600' },
  modalOptionHint: { color: colors.textTertiary, fontSize: 12, marginTop: 2 },
  emailInput: {
    backgroundColor: 'rgba(255,255,255,0.04)',
    borderRadius: 10,
    padding: 12,
    color: colors.textPrimary,
    fontSize: 15,
    borderWidth: 1,
    borderColor: colors.border,
    marginBottom: 10,
  },
  errorLine: { color: colors.red, fontSize: 13, marginBottom: 8 },
  modalRow: { flexDirection: 'row', justifyContent: 'flex-end', gap: 10, marginTop: 6 },
  cancelBtn: { paddingHorizontal: 16, paddingVertical: 10, borderRadius: 10 },
  cancelBtnText: { color: colors.textSecondary, fontSize: 14, fontWeight: '600' },
  saveBtn: { backgroundColor: colors.purple, paddingHorizontal: 20, paddingVertical: 10, borderRadius: 10, alignItems: 'center', minWidth: 110 },
  saveBtnDisabled: { opacity: 0.5 },
  saveBtnText: { color: '#fff', fontSize: 14, fontWeight: '700' },
});
