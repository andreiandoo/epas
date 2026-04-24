import React, { useCallback, useEffect, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
  TextInput,
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors } from '../theme/colors';
import { useAuth } from '../context/AuthContext';
import { getTicket, createNote, updateNote, deleteNote } from '../api/venueOwner';

function BackIcon({ size = 22, color }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color || colors.textPrimary} strokeWidth={2}>
      <Path d="M19 12H5M12 19l-7-7 7-7" />
    </Svg>
  );
}

function TrashIcon({ size = 16, color }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color || colors.red} strokeWidth={2}>
      <Path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M6 6l1 14a2 2 0 002 2h6a2 2 0 002-2l1-14" />
    </Svg>
  );
}

function EditIcon({ size = 16, color }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color || colors.textSecondary} strokeWidth={2}>
      <Path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
      <Path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
    </Svg>
  );
}

function formatDateTime(iso) {
  if (!iso) return '—';
  try {
    const d = new Date(iso);
    const date = d.toLocaleDateString('ro-RO', { day: '2-digit', month: 'short', year: 'numeric' });
    const time = d.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });
    return `${date} · ${time}`;
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

function InfoRow({ label, value }) {
  if (value === undefined || value === null || value === '') return null;
  return (
    <View style={styles.infoRow}>
      <Text style={styles.infoLabel}>{label}</Text>
      <Text style={styles.infoValue}>{value}</Text>
    </View>
  );
}

function NoteItem({ note, currentUserId, onEdit, onDelete }) {
  const isMine = String(note.author?.id) === String(currentUserId);
  const authorName = note.author?.name || 'Anonim';
  const targetBadge = note.target_type === 'order' ? 'Comandă' : note.target_type === 'customer' ? 'Client' : 'Bilet';

  return (
    <View style={styles.noteCard}>
      <View style={styles.noteHeader}>
        <View style={{ flex: 1 }}>
          <Text style={styles.noteAuthor}>{authorName}</Text>
          <Text style={styles.noteDate}>{formatDateTime(note.updated_at || note.created_at)} · {targetBadge}</Text>
        </View>
        {isMine && (
          <View style={styles.noteActions}>
            <TouchableOpacity onPress={() => onEdit(note)} style={styles.noteActionBtn}>
              <EditIcon />
            </TouchableOpacity>
            <TouchableOpacity onPress={() => onDelete(note)} style={styles.noteActionBtn}>
              <TrashIcon />
            </TouchableOpacity>
          </View>
        )}
      </View>
      <Text style={styles.noteText}>{note.note}</Text>
    </View>
  );
}

