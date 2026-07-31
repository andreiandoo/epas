/** Per-context tab configuration (matches the design's tenant-specific navs). */
import type React from 'react';
import type { ContextKind } from '@/theme/colors';

import HomeScreen from '@/screens/HomeScreen';
import ScanScreen from '@/screens/ScanScreen';
import SalesScreen from '@/screens/SalesScreen';
import ReportsScreen from '@/screens/ReportsScreen';
import SettingsScreen from '@/screens/SettingsScreen';

export interface TabDef {
  key: string;
  label: string;
  icon: string;
  component: React.ComponentType<Record<string, unknown>>;
}

const HOME: TabDef = { key: 'home', label: 'Home', icon: '⌂', component: HomeScreen };
const SCAN: TabDef = { key: 'scan', label: 'Scanare', icon: '▣', component: ScanScreen };
const SALES: TabDef = { key: 'sales', label: 'Vânzări', icon: '≡', component: SalesScreen };
const REPORTS: TabDef = { key: 'reports', label: 'Rapoarte', icon: '▤', component: ReportsScreen };
const SETTINGS: TabDef = { key: 'settings', label: 'Setări', icon: '⚙', component: SettingsScreen };

/**
 * Returns the bottom-tab set for a context. Foundation keeps the core five;
 * tenant-specific tabs (Abonamente, Live, Fani, Contracte…) are layered in
 * as their screens land — kept here so the shape is obvious.
 */
export function navTabsFor(kind: ContextKind): TabDef[] {
  switch (kind) {
    case 'venue':
      return [HOME, { ...SCAN, key: 'live', label: 'Live', icon: '⚡' }, SALES, REPORTS, SETTINGS];
    case 'theatre':
    case 'artist':
    case 'agency':
    case 'festival':
    case 'organizer':
    case 'tenant':
    default:
      return [HOME, SCAN, SALES, REPORTS, SETTINGS];
  }
}
