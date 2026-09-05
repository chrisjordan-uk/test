<?php
require __DIR__ . '/config.php';
requirePermission('profit', 'view');

$canManage = can('profit', 'manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) {
        flash('error', 'You do not have permission to do that.');
        redirect('profit.php');
    }
    if (($_POST['action'] ?? '') === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        // Only manual (not product-linked) entries may be deleted here.
        $pdo->prepare('DELETE FROM transactions WHERE id = ? AND product_id IS NULL')->execute([$id]);
        flash('success', 'Entry deleted.');
    }
    redirect('profit.php');
}

$groupBy = ($_GET['group'] ?? 'month') === 'year' ? 'year' : 'month';
$format = $groupBy === 'year' ? '%Y' : '%Y-%m';

$periodsStmt = $pdo->prepare(
    "SELECT DATE_FORMAT(transaction_date, ?) AS period,
            SUM(CASE WHEN type = 'sale' THEN amount * quantity ELSE 0 END) AS revenue,
            SUM(CASE WHEN type = 'purchase' THEN amount * quantity ELSE 0 END) AS cost,
            SUM(CASE WHEN type = 'expense' THEN amount * quantity ELSE 0 END) AS expenses
     FROM transactions GROUP BY period ORDER BY period ASC LIMIT 36"
);
$periodsStmt->execute([$format]);
$periods = $periodsStmt->fetchAll();

$totals = $pdo->query(
    "SELECT SUM(CASE WHEN type = 'sale' THEN amount * quantity ELSE 0 END) AS revenue,
            SUM(CASE WHEN type = 'purchase' THEN amount * quantity ELSE 0 END) AS cost,
            SUM(CASE WHEN type = 'expense' THEN amount * quantity ELSE 0 END) AS expenses
     FROM transactions"
)->fetch();
$totalRevenue = $totals['revenue'] ?? 0;
$totalCost = ($totals['cost'] ?? 0) + ($totals['expenses'] ?? 0);
$netProfit = $totalRevenue - $totalCost;

// Sales performance, computed straight from sold products so it reflects
// individual item economics rather than the ledger totals above.
$perf = $pdo->query(
    "SELECT COUNT(*) AS items_sold, AVG(sold_price) AS avg_sale_price,
            AVG((sold_price - bought_price) / NULLIF(bought_price, 0) * 100) AS avg_margin,
            AVG(DATEDIFF(sold_date, purchase_date)) AS avg_days_to_sell
     FROM products
     WHERE status = 'sold' AND sold_price IS NOT NULL"
)->fetch();

$expenseBreakdown = $pdo->query(
    "SELECT COALESCE(NULLIF(category, ''), 'Uncategorised') AS category,
            SUM(amount * quantity) AS total
     FROM transactions WHERE type = 'expense'
     GROUP BY category ORDER BY total DESC"
)->fetchAll();

$transactions = $pdo->query(
    'SELECT t.*, p.name AS product_name FROM transactions t
     LEFT JOIN products p ON p.id = t.product_id
     ORDER BY t.transaction_date DESC, t.id DESC LIMIT 300'
)->fetchAll();

$typeMeta = [
    'purchase' => ['label' => 'Stock purchase', 'class' => 'bg-amber-100 text-amber-700'],
    'sale' => ['label' => 'Sale', 'class' => 'bg-emerald-100 text-emerald-700'],
    'expense' => ['label' => 'Expense', 'class' => 'bg-red-100 text-red-700'],
];

$chartLabels = array_column($periods, 'period');
$chartProfit = array_map(fn($p) => round(($p['revenue'] ?? 0) - ($p['cost'] ?? 0) - ($p['expenses'] ?? 0), 2), $periods);
$chartRevenue = array_map(fn($p) => round($p['revenue'] ?? 0, 2), $periods);

$pageTitle = 'Profit';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
  <div>
    <h1 class="text-2xl font-bold text-slate-900">Profit</h1>
    <p class="mt-1 text-sm text-slate-500">Track stock purchases, sales and expenses — profit is calculated automatically.</p>
  </div>
  <div class="flex items-center gap-2">
    <a href="export_profit.php" class="<?= BTN_SECONDARY ?>">⬇ Export CSV</a>
    <?php if ($canManage): ?>
      <a href="transaction_form.php" class="<?= BTN_PRIMARY ?>">+ Add entry</a>
    <?php endif; ?>
  </div>
</div>

<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
  <div class="<?= CARD ?> p-5">
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total revenue</p>
    <p class="mt-2 text-2xl font-bold text-slate-900"><?= money($totalRevenue) ?></p>
  </div>
  <div class="<?= CARD ?> p-5">
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total cost &amp; expenses</p>
    <p class="mt-2 text-2xl font-bold text-slate-900"><?= money($totalCost) ?></p>
  </div>
  <div class="<?= CARD ?> p-5">
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Net profit</p>
    <p class="mt-2 text-2xl font-bold <?= $netProfit >= 0 ? 'text-emerald-600' : 'text-red-600' ?>"><?= money($netProfit) ?></p>
  </div>
