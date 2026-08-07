/* =========================================================
   TIXELLO — set de iconite SVG inline (§4.4)
   Extras 1:1 din <defs> al prototipului organizer-app.html.
   Folosire: <Icon name="bell" size={20} />  — stroke = currentColor.
   ATENTIE: fisier generat. Nu edita manual iconitele existente.
   ========================================================= */
import type { CSSProperties } from 'react';

export const ICON_NAMES = [
  "bell",
  "scan",
  "cart",
  "grid",
  "chart",
  "cog",
  "cal",
  "people",
  "user",
  "band",
  "wallet",
  "ticket",
  "cash",
  "card",
  "check",
  "checkc",
  "x",
  "xc",
  "plus",
  "minus",
  "clock",
  "chev",
  "back",
  "star",
  "door",
  "in",
  "alert",
  "search",
  "mail",
  "pin",
  "logout",
  "trend",
  "nfc",
  "list",
  "edit",
  "trash",
  "download",
  "phone",
  "camera",
  "mic",
  "pause",
  "play",
  "book",
  "swap",
  "boat",
  "info",
] as const;

export type IconName = (typeof ICON_NAMES)[number];

/** Sprite-ul se monteaza o singura data, in radacina aplicatiei. */
export function IconSprite() {
  return (
    <svg width={0} height={0} style={{ position: 'absolute' }} aria-hidden="true">
      <defs>
        <g id="i-bell"><path d="M18 8A6 6 0 1 0 6 8c0 7-3 9-3 9h18s-3-2-3-9zM13.7 21a2 2 0 0 1-3.4 0" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"/></g>
        <g id="i-scan"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/><path d="M7 12h10" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/></g>
        <g id="i-cart"><circle cx="9" cy="21" r="1.6" fill="currentColor"/><circle cx="19" cy="21" r="1.6" fill="currentColor"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></g>
        <g id="i-grid"><rect x="3" y="3" width="7" height="7" rx="1.5" fill="none" stroke="currentColor" strokeWidth="2"/><rect x="14" y="3" width="7" height="7" rx="1.5" fill="none" stroke="currentColor" strokeWidth="2"/><rect x="14" y="14" width="7" height="7" rx="1.5" fill="none" stroke="currentColor" strokeWidth="2"/><rect x="3" y="14" width="7" height="7" rx="1.5" fill="none" stroke="currentColor" strokeWidth="2"/></g>
        <g id="i-chart"><path d="M18 20V10M12 20V4M6 20v-6" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/></g>
        <g id="i-cog"><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" strokeWidth="2"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 6 19.4l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0-1.1-2.7H2a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 3.2 6l-.1-.1A2 2 0 1 1 5.9 3l.1.1A1.6 1.6 0 0 0 8.6 4.2V4a2 2 0 1 1 4 0v.1A1.6 1.6 0 0 0 15.4 5l.1-.1A2 2 0 1 1 18.3 8l-.1.1a1.6 1.6 0 0 0 1.1 2.7H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z" fill="none" stroke="currentColor" strokeWidth="1.6"/></g>
        <g id="i-cal"><rect x="3" y="4" width="18" height="18" rx="2" fill="none" stroke="currentColor" strokeWidth="2"/><path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/></g>
        <g id="i-people"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/><circle cx="9" cy="7" r="4" fill="none" stroke="currentColor" strokeWidth="2"/><path d="M23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/></g>
        <g id="i-user"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/><circle cx="12" cy="7" r="4" fill="none" stroke="currentColor" strokeWidth="2"/></g>
        <g id="i-band"><rect x="3" y="8" width="18" height="8" rx="4" fill="none" stroke="currentColor" strokeWidth="1.9"/><rect x="9" y="5.5" width="6" height="13" rx="2" fill="none" stroke="currentColor" strokeWidth="1.9"/><path d="M11.5 12h1" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round"/></g>
        <g id="i-wallet"><path d="M20 12V8H6a2 2 0 0 1 0-4h12v4M4 6v12a2 2 0 0 0 2 2h14v-4M18 12a2 2 0 0 0 0 4h4v-4Z" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round"/></g>
        <g id="i-ticket"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z" fill="none" stroke="currentColor" strokeWidth="2"/><path d="M13 5v2M13 17v2M13 11v2" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/></g>
        <g id="i-cash"><line x1="12" y1="1" x2="12" y2="23" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/></g>
        <g id="i-card"><rect x="1" y="4" width="22" height="16" rx="2" fill="none" stroke="currentColor" strokeWidth="2"/><line x1="1" y1="10" x2="23" y2="10" stroke="currentColor" strokeWidth="2"/></g>
        <g id="i-check"><path d="M20 6 9 17l-5-5" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"/></g>
        <g id="i-checkc"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" strokeWidth="2"/><path d="M8 12l3 3 5-6" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></g>
        <g id="i-x"><line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"/><line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"/></g>
        <g id="i-xc"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" strokeWidth="2"/><path d="M15 9l-6 6M9 9l6 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/></g>
        <g id="i-plus"><line x1="12" y1="5" x2="12" y2="19" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"/><line x1="5" y1="12" x2="19" y2="12" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"/></g>
        <g id="i-minus"><line x1="5" y1="12" x2="19" y2="12" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"/></g>
        <g id="i-clock"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" strokeWidth="2"/><path d="M12 7v5l3 2" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/></g>
        <g id="i-chev"><path d="M9 18l6-6-6-6" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></g>
        <g id="i-back"><path d="M15 18l-6-6 6-6" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></g>
        <g id="i-star"><path d="M12 2l3 6.5 7 .8-5 4.8 1.3 7L12 18l-6.3 3.1L7 14.1 2 9.3l7-.8z" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round"/></g>
        <g id="i-door"><path d="M13 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 12h.01M13 3v18M9 8l-4 4 4 4" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round"/></g>
        <g id="i-in"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round"/></g>
        <g id="i-alert"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinejoin="round"/><path d="M12 9v4M12 17h.01" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round"/></g>
        <g id="i-search"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" strokeWidth="2"/><path d="M21 21l-4-4" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/></g>
        <g id="i-mail"><rect x="2" y="4" width="20" height="16" rx="2" fill="none" stroke="currentColor" strokeWidth="2"/><path d="m2 6 10 7 10-7" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/></g>
        <g id="i-pin"><path d="M12 21s-7-5.7-7-11a7 7 0 0 1 14 0c0 5.3-7 11-7 11z" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinejoin="round"/><circle cx="12" cy="10" r="2.5" fill="none" stroke="currentColor" strokeWidth="1.9"/></g>
        <g id="i-logout"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round"/></g>
        <g id="i-trend"><path d="M23 6l-9.5 9.5-5-5L1 18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/><path d="M17 6h6v6" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></g>
        <g id="i-nfc"><path d="M6 8.7A9 9 0 0 1 18 8.7M9 11.5a5 5 0 0 1 6 0M12 14.3v.01" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round"/></g>
        <g id="i-list"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/></g>
        <g id="i-edit"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round"/><path d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4z" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinejoin="round"/></g>
        <g id="i-trash"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round"/></g>
        <g id="i-download"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round"/></g>
        <g id="i-phone"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinejoin="round"/></g>
        <g id="i-camera"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinejoin="round"/><circle cx="12" cy="13" r="4" fill="none" stroke="currentColor" strokeWidth="1.9"/></g>
        <g id="i-mic"><rect x="9" y="2" width="6" height="12" rx="3" fill="none" stroke="currentColor" strokeWidth="1.9"/><path d="M5 10a7 7 0 0 0 14 0M12 19v3" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round"/></g>
        <g id="i-pause"><rect x="6" y="4" width="4" height="16" rx="1" fill="currentColor"/><rect x="14" y="4" width="4" height="16" rx="1" fill="currentColor"/></g>
        <g id="i-play"><path d="M6 4l14 8-14 8z" fill="currentColor"/></g>
        <g id="i-book"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round"/></g>
        <g id="i-swap"><path d="M17 1l4 4-4 4M3 11V9a4 4 0 0 1 4-4h14M7 23l-4-4 4-4M21 13v2a4 4 0 0 1-4 4H3" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round"/></g>
        <g id="i-boat"><path d="M2 20a3 3 0 0 0 2.5-1 3 3 0 0 1 5 0 3 3 0 0 0 5 0 3 3 0 0 1 5 0 3 3 0 0 0 2.5 1M4 18l-1.5-5.5a1 1 0 0 1 1-1.3H18a1 1 0 0 1 1 1.3L17.5 18M12 5v6M8 8h8" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"/></g>
        <g id="i-info"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" strokeWidth="1.9"/><path d="M12 16v-4M12 8h.01" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round"/></g>
      </defs>
    </svg>
  );
}

export function Icon({
  name,
  size = 20,
  className,
  style,
}: {
  name: IconName;
  size?: number;
  className?: string;
  style?: CSSProperties;
}) {
  return (
    <svg viewBox="0 0 24 24" width={size} height={size} className={className} style={style}>
      <use href={`#i-${name}`} />
    </svg>
  );
}
