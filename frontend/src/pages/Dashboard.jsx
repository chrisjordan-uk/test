import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  ResponsiveContainer,
  CartesianGrid,
} from 'recharts';
import { api } from '../api/client';
import { formatMoney, formatDate, STATUS_MAP } from '../constants';
import StatusBadge from '../components/StatusBadge';
import Icon from '../components/Icon';

function StatCard({ label, value, sub, icon, tone = 'brand' }) {
  const tones = {
    brand: 'bg-brand-50 text-brand-600',
    emerald: 'bg-emerald-50 text-emerald-600',
    amber: 'bg-amber-50 text-amber-600',
    slate: 'bg-slate-100 text-slate-600',
  };
  return (
    <div className="card p-5">
      <div className="flex items-start justify-between">
        <div>
          <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</p>
          <p className="mt-2 text-2xl font-bold text-slate-900">{value}</p>
          {sub && <p className="mt-1 text-xs text-slate-500">{sub}</p>}
        </div>
        <div className={`flex h-10 w-10 items-center justify-center rounded-xl ${tones[tone]}`}>
          <Icon name={icon} className="h-5 w-5" />
        </div>
      </div>
    </div>
  );
}

export default function Dashboard() {
  const [stats, setStats] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api
      .get('/dashboard/stats')
      .then(setStats)
      .catch((err) => setError(err.message));
  }, []);

  if (error) return <p className="rounded-lg bg-red-50 p-4 text-sm text-red-600">{error}</p>;
  if (!stats) return <p className="text-slate-400">Loading dashboard…</p>;

  const statusChartData = Object.entries(stats.statusCounts).map(([status, count]) => ({
    status: STATUS_MAP[status]?.label || status,
    count,
  }));

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">Home</h1>
        <p className="mt-1 text-sm text-slate-500">
          A quick overview of your Vinted resell business.
        </p>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label="Total products"
          value={stats.totalProducts}
          sub={`${stats.inventory.count} currently in stock`}
          icon="box"
        />
        <StatCard
          label="Inventory value"
          value={formatMoney(stats.inventory.value)}
          sub="Cost of items not yet sold"
          icon="tag"
          tone="slate"
        />
        <StatCard
          label="Profit this month"
          value={formatMoney(stats.profit.month.profit)}
          sub={`${formatMoney(stats.profit.month.revenue)} revenue`}
          icon="chart"
          tone="emerald"
        />
        <StatCard
          label="Profit this year"
          value={formatMoney(stats.profit.year.profit)}
          sub={`${formatMoney(stats.profit.year.revenue)} revenue`}
          icon="chart"
          tone="amber"
        />
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="card p-5 lg:col-span-2">
          <h2 className="mb-4 text-sm font-semibold text-slate-900">Products by status</h2>
          <ResponsiveContainer width="100%" height={260}>
            <BarChart data={statusChartData} margin={{ left: -20 }}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
              <XAxis dataKey="status" tick={{ fontSize: 12 }} stroke="#94a3b8" />
              <YAxis allowDecimals={false} tick={{ fontSize: 12 }} stroke="#94a3b8" />
              <Tooltip cursor={{ fill: '#f1f5f9' }} />
              <Bar dataKey="count" fill="#4b63f6" radius={[6, 6, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>

        <div className="card p-5">
          <h2 className="mb-4 text-sm font-semibold text-slate-900">Top brands</h2>
          <ul className="space-y-3">
            {stats.topBrands.length === 0 && (
              <p className="text-sm text-slate-400">No products yet.</p>
            )}
            {stats.topBrands.map((b) => (
              <li key={b.brand} className="flex items-center justify-between text-sm">
                <span className="font-medium text-slate-700">{b.brand}</span>
                <span className="badge bg-slate-100 text-slate-600">{b.count}</span>
              </li>
            ))}
          </ul>
        </div>
      </div>

      <div className="card p-5">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-sm font-semibold text-slate-900">Recently updated products</h2>
          <Link to="/inventory" className="text-sm font-medium text-brand-600 hover:text-brand-700">
            View inventory →
          </Link>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="text-xs uppercase tracking-wide text-slate-400">
                <th className="pb-2 pr-4">Product</th>
                <th className="pb-2 pr-4">Brand</th>
                <th className="pb-2 pr-4">Status</th>
                <th className="pb-2 pr-4">Bought</th>
                <th className="pb-2 pr-4">Sold</th>
                <th className="pb-2">Updated</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {stats.recentProducts.map((p) => (
                <tr key={p.id}>
                  <td className="py-2 pr-4 font-medium text-slate-800">{p.name}</td>
                  <td className="py-2 pr-4 text-slate-600">{p.brand || '—'}</td>
                  <td className="py-2 pr-4"><StatusBadge status={p.status} /></td>
                  <td className="py-2 pr-4 text-slate-600">{formatMoney(p.bought_price)}</td>
                  <td className="py-2 pr-4 text-slate-600">
                    {p.sold_price != null ? formatMoney(p.sold_price) : '—'}
                  </td>
                  <td className="py-2 text-slate-500">{formatDate(p.updated_at)}</td>
                </tr>
              ))}
              {stats.recentProducts.length === 0 && (
                <tr>
                  <td colSpan={6} className="py-6 text-center text-slate-400">
                    No products yet — add your first one from the Products page.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
