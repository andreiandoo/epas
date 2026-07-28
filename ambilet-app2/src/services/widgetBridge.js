// Thin JS wrapper around the Android AmBilet home-screen widget native
// module. Silent no-op on iOS + on Android builds where the module isn't
// linked yet (dev without prebuild).
//
// Called from EventContext whenever eventStats change — updates the tile
// with a compact snapshot (event + status + sold/capacity + scanned +
// online/POS + revenue + active_events_count) and triggers an instant
// redraw. Android's system update tick (30 min minimum) still applies
// when the app is closed; while the app is running, this bridge keeps
// the widget within ~30 seconds of fresh data.
import { NativeModules, Platform } from 'react-native';
import { pickString } from '../utils/pickString';

const Widget = Platform.OS === 'android' ? NativeModules?.AmbiletWidget : null;

const STATUS_LABEL = {
  live: 'LIVE',
  today: 'AZI',
  future: 'URMEAZĂ',
  past: 'TRECUT',
};

/**
 * Push the current event snapshot to the Android widget SharedPreferences
 * and trigger an immediate redraw. Safe to call as often as you like —
 * cheap (single SharedPreferences write + local broadcast).
 *
 * @param {object} params
 * @param {object} params.event               - selected event object (has .name, .title, .timeCategory)
 * @param {object} params.stats               - eventStats object (total_sold, capacity, checked_in, online_count, door_count, revenue)
 * @param {number} params.activeEventsCount   - how many live/today/future events the organizer currently has
 */
export function pushWidgetSnapshot({ event, stats, activeEventsCount = 0 } = {}) {
  if (!Widget?.updateWidget) return;
  try {
    const eventName = pickString(event?.name || event?.title, 'AmBilet');
    const status = STATUS_LABEL[event?.timeCategory] || '';
    const payload = {
      event_name: eventName,
      event_status: status,
      sold_total: Number(stats?.total_sold ?? 0) || 0,
      capacity: Number(stats?.capacity ?? 0) || 0,
      scanned: Number(stats?.checked_in ?? 0) || 0,
      online_count: Number(stats?.online_count ?? 0) || 0,
      door_count: Number(stats?.door_count ?? 0) || 0,
      revenue_total: Math.round(Number(stats?.revenue ?? 0)) || 0,
      active_events_count: Number(activeEventsCount) || 0,
    };
    Widget.updateWidget(payload);
  } catch {
    // Best-effort — a failed widget update shouldn't affect the app.
  }
}
