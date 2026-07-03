// UpdateBanner — mic banner care apare cand hook-ul useAppUpdate detecteaza
// o versiune mai noua pe server.
//
// Design:
//  - Compact, culoare accent (galben pentru soft, rosu daca force_update)
//  - Text: "Actualizare v{latest} disponibilă"
//  - Tap => modal simplu cu url-ul de descarcare + copiere url
//  - Non-blocking (nu se suprapune peste actiuni critice ale kiosk-ului)
//
// Layout: pozitionat absolut, cu prop `position` care controleaza colturile
// (default: bottom-center pentru ca top e ocupat de status/refresh in
// scan-app clasic; pe kiosk-ul de Sf. Ana top e liber deci putem folosi
// oriunde).

import React, { useState } from 'react';
import { View, Text, Modal, Pressable, StyleSheet, Linking, Platform } from 'react-native';
import { colors } from '../theme/colors';
import { APP_VERSION } from '../config/version';

export default function UpdateBanner({
  hasUpdate,
  latestVersion,
  downloadUrl,
  forceUpdate = false,
  position = 'bottom-center',
}) {
  const [modalOpen, setModalOpen] = useState(false);
  if (!hasUpdate) return null;

  const openDownload = () => {
    if (!downloadUrl) return;
    Linking.openURL(downloadUrl).catch(() => {});
  };

  return (
    <>
      <View
        pointerEvents="box-none"
        style={[
          styles.wrap,
          position === 'top-center' && styles.wrapTop,
          position === 'top-right' && styles.wrapTopRight,
          position === 'bottom-center' && styles.wrapBottomCenter,
          position === 'bottom-right' && styles.wrapBottomRight,
        ]}
      >
        <Pressable
          onPress={() => setModalOpen(true)}
          style={({ pressed }) => [
            styles.banner,
            forceUpdate && styles.bannerForce,
            pressed && styles.bannerPressed,
          ]}
          accessibilityLabel={`Actualizare versiune ${latestVersion} disponibila`}
        >
          <Text style={styles.bannerIcon}>⬆</Text>
          <View style={styles.bannerBody}>
            <Text style={styles.bannerTitle}>
              {forceUpdate ? 'Actualizare obligatorie' : 'Actualizare disponibilă'}
            </Text>
            <Text style={styles.bannerSub}>
              v{latestVersion} — instalat: v{APP_VERSION}
            </Text>
          </View>
        </Pressable>
      </View>

      <Modal
        transparent
        visible={modalOpen}
        animationType="fade"
        onRequestClose={() => setModalOpen(false)}
      >
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>Actualizează aplicația</Text>
            <Text style={styles.modalText}>
              O versiune mai nouă (v{latestVersion}) este disponibilă. Versiunea instalată este v{APP_VERSION}.
            </Text>
            <Text style={styles.modalHint}>
              Descarcă noul APK și instaleaz-o peste versiunea curentă. Datele tale rămân.
            </Text>
            {downloadUrl ? (
              <Text style={styles.modalUrl} selectable>{downloadUrl}</Text>
            ) : null}
            <View style={styles.modalActions}>
              <Pressable
                onPress={() => setModalOpen(false)}
                style={({ pressed }) => [styles.btnGhost, pressed && styles.btnGhostPressed]}
              >
                <Text style={styles.btnGhostText}>Mai târziu</Text>
              </Pressable>
              <Pressable
                onPress={openDownload}
                style={({ pressed }) => [styles.btnPrimary, pressed && styles.btnPrimaryPressed]}
              >
                <Text style={styles.btnPrimaryText}>Descarcă acum</Text>
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>
    </>
  );
}

const styles = StyleSheet.create({
  wrap: {
    position: 'absolute',
    left: 0,
    right: 0,
    zIndex: 30,
    alignItems: 'center',
  },
  wrapTop: { top: 16 },
  wrapTopRight: { top: 16, right: 16, left: undefined, alignItems: 'flex-end' },
  wrapBottomCenter: { bottom: 20 },
  wrapBottomRight: { bottom: 20, right: 16, left: undefined, alignItems: 'flex-end' },

  banner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 999,
    backgroundColor: '#D4922A', // amber/oranj — atenție dar nu alarmant
    ...Platform.select({
      android: { elevation: 6 },
      ios: { shadowColor: '#000', shadowOpacity: 0.25, shadowRadius: 8, shadowOffset: { width: 0, height: 3 } },
    }),
  },
  bannerForce: {
    backgroundColor: '#BE3237', // rosu — obligatoriu
  },
  bannerPressed: {
    opacity: 0.85,
  },
  bannerIcon: {
    fontSize: 18,
    color: colors.paper,
    fontWeight: '900',
  },
  bannerBody: { flexShrink: 1 },
  bannerTitle: {
    fontSize: 14,
    fontWeight: '800',
    color: colors.paper,
    letterSpacing: 0.2,
  },
  bannerSub: {
    fontSize: 12,
    color: colors.paper,
    opacity: 0.9,
  },

  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.55)',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  modalCard: {
    width: '100%',
    maxWidth: 480,
    borderRadius: 20,
    backgroundColor: colors.paper,
    padding: 24,
  },
  modalTitle: {
    fontSize: 22,
    fontWeight: '900',
    color: colors.ink,
    marginBottom: 10,
  },
  modalText: {
    fontSize: 15,
    color: colors.ink,
    marginBottom: 8,
    lineHeight: 22,
  },
  modalHint: {
    fontSize: 13,
    color: colors.ink,
    opacity: 0.7,
    marginBottom: 12,
    lineHeight: 20,
  },
  modalUrl: {
    fontSize: 13,
    color: colors.primary,
    marginBottom: 20,
    fontFamily: Platform.select({ ios: 'Menlo', android: 'monospace', default: 'monospace' }),
  },
  modalActions: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: 10,
  },
  btnGhost: {
    paddingHorizontal: 18,
    paddingVertical: 12,
    borderRadius: 12,
  },
  btnGhostPressed: {
    backgroundColor: 'rgba(0,0,0,0.05)',
  },
  btnGhostText: {
    fontSize: 15,
    fontWeight: '700',
    color: colors.ink,
    opacity: 0.7,
  },
  btnPrimary: {
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 12,
    backgroundColor: colors.primary,
  },
  btnPrimaryPressed: {
    backgroundColor: colors.primaryDark,
  },
  btnPrimaryText: {
    fontSize: 15,
    fontWeight: '800',
    color: colors.paper,
  },
});