</div>

<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
  <div class="<?= CARD ?> p-5">
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Items sold</p>
    <p class="mt-2 text-xl font-bold text-slate-900"><?= (int) $perf['items_sold'] ?></p>
  </div>
  <div class="<?= CARD ?> p-5">
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Avg. sale price</p>
    <p class="mt-2 text-xl font-bold text-slate-900"><?= $perf['avg_sale_price'] !== null ? money($perf['avg_sale_price']) : '—' ?></p>
  </div>
  <div class="<?= CARD ?> p-5">
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Avg. margin</p>
    <p class="mt-2 text-xl font-bold text-slate-900"><?= $perf['avg_margin'] !== null ? round($perf['avg_margin']) . '%' : '—' ?></p>
  </div>
  <div class="<?= CARD ?> p-5">
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Avg. days to sell</p>
    <p class="mt-2 text-xl font-bold text-slate-900"><?= $perf['avg_days_to_sell'] !== null ? round($perf['avg_days_to_sell']) : '—' ?></p>
  </div>
</div>

<div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
  <div class="<?= CARD ?> p-5 lg:col-span-2">
    <div class="mb-4 flex items-center justify-between">
      <h2 class="text-sm font-semibold text-slate-900">Profit over time</h2>
      <div class="flex gap-1 rounded-lg bg-slate-100 p-1 text-sm">
        <a href="?group=month" class="rounded-md px-3 py-1 font-medium <?= $groupBy === 'month' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500' ?>">Monthly</a>
        <a href="?group=year" class="rounded-md px-3 py-1 font-medium <?= $groupBy === 'year' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500' ?>">Yearly</a>
      </div>
    </div>
    <canvas id="profitChart" height="90"></canvas>
  </div>

  <div class="<?= CARD ?> p-5">
    <h2 class="mb-4 text-sm font-semibold text-slate-900">Expenses by category</h2>
    <?php if (!$expenseBreakdown): ?>
      <p class="text-sm text-slate-400">No expenses logged yet.</p>
    <?php endif; ?>
    <ul class="space-y-3">
      <?php foreach ($expenseBreakdown as $row): ?>
        <li class="flex items-center justify-between text-sm">
          <span class="text-slate-600"><?= e($row['category']) ?></span>
          <span class="font-medium text-slate-800"><?= money($row['total']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>

<div class="<?= CARD ?> overflow-hidden">
  <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold text-slate-900">Ledger</h2></div>
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
      <thead class="bg-slate-50">
        <tr class="text-xs uppercase tracking-wide text-slate-500">
          <th class="px-4 py-3">Date</th>
          <th class="px-4 py-3">Type</th>
          <th class="px-4 py-3">Description</th>
          <th class="px-4 py-3">Qty</th>
          <th class="px-4 py-3">Amount</th>
          <?php if ($canManage): ?><th class="px-4 py-3 text-right">Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($transactions as $t): $meta = $typeMeta[$t['type']]; ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 text-slate-500"><?= fmtDate($t['transaction_date']) ?></td>
            <td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $meta['class'] ?>"><?= e($meta['label']) ?></span></td>
            <td class="px-4 py-3 text-slate-700">
              <?= e($t['description']) ?>
              <?php if ($t['category']): ?>
                <span class="ml-1 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500"><?= e($t['category']) ?></span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-slate-600"><?= (int) $t['quantity'] ?></td>
            <td class="px-4 py-3 font-medium text-slate-800"><?= money($t['amount'] * $t['quantity']) ?></td>
            <?php if ($canManage): ?>
              <td class="px-4 py-3 text-right">
                <?php if ($t['product_id']): ?>
                  <span class="text-xs text-slate-400" title="Linked to a product — edit it from the Products page">Auto</span>
                <?php else: ?>
                  <form method="post" onsubmit="return confirm('Delete this entry?');" class="inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                    <button type="submit" class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600">🗑</button>
                  </form>
                <?php endif; ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        <?php if (!$transactions): ?>
          <tr><td colspan="<?= $canManage ? 6 : 5 ?>" class="px-4 py-10 text-center text-slate-400">No entries yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
<script>
  new Chart(document.getElementById('profitChart'), {
    type: 'line',
    data: {
      labels: <?= json_encode($chartLabels) ?>,
      datasets: [
        { label: 'Profit', data: <?= json_encode($chartProfit) ?>, borderColor: '#4b63f6', backgroundColor: '#4b63f6', tension: 0.3 },
        { label: 'Revenue', data: <?= json_encode($chartRevenue) ?>, borderColor: '#10b981', backgroundColor: '#10b981', borderDash: [4, 3], tension: 0.3 },
      ],
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } },
  });
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
