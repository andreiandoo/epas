import React, { useCallback, useEffect, useState } from 'react';
import {
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import { useApp } from '@/store/AppContext';
import * as api from '@/api/endpoints';
import type { EventSummary } from '@/api/types';
import { AppHeader } from '@/components/AppHeader';
import { Card, KpiCard, ProgressBar, SectionTitle, Badge } from '@/components/ui';
import { palette, radius, withAlpha } from '@/theme/colors';

function lei(n: number): string {
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`;
  if (n >= 1000) return `${Math.round(n / 1000)}k`;
  return String(n);
}

export default function HomeScreen() {
  const { organizer, contextKind, accent, apiCtx } = useApp();
  const nav = useNavigation<any>();
  const [events, setEvents] = useState<EventSummary[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      const res = await api.listEvents(apiCtx);
      setEvents(res.data ?? []);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Nu am putut încărca datele.');
    }
  }, [apiCtx]);

  useEffect(() => {
    load();
  }, [load]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  }, [load]);

  const totalRevenue = events.reduce((s, e) => s + (e.revenue ?? 0), 0);
  const totalSold = events.reduce((s, e) => s + (e.tickets_sold ?? 0), 0);
  const active = events.filter((e) => e.status === 'active' || e.status === 'published');

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <AppHeader
        name={organizer?.name ?? 'Tixello'}
        subtitle={`${organizer?.city ?? ''}${organizer?.city ? ' · ' : ''}${labelFor(contextKind)}`}
        accent={accent}
        unread={3}
        onAvatar={() => nav.navigate('Switcher')}
        onSwitch={() => nav.navigate('Switcher')}
        onBell={() => nav.navigate('Notifications')}
      />
      <ScrollView
        contentContainerStyle={styles.body}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={accent.base} />
        }
      >
        <View style={[styles.hero, { backgroundColor: accent.soft, borderColor: accent.border }]}>
          <Text style={styles.heroLabel}>Încasări total · 2025</Text>
          <Text style={styles.heroBig}>{totalRevenue ? `${lei(totalRevenue)} lei` : '—'}</Text>
          <View style={styles.heroStats}>
            <HeroStat v={String(events.length)} l="Evenimente" />
            <HeroStat v={totalSold ? totalSold.toLocaleString('ro-RO') : '—'} l="Bilete" />
            <HeroStat v={`${active.length}`} l="Active" />
          </View>
        </View>

        <View style={styles.kpis}>
          <KpiCard value={String(active.length)} label="Active" color={accent.base} />
          <KpiCard value={totalSold ? lei(totalSold) : '—'} label="Vândute" />
          <KpiCard value={totalRevenue ? lei(totalRevenue) : '—'} label="Venituri" />
        </View>

        {error && (
          <Card style={{ ...styles.errorCard, borderColor: withAlpha(palette.danger, 0.3) }}>
            <Text style={styles.errorText}>{error}</Text>
            <Text style={styles.errorHint}>Trage în jos pentru reîncărcare.</Text>
          </Card>
        )}

        <SectionTitle>Evenimente active</SectionTitle>
        {events.length === 0 && !error ? (
          <Card style={styles.empty}>
            <Text style={styles.emptyText}>Niciun eveniment încă.</Text>
          </Card>
        ) : (
          events.slice(0, 6).map((e) => {
            const pct = e.capacity ? Math.round(((e.tickets_sold ?? 0) / e.capacity) * 100) : 0;
            return (
              <Card key={e.id} style={styles.evc}>
                <View style={styles.evTop}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.evName}>{e.name}</Text>
                    <Text style={styles.evMeta}>
                      {[e.venue, e.starts_at].filter(Boolean).join(' · ') || '—'}
                    </Text>
                    <View style={{ flexDirection: 'row', gap: 6, marginTop: 6 }}>
                      <Badge label="● Activ" color={accent.base} bg={accent.soft} border={accent.border} />
                    </View>
                  </View>
                  <View style={styles.evRt}>
                    <Text style={styles.evSold}>{(e.tickets_sold ?? 0).toLocaleString('ro-RO')}</Text>
                    <Text style={styles.evCap}>/ {(e.capacity ?? 0).toLocaleString('ro-RO')}</Text>
                    <Text style={[styles.evRev, { color: accent.base }]}>
                      {e.revenue ? `${lei(e.revenue)} lei` : ''}
                    </Text>
                  </View>
                </View>
                <ProgressBar pct={pct} color={accent.base} />
              </Card>
            );
          })
        )}
      </ScrollView>
    </SafeAreaView>
  );
}

function HeroStat({ v, l }: { v: string; l: string }) {
  return (
    <View>
      <Text style={styles.heroStatV}>{v}</Text>
      <Text style={styles.heroStatL}>{l}</Text>
    </View>
  );
}

function labelFor(kind: string): string {
  const map: Record<string, string> = {
    organizer: 'Organizator',
    theatre: 'Teatru',
    venue: 'Venue',
    artist: 'Artist',
    agency: 'Agenție',
    festival: 'Festival',
    tenant: 'Tenant',
  };
  return map[kind] ?? 'Organizator';
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: palette.bg },
  body: { paddingHorizontal: 15, paddingBottom: 30, gap: 12 },
  hero: { borderRadius: radius.xl, borderWidth: 1, padding: 18 },
  heroLabel: {
    fontSize: 10,
    fontWeight: '800',
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    color: palette.muted,
  },
  heroBig: { fontSize: 29, fontWeight: '900', color: palette.text, marginVertical: 3 },
  heroStats: { flexDirection: 'row', gap: 16 },
  heroStatV: { fontSize: 17, fontWeight: '900', color: palette.text },
  heroStatL: { fontSize: 9.5, color: palette.hint, textTransform: 'uppercase', marginTop: 1 },
  kpis: { flexDirection: 'row', gap: 9 },
  evc: { padding: 13 },
  evTop: { flexDirection: 'row', justifyContent: 'space-between', gap: 10, marginBottom: 9 },
  evName: { color: palette.text, fontSize: 14, fontWeight: '800' },
  evMeta: { color: palette.muted, fontSize: 11.5, marginTop: 2 },
  evRt: { alignItems: 'flex-end' },
  evSold: { color: palette.text, fontSize: 15, fontWeight: '900' },
  evCap: { color: palette.faint, fontSize: 9 },
  evRev: { fontSize: 11.5, fontWeight: '800', marginTop: 3 },
  empty: { padding: 20, alignItems: 'center' },
  emptyText: { color: palette.muted, fontSize: 13 },
  errorCard: { padding: 14, gap: 4 },
  errorText: { color: palette.danger, fontSize: 12.5, fontWeight: '700' },
  errorHint: { color: palette.hint, fontSize: 11 },
});
