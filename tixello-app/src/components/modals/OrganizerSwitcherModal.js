import React from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  Modal,
  StyleSheet,
  ScrollView,
  Image,
  ActivityIndicator,
} from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { colors } from '../../theme/colors';
import { useAuth } from '../../context/AuthContext';

export default function OrganizerSwitcherModal({ visible, onClose, onSwitched }) {
  const { availableOrganizers, switchOrganizer, isSwitching } = useAuth();

  const handleSelect = async (organizerId) => {
    const current = availableOrganizers.find((o) => o.is_current);
    if (current && String(current.organizer_id) === String(organizerId)) {
      onClose();
      return;
    }

    try {
      const result = await switchOrganizer(organizerId);
      if (result?.success) {
        onClose();
        if (onSwitched) onSwitched();
      }
    } catch (e) {
      // Error handled silently; could add a toast later
    }
  };

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      onRequestClose={onClose}
    >
      <TouchableOpacity style={styles.backdrop} activeOpacity={1} onPress={onClose}>
        <TouchableOpacity activeOpacity={1} style={styles.sheet} onPress={(e) => e.stopPropagation()}>
          <View style={styles.header}>
            <Text style={styles.title}>Selectează organizatorul</Text>
            <TouchableOpacity onPress={onClose} style={styles.closeBtn}>
              <Svg width={18} height={18} viewBox="0 0 24 24" fill="none">
                <Path d="M6 6l12 12M18 6L6 18" stroke={colors.textSecondary} strokeWidth={2} strokeLinecap="round" />
              </Svg>
            </TouchableOpacity>
          </View>

          <Text style={styles.subtitle}>
            Ești membru în echipa mai multor organizatori. Alege pentru care vrei să scanezi bilete.
          </Text>

          <ScrollView style={styles.list} showsVerticalScrollIndicator={false}>
            {availableOrganizers.map((org) => (
              <TouchableOpacity
                key={org.organizer_id}
                style={[styles.item, org.is_current && styles.itemActive]}
                onPress={() => handleSelect(org.organizer_id)}
                disabled={isSwitching}
                activeOpacity={0.7}
              >
                <View style={styles.itemLeft}>
                  {org.logo ? (
                    <Image source={{ uri: org.logo }} style={styles.logo} />
                  ) : (
                    <View style={[styles.logo, styles.logoPlaceholder]}>
                      <Text style={styles.logoPlaceholderText}>
                        {(org.name || '?').charAt(0).toUpperCase()}
                      </Text>
                    </View>
                  )}
                  <View style={styles.itemInfo}>
                    <Text style={styles.itemName} numberOfLines={1}>{org.name}</Text>
                    <Text style={styles.itemRole}>{roleLabel(org.role)}</Text>
                  </View>
                </View>
                {org.is_current ? (
                  <View style={styles.currentBadge}>
                    <Svg width={14} height={14} viewBox="0 0 24 24" fill="none">
                      <Path d="M5 13l4 4L19 7" stroke={colors.white} strokeWidth={2.5} strokeLinecap="round" strokeLinejoin="round" />
                    </Svg>
                  </View>
                ) : (
                  <Svg width={18} height={18} viewBox="0 0 24 24" fill="none">
                    <Path d="M9 18l6-6-6-6" stroke={colors.textTertiary} strokeWidth={2} strokeLinecap="round" />
                  </Svg>
                )}
              </TouchableOpacity>
            ))}
          </ScrollView>

          {isSwitching && (
            <View style={styles.overlay}>
              <ActivityIndicator color={colors.textPrimary} size="large" />
              <Text style={styles.overlayText}>Se schimbă organizatorul…</Text>
            </View>
          )}
        </TouchableOpacity>
      </TouchableOpacity>
    </Modal>
  );
}

function roleLabel(role) {
  switch (role) {
    case 'admin': return 'Administrator';
    case 'manager': return 'Manager';
    case 'staff': return 'Staff';
    case 'owner': return 'Proprietar';
    default: return role || '';
  }
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.6)',
    justifyContent: 'center',
    paddingHorizontal: 20,
  },
  sheet: {
    backgroundColor: colors.background,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: colors.border,
    maxHeight: '80%',
    overflow: 'hidden',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingTop: 20,
    paddingBottom: 8,
  },
  title: {
    fontSize: 18,
    fontWeight: '700',
    color: colors.textPrimary,
  },
  closeBtn: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: colors.surface,
    alignItems: 'center',
    justifyContent: 'center',
  },
  subtitle: {
    fontSize: 13,
    color: colors.textSecondary,
    paddingHorizontal: 20,
    marginBottom: 16,
  },
  list: {
    paddingHorizontal: 12,
    paddingBottom: 20,
  },
  item: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 12,
    marginHorizontal: 8,
    marginBottom: 8,
    borderRadius: 14,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
  },
  itemActive: {
    borderColor: '#C41E3A',
    backgroundColor: 'rgba(196, 30, 58, 0.08)',
  },
  itemLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
    gap: 12,
  },
  logo: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: colors.surface,
  },
  logoPlaceholder: {
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#C41E3A',
  },
  logoPlaceholderText: {
    color: colors.white,
    fontSize: 18,
    fontWeight: '700',
  },
  itemInfo: {
    flex: 1,
  },
  itemName: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 2,
  },
  itemRole: {
    fontSize: 12,
    color: colors.textSecondary,
  },
  currentBadge: {
    width: 24,
    height: 24,
    borderRadius: 12,
    backgroundColor: '#C41E3A',
    alignItems: 'center',
    justifyContent: 'center',
  },
  overlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(0,0,0,0.7)',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
  },
  overlayText: {
    color: colors.textPrimary,
    fontSize: 14,
    fontWeight: '500',
  },
});
