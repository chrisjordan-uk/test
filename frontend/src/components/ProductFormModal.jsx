import { useState } from 'react';
import Modal from './Modal';
import { api } from '../api/client';
import { STATUSES, CONDITIONS } from '../constants';

const emptyForm = {
  name: '',
  brand: '',
  product_number: '',
  category: '',
  size: '',
  color: '',
  item_condition: CONDITIONS[2],
  status: 'available',
  bought_price: '',
  sold_price: '',
  purchase_date: new Date().toISOString().slice(0, 10),
  sold_date: '',
  buyer: '',
  notes: '',
};

export default function ProductFormModal({ product, onClose, onSaved }) {
  const isEdit = !!product;
  const [form, setForm] = useState(() =>
    isEdit
      ? {
          ...emptyForm,
          ...product,
          bought_price: product.bought_price ?? '',
          sold_price: product.sold_price ?? '',
          purchase_date: product.purchase_date?.slice(0, 10) || '',
          sold_date: product.sold_date?.slice(0, 10) || '',
        }
      : emptyForm
  );
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  function update(field, value) {
    setForm((f) => ({ ...f, [field]: value }));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');
    setSaving(true);
    try {
      const payload = {
        ...form,
        bought_price: form.bought_price === '' ? 0 : Number(form.bought_price),
        sold_price: form.sold_price === '' ? null : Number(form.sold_price),
        purchase_date: form.purchase_date || null,
        sold_date: form.sold_date || null,
      };
      const saved = isEdit
        ? await api.put(`/products/${product.id}`, payload)
        : await api.post('/products', payload);
      onSaved(saved.product);
    } catch (err) {
      setError(err.message || 'Could not save product.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <Modal title={isEdit ? 'Edit product' : 'Add product'} onClose={onClose} wide>
      <form onSubmit={handleSubmit} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <label className="label" htmlFor="name">Name *</label>
          <input
            id="name"
            className="input"
            value={form.name}
            onChange={(e) => update('name', e.target.value)}
            required
          />
        </div>

        <div>
          <label className="label" htmlFor="brand">Brand</label>
          <input id="brand" className="input" value={form.brand || ''} onChange={(e) => update('brand', e.target.value)} />
        </div>
        <div>
          <label className="label" htmlFor="product_number">Product #</label>
          <input
            id="product_number"
            className="input"
            placeholder="Auto-generated if left blank"
            value={form.product_number || ''}
            onChange={(e) => update('product_number', e.target.value)}
          />
        </div>

        <div>
          <label className="label" htmlFor="category">Category</label>
          <input id="category" className="input" value={form.category || ''} onChange={(e) => update('category', e.target.value)} />
        </div>
        <div>
          <label className="label" htmlFor="size">Size</label>
          <input id="size" className="input" value={form.size || ''} onChange={(e) => update('size', e.target.value)} />
        </div>

        <div>
          <label className="label" htmlFor="color">Color</label>
          <input id="color" className="input" value={form.color || ''} onChange={(e) => update('color', e.target.value)} />
        </div>
        <div>
          <label className="label" htmlFor="item_condition">Condition</label>
          <select
            id="item_condition"
            className="input"
            value={form.item_condition || ''}
            onChange={(e) => update('item_condition', e.target.value)}
          >
            {CONDITIONS.map((c) => (
              <option key={c} value={c}>{c}</option>
            ))}
          </select>
        </div>

        <div>
          <label className="label" htmlFor="status">Status</label>
          <select id="status" className="input" value={form.status} onChange={(e) => update('status', e.target.value)}>
            {STATUSES.map((s) => (
              <option key={s.value} value={s.value}>{s.label}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="label" htmlFor="buyer">Buyer</label>
          <input id="buyer" className="input" value={form.buyer || ''} onChange={(e) => update('buyer', e.target.value)} />
        </div>

        <div>
          <label className="label" htmlFor="bought_price">Bought for ($) *</label>
          <input
            id="bought_price"
            type="number"
            min="0"
            step="0.01"
            className="input"
            value={form.bought_price}
            onChange={(e) => update('bought_price', e.target.value)}
            required
          />
        </div>
        <div>
          <label className="label" htmlFor="sold_price">Sold for ($)</label>
          <input
            id="sold_price"
            type="number"
            min="0"
            step="0.01"
            className="input"
            value={form.sold_price}
            onChange={(e) => update('sold_price', e.target.value)}
          />
        </div>

        <div>
          <label className="label" htmlFor="purchase_date">Purchase date</label>
          <input
            id="purchase_date"
            type="date"
            className="input"
            value={form.purchase_date || ''}
            onChange={(e) => update('purchase_date', e.target.value)}
          />
        </div>
        <div>
          <label className="label" htmlFor="sold_date">Sold date</label>
          <input
            id="sold_date"
            type="date"
            className="input"
            value={form.sold_date || ''}
            onChange={(e) => update('sold_date', e.target.value)}
          />
        </div>

        <div className="sm:col-span-2">
          <label className="label" htmlFor="notes">Notes</label>
          <textarea
            id="notes"
            rows={2}
            className="input"
            value={form.notes || ''}
            onChange={(e) => update('notes', e.target.value)}
          />
        </div>

        {error && (
          <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600 sm:col-span-2">{error}</p>
        )}

        <div className="flex justify-end gap-2 sm:col-span-2">
          <button type="button" onClick={onClose} className="btn-secondary">Cancel</button>
          <button type="submit" disabled={saving} className="btn-primary">
            {saving ? 'Saving…' : isEdit ? 'Save changes' : 'Add product'}
          </button>
        </div>
      </form>
    </Modal>
  );
}
