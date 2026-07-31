import React, { useCallback, useEffect, useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useApp } from '@/store/AppContext';
import * as api from '@/api/endpoints';
import type { EventSummary, EventStats } from '@/api/types';
import { Card, KpiCard, SectionTitle } from '@/components/ui';
import { palette, radius } from '@/theme/colors';

export default function ReportsScreen() {
  const { accent, apiCtx } = useApp();
  const [events, setEvents] = useState<EventSummary[]>([]);
  const [selected, setSelected] = useState<EventSummary | null>(null);
  const [stats, setStats] = useState<EventStats | null>(null);

  useEffect(() => {
    (async () => {
      try {
        const res = await api.listEvents(apiCtx);
        setEvents(res.data ?? []);
        if (res.data?.[0]) setSelected(res.data[0]);
      } catch {
        /* handled by empty state */
      }
    })();
  }, [apiCtx]);

  const loadStats = useCallback(
    async (ev: EventSummary) => {
      setStats(null);
      try {
        setStats(await api.eventStatistics(apiCtx, ev.id));
      } catch {
        setStats(null);
      }
    },
    [apiCtx],
  );

  useEffect(() => {
    if (selected) loadStats(selected);
  }, [selected, loadStats]);

  const rate = stats?.check_in_rate ?? (stats && stats.sold ? Math.round((stats.entered / stats.sold) * 100) : 0);

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <View style={styles.topbar}>
        <Text style={styles.title}>Rapoarte</Text>
      </View>
      <ScrollView contentContainerStyle={styles.body}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.picker}>
          {events.map((e) => {
            const on = selected?.id === e.id;
            return (
              <Text
                key={e.id}
                onPress={() => setSelected(e)}
                style={[
                  styles.chip,
                  on
                    ? { color: accent.base, backgroundColor: accent.soft, borderColor: accent.border }
                    : { color: palette.muted },
                ]}
              >
                {e.name}
              </Text>
            );
          })}
          {events.length === 0 && <Text style={styles.empty}>Niciun eveniment.</Text>}
        </ScrollView>

        <View style={styles.ring}>
          <View style={[styles.gauge, { borderColor: accent.base }]}>
            <Text style={styles.gaugeVal}>{rate}%</Text>
            <Text style={styles.gaugeLabel}>check-in</Text>
          </View>
          <View style={{ gap: 8, flex: 1 }}>
            <Legend c={palette.success} l="Intrați" v={stats?.entered} />
            <Legend c={palette.faint} l="Neintrați" v={stats ? stats.sold - stats.entered : undefined} />
            <Legend c={accent.base} l="Vândute" v={stats?.sold} />
          </View>
        </View>

        <View style={styles.kpis}>
          <KpiCard value={stats ? String(stats.sold) : '—'} label="Vândute" color={accent.base} />
          <KpiCard value={stats ? String(stats.entered) : '—'} label="Intrați" />
          <KpiCard value={stats?.revenue ? `${Math.round(stats.revenue / 1000)}k` : '—'} label="Venituri" />
        </View>

        <SectionTitle>Export</SectionTitle>
        <Card style={styles.exportRow}>
          <Text style={styles.exportText}>⭳  Export CSV — participanți / vânzări / staff</Text>
        </Card>
      </ScrollView>
    </SafeAreaView>
  );
}

function Legend({ c, l, v }: { c: string; l: string; v?: number }) {
  return (
    <View style={styles.legend}>
      <View style={[styles.dot, { backgroundColor: c }]} />
      <Text style={styles.legendLabel}>{l}</Text>
      <Text style={styles.legendVal}>{v != null ? v.toLocaleString('ro-RO') : '—'}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: palette.bg },
  topbar: { paddingHorizontal: 16, paddingTop: 10, paddingBottom: 8 },
  title: { color: palette.text, fontSize: 20, fontWeight: '900' },
  body: { paddingHorizontal: 15, paddingBottom: 24, gap: 12 },
  picker: { gap: 7, paddingRight: 12 },
  chip: {
    fontSize: 12,
    fontWeight: '800',
    paddingHorizontal: 13,
    paddingVertical: 8,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: palette.border2,
    backgroundColor: palette.surface2,
    overflow: 'hidden',
  },
  empty: { color: palette.muted, fontSize: 12 },
  ring: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 16,
    backgroundColor: palette.surface,
    borderWidth: 1,
    borderColor: palette.border2,
    borderRadius: radius.lg,
    padding: 16,
  },
  gauge: {
    width: 92,
    height: 92,
    borderRadius: 46,
    borderWidth: 6,
    alignItems: 'center',
    justifyContent: 'center',
  },
  gaugeVal: { color: palette.text, fontSize: 20, fontWeight: '900' },
  gaugeLabel: { color: palette.hint, fontSize: 8.5 },
  legend: { flexDirection: 'row', alignItems: 'center', gap: 7 },
  dot: { width: 8, height: 8, borderRadius: 3 },
  legendLabel: { color: palette.muted, fontSize: 11, flex: 1 },
  legendVal: { color: palette.text, fontSize: 11, fontWeight: '800' },
  kpis: { flexDirection: 'row', gap: 9 },
  exportRow: { padding: 14 },
  exportText: { color: palette.text, fontSize: 12.5, fontWeight: '600' },
});
