// Format RON amount with 2 decimals — "12.450,00 lei". Two decimals are
// meaningful for POS + reports (e.g. commission math, small refunds).
// Callers that want a compact integer render (widgets, header pill)
// should coerce to Math.round + toLocaleString themselves — this helper
// always renders with full precision.
export function formatCurrency(amount) {
  const n = Number(amount) || 0;
  return new Intl.NumberFormat('ro-RO', {
    style: 'decimal',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n) + ' lei';
}
