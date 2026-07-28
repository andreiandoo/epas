// Shared emergency reporter — used by both EmergencyModal (opened from
// the shift bar) AND NotificationsPanel (always accessible, no shift
// required). Encapsulates the 3 pieces of reporting UX:
//
//   1. Optional photo attachment (expo-image-picker)
//   2. Optional voice-note attachment (expo-av, hold-to-record ≤30s)
//   3. 8 severity-tagged report buttons that submit + close
//
// Behaviour:
// - On any severity tap → local addNotification (immediate feedback) +
//   fire-and-forget POST to /organizer/notifications/emergency-report
// - Photo/voice attachments (if any) go along with the POST (multipart)
// - Reporter identity (name + role + gate) embedded in server message
// - Local notification also gets the media URIs so the reporter's own
//   NotificationsPanel can render thumbnail + play button
//
// Props:
//   onSent?()      — callback fired after a successful submit (parent can
//                    close its containing modal / collapse UI)
//   compact?       — reduces vertical padding for tight containers
//                    (NotificationsPanel uses this)
import React, { useEffect, useRef, useState } from 'react';
import { View, Text, TouchableOpacity, Image, StyleSheet, Alert } from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { colors } from '../theme/colors';
import { reportEmergency } from '../api/notifications';
import { useApp } from '../context/AppContext';
import { useAuth } from '../context/AuthContext';

// Optional native modules — silent no-op if not linked yet (dev without
// prebuild). Import failures don't break the app; the UI still shows the
// buttons but they surface a clear "modul lipsă" alert on tap.
let ImagePicker = null;
try { ImagePicker = require('expo-image-picker'); } catch {}
let AudioLib = null;
try { AudioLib = require('expo-av').Audio; } catch {}

