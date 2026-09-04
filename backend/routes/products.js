const express = require('express');
const pool = require('../config/db');
const { requireAuth } = require('../middleware/auth');
const { requirePermission, requireAnyPermission } = require('../middleware/permissions');

const router = express.Router();

const STATUSES = ['available', 'listed', 'to_ship', 'shipped', 'sold', 'returned', 'rejected'];

const EDITABLE_FIELDS = [
  'product_number',
  'name',
  'brand',
  'category',
  'size',
  'color',
  'item_condition',
  'status',
  'bought_price',
  'sold_price',
  'purchase_date',
  'sold_date',
  'buyer',
  'notes',
  'image_url',
];

router.use(requireAuth);

// Keep the profit ledger in sync with a product's purchase/sale data.
// `conn` must be a connection already inside a transaction.
async function syncPurchaseTransaction(conn, product) {
  const [rows] = await conn.query(
    'SELECT id FROM transactions WHERE product_id = ? AND type = "purchase" LIMIT 1',
    [product.id]
  );
  const description = `Stock purchase: ${product.name}${
    product.product_number ? ` (${product.product_number})` : ''
  }`;
  const date = product.purchase_date || new Date().toISOString().slice(0, 10);
  const amount = product.bought_price || 0;

  if (rows.length) {
    await conn.query(
      'UPDATE transactions SET description = ?, amount = ?, transaction_date = ? WHERE id = ?',
      [description, amount, date, rows[0].id]
    );
  } else {
    await conn.query(
      `INSERT INTO transactions (type, product_id, description, amount, quantity, transaction_date, created_by)
       VALUES ('purchase', ?, ?, ?, 1, ?, ?)`,
      [product.id, description, amount, date, product.created_by || null]
    );
  }
}

async function syncSaleTransaction(conn, product, actorId) {
  const [rows] = await conn.query(
    'SELECT id FROM transactions WHERE product_id = ? AND type = "sale" LIMIT 1',
    [product.id]
  );

  const shouldHaveSale = product.status === 'sold' && product.sold_price != null;

  if (!shouldHaveSale) {
    if (rows.length) {
      await conn.query('DELETE FROM transactions WHERE id = ?', [rows[0].id]);
    }
    return;
  }

  const description = `Sale: ${product.name}${
    product.product_number ? ` (${product.product_number})` : ''
  }${product.buyer ? ` to ${product.buyer}` : ''}`;
  const date = product.sold_date || new Date().toISOString().slice(0, 10);

  if (rows.length) {
    await conn.query(
      'UPDATE transactions SET description = ?, amount = ?, transaction_date = ? WHERE id = ?',
      [description, product.sold_price, date, rows[0].id]
    );
  } else {
    await conn.query(
      `INSERT INTO transactions (type, product_id, description, amount, quantity, transaction_date, created_by)
       VALUES ('sale', ?, ?, ?, 1, ?, ?)`,
      [product.id, description, product.sold_price, date, actorId || null]
    );
  }
}

// GET /api/products - list, with optional filters. Shared by the Inventory
// (read-only) and Products (management) pages.
router.get('/', requireAnyPermission(['inventory', 'products'], 'view'), async (req, res) => {
  try {
    const { status, brand, search, sort = 'updated_at', dir = 'desc', limit = 500 } = req.query;
    const where = [];
    const params = [];

    if (status) {
      const statuses = String(status).split(',').filter((s) => STATUSES.includes(s));
      if (statuses.length) {
        where.push(`status IN (${statuses.map(() => '?').join(',')})`);
        params.push(...statuses);
      }
    }
    if (brand) {
      where.push('brand = ?');
      params.push(brand);
    }
    if (search) {
      where.push('(name LIKE ? OR product_number LIKE ? OR brand LIKE ?)');
      const like = `%${search}%`;
      params.push(like, like, like);
    }

    const sortColumn = EDITABLE_FIELDS.includes(sort) || sort === 'updated_at' || sort === 'created_at'
      ? sort
      : 'updated_at';
    const sortDir = String(dir).toLowerCase() === 'asc' ? 'ASC' : 'DESC';

    const sql = `SELECT * FROM products
      ${where.length ? `WHERE ${where.join(' AND ')}` : ''}
      ORDER BY ${sortColumn} ${sortDir}
      LIMIT ?`;
    params.push(Math.min(Number(limit) || 500, 2000));

    const [rows] = await pool.query(sql, params);
    res.json({ products: rows });
  } catch (err) {
    console.error('Listing products failed:', err);
    res.status(500).json({ error: 'Could not load products.' });
  }
});

// GET /api/products/meta - distinct brands/categories/sizes for filter UI.
router.get('/meta', requireAnyPermission(['inventory', 'products'], 'view'), async (req, res) => {
  try {
    const [[brands], [categories], [sizes]] = await Promise.all([
      pool.query('SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand <> "" ORDER BY brand'),
      pool.query('SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> "" ORDER BY category'),
      pool.query('SELECT DISTINCT size FROM products WHERE size IS NOT NULL AND size <> "" ORDER BY size'),
    ]);
    res.json({
      statuses: STATUSES,
      brands: brands.map((r) => r.brand),
      categories: categories.map((r) => r.category),
      sizes: sizes.map((r) => r.size),
    });
  } catch (err) {
    console.error('Loading product metadata failed:', err);
    res.status(500).json({ error: 'Could not load product filters.' });
  }
});

