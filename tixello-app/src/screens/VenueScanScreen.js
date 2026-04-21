import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Modal,
  TextInput,
  ScrollView,
  ActivityIndicator,
  Vibration,
  Platform,
  Dimensions,
  KeyboardAvoidingView,
} from 'react-native';
import Svg, { Path, Circle } from 'react-native-svg';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors } from '../theme/colors';
import { useAuth } from '../context/AuthContext';
import { scanLookup, createNote } from '../api/venueOwner';

const { width: SCREEN_WIDTH } = Dimensions.get('window');
const SCANNER_SIZE = Math.min(300, SCREEN_WIDTH - 60);
const CORNER_SIZE = 36;
const CORNER_THICKNESS = 4;

function XIcon({ size = 20, color }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color || colors.textPrimary} strokeWidth={2.5}>
      <Path d="M18 6L6 18M6 6l12 12" strokeLinecap="round" strokeLinejoin="round" />
    </Svg>
  );
}

function KeyboardIcon({ size = 22, color }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color || colors.textPrimary} strokeWidth={2}>
      <Path d="M2 6h20v12H2zM6 10h.01M10 10h.01M14 10h.01M18 10h.01M6 14h.01M18 14h.01M10 14h4" strokeLinecap="round" strokeLinejoin="round" />
    </Svg>
  );
}

function ScanCorner({ position, color }) {
  const style = {
    topLeft: { top: 0, left: 0, borderTopWidth: CORNER_THICKNESS, borderLeftWidth: CORNER_THICKNESS, borderTopLeftRadius: 10 },
    topRight: { top: 0, right: 0, borderTopWidth: CORNER_THICKNESS, borderRightWidth: CORNER_THICKNESS, borderTopRightRadius: 10 },
    bottomLeft: { bottom: 0, left: 0, borderBottomWidth: CORNER_THICKNESS, borderLeftWidth: CORNER_THICKNESS, borderBottomLeftRadius: 10 },
    bottomRight: { bottom: 0, right: 0, borderBottomWidth: CORNER_THICKNESS, borderRightWidth: CORNER_THICKNESS, borderBottomRightRadius: 10 },
  }[position];
  return <View style={[styles.corner, style, { borderColor: color }]} />;
}