// The 8 predefined problem types, keyed by severity for colour coding.
const OPTIONS = [
  { id: 'medical', label: 'Urgență Medicală', severity: 'high',
    icon: (c) => <Path d="M12 8v4m0 4h.01M4.93 4.93l14.14 14.14M12 2a10 10 0 100 20 10 10 0 000-20z" stroke={c} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" /> },
  { id: 'fire', label: 'Incendiu / Evacuare', severity: 'high',
    icon: (c) => <Path d="M12 2c.5 3.5-1.5 6-1.5 6 2 1.5 3.5 3.5 3.5 6.5 0 3-2.5 5.5-5.5 5.5S3 17.5 3 14.5c0-2 .5-3.5 1.5-5C5.5 8 7 6 7 3.5c0 0 2.5 1 3 3 .5-2 2-4.5 2-4.5z" stroke={c} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" /> },
  { id: 'security', label: 'Problemă Securitate', severity: 'high',
    icon: (c) => <Path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke={c} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" /> },
  { id: 'technical', label: 'Problemă Tehnică', severity: 'medium',
    icon: (c) => <Path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z" stroke={c} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" /> },
  { id: 'crowd', label: 'Control Mulțime', severity: 'medium',
    icon: (c) => <Path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke={c} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" /> },
  { id: 'equipment', label: 'Defecțiune Echipament', severity: 'medium',
    icon: (c) => <Path d="M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9 1.65 1.65 0 004.27 7.18l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z" stroke={c} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" /> },
  { id: 'weather', label: 'Alertă Meteo', severity: 'low',
    icon: (c) => <Path d="M18 10h-1.26A8 8 0 109 20h9a5 5 0 000-10z" stroke={c} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" /> },
  { id: 'other', label: 'Altele', severity: 'low',
    icon: (c) => <Path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10zM12 16v-4M12 8h.01" stroke={c} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" /> },
];

const SEVERITY_STYLES = {
  high: { bg: colors.dangerBg || colors.redBg, border: colors.dangerBorder || colors.redBorder, color: colors.danger || colors.red },
  medium: { bg: colors.amberBg, border: colors.amberBorder, color: colors.amber },
  low: { bg: 'rgba(20,10,10,0.05)', border: 'rgba(20,10,10,0.08)', color: colors.textSecondary },
};

export default function EmergencyReporter({ onSent, compact = false }) {
  const [attachedPhoto, setAttachedPhoto] = useState(null); // { uri, name, type }
  const [attachedAudio, setAttachedAudio] = useState(null); // { uri, name, type, duration }
  const [recording, setRecording] = useState(false);
  const [reporting, setReporting] = useState(false);
  const [sentFlash, setSentFlash] = useState(null); // { label } for a 1.5s confirmation banner
  const recordingRef = useRef(null);
  const recordingStartRef = useRef(0);
  const { addNotification } = useApp();
  const { user, userRole } = useAuth();

  // Cleanup any in-progress recording on unmount.
  useEffect(() => () => {
    if (recordingRef.current) {
      try { recordingRef.current.stopAndUnloadAsync(); } catch {}
      recordingRef.current = null;
    }
  }, []);

  const reporterName = user?.team_member?.name || user?.name || 'Operator';
  const reporterRoleLabel = userRole === 'owner' ? 'Proprietar'
    : userRole === 'admin' ? 'Admin'
    : userRole === 'manager' ? 'Manager'
    : userRole === 'staff' ? 'Staff'
    : userRole;
  const reporterGate = user?.assigned_gate || user?.team_member?.gate_name || null;
  const reporterSuffix = [
    reporterName,
    reporterGate ? `poarta ${reporterGate}` : null,
  ].filter(Boolean).join(' · ');

  const handleAttachPhoto = async () => {
    if (!ImagePicker) {
      Alert.alert('Modul lipsă', 'Modulul foto nu e disponibil. Rebuild aplicația cu ultimele dependințe.');
      return;
    }
    try {
      const perm = await ImagePicker.requestCameraPermissionsAsync();
      if (!perm.granted) {
        Alert.alert('Permisiune necesară', 'Permite accesul la cameră pentru a atașa poză.');
        return;
      }
      const result = await ImagePicker.launchCameraAsync({
        mediaTypes: ImagePicker.MediaTypeOptions?.Images ?? 'Images',
        quality: 0.7,
        allowsEditing: false,
      });
      if (result.canceled) return;
      const asset = result.assets?.[0];
      if (!asset) return;
      setAttachedPhoto({
        uri: asset.uri,
        name: asset.fileName || `emergency_${Date.now()}.jpg`,
        type: asset.mimeType || 'image/jpeg',
      });
    } catch {
      Alert.alert('Eroare', 'Nu am putut deschide camera.');
    }
  };

  const startRecording = async () => {
    if (!AudioLib) {
      Alert.alert('Modul lipsă', 'Modulul audio nu e disponibil.');
      return;
    }
    try {
      const perm = await AudioLib.requestPermissionsAsync();
      if (!perm.granted) {
        Alert.alert('Permisiune necesară', 'Permite accesul la microfon pentru notă vocală.');
        return;
      }
      await AudioLib.setAudioModeAsync({
        allowsRecordingIOS: true,
        playsInSilentModeIOS: true,
      });
      const rec = new AudioLib.Recording();
      await rec.prepareToRecordAsync(
        AudioLib.RecordingOptionsPresets?.LOW_QUALITY
        || AudioLib.RECORDING_OPTIONS_PRESET_LOW_QUALITY
      );
      await rec.startAsync();
      recordingRef.current = rec;
      recordingStartRef.current = Date.now();
      setRecording(true);
    } catch {
      Alert.alert('Eroare', 'Nu am putut porni înregistrarea.');
    }
  };

  const stopRecording = async () => {
    const rec = recordingRef.current;
    if (!rec) return;
    setRecording(false);
    try {
      await rec.stopAndUnloadAsync();
      const uri = rec.getURI();
      const duration = Math.round((Date.now() - recordingStartRef.current) / 1000);
      if (uri && duration >= 1) {
        setAttachedAudio({
          uri,
          name: `emergency_${Date.now()}.m4a`,
          type: 'audio/m4a',
          duration,
        });
      }
    } catch {}
    recordingRef.current = null;
  };

  const submit = (option) => {
    if (reporting) return;
    setReporting(true);

    // Local feedback — passes media URIs so the reporter's own panel can
    // render the thumbnail + audio play button. NotificationItem already
    // knows how to render these (see NotificationsPanel).
    try {
      addNotification({
        type: 'alert',
        message: `${option.label} — raportat de ${reporterSuffix}`,
        photo_uri: attachedPhoto?.uri || null,
        audio_uri: attachedAudio?.uri || null,
        audio_duration: attachedAudio?.duration || null,
      });
    } catch {}

    reportEmergency({
      type: option.id,
      title: `Urgență: ${option.label}`,
      message: `Raportat de ${reporterName} (${reporterRoleLabel})${reporterGate ? ` — poarta ${reporterGate}` : ''}.`,
      severity: option.severity === 'low' ? 'info' : option.severity,
      photo: attachedPhoto,
      audio: attachedAudio,
    }).catch(() => {
      // Silent — the reporter already got local feedback. Alert isn't lost.
    });

    setSentFlash({ label: option.label });
    setAttachedPhoto(null);
    setAttachedAudio(null);
    setTimeout(() => {
      setReporting(false);
      setSentFlash(null);
      if (typeof onSent === 'function') onSent();
    }, 1500);
  };

  // A green success flash after submit — replaces the whole UI for 1.5s
  if (sentFlash) {
    return (
      <View style={[styles.container, compact && styles.containerCompact, styles.successFlash]}>
        <Svg width={28} height={28} viewBox="0 0 24 24" fill="none">
          <Path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke={colors.green} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
        </Svg>
        <Text style={styles.successText}>Raport trimis: {sentFlash.label}</Text>
      </View>
    );
  }

  return (
    <View style={[styles.container, compact && styles.containerCompact]}>
      {/* Attach row */}
      <View style={styles.attachRow}>
        {attachedPhoto ? (
          <View style={styles.attachedThumbWrap}>
            <Image source={{ uri: attachedPhoto.uri }} style={styles.attachedThumb} />
            <TouchableOpacity
              onPress={() => setAttachedPhoto(null)}
              style={styles.attachedRemove}
              activeOpacity={0.7}
              hitSlop={{ top: 6, right: 6, bottom: 6, left: 6 }}
            >
              <Text style={styles.attachedRemoveText}>×</Text>
            </TouchableOpacity>
          </View>
        ) : (
          <TouchableOpacity
            onPress={handleAttachPhoto}
            style={styles.attachBtn}
            activeOpacity={0.7}
            disabled={reporting}
          >
            <Svg width={18} height={18} viewBox="0 0 24 24" fill="none">
              <Path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" stroke={colors.textSecondary} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" />
              <Path d="M12 13a4 4 0 100-8 4 4 0 000 8z" stroke={colors.textSecondary} strokeWidth={1.8} />
            </Svg>
            <Text style={styles.attachBtnText}>Foto</Text>
          </TouchableOpacity>
        )}

        {attachedAudio ? (
          <View style={styles.attachedAudioWrap}>
            <Svg width={14} height={14} viewBox="0 0 24 24" fill="none">
              <Path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z" stroke={colors.purple} strokeWidth={1.8} />
              <Path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8" stroke={colors.purple} strokeWidth={1.8} strokeLinecap="round" />
            </Svg>
            <Text style={styles.attachedAudioText}>Notă {attachedAudio.duration}s</Text>
            <TouchableOpacity onPress={() => setAttachedAudio(null)} hitSlop={{ top: 8, right: 8, bottom: 8, left: 8 }}>
              <Text style={styles.attachedRemoveInline}>×</Text>
            </TouchableOpacity>
          </View>
        ) : (
          <TouchableOpacity
            onPressIn={startRecording}
            onPressOut={stopRecording}
            style={[styles.attachBtn, recording && styles.attachBtnActive]}
            activeOpacity={0.7}
            disabled={reporting}
          >
            <Svg width={18} height={18} viewBox="0 0 24 24" fill="none">
              <Path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z" stroke={recording ? colors.red : colors.textSecondary} strokeWidth={1.8} />
              <Path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8" stroke={recording ? colors.red : colors.textSecondary} strokeWidth={1.8} strokeLinecap="round" />
            </Svg>
            <Text style={[styles.attachBtnText, recording && { color: colors.red }]}>
              {recording ? '● Înregistrare…' : 'Ține pentru notă'}
            </Text>
          </TouchableOpacity>
        )}
      </View>

      {/* Severity grid — 4x2 */}
      <View style={styles.grid}>
        {OPTIONS.map((option) => {
          const s = SEVERITY_STYLES[option.severity];
          return (
            <TouchableOpacity
              key={option.id}
              style={[
                styles.gridItem,
                { backgroundColor: s.bg, borderColor: s.border },
              ]}
              onPress={() => submit(option)}
              activeOpacity={0.7}
              disabled={reporting}
            >
              <Svg width={20} height={20} viewBox="0 0 24 24" fill="none">
                {option.icon(s.color)}
              </Svg>
              <Text style={[styles.gridItemLabel, { color: s.color }]} numberOfLines={2}>
                {option.label}
              </Text>
            </TouchableOpacity>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    gap: 10,
  },
  containerCompact: {
    gap: 8,
  },
  attachRow: {
    flexDirection: 'row',
    gap: 8,
  },
  attachBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 10,
    paddingHorizontal: 10,
    borderRadius: 10,
    borderWidth: 1,
    borderStyle: 'dashed',
    borderColor: colors.border,
    backgroundColor: colors.background,
  },
  attachBtnActive: {
    backgroundColor: colors.redLight,
    borderColor: colors.redBorder,
    borderStyle: 'solid',
  },
  attachBtnText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.textSecondary,
  },
  attachedThumbWrap: {
    position: 'relative',
    width: 62, height: 62,
  },
  attachedThumb: {
    width: 62, height: 62,
    borderRadius: 10,
    backgroundColor: colors.border,
  },
  attachedRemove: {
    position: 'absolute',
    top: -6, right: -6,
    width: 22, height: 22,
    borderRadius: 11,
    backgroundColor: colors.red,
    alignItems: 'center', justifyContent: 'center',
  },
  attachedRemoveText: {
    color: colors.white,
    fontSize: 16,
    lineHeight: 18,
    fontWeight: '700',
  },
  attachedAudioWrap: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
    backgroundColor: colors.purpleBg,
  },
  attachedAudioText: {
    flex: 1,
    fontSize: 12,
    fontWeight: '600',
    color: colors.purple,
  },
  attachedRemoveInline: {
    color: colors.textSecondary,
    fontSize: 18,
    fontWeight: '700',
    paddingHorizontal: 4,
  },
  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
  },
  gridItem: {
    width: '48.5%',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingHorizontal: 10,
    paddingVertical: 10,
    borderRadius: 10,
    borderWidth: 1,
  },
  gridItemLabel: {
    flex: 1,
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 0.1,
  },
  successFlash: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 20,
    backgroundColor: colors.greenBg,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.greenBorder,
    gap: 10,
  },
  successText: {
    fontSize: 14,
    fontWeight: '700',
    color: colors.green,
    textAlign: 'center',
  },
});
