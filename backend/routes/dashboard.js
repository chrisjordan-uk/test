const express = require('express');
const pool = require('../config/db');
const { requireAuth } = require('../middleware/auth');
const { requirePermission } = require('../middleware/permissions');

const router = express.Router();

router.use(requireAuth);

router.get('/stats', requirePermission('dashboard', 'view'), async (req, res) => {
  try {
    const [[totals]] = await pool.query('SELECT COUNT(*) AS total FROM products');

    const [statusRows] = await pool.query(
      'SELECT status, COUNT(*) AS count FROM products GROUP BY status'
    );

    const [[inventoryValue]] = await pool.query(
      `SELECT COALESCE(SUM(bought_price), 0) AS value, COUNT(*) AS count
       FROM products WHERE status NOT IN ('sold', 'returned', 'rejected')`
    );

    const [[monthProfit]] = await pool.query(
      `SELECT
         SUM(CASE WHEN type = 'sale' THEN amount * quantity ELSE 0 END) AS revenue,
         SUM(CASE WHEN type IN ('purchase', 'expense') THEN amount * quantity ELSE 0 END) AS cost
       FROM transactions
       WHERE transaction_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')`
    );

    const [[yearProfit]] = await pool.query(
      `SELECT
         SUM(CASE WHEN type = 'sale' THEN amount * quantity ELSE 0 END) AS revenue,
         SUM(CASE WHEN type IN ('purchase', 'expense') THEN amount * quantity ELSE 0 END) AS cost
       FROM transactions
       WHERE YEAR(transaction_date) = YEAR(CURDATE())`
    );

    const [recentProducts] = await pool.query(
      'SELECT id, product_number, name, brand, status, bought_price, sold_price, updated_at FROM products ORDER BY updated_at DESC LIMIT 8'
    );

    const [topBrands] = await pool.query(
      `SELECT brand, COUNT(*) AS count FROM products
       WHERE brand IS NOT NULL AND brand <> ''
       GROUP BY brand ORDER BY count DESC LIMIT 5`
    );

    const statusCounts = Object.fromEntries(statusRows.map((r) => [r.status, r.count]));

    res.json({
      totalProducts: totals.total,
      statusCounts,
      inventory: { value: inventoryValue.value, count: inventoryValue.count },
      profit: {
        month: {
          revenue: monthProfit.revenue || 0,
          cost: monthProfit.cost || 0,
          profit: (monthProfit.revenue || 0) - (monthProfit.cost || 0),
        },
        year: {
          revenue: yearProfit.revenue || 0,
          cost: yearProfit.cost || 0,
          profit: (yearProfit.revenue || 0) - (yearProfit.cost || 0),
        },
      },
      recentProducts,
      topBrands,
    });
  } catch (err) {
    console.error('Building dashboard stats failed:', err);
    res.status(500).json({ error: 'Could not load dashboard statistics.' });
  }
});

module.exports = router;