export default function VenueTicketDetailScreen({ route, navigation }) {
  const insets = useSafeAreaInsets();
  const { ticketId } = route.params || {};
  const { venueOwner } = useAuth();
  const currentUserId = venueOwner?.id;

  const [ticket, setTicket] = useState(null);
  const [notes, setNotes] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  const [newNote, setNewNote] = useState('');
  const [editingNote, setEditingNote] = useState(null); // { id, note }
  const [isSaving, setIsSaving] = useState(false);
  const [groupByCustomer, setGroupByCustomer] = useState(false);

  const loadTicket = useCallback(async () => {
    try {
      setIsLoading(true);
      setError('');
      const data = await getTicket(ticketId);
      if (data?.success) {
        const t = data.data?.ticket;
        setTicket(t || null);
        setNotes(t?.notes || []);
      } else {
        setError(data?.message || 'Nu am putut încărca biletul');
      }
    } catch (err) {
      setError(err?.message || 'Eroare de conexiune');
    } finally {
      setIsLoading(false);
    }
  }, [ticketId]);

  useEffect(() => {
    loadTicket();
  }, [loadTicket]);

  const customer = ticket?.customer || {};
  const canGroupByCustomer = !!customer.id; // need a customer identity to group

  const saveNote = async () => {
    const text = newNote.trim();
    if (!text) return;
    setIsSaving(true);
    try {
      if (editingNote) {
        const data = await updateNote(editingNote.id, text);
        if (data?.success) {
          await loadTicket();
          setEditingNote(null);
          setNewNote('');
        } else {
          Alert.alert('Eroare', data?.message || 'Nu am putut salva');
        }
      } else {
        const useGroup = groupByCustomer && canGroupByCustomer;
        const targetType = useGroup ? 'customer' : 'ticket';
        const targetId = useGroup ? customer.id : ticketId;
        const data = await createNote(targetType, targetId, text);
        if (data?.success) {
          await loadTicket();
          setNewNote('');
          setGroupByCustomer(false);
        } else {
          Alert.alert('Eroare', data?.message || 'Nu am putut salva');
        }
      }
    } catch (err) {
      Alert.alert('Eroare', err?.message || 'Eroare de conexiune');
    } finally {
      setIsSaving(false);
    }
  };

  const handleEdit = (note) => {
    setEditingNote(note);
    setNewNote(note.note);
  };

  const cancelEdit = () => {
    setEditingNote(null);
    setNewNote('');
  };

  const handleDelete = (note) => {
    Alert.alert(
      'Ștergi nota?',
      'Această acțiune nu poate fi anulată.',
      [
        { text: 'Anulează', style: 'cancel' },
        {
          text: 'Șterge', style: 'destructive',
          onPress: async () => {
            try {
              const data = await deleteNote(note.id);
              if (data?.success) {
                await loadTicket();
              } else {
                Alert.alert('Eroare', data?.message || 'Nu am putut șterge');
              }
            } catch (err) {
              Alert.alert('Eroare', err?.message || 'Eroare de conexiune');
            }
          },
        },
      ]
    );
  };

  if (isLoading) {
    return (
      <View style={[styles.container, styles.center, { paddingTop: insets.top }]}>
        <ActivityIndicator size="large" color={colors.purple} />
      </View>
    );
  }

  const seat = ticket?.seat;
  const event = ticket?.event;

  return (
    <KeyboardAvoidingView
      style={{ flex: 1, backgroundColor: colors.background, paddingTop: insets.top }}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <View style={styles.topBar}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
          <BackIcon />
        </TouchableOpacity>
        <Text style={styles.topTitle}>Detalii bilet</Text>
      </View>

      {error !== '' && (
        <View style={styles.errorBox}>
          <Text style={styles.errorText}>{error}</Text>
        </View>
      )}

      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40 }}>
        {/* Ticket status */}
        <View style={styles.card}>
          <View style={styles.statusRow}>
            <Text style={styles.ticketId}>#{ticket?.id}</Text>
            <Text style={[styles.statusBadge, { color: statusColor(ticket?.status) }]}>
              {statusLabel(ticket?.status)}
            </Text>
          </View>
          <InfoRow label="Cod" value={ticket?.code} />
          <InfoRow label="Tip bilet" value={ticket?.ticket_type?.name} />
          <InfoRow label="Preț" value={ticket?.price ? `${ticket.price} RON` : null} />
          {seat && (
            <>
              <InfoRow label="Secțiune" value={seat.section_name} />
              <InfoRow label="Rând" value={seat.row_label} />
              <InfoRow label="Loc" value={seat.seat_number} />
            </>
          )}
          <InfoRow label="Check-in" value={ticket?.checked_in_at ? formatDateTime(ticket.checked_in_at) : 'Nu s-a făcut'} />
        </View>

        {/* Customer */}
        <View style={styles.card}>
          <Text style={styles.cardLabel}>Client</Text>
          <Text style={styles.customerName}>{customer.full_name || '—'}</Text>
          <InfoRow label="Nume" value={customer.first_name} />
          <InfoRow label="Prenume" value={customer.last_name} />
          <InfoRow label="Telefon" value={customer.phone} />
        </View>

        {/* Order */}
        {ticket?.order && (
          <View style={styles.card}>
            <Text style={styles.cardLabel}>Comandă</Text>
            <InfoRow label="Număr" value={ticket.order.order_number} />
            <InfoRow label="Data" value={formatDateTime(ticket.order.placed_at)} />
            <InfoRow label="Total" value={ticket.order.total !== null ? `${ticket.order.total} ${ticket.order.currency || 'RON'}` : null} />
            <InfoRow label="Status comandă" value={ticket.order.status} />
          </View>
        )}

        {/* Event */}
        {event && (
          <View style={styles.card}>
            <Text style={styles.cardLabel}>Eveniment</Text>
            <Text style={styles.eventTitle}>{event.title}</Text>
            {event.venue?.name && (
              <Text style={styles.eventMeta}>{event.venue.name}{event.venue.city ? ` · ${event.venue.city}` : ''}</Text>
            )}
          </View>
        )}

        {/* Notes */}
        <View style={styles.card}>
          <Text style={styles.cardLabel}>Mențiuni ({notes.length})</Text>
          {notes.length === 0 ? (
            <Text style={styles.emptyNotes}>Nu sunt mențiuni pentru acest bilet.</Text>
          ) : (
            notes.map(n => (
              <NoteItem
                key={n.id}
                note={n}
                currentUserId={currentUserId}
                onEdit={handleEdit}
                onDelete={handleDelete}
              />
            ))
          )}

          <View style={styles.addNote}>
            <TextInput
              style={styles.noteInput}
              placeholder={editingNote ? 'Editează mențiunea...' : 'Adaugă o mențiune nouă...'}
              placeholderTextColor={colors.textTertiary}
              value={newNote}
              onChangeText={setNewNote}
              multiline
              maxLength={4000}
            />

            {!editingNote && canGroupByCustomer && (
              <TouchableOpacity
                style={styles.groupToggle}
                onPress={() => setGroupByCustomer(v => !v)}
                activeOpacity={0.7}
              >
                <View style={[styles.checkbox, groupByCustomer && styles.checkboxChecked]}>
                  {groupByCustomer && (
                    <Svg width={12} height={12} viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth={3}>
                      <Path d="M20 6L9 17l-5-5" strokeLinecap="round" strokeLinejoin="round" />
                    </Svg>
                  )}
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={styles.groupToggleLabel}>
                    Grupează biletele
                    {customer.tickets_at_event_count > 0
                      ? ` (${customer.tickets_at_event_count} ${customer.tickets_at_event_count === 1 ? 'bilet' : 'bilete'} la acest eveniment)`
                      : ''}
                  </Text>
                  <Text style={styles.groupToggleHint}>
                    Aplică această mențiune la toate biletele clientului
                  </Text>
                </View>
              </TouchableOpacity>
            )}

            <View style={styles.noteButtonsRow}>
              {editingNote && (
                <TouchableOpacity style={styles.cancelBtn} onPress={cancelEdit}>
                  <Text style={styles.cancelBtnText}>Renunță</Text>
                </TouchableOpacity>
              )}
              <TouchableOpacity
                style={[styles.saveBtn, (!newNote.trim() || isSaving) && styles.saveBtnDisabled]}
                onPress={saveNote}
                disabled={!newNote.trim() || isSaving}
              >
                {isSaving ? (
                  <ActivityIndicator color="#fff" size="small" />
                ) : (
                  <Text style={styles.saveBtnText}>{editingNote ? 'Salvează' : 'Adaugă'}</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  center: { justifyContent: 'center', alignItems: 'center' },
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
  card: {
    backgroundColor: colors.surface,
    borderRadius: 12,
    padding: 14,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: colors.border,
  },
  cardLabel: { color: colors.textTertiary, fontSize: 11, fontWeight: '700', textTransform: 'uppercase', marginBottom: 8 },
  statusRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 },
  ticketId: { color: colors.textPrimary, fontSize: 18, fontWeight: '700' },
  statusBadge: { fontSize: 12, fontWeight: '700', textTransform: 'uppercase' },
  infoRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 4 },
  infoLabel: { color: colors.textSecondary, fontSize: 13 },
  infoValue: { color: colors.textPrimary, fontSize: 13, fontWeight: '500', flex: 1, textAlign: 'right', marginLeft: 10 },
  customerName: { color: colors.textPrimary, fontSize: 16, fontWeight: '700', marginBottom: 8 },
  eventTitle: { color: colors.textPrimary, fontSize: 15, fontWeight: '600' },
  eventMeta: { color: colors.textSecondary, fontSize: 12, marginTop: 3 },
  emptyNotes: { color: colors.textTertiary, fontSize: 13, fontStyle: 'italic', marginBottom: 12 },
  noteCard: {
    backgroundColor: 'rgba(255,255,255,0.02)',
    borderRadius: 10,
    padding: 10,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.04)',
  },
  noteHeader: { flexDirection: 'row', alignItems: 'flex-start', marginBottom: 6 },
  noteAuthor: { color: colors.textPrimary, fontSize: 13, fontWeight: '600' },
  noteDate: { color: colors.textTertiary, fontSize: 11, marginTop: 1 },
  noteActions: { flexDirection: 'row', gap: 4 },
  noteActionBtn: { padding: 6 },
  noteText: { color: colors.textSecondary, fontSize: 13, lineHeight: 18 },
  addNote: { marginTop: 8 },
  noteInput: {
    backgroundColor: 'rgba(255,255,255,0.04)',
    borderRadius: 10,
    padding: 12,
    color: colors.textPrimary,
    fontSize: 14,
    minHeight: 70,
    textAlignVertical: 'top',
    borderWidth: 1,
    borderColor: colors.border,
  },
  groupToggle: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
    paddingVertical: 10,
    marginTop: 4,
  },
  checkbox: {
    width: 20,
    height: 20,
    borderRadius: 5,
    borderWidth: 1.5,
    borderColor: colors.border,
    backgroundColor: 'rgba(255,255,255,0.02)',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 1,
  },
  checkboxChecked: {
    backgroundColor: colors.purple,
    borderColor: colors.purple,
  },
  groupToggleLabel: { color: colors.textPrimary, fontSize: 13, fontWeight: '600' },
  groupToggleHint: { color: colors.textTertiary, fontSize: 11, marginTop: 2 },
  noteButtonsRow: { flexDirection: 'row', justifyContent: 'flex-end', gap: 8, marginTop: 8 },
  cancelBtn: { paddingHorizontal: 14, paddingVertical: 10, borderRadius: 10 },
  cancelBtnText: { color: colors.textSecondary, fontSize: 13, fontWeight: '600' },
  saveBtn: { backgroundColor: colors.purple, paddingHorizontal: 20, paddingVertical: 10, borderRadius: 10, minWidth: 100, alignItems: 'center' },
  saveBtnDisabled: { opacity: 0.5 },
  saveBtnText: { color: '#fff', fontSize: 13, fontWeight: '700' },
  errorBox: { marginHorizontal: 16, marginTop: 8, padding: 10, backgroundColor: colors.redBg, borderRadius: 10, borderWidth: 1, borderColor: colors.redBorder },
  errorText: { color: colors.red, fontSize: 13 },
});