router.get('/:id', requireAnyPermission(['inventory', 'products'], 'view'), async (req, res) => {
  try {
    const [rows] = await pool.query('SELECT * FROM products WHERE id = ?', [req.params.id]);
    if (!rows[0]) return res.status(404).json({ error: 'Product not found.' });
    res.json({ product: rows[0] });
  } catch (err) {
    console.error('Loading product failed:', err);
    res.status(500).json({ error: 'Could not load product.' });
  }
});

router.post('/', requirePermission('products', 'manage'), async (req, res) => {
  const body = req.body || {};
  if (!body.name || body.bought_price == null) {
    return res.status(400).json({ error: 'Name and bought price are required.' });
  }
  if (body.status && !STATUSES.includes(body.status)) {
    return res.status(400).json({ error: 'Invalid status.' });
  }

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();

    const fields = EDITABLE_FIELDS.filter((f) => f in body);
    const columns = ['created_by', ...fields];
    const placeholders = columns.map(() => '?');
    const values = [req.user.sub, ...fields.map((f) => (body[f] === '' ? null : body[f]))];

    const [result] = await conn.query(
      `INSERT INTO products (${columns.join(', ')}) VALUES (${placeholders.join(', ')})`,
      values
    );
    const id = result.insertId;

    if (!body.product_number) {
      const generated = `VR-${String(id).padStart(6, '0')}`;
      await conn.query('UPDATE products SET product_number = ? WHERE id = ?', [generated, id]);
    }

    const [[product]] = await conn.query('SELECT * FROM products WHERE id = ?', [id]);
    await syncPurchaseTransaction(conn, product);
    await syncSaleTransaction(conn, product, req.user.sub);

    await conn.commit();

    const [[fresh]] = await pool.query('SELECT * FROM products WHERE id = ?', [id]);
    res.status(201).json({ product: fresh });
  } catch (err) {
    await conn.rollback();
    if (err.code === 'ER_DUP_ENTRY') {
      return res.status(409).json({ error: 'That product number is already in use.' });
    }
    console.error('Creating product failed:', err);
    res.status(500).json({ error: 'Could not create product.' });
  } finally {
    conn.release();
  }
});

async function updateProduct(req, res) {
  const body = req.body || {};
  if (body.status && !STATUSES.includes(body.status)) {
    return res.status(400).json({ error: 'Invalid status.' });
  }

  const conn = await pool.getConnection();
  try {
    const [existingRows] = await conn.query('SELECT * FROM products WHERE id = ?', [req.params.id]);
    if (!existingRows[0]) {
      conn.release();
      return res.status(404).json({ error: 'Product not found.' });
    }

    const fields = EDITABLE_FIELDS.filter((f) => f in body);
    if (!fields.length) {
      conn.release();
      return res.status(400).json({ error: 'No editable fields provided.' });
    }

    await conn.beginTransaction();
    const setClause = fields.map((f) => `${f} = ?`).join(', ');
    const values = fields.map((f) => (body[f] === '' ? null : body[f]));
    await conn.query(`UPDATE products SET ${setClause} WHERE id = ?`, [...values, req.params.id]);

    const [[product]] = await conn.query('SELECT * FROM products WHERE id = ?', [req.params.id]);
    await syncPurchaseTransaction(conn, product);
    await syncSaleTransaction(conn, product, req.user.sub);

    await conn.commit();
    res.json({ product });
  } catch (err) {
    await conn.rollback();
    if (err.code === 'ER_DUP_ENTRY') {
      return res.status(409).json({ error: 'That product number is already in use.' });
    }
    console.error('Updating product failed:', err);
    res.status(500).json({ error: 'Could not update product.' });
  } finally {
    conn.release();
  }
}

router.put('/:id', requirePermission('products', 'manage'), updateProduct);
router.patch('/:id', requirePermission('products', 'manage'), updateProduct);

// Small dedicated endpoint for the common "just change the status" action
// used from the Inventory/Products table rows.
router.patch('/:id/status', requirePermission('products', 'manage'), async (req, res) => {
  const { status } = req.body || {};
  if (!status || !STATUSES.includes(status)) {
    return res.status(400).json({ error: 'Invalid status.' });
  }
  req.body = { status };
  return updateProduct(req, res);
});

router.delete('/:id', requirePermission('products', 'manage'), async (req, res) => {
  try {
    const [result] = await pool.query('DELETE FROM products WHERE id = ?', [req.params.id]);
    if (!result.affectedRows) return res.status(404).json({ error: 'Product not found.' });
    res.status(204).end();
  } catch (err) {
    console.error('Deleting product failed:', err);
    res.status(500).json({ error: 'Could not delete product.' });
  }
});

module.exports = router;
