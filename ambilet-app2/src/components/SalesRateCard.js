import React, { useEffect, useState, useMemo } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { colors } from '../theme/colors';
import { useApp } from '../context/AppContext';

// Live sales rate — computes bilete/min din ultimele 10min pe baza
// `recentSales` (local per-device). Compară cu fereastra anterioară
// (10-20min în urmă) pentru direcție (up / down / flat).
// NB: strict per-device — dacă un alt operator vinde pe alt telefon,
// nu se reflectă. Label-ul "Ritm vânzare" ține de contextul local.

const WINDOW_MS = 10 * 60_000;

function countSalesInWindow(sales, windowStart, windowEnd) {
  return sales.reduce((n, s) => {
    const t = s.timestamp || s.at || 0;
    if (t >= windowStart && t < windowEnd) {
      return n + (s.quantity || 1);
    }
    return n;
  }, 0);
}

export default function SalesRateCard() {
  const { recentSales } = useApp();
  const [tick, setTick] = useState(0);

  // Re-tick every 30s so the trailing window slides without
  // waiting for a new sale to trigger a re-render.
  useEffect(() => {
    const id = setInterval(() => setTick(t => t + 1), 30_000);
    return () => clearInterval(id);
  }, []);

  const { currentRate, prevRate, deltaPct, direction } = useMemo(() => {
    const now = Date.now();
    const currentCount = countSalesInWindow(recentSales, now - WINDOW_MS, now);
    const prevCount = countSalesInWindow(recentSales, now - 2 * WINDOW_MS, now - WINDOW_MS);
    const rate = currentCount / 10; // per minute
    const prev = prevCount / 10;
    let delta = 0;
    if (prev > 0) delta = ((rate - prev) / prev) * 100;
    else if (rate > 0) delta = 100; // brand-new activity
    const dir = rate > prev + 0.05 ? 'up' : rate < prev - 0.05 ? 'down' : 'flat';
    return { currentRate: rate, prevRate: prev, deltaPct: delta, direction: dir };
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [recentSales, tick]);

  // Only show the card once at least one sale has landed — a "0.0/min"
  // reading before opening is noise. After the first sale we always render.
  if (recentSales.length === 0) return null;

  const arrowColor = direction === 'up' ? colors.green
    : direction === 'down' ? colors.red
    : colors.textTertiary;
  const arrowPath = direction === 'up' ? 'M12 5v14m0-14l-6 6m6-6l6 6'
    : direction === 'down' ? 'M12 19V5m0 14l-6-6m6 6l6-6'
    : 'M5 12h14';

  const rateLabel = currentRate < 0.05 ? '0'
    : currentRate < 10 ? currentRate.toFixed(1)
    : String(Math.round(currentRate));

  const deltaLabel = prevRate === 0 && currentRate === 0 ? '—'
    : direction === 'flat' ? 'stabil'
    : `${direction === 'down' ? '−' : '+'}${Math.abs(deltaPct).toFixed(0)}%`;

  return (
    <View style={styles.card}>
      <View style={styles.headerRow}>
        <Text style={styles.label}>Ritm vânzare</Text>
        <View style={[styles.deltaChip, direction === 'up' && styles.deltaChipUp, direction === 'down' && styles.deltaChipDown]}>
          <Svg width={11} height={11} viewBox="0 0 24 24" fill="none">
            <Path d={arrowPath} stroke={arrowColor} strokeWidth={2.5} strokeLinecap="round" strokeLinejoin="round" />
          </Svg>
          <Text style={[styles.deltaText, { color: arrowColor }]}>{deltaLabel}</Text>
        </View>
      </View>
      <View style={styles.valueRow}>
        <Text style={styles.valueBig}>{rateLabel}</Text>
        <Text style={styles.valueUnit}>bilete / min</Text>
      </View>
      <Text style={styles.subLabel}>
        {`ultimele 10 min · vs ${prevRate.toFixed(1)}/min anterior`}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 14,
    padding: 14,
    marginBottom: 12,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 4,
  },
  label: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.textSecondary,
    textTransform: 'uppercase',
    letterSpacing: 0.4,
  },
  deltaChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 999,
    backgroundColor: colors.background,
    borderWidth: 1,
    borderColor: colors.border,
  },
  deltaChipUp: {
    backgroundColor: colors.greenLight,
    borderColor: colors.greenBorder,
  },
  deltaChipDown: {
    backgroundColor: colors.redLight,
    borderColor: colors.redBorder,
  },
  deltaText: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 0.2,
  },
  valueRow: {
    flexDirection: 'row',
    alignItems: 'baseline',
    gap: 6,
    marginTop: 2,
  },
  valueBig: {
    fontSize: 30,
    fontWeight: '800',
    color: colors.textPrimary,
    letterSpacing: -0.5,
    fontVariant: ['tabular-nums'],
  },
  valueUnit: {
    fontSize: 13,
    color: colors.textSecondary,
    fontWeight: '600',
  },
  subLabel: {
    fontSize: 11,
    color: colors.textTertiary,
    marginTop: 4,
  },
});
