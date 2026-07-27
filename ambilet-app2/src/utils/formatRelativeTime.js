// Compact Romanian relative time (for the "acum 12s" sync indicator).
// Rounds to seconds/minutes/hours — no need for full i18n here.
export function formatRelativeTime(timestamp) {
  if (!timestamp) return '';
  const diff = Math.max(0, Date.now() - timestamp);
  const s = Math.floor(diff / 1000);
  if (s < 5) return 'acum';
  if (s < 60) return `acum ${s}s`;
  const m = Math.floor(s / 60);
  if (m < 60) return `acum ${m}m`;
  const h = Math.floor(m / 60);
  if (h < 24) return `acum ${h}h`;
  const d = Math.floor(h / 24);
  return `acum ${d}z`;
}
