import { useEffect, useState } from 'react';
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  Tooltip,
  ResponsiveContainer,
  CartesianGrid,
} from 'recharts';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';
import { formatMoney, formatDate } from '../constants';
import Icon from '../components/Icon';
import Modal from '../components/Modal';

const TYPE_LABELS = {
  purchase: { label: 'Stock purchase', color: 'bg-amber-100 text-amber-700' },
  sale: { label: 'Sale', color: 'bg-emerald-100 text-emerald-700' },
  expense: { label: 'Expense', color: 'bg-red-100 text-red-700' },
};

function StatCard({ label, value, tone = 'text-slate-900' }) {
  return (
    <div className="card p-5">
      <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</p>
      <p className={`mt-2 text-2xl font-bold ${tone}`}>{value}</p>
    </div>
  );
}

export default function Profit() {
  const { can } = useAuth();
  const canManage = can('profit', 'manage');

  const [groupBy, setGroupBy] = useState('month');
  const [summary, setSummary] = useState(null);
  const [transactions, setTransactions] = useState([]);
  const [error, setError] = useState('');
  const [showForm, setShowForm] = useState(false);

  function loadSummary() {
    api.get(`/profit/summary?groupBy=${groupBy}`).then(setSummary).catch((err) => setError(err.message));
  }
  function loadTransactions() {
    api.get('/profit/transactions').then((data) => setTransactions(data.transactions)).catch((err) => setError(err.message));
  }

  useEffect(loadSummary, [groupBy]);
  useEffect(loadTransactions, []);

  async function handleDelete(id) {
    try {
      await api.delete(`/profit/transactions/${id}`);
      setTransactions((prev) => prev.filter((t) => t.id !== id));
      loadSummary();
    } catch (err) {
      setError(err.message);
    }
  }

  const chartData = summary ? [...summary.periods].reverse() : [];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Profit</h1>
          <p className="mt-1 text-sm text-slate-500">
            Track stock purchases, sales and expenses — profit is calculated automatically.
          </p>
        </div>
        {canManage && (
          <button className="btn-primary" onClick={() => setShowForm(true)}>
            <Icon name="plus" className="h-4 w-4" />
            Add entry
          </button>
        )}
      </div>

      {error && <p className="rounded-lg bg-red-50 p-4 text-sm text-red-600">{error}</p>}

      {summary && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <StatCard label="Total revenue" value={formatMoney(summary.totals.revenue)} />
          <StatCard
            label="Total cost & expenses"
            value={formatMoney(summary.totals.cost + summary.totals.expenses)}
          />
          <StatCard
            label="Net profit"
            value={formatMoney(summary.totals.profit)}
            tone={summary.totals.profit >= 0 ? 'text-emerald-600' : 'text-red-600'}
          />
        </div>
      )}

      <div className="card p-5">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-sm font-semibold text-slate-900">Profit over time</h2>
          <div className="flex gap-1 rounded-lg bg-slate-100 p-1 text-sm">
            {['month', 'year'].map((g) => (
              <button
                key={g}
                onClick={() => setGroupBy(g)}
                className={`rounded-md px-3 py-1 font-medium capitalize transition-colors ${
                  groupBy === g ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-500'
                }`}
              >
                {g}ly
              </button>
            ))}
          </div>
        </div>
        <ResponsiveContainer width="100%" height={260}>
          <LineChart data={chartData} margin={{ left: -20 }}>
            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
            <XAxis dataKey="period" tick={{ fontSize: 12 }} stroke="#94a3b8" />
            <YAxis tick={{ fontSize: 12 }} stroke="#94a3b8" />
            <Tooltip formatter={(v) => formatMoney(v)} />
            <Line type="monotone" dataKey="profit" stroke="#4b63f6" strokeWidth={2} dot={false} />
            <Line type="monotone" dataKey="revenue" stroke="#10b981" strokeWidth={1.5} dot={false} strokeDasharray="4 3" />
          </LineChart>
        </ResponsiveContainer>
      </div>

      <div className="card overflow-hidden">
        <div className="border-b border-slate-100 px-5 py-4">
          <h2 className="text-sm font-semibold text-slate-900">Ledger</h2>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-slate-50">
              <tr className="text-xs uppercase tracking-wide text-slate-500">
                <th className="px-4 py-3">Date</th>
                <th className="px-4 py-3">Type</th>
                <th className="px-4 py-3">Description</th>
                <th className="px-4 py-3">Qty</th>
                <th className="px-4 py-3">Amount</th>
                {canManage && <th className="px-4 py-3 text-right">Actions</th>}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {transactions.map((t) => (
                <tr key={t.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3 text-slate-500">{formatDate(t.transaction_date)}</td>
                  <td className="px-4 py-3">
                    <span className={`badge ${TYPE_LABELS[t.type].color}`}>{TYPE_LABELS[t.type].label}</span>
                  </td>
                  <td className="px-4 py-3 text-slate-700">{t.description}</td>
                  <td className="px-4 py-3 text-slate-600">{t.quantity}</td>
                  <td className="px-4 py-3 font-medium text-slate-800">
                    {formatMoney(t.amount * t.quantity)}
                  </td>
                  {canManage && (
                    <td className="px-4 py-3 text-right">
                      {t.product_id ? (
                        <span className="text-xs text-slate-400" title="Linked to a product — edit it from the Products page">
                          Auto
                        </span>
                      ) : (
                        <button
                          className="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
                          onClick={() => handleDelete(t.id)}
                          aria-label="Delete"
                        >
                          <Icon name="trash" className="h-4 w-4" />
                        </button>
                      )}
                    </td>
                  )}
                </tr>
              ))}
              {transactions.length === 0 && (
                <tr>
                  <td colSpan={canManage ? 6 : 5} className="px-4 py-10 text-center text-slate-400">
                    No entries yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {showForm && (
        <TransactionFormModal
          onClose={() => setShowForm(false)}
          onSaved={(t) => {
            setTransactions((prev) => [t, ...prev]);
            setShowForm(false);
            loadSummary();
          }}
        />
      )}
    </div>
  );
}

function TransactionFormModal({ onClose, onSaved }) {
  const [type, setType] = useState('purchase');
  const [description, setDescription] = useState('');
  const [amount, setAmount] = useState('');
  const [quantity, setQuantity] = useState(1);
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');
    setSaving(true);
    try {
      const data = await api.post('/profit/transactions', {
        type,
        description,
        amount: Number(amount),
        quantity: Number(quantity) || 1,
        transaction_date: date,
      });
      onSaved(data.transaction);
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  }

  return (
    <Modal title="Add profit entry" onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className="label" htmlFor="type">Type</label>
          <select id="type" className="input" value={type} onChange={(e) => setType(e.target.value)}>
            <option value="purchase">Stock purchase</option>
            <option value="sale">Sale</option>
            <option value="expense">Expense (shipping, fees…)</option>
          </select>
        </div>
        <div>
          <label className="label" htmlFor="description">Description</label>
          <input
            id="description"
            className="input"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="e.g. New batch of 10 t-shirts"
            required
          />
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="label" htmlFor="amount">Amount per unit ($)</label>
            <input
              id="amount"
              type="number"
              min="0"
              step="0.01"
              className="input"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              required
            />
          </div>
          <div>
            <label className="label" htmlFor="quantity">Quantity</label>
            <input
              id="quantity"
              type="number"
              min="1"
              className="input"
              value={quantity}
              onChange={(e) => setQuantity(e.target.value)}
            />
          </div>
        </div>
        <div>
          <label className="label" htmlFor="date">Date</label>
          <input id="date" type="date" className="input" value={date} onChange={(e) => setDate(e.target.value)} required />
        </div>

        {error && <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{error}</p>}

        <div className="flex justify-end gap-2">
          <button type="button" className="btn-secondary" onClick={onClose}>Cancel</button>
          <button type="submit" disabled={saving} className="btn-primary">
            {saving ? 'Saving…' : 'Add entry'}
          </button>
        </div>
      </form>
    </Modal>
  );
}
