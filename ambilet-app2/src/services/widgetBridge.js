// Thin JS wrapper around the Android AmBilet home-screen widget native
// module. Silent no-op on iOS + on Android builds where the module isn't
// linked yet (dev without prebuild).
//
// Called from EventContext whenever eventStats change — updates the tile
// with the current event name + sold-today count and triggers an instant
// redraw. Android's system update tick (30 min minimum) still applies
// when the app is closed; while the app is running, this bridge keeps
// the widget within ~30 seconds of fresh data.
import { NativeModules, Platform } from 'react-native';
import { pickString } from '../utils/pickString';

const Widget = Platform.OS === 'android' ? NativeModules?.AmbiletWidget : null;

/**
 * Push the current event snapshot to the Android widget SharedPreferences
 * and trigger an immediate redraw. Safe to call as often as you like —
 * cheap (single SharedPreferences write + local broadcast).
 *
 * @param {object} event  - selected event object (has .name / .title)
 * @param {object} stats  - eventStats object (has .total_sold)
 */
export function pushWidgetSnapshot(event, stats) {
  if (!Widget?.updateWidget) return;
  try {
    const eventName = pickString(event?.name || event?.title, 'AmBilet Scan');
    // Use total_sold (agregat pe eveniment) — se aliniază cu ce afișează
    // Panoul + Rapoartele. Nu tracking pe device.
    const soldToday = Number(stats?.total_sold ?? 0) || 0;
    Widget.updateWidget(eventName, soldToday);
  } catch {
    // Best-effort — a failed widget update shouldn't affect the app.
  }
}