function extractCode(input) {
  if (!input) return null;
  const trimmed = input.trim();
  const urlMatch = trimmed.match(/\/t\/([A-Za-z0-9_-]+)/);
  if (urlMatch) return urlMatch[1].toUpperCase();
  const verifyMatch = trimmed.match(/\/verify\/([A-Za-z0-9_-]+)/);
  if (verifyMatch) return verifyMatch[1].toUpperCase();
  return trimmed.toUpperCase();
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

function statusColor(s) {
  switch (s) {
    case 'used': return colors.green;
    case 'valid': return colors.cyan;
    case 'cancelled':
    case 'refunded':
      return colors.red;
    case 'pending': return colors.amber;
    default: return colors.textSecondary;
  }
}

function statusLabel(s) {
  switch (s) {
    case 'used': return 'UTILIZAT';
    case 'valid': return 'VALID';
    case 'cancelled': return 'ANULAT';
    case 'refunded': return 'RAMBURSAT';
    case 'pending': return 'ÎN AȘTEPTARE';
    default: return (s || '').toUpperCase() || '—';
  }
}

export default function VenueScanScreen() {
  const insets = useSafeAreaInsets();
  const { venueOwner } = useAuth();
  const [permission, requestPermission] = useCameraPermissions();
  const [isScanning, setIsScanning] = useState(false);
  const [isLookingUp, setIsLookingUp] = useState(false);
  const [result, setResult] = useState(null); // { ticket } or { error }
  const [showManual, setShowManual] = useState(false);
  const [manualCode, setManualCode] = useState('');
  const [newNote, setNewNote] = useState('');
  const [isSavingNote, setIsSavingNote] = useState(false);
  const scanLock = useRef(false);

  useEffect(() => {
    if (permission && !permission.granted) {
      requestPermission();
    }
  }, [permission]);

  const handleCodeRead = useCallback(async (raw) => {
    if (scanLock.current || isLookingUp) return;
    scanLock.current = true;
    setIsScanning(false);
    Vibration.vibrate(50);

    const code = extractCode(raw);
    if (!code) {
      scanLock.current = false;
      return;
    }

    setIsLookingUp(true);
    try {
      const data = await scanLookup(code);
      if (data?.success) {
        setResult({ ticket: data.data?.ticket });
      } else {
        setResult({ error: data?.message || 'Bilet negăsit' });
      }
    } catch (err) {
      setResult({ error: err?.message || 'Eroare de conexiune' });
    } finally {
      setIsLookingUp(false);
    }
  }, [isLookingUp]);

  const closeResult = () => {
    setResult(null);
    setNewNote('');
    scanLock.current = false;
    setIsScanning(true);
  };

  const handleManual = async () => {
    if (!manualCode.trim()) return;
    setShowManual(false);
    setIsLookingUp(true);
    try {
      const data = await scanLookup(extractCode(manualCode));
      if (data?.success) setResult({ ticket: data.data?.ticket });
      else setResult({ error: data?.message || 'Bilet negăsit' });
    } catch (err) {
      setResult({ error: err?.message || 'Eroare de conexiune' });
    } finally {
      setIsLookingUp(false);
      setManualCode('');
    }
  };

  const addNoteForResult = async () => {
    const text = newNote.trim();
    if (!text || !result?.ticket?.id) return;
    setIsSavingNote(true);
    try {
      const data = await createNote('ticket', result.ticket.id, text);
      if (data?.success) {
        const notes = [data.data?.note, ...(result.ticket.notes || [])];
        setResult({ ticket: { ...result.ticket, notes } });
        setNewNote('');
      }
    } catch (err) {
    } finally {
      setIsSavingNote(false);
    }
  };

  if (!permission) {
    return <View style={[styles.container, { paddingTop: insets.top }]} />;
  }

  if (!permission.granted) {
    return (
      <View style={[styles.container, styles.center, { paddingTop: insets.top }]}>
        <Text style={styles.permText}>Aplicația are nevoie de acces la cameră pentru a scana biletele.</Text>
        <TouchableOpacity style={styles.permBtn} onPress={requestPermission}>
          <Text style={styles.permBtnText}>Activează camera</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View style={[styles.container, { paddingTop: insets.top }]}>
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Scanare info</Text>
        <Text style={styles.headerSubtitle}>Informații bilet (nu face check-in)</Text>
      </View>

      <View style={styles.scannerWrap}>
        {isScanning ? (
          <CameraView
            style={StyleSheet.absoluteFill}
            facing="back"
            barcodeScannerSettings={{
              barcodeTypes: ['qr', 'ean13', 'ean8', 'code128', 'code39', 'code93', 'upc_a', 'upc_e'],
            }}
            onBarcodeScanned={isScanning ? ({ data }) => handleCodeRead(data) : undefined}
          />
        ) : (
          <View style={[StyleSheet.absoluteFill, styles.scannerIdle]}>
            <Text style={styles.scannerIdleText}>
              {isLookingUp ? 'Caut biletul…' : 'Apasă "Pornește scanarea" pentru a începe'}
            </Text>
          </View>
        )}

        <View style={styles.scannerFrame}>
          <ScanCorner position="topLeft" color={isScanning ? colors.purple : 'rgba(255,255,255,0.3)'} />
          <ScanCorner position="topRight" color={isScanning ? colors.purple : 'rgba(255,255,255,0.3)'} />
          <ScanCorner position="bottomLeft" color={isScanning ? colors.purple : 'rgba(255,255,255,0.3)'} />
          <ScanCorner position="bottomRight" color={isScanning ? colors.purple : 'rgba(255,255,255,0.3)'} />
        </View>
      </View>

      <View style={styles.actions}>
        <TouchableOpacity
          style={[styles.primaryBtn, isScanning && styles.primaryBtnActive]}
          onPress={() => {
            scanLock.current = false;
            setIsScanning(s => !s);
          }}
        >
          <Text style={styles.primaryBtnText}>
            {isScanning ? 'Oprește scanarea' : 'Pornește scanarea'}
          </Text>
        </TouchableOpacity>

        <TouchableOpacity style={styles.secondaryBtn} onPress={() => setShowManual(true)}>
          <KeyboardIcon />
          <Text style={styles.secondaryBtnText}>Introdu cod manual</Text>
        </TouchableOpacity>
      </View>

      {isLookingUp && !result && (
        <View style={styles.overlay}>
          <ActivityIndicator size="large" color={colors.purple} />
        </View>
      )}

      {/* Manual entry modal */}
      <Modal visible={showManual} transparent animationType="fade" statusBarTranslucent>
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
          style={styles.modalBackdrop}
        >
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>Introdu codul biletului</Text>
            <TextInput
              style={styles.manualInput}
              value={manualCode}
              onChangeText={setManualCode}
              placeholder="Ex. F65C693EB"
              placeholderTextColor={colors.textTertiary}
              autoCapitalize="characters"
              autoCorrect={false}
            />
            <View style={styles.modalRow}>
              <TouchableOpacity style={styles.cancelBtn} onPress={() => { setShowManual(false); setManualCode(''); }}>
                <Text style={styles.cancelBtnText}>Anulează</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.saveBtn, !manualCode.trim() && styles.saveBtnDisabled]}
                onPress={handleManual}
                disabled={!manualCode.trim()}
              >
                <Text style={styles.saveBtnText}>Caută</Text>
              </TouchableOpacity>
            </View>
          </View>
        </KeyboardAvoidingView>
      </Modal>

      {/* Result modal */}
      <Modal visible={!!result} transparent animationType="fade" statusBarTranslucent>
        <View style={styles.resultBackdrop}>
          <View style={[styles.resultCard, { maxHeight: '85%' }]}>
            <View style={styles.resultHeader}>
              <Text style={styles.resultTitle}>
                {result?.error ? 'Bilet negăsit' : 'Info bilet'}
              </Text>
              <TouchableOpacity onPress={closeResult} style={styles.closeBtn}>
                <XIcon />
              </TouchableOpacity>
            </View>

            <ScrollView contentContainerStyle={{ padding: 16 }}>
              {result?.error ? (
                <Text style={styles.errorText}>{result.error}</Text>
              ) : result?.ticket ? (
                <>
                  <View style={styles.ticketStatusRow}>
                    <Text style={styles.ticketId}>#{result.ticket.id}</Text>
                    <Text style={[styles.ticketStatus, { color: statusColor(result.ticket.status) }]}>
                      {statusLabel(result.ticket.status)}
                    </Text>
                  </View>

                  <Text style={styles.customerName}>
                    {result.ticket.customer?.full_name || '—'}
                  </Text>

                  {result.ticket.ticket_type?.name && (
                    <Text style={styles.line}>{result.ticket.ticket_type.name}</Text>
                  )}

                  {result.ticket.seat && (
                    <Text style={styles.line}>
                      {[
                        result.ticket.seat.section_name,
                        result.ticket.seat.row_label && `rând ${result.ticket.seat.row_label}`,
                        result.ticket.seat.seat_number && `loc ${result.ticket.seat.seat_number}`,
                      ].filter(Boolean).join(' · ')}
                    </Text>
                  )}

                  {result.ticket.event?.title && (
                    <Text style={styles.eventLine}>
                      {result.ticket.event.title}
                      {result.ticket.event.venue?.name ? ` @ ${result.ticket.event.venue.name}` : ''}
                    </Text>
                  )}

                  {result.ticket.order?.order_number && (
                    <Text style={styles.line}>
                      Comandă: {result.ticket.order.order_number} · {formatDateTime(result.ticket.order.placed_at)}
                    </Text>
                  )}

                  {result.ticket.checked_in_at && (
                    <Text style={[styles.line, { color: colors.green }]}>
                      Check-in: {formatDateTime(result.ticket.checked_in_at)}
                    </Text>
                  )}

                  <Text style={styles.notesHeader}>
                    Mențiuni ({result.ticket.notes?.length || 0})
                  </Text>
                  {(result.ticket.notes || []).length === 0 ? (
                    <Text style={styles.emptyNotes}>Nu sunt mențiuni pentru acest bilet.</Text>
                  ) : (
                    result.ticket.notes.map(n => (
                      <View key={n.id} style={styles.noteCard}>
                        <Text style={styles.noteMeta}>
                          {n.author?.name || 'Anonim'} · {formatDateTime(n.created_at)}
                        </Text>
                        <Text style={styles.noteText}>{n.note}</Text>
                      </View>
                    ))
                  )}

                  <TextInput
                    style={styles.noteInput}
                    placeholder="Adaugă o mențiune rapidă..."
                    placeholderTextColor={colors.textTertiary}
                    value={newNote}
                    onChangeText={setNewNote}
                    multiline
                  />
                  <TouchableOpacity
                    style={[styles.saveBtn, (!newNote.trim() || isSavingNote) && styles.saveBtnDisabled]}
                    onPress={addNoteForResult}
                    disabled={!newNote.trim() || isSavingNote}
                  >
                    {isSavingNote ? (
                      <ActivityIndicator color="#fff" size="small" />
                    ) : (
                      <Text style={styles.saveBtnText}>Salvează mențiunea</Text>
                    )}
                  </TouchableOpacity>
                </>
              ) : null}
            </ScrollView>

            <TouchableOpacity style={styles.nextBtn} onPress={closeResult}>
              <Text style={styles.nextBtnText}>Scanează următorul</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  center: { justifyContent: 'center', alignItems: 'center', paddingHorizontal: 24 },
  header: { padding: 16, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
  headerTitle: { color: colors.textPrimary, fontSize: 18, fontWeight: '700' },
  headerSubtitle: { color: colors.textSecondary, fontSize: 12, marginTop: 2 },
  scannerWrap: {
    marginHorizontal: 20,
    marginTop: 24,
    width: SCANNER_SIZE,
    height: SCANNER_SIZE,
    alignSelf: 'center',
    borderRadius: 16,
    overflow: 'hidden',
    backgroundColor: '#000',
    position: 'relative',
  },
  scannerIdle: { justifyContent: 'center', alignItems: 'center', backgroundColor: 'rgba(255,255,255,0.03)' },
  scannerIdleText: { color: colors.textSecondary, textAlign: 'center', paddingHorizontal: 16, fontSize: 13 },
  scannerFrame: { position: 'absolute', top: 0, left: 0, right: 0, bottom: 0 },
  corner: { position: 'absolute', width: CORNER_SIZE, height: CORNER_SIZE },
  actions: { paddingHorizontal: 20, marginTop: 20, gap: 10 },
  primaryBtn: { backgroundColor: colors.purple, padding: 14, borderRadius: 12, alignItems: 'center' },
  primaryBtnActive: { backgroundColor: colors.red },
  primaryBtnText: { color: '#fff', fontSize: 15, fontWeight: '700' },
  secondaryBtn: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    gap: 8,
    padding: 12,
    borderRadius: 12,
    backgroundColor: 'rgba(255,255,255,0.04)',
    borderWidth: 1,
    borderColor: colors.border,
  },
  secondaryBtnText: { color: colors.textPrimary, fontSize: 14, fontWeight: '600' },
  overlay: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(0,0,0,0.6)', justifyContent: 'center', alignItems: 'center' },
  permText: { color: colors.textSecondary, textAlign: 'center', marginBottom: 20, fontSize: 14 },
  permBtn: { backgroundColor: colors.purple, paddingHorizontal: 20, paddingVertical: 12, borderRadius: 10 },
  permBtnText: { color: '#fff', fontWeight: '700' },

  modalBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.7)', justifyContent: 'center', padding: 24 },
  modalCard: { backgroundColor: colors.background, borderRadius: 16, padding: 20, borderWidth: 1, borderColor: colors.border },
  modalTitle: { color: colors.textPrimary, fontSize: 16, fontWeight: '700', marginBottom: 12 },
  manualInput: {
    backgroundColor: 'rgba(255,255,255,0.04)',
    borderRadius: 10,
    padding: 12,
    color: colors.textPrimary,
    fontSize: 15,
    letterSpacing: 1,
    borderWidth: 1,
    borderColor: colors.border,
  },
  modalRow: { flexDirection: 'row', justifyContent: 'flex-end', gap: 10, marginTop: 14 },
  cancelBtn: { paddingHorizontal: 16, paddingVertical: 10, borderRadius: 10 },
  cancelBtnText: { color: colors.textSecondary, fontSize: 14, fontWeight: '600' },
  saveBtn: { backgroundColor: colors.purple, paddingHorizontal: 20, paddingVertical: 10, borderRadius: 10, alignItems: 'center', minWidth: 110 },
  saveBtnDisabled: { opacity: 0.5 },
  saveBtnText: { color: '#fff', fontSize: 14, fontWeight: '700' },

  resultBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.75)', justifyContent: 'flex-end' },
  resultCard: { backgroundColor: colors.background, borderTopLeftRadius: 18, borderTopRightRadius: 18, borderWidth: 1, borderColor: colors.border },
  resultHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(255,255,255,0.05)',
  },
  resultTitle: { color: colors.textPrimary, fontSize: 16, fontWeight: '700' },
  closeBtn: { padding: 6, borderRadius: 20, backgroundColor: 'rgba(255,255,255,0.05)' },
  ticketStatusRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 },
  ticketId: { color: colors.textPrimary, fontSize: 20, fontWeight: '700' },
  ticketStatus: { fontSize: 13, fontWeight: '700' },
  customerName: { color: colors.textPrimary, fontSize: 18, fontWeight: '700', marginBottom: 6 },
  line: { color: colors.textSecondary, fontSize: 13, marginTop: 4 },
  eventLine: { color: colors.textPrimary, fontSize: 14, marginTop: 10, fontWeight: '600' },
  notesHeader: { color: colors.textTertiary, fontSize: 12, fontWeight: '700', textTransform: 'uppercase', marginTop: 16, marginBottom: 8 },
  emptyNotes: { color: colors.textTertiary, fontSize: 13, fontStyle: 'italic' },
  noteCard: { backgroundColor: 'rgba(255,255,255,0.03)', padding: 10, borderRadius: 10, marginBottom: 6 },
  noteMeta: { color: colors.textTertiary, fontSize: 11, marginBottom: 4 },
  noteText: { color: colors.textSecondary, fontSize: 13, lineHeight: 18 },
  noteInput: {
    backgroundColor: 'rgba(255,255,255,0.04)',
    borderRadius: 10,
    padding: 12,
    color: colors.textPrimary,
    fontSize: 14,
    minHeight: 70,
    textAlignVertical: 'top',
    marginTop: 12,
    borderWidth: 1,
    borderColor: colors.border,
  },
  errorText: { color: colors.red, fontSize: 14, textAlign: 'center', padding: 20 },
  nextBtn: { backgroundColor: colors.purple, padding: 14, alignItems: 'center' },
  nextBtnText: { color: '#fff', fontWeight: '700', fontSize: 15 },
});
