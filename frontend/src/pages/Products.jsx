import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';
import { formatMoney, STATUSES } from '../constants';
import StatusBadge from '../components/StatusBadge';
import Icon from '../components/Icon';
import ProductFormModal from '../components/ProductFormModal';

export default function Products() {
  const { can } = useAuth();
  const canManage = can('products', 'manage');

  const [products, setProducts] = useState([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [editing, setEditing] = useState(undefined); // undefined = closed, null = create, obj = edit
  const [deletingId, setDeletingId] = useState(null);

  function load() {
    setLoading(true);
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    return api
      .get(`/products?${params.toString()}`)
      .then((data) => setProducts(data.products))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }

  useEffect(() => {
    const t = setTimeout(load, 200);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  async function handleStatusChange(product, status) {
    setProducts((prev) => prev.map((p) => (p.id === product.id ? { ...p, status } : p)));
    try {
      await api.patch(`/products/${product.id}/status`, { status });
    } catch (err) {
      setError(err.message);
      load();
    }
  }

  async function handleDelete(id) {
    setDeletingId(null);
    try {
      await api.delete(`/products/${id}`);
      setProducts((prev) => prev.filter((p) => p.id !== id));
    } catch (err) {
      setError(err.message);
    }
  }

  function handleSaved(product) {
    setProducts((prev) => {
      const exists = prev.some((p) => p.id === product.id);
      return exists ? prev.map((p) => (p.id === product.id ? product : p)) : [product, ...prev];
    });
    setEditing(undefined);
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Products</h1>
          <p className="mt-1 text-sm text-slate-500">Add new stock and manage each item's status.</p>
        </div>
        <div className="flex items-center gap-2">
          <div className="relative">
            <Icon name="search" className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
            <input
              className="input w-56 pl-9"
              placeholder="Search products…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          {canManage && (
            <button className="btn-primary" onClick={() => setEditing(null)}>
              <Icon name="plus" className="h-4 w-4" />
              Add product
            </button>
          )}
        </div>
      </div>

      {error && <p className="rounded-lg bg-red-50 p-4 text-sm text-red-600">{error}</p>}

      <div className="card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-slate-50">
              <tr className="text-xs uppercase tracking-wide text-slate-500">
                <th className="px-4 py-3">Product #</th>
                <th className="px-4 py-3">Name / Brand</th>
                <th className="px-4 py-3">Size</th>
                <th className="px-4 py-3">Bought</th>
                <th className="px-4 py-3">Sold</th>
                <th className="px-4 py-3">Status</th>
                {canManage && <th className="px-4 py-3 text-right">Actions</th>}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {products.map((p) => (
                <tr key={p.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3 font-mono text-xs text-slate-500">{p.product_number}</td>
                  <td className="px-4 py-3">
                    <p className="font-medium text-slate-800">{p.name}</p>
                    <p className="text-xs text-slate-500">{p.brand || '—'}</p>
                  </td>
                  <td className="px-4 py-3 text-slate-600">{p.size || '—'}</td>
                  <td className="px-4 py-3 text-slate-600">{formatMoney(p.bought_price)}</td>
                  <td className="px-4 py-3 text-slate-600">
                    {p.sold_price != null ? formatMoney(p.sold_price) : '—'}
                  </td>
                  <td className="px-4 py-3">
                    {canManage ? (
                      <select
                        className="rounded-lg border-0 bg-transparent py-1 text-xs font-medium focus:ring-2 focus:ring-brand-200"
                        value={p.status}
                        onChange={(e) => handleStatusChange(p, e.target.value)}
                      >
                        {STATUSES.map((s) => (
                          <option key={s.value} value={s.value}>{s.label}</option>
                        ))}
                      </select>
                    ) : (
                      <StatusBadge status={p.status} />
                    )}
                  </td>
                  {canManage && (
                    <td className="px-4 py-3">
                      <div className="flex justify-end gap-1">
                        <button
                          className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                          onClick={() => setEditing(p)}
                          aria-label="Edit"
                        >
                          <Icon name="edit" className="h-4 w-4" />
                        </button>
                        <button
                          className="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
                          onClick={() => setDeletingId(p.id)}
                          aria-label="Delete"
                        >
                          <Icon name="trash" className="h-4 w-4" />
                        </button>
                      </div>
                    </td>
                  )}
                </tr>
              ))}
              {!loading && products.length === 0 && (
                <tr>
                  <td colSpan={canManage ? 7 : 6} className="px-4 py-10 text-center text-slate-400">
                    No products yet.{canManage && ' Click "Add product" to create one.'}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {editing !== undefined && (
        <ProductFormModal
          product={editing}
          onClose={() => setEditing(undefined)}
          onSaved={handleSaved}
        />
      )}

      {deletingId != null && (
        <ConfirmDeleteModal
          onCancel={() => setDeletingId(null)}
          onConfirm={() => handleDelete(deletingId)}
        />
      )}
    </div>
  );
}

function ConfirmDeleteModal({ onCancel, onConfirm }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm">
      <div className="card w-full max-w-sm p-6">
        <h2 className="text-lg font-semibold text-slate-900">Delete product?</h2>
        <p className="mt-2 text-sm text-slate-500">
          This will permanently remove the product. This can't be undone.
        </p>
        <div className="mt-5 flex justify-end gap-2">
          <button className="btn-secondary" onClick={onCancel}>Cancel</button>
          <button className="btn-danger" onClick={onConfirm}>Delete</button>
        </div>
      </div>
    </div>
  );
}
