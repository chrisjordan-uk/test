const express = require('express');
const pool = require('../config/db');
const { requireAuth } = require('../middleware/auth');
const { requirePermission } = require('../middleware/permissions');

const router = express.Router();
const TYPES = ['purchase', 'sale', 'expense'];

router.use(requireAuth);

// GET /api/profit/transactions - the ledger table (stock purchases, sales, expenses).
router.get('/transactions', requirePermission('profit', 'view'), async (req, res) => {
  try {
    const { type, from, to, limit = 500 } = req.query;
    const where = [];
    const params = [];

    if (type) {
      const types = String(type).split(',').filter((t) => TYPES.includes(t));
      if (types.length) {
        where.push(`t.type IN (${types.map(() => '?').join(',')})`);
        params.push(...types);
      }
    }
    if (from) {
      where.push('t.transaction_date >= ?');
      params.push(from);
    }
    if (to) {
      where.push('t.transaction_date <= ?');
      params.push(to);
    }

    const sql = `SELECT t.*, p.name AS product_name, p.product_number
      FROM transactions t
      LEFT JOIN products p ON p.id = t.product_id
      ${where.length ? `WHERE ${where.join(' AND ')}` : ''}
      ORDER BY t.transaction_date DESC, t.id DESC
      LIMIT ?`;
    params.push(Math.min(Number(limit) || 500, 2000));

    const [rows] = await pool.query(sql, params);
    res.json({ transactions: rows });
  } catch (err) {
    console.error('Listing transactions failed:', err);
    res.status(500).json({ error: 'Could not load the profit ledger.' });
  }
});

// GET /api/profit/summary?groupBy=month|year - aggregated revenue/cost/profit.
router.get('/summary', requirePermission('profit', 'view'), async (req, res) => {
  try {
    const groupBy = req.query.groupBy === 'year' ? 'year' : 'month';
    const format = groupBy === 'year' ? '%Y' : '%Y-%m';

    const [periods] = await pool.query(
      `SELECT DATE_FORMAT(transaction_date, ?) AS period,
              SUM(CASE WHEN type = 'sale' THEN amount * quantity ELSE 0 END) AS revenue,
              SUM(CASE WHEN type = 'purchase' THEN amount * quantity ELSE 0 END) AS cost,
              SUM(CASE WHEN type = 'expense' THEN amount * quantity ELSE 0 END) AS expenses,
              SUM(CASE WHEN type = 'sale' THEN quantity ELSE 0 END) AS items_sold
       FROM transactions
       GROUP BY period
       ORDER BY period DESC
       LIMIT 36`,
      [format]
    );

    const withProfit = periods.map((p) => ({
      period: p.period,
      revenue: p.revenue,
      cost: p.cost,
      expenses: p.expenses,
      profit: p.revenue - p.cost - p.expenses,
      itemsSold: p.items_sold,
    }));

    const [[totals]] = await pool.query(
      `SELECT SUM(CASE WHEN type = 'sale' THEN amount * quantity ELSE 0 END) AS revenue,
              SUM(CASE WHEN type = 'purchase' THEN amount * quantity ELSE 0 END) AS cost,
              SUM(CASE WHEN type = 'expense' THEN amount * quantity ELSE 0 END) AS expenses,
              SUM(CASE WHEN type = 'sale' THEN quantity ELSE 0 END) AS items_sold
       FROM transactions`
    );

    res.json({
      groupBy,
      periods: withProfit,
      totals: {
        revenue: totals.revenue || 0,
        cost: totals.cost || 0,
        expenses: totals.expenses || 0,
        profit: (totals.revenue || 0) - (totals.cost || 0) - (totals.expenses || 0),
        itemsSold: totals.items_sold || 0,
      },
    });
  } catch (err) {
    console.error('Building profit summary failed:', err);
    res.status(500).json({ error: 'Could not calculate profit.' });
  }
});

router.post('/transactions', requirePermission('profit', 'manage'), async (req, res) => {
  const { type, description, amount, quantity = 1, transaction_date, product_id } = req.body || {};
  if (!TYPES.includes(type) || !description || amount == null || !transaction_date) {
    return res.status(400).json({
      error: 'type, description, amount and transaction_date are required.',
    });
  }

  try {
    const [result] = await pool.query(
      `INSERT INTO transactions (type, product_id, description, amount, quantity, transaction_date, created_by)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [type, product_id || null, description, amount, quantity, transaction_date, req.user.sub]
    );
    const [[row]] = await pool.query('SELECT * FROM transactions WHERE id = ?', [result.insertId]);
    res.status(201).json({ transaction: row });
  } catch (err) {
    console.error('Creating transaction failed:', err);
    res.status(500).json({ error: 'Could not save that entry.' });
  }
});

router.put('/transactions/:id', requirePermission('profit', 'manage'), async (req, res) => {
  const { type, description, amount, quantity, transaction_date } = req.body || {};
  if (type && !TYPES.includes(type)) {
    return res.status(400).json({ error: 'Invalid type.' });
  }

  const fields = {};
  if (type) fields.type = type;
  if (description) fields.description = description;
  if (amount != null) fields.amount = amount;
  if (quantity != null) fields.quantity = quantity;
  if (transaction_date) fields.transaction_date = transaction_date;

  if (!Object.keys(fields).length) {
    return res.status(400).json({ error: 'No editable fields provided.' });
  }

  try {
    const setClause = Object.keys(fields).map((f) => `${f} = ?`).join(', ');
    const [result] = await pool.query(`UPDATE transactions SET ${setClause} WHERE id = ?`, [
      ...Object.values(fields),
      req.params.id,
    ]);
    if (!result.affectedRows) return res.status(404).json({ error: 'Entry not found.' });
    const [[row]] = await pool.query('SELECT * FROM transactions WHERE id = ?', [req.params.id]);
    res.json({ transaction: row });
  } catch (err) {
    console.error('Updating transaction failed:', err);
    res.status(500).json({ error: 'Could not update that entry.' });
  }
});

router.delete('/transactions/:id', requirePermission('profit', 'manage'), async (req, res) => {
  try {
    const [result] = await pool.query('DELETE FROM transactions WHERE id = ?', [req.params.id]);
    if (!result.affectedRows) return res.status(404).json({ error: 'Entry not found.' });
    res.status(204).end();
  } catch (err) {
    console.error('Deleting transaction failed:', err);
    res.status(500).json({ error: 'Could not delete that entry.' });
  }
});

module.exports = router;
