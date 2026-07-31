import React, { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useApp } from '@/store/AppContext';
import { Card, SectionTitle } from '@/components/ui';
import { palette, radius } from '@/theme/colors';

function Row({
  title,
  desc,
  value,
  onToggle,
  right,
}: {
  title: string;
  desc?: string;
  value?: boolean;
  onToggle?: (v: boolean) => void;
  right?: string;
}) {
  const { accent } = useApp();
  return (
    <View style={styles.row}>
      <View style={{ flex: 1 }}>
        <Text style={styles.rowTitle}>{title}</Text>
        {desc ? <Text style={styles.rowDesc}>{desc}</Text> : null}
      </View>
      {onToggle ? (
        <Switch
          value={value}
          onValueChange={onToggle}
          trackColor={{ true: accent.base, false: palette.surface2 }}
          thumbColor="#fff"
        />
      ) : (
        <Text style={styles.rowRight}>{right ?? '›'}</Text>
      )}
    </View>
  );
}

export default function SettingsScreen() {
  const { organizer, accent, signOut } = useApp();
  const [vibrate, setVibrate] = useState(true);
  const [sound, setSound] = useState(true);
  const [autoConfirm, setAutoConfirm] = useState(false);
  const [nfc, setNfc] = useState(true);

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <View style={styles.topbar}>
        <Text style={styles.title}>Setări</Text>
      </View>
      <ScrollView contentContainerStyle={styles.body}>
        <Card style={styles.account}>
          <View style={[styles.av, { backgroundColor: accent.soft, borderColor: accent.border }]}>
            <Text style={[styles.avText, { color: accent.base }]}>
              {(organizer?.name ?? 'TX').slice(0, 2).toUpperCase()}
            </Text>
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.accName}>{organizer?.name ?? 'Tixello'}</Text>
            <Text style={styles.accSub}>{organizer?.contact_name ?? 'Cont organizator'}</Text>
          </View>
        </Card>

        <SectionTitle>Scanner</SectionTitle>
        <Card>
          <Row title="Vibrație" value={vibrate} onToggle={setVibrate} />
          <View style={styles.sep} />
          <Row title="Efecte sonore" value={sound} onToggle={setSound} />
          <View style={styles.sep} />
          <Row title="Auto-confirmare valide" desc="Fără tap la fiecare scanare" value={autoConfirm} onToggle={setAutoConfirm} />
        </Card>

        <SectionTitle>Hardware</SectionTitle>
        <Card>
          <Row title="Imprimantă bon" desc="Thermal 80mm" right="›" />
          <View style={styles.sep} />
          <Row title="Card prin NFC" desc="Plată contactless" value={nfc} onToggle={setNfc} />
        </Card>

        <SectionTitle>Cont</SectionTitle>
        <Card>
          <Row title="Profil & date companie" right="›" />
          <View style={styles.sep} />
          <Row title="Conturi bancare / payout" right="›" />
          <View style={styles.sep} />
          <Row title="Schimbă parola" right="›" />
        </Card>

        <Pressable onPress={signOut} style={styles.logout}>
          <Text style={styles.logoutText}>Deconectare</Text>
        </Pressable>
        <Text style={styles.version}>Tixello · v0.1.0</Text>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: palette.bg },
  topbar: { paddingHorizontal: 16, paddingTop: 10, paddingBottom: 8 },
  title: { color: palette.text, fontSize: 20, fontWeight: '900' },
  body: { paddingHorizontal: 15, paddingBottom: 30, gap: 10 },
  account: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14 },
  av: { width: 44, height: 44, borderRadius: radius.md, borderWidth: 1, alignItems: 'center', justifyContent: 'center' },
  avText: { fontSize: 14, fontWeight: '900' },
  accName: { color: palette.text, fontSize: 15, fontWeight: '900' },
  accSub: { color: palette.muted, fontSize: 11.5, marginTop: 2 },
  row: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 13, paddingVertical: 12, gap: 12 },
  rowTitle: { color: palette.text, fontSize: 13, fontWeight: '700' },
  rowDesc: { color: palette.hint, fontSize: 10.5, marginTop: 1 },
  rowRight: { color: palette.faint, fontSize: 16 },
  sep: { height: 1, backgroundColor: palette.border, marginHorizontal: 13 },
  logout: {
    marginTop: 8,
    borderRadius: radius.md,
    paddingVertical: 14,
    alignItems: 'center',
    backgroundColor: 'rgba(240,79,79,0.1)',
    borderWidth: 1,
    borderColor: 'rgba(240,79,79,0.28)',
  },
  logoutText: { color: palette.danger, fontSize: 13, fontWeight: '800' },
  version: { color: palette.faint, fontSize: 11, textAlign: 'center', marginTop: 6 },
});
