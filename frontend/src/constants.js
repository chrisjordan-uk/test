export const STATUSES = [
  { value: 'available', label: 'Available', color: 'bg-slate-100 text-slate-700' },
  { value: 'listed', label: 'Listed', color: 'bg-sky-100 text-sky-700' },
  { value: 'to_ship', label: 'To Ship', color: 'bg-amber-100 text-amber-700' },
  { value: 'shipped', label: 'Shipped', color: 'bg-indigo-100 text-indigo-700' },
  { value: 'sold', label: 'Sold', color: 'bg-emerald-100 text-emerald-700' },
  { value: 'returned', label: 'Returned', color: 'bg-orange-100 text-orange-700' },
  { value: 'rejected', label: 'Rejected', color: 'bg-red-100 text-red-700' },
];

export const STATUS_MAP = Object.fromEntries(STATUSES.map((s) => [s.value, s]));

export const CONDITIONS = [
  'New with tags',
  'New without tags',
  'Very good',
  'Good',
  'Satisfactory',
];

export const NAV_ITEMS = [
  { to: '/', label: 'Home', feature: 'dashboard', icon: 'home' },
  { to: '/inventory', label: 'Inventory', feature: 'inventory', icon: 'box' },
  { to: '/products', label: 'Products', feature: 'products', icon: 'tag' },
  { to: '/profit', label: 'Profit', feature: 'profit', icon: 'chart' },
  { to: '/users', label: 'Users & Roles', feature: 'users', icon: 'users' },
];

export function formatMoney(value) {
  const n = Number(value || 0);
  return n.toLocaleString('en-US', { style: 'currency', currency: 'USD' });
}

export function formatDate(value) {
  if (!value) return '—';
  return new Date(value).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}
