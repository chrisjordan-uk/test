import { STATUS_MAP } from '../constants';

export default function StatusBadge({ status }) {
  const meta = STATUS_MAP[status] || { label: status, color: 'bg-slate-100 text-slate-700' };
  return <span className={`badge ${meta.color}`}>{meta.label}</span>;
}
