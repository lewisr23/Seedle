/**
 * Small bits of plant vocabulary shared by the library, the plot planner and
 * the sowing calendar, so the three pages never disagree about what a herb
 * looks like or where the year starts.
 */

export const MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
];

export const MONTHS_SHORT = ['J', 'F', 'M', 'A', 'M', 'J', 'J', 'A', 'S', 'O', 'N', 'D'];

export const TYPE_ICONS = {
  vegetable: '🥕',
  fruit: '🍓',
  herb: '🌿',
  flower: '🌼',
  tree: '🌳',
  shrub: '🪴',
};

export function typeIcon(type) {
  return TYPE_ICONS[type] || '🌱';
}

/**
 * Centimetres as a gardener would say them: 80cm, 1.2m, 3m.
 */
export function formatCm(cm) {
  if (cm === null || cm === undefined) return '';
  if (cm < 100) return `${cm}cm`;
  const metres = cm / 100;
  return `${Number.isInteger(metres) ? metres : metres.toFixed(metres < 10 ? 2 : 1).replace(/\.?0+$/, '')}m`;
}
