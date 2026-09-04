import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import { formatMoney, formatDate, STATUSES } from '../constants';
import StatusBadge from '../components/StatusBadge';
import Icon from '../components/Icon';

export default function Inventory() {
  const [products, setProducts] = useState([]);
  const [meta, setMeta] = useState({ brands: [] });
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [brand, setBrand] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    api.get('/products/meta').then(setMeta).catch(() => {});
  }, []);

  useEffect(() => {
    setLoading(true);
    const params = new URLSearchParams();
    if (status) params.set('status', status);
    if (brand) params.set('brand', brand);
    if (search) params.set('search', search);
    api
      .get(`/products?${params.toString()}`)
      .then((data) => setProducts(data.products))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [status, brand, search]);

  const totals = useMemo(() => {
    const value = products.reduce((sum, p) => sum + Number(p.bought_price || 0), 0);
    return { count: products.length, value };
  }, [products]);

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Inventory</h1>
          <p className="mt-1 text-sm text-slate-500">
            {totals.count} products · {formatMoney(totals.value)} in cost
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <div className="relative">
            <Icon name="search" className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
            <input
              className="input w-56 pl-9"
              placeholder="Search name, brand, SKU…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          <select className="input w-40" value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">All statuses</option>
            {STATUSES.map((s) => (
              <option key={s.value} value={s.value}>{s.label}</option>
            ))}
          </select>
          <select className="input w-40" value={brand} onChange={(e) => setBrand(e.target.value)}>
            <option value="">All brands</option>
            {meta.brands.map((b) => (
              <option key={b} value={b}>{b}</option>
            ))}
          </select>
        </div>
      </div>

      {error && <p className="rounded-lg bg-red-50 p-4 text-sm text-red-600">{error}</p>}

      <div className="card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-slate-50">
              <tr className="text-xs uppercase tracking-wide text-slate-500">
                <th className="px-4 py-3">Product #</th>
                <th className="px-4 py-3">Name</th>
                <th className="px-4 py-3">Brand</th>
                <th className="px-4 py-3">Size</th>
                <th className="px-4 py-3">Condition</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3">Bought</th>
                <th className="px-4 py-3">Sold</th>
                <th className="px-4 py-3">Purchased</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {products.map((p) => (
                <tr key={p.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3 font-mono text-xs text-slate-500">{p.product_number}</td>
                  <td className="px-4 py-3 font-medium text-slate-800">{p.name}</td>
                  <td className="px-4 py-3 text-slate-600">{p.brand || '—'}</td>
                  <td className="px-4 py-3 text-slate-600">{p.size || '—'}</td>
                  <td className="px-4 py-3 text-slate-600">{p.item_condition || '—'}</td>
                  <td className="px-4 py-3"><StatusBadge status={p.status} /></td>
                  <td className="px-4 py-3 text-slate-600">{formatMoney(p.bought_price)}</td>
                  <td className="px-4 py-3 text-slate-600">
                    {p.sold_price != null ? formatMoney(p.sold_price) : '—'}
                  </td>
                  <td className="px-4 py-3 text-slate-500">{formatDate(p.purchase_date)}</td>
                </tr>
              ))}
              {!loading && products.length === 0 && (
                <tr>
                  <td colSpan={9} className="px-4 py-10 text-center text-slate-400">
                    No products match these filters.
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
