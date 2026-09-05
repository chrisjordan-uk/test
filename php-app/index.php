<?php
require __DIR__ . '/config.php';
requirePermission('dashboard', 'view');

$totalProducts = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();

$statusCounts = [];
foreach ($pdo->query('SELECT status, COUNT(*) AS count FROM products GROUP BY status') as $row) {
    $statusCounts[$row['status']] = (int) $row['count'];
}

$inventory = $pdo->query(
    "SELECT COALESCE(SUM(bought_price), 0) AS value, COUNT(*) AS count
     FROM products WHERE status NOT IN ('sold', 'returned', 'rejected')"
)->fetch();

$monthProfit = $pdo->query(
    "SELECT
       SUM(CASE WHEN type = 'sale' THEN amount * quantity ELSE 0 END) AS revenue,
       SUM(CASE WHEN type IN ('purchase', 'expense') THEN amount * quantity ELSE 0 END) AS cost
     FROM transactions
     WHERE transaction_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
)->fetch();

$yearProfit = $pdo->query(
    "SELECT
       SUM(CASE WHEN type = 'sale' THEN amount * quantity ELSE 0 END) AS revenue,
       SUM(CASE WHEN type IN ('purchase', 'expense') THEN amount * quantity ELSE 0 END) AS cost
     FROM transactions
     WHERE YEAR(transaction_date) = YEAR(CURDATE())"
)->fetch();

$recentProducts = $pdo->query(
    'SELECT id, product_number, name, brand, status, bought_price, sold_price, updated_at
     FROM products ORDER BY updated_at DESC LIMIT 8'
)->fetchAll();

$topBrands = $pdo->query(
    "SELECT brand, COUNT(*) AS count FROM products
     WHERE brand IS NOT NULL AND brand <> ''
     GROUP BY brand ORDER BY count DESC LIMIT 5"
)->fetchAll();

$maxStatusCount = max([1, ...array_values($statusCounts)]);

$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-8">
  <h1 class="text-2xl font-bold text-slate-900">Home</h1>
  <p class="mt-1 text-sm text-slate-500">A quick overview of your Vinted resell business.</p>
</div>

<div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
  <div class="<?= CARD ?> p-5">
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total products</p>
    <p class="mt-2 text-2xl font-bold text-slate-900"><?= $totalProducts ?></p>
    <p class="mt-1 text-xs text-slate-500"><?= (int) $inventory['count'] ?> currently in stock</p>
  </div>
  <div class="<?= CARD ?> p-5">
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Inventory value</p>
    <p class="mt-2 text-2xl font-bold text-slate-900"><?= money($inventory['value']) ?></p>
    <p class="mt-1 text-xs text-slate-500">Cost of items not yet sold</p>
  </div>
  <div class="<?= CARD ?> p-5">
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Profit this month</p>
    <p class="mt-2 text-2xl font-bold text-emerald-600"><?= money(($monthProfit['revenue'] ?? 0) - ($monthProfit['cost'] ?? 0)) ?></p>
    <p class="mt-1 text-xs text-slate-500"><?= money($monthProfit['revenue'] ?? 0) ?> revenue</p>
  </div>
  <div class="<?= CARD ?> p-5">
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Profit this year</p>
    <p class="mt-2 text-2xl font-bold text-amber-600"><?= money(($yearProfit['revenue'] ?? 0) - ($yearProfit['cost'] ?? 0)) ?></p>
    <p class="mt-1 text-xs text-slate-500"><?= money($yearProfit['revenue'] ?? 0) ?> revenue</p>
  </div>
</div>

<div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
  <div class="<?= CARD ?> p-5 lg:col-span-2">
    <h2 class="mb-4 text-sm font-semibold text-slate-900">Products by status</h2>
    <?php if (!$statusCounts): ?>
      <p class="text-sm text-slate-400">No products yet.</p>
    <?php endif; ?>
    <div class="space-y-3">
      <?php foreach ($statusCounts as $status => $count): ?>
        <div>
          <div class="mb-1 flex justify-between text-xs text-slate-500">
            <span><?= e(STATUSES[$status]['label'] ?? $status) ?></span>
            <span><?= $count ?></span>
          </div>
          <div class="h-2 w-full rounded-full bg-slate-100">
            <div class="h-2 rounded-full bg-indigo-600" style="width: <?= round($count / $maxStatusCount * 100) ?>%"></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="<?= CARD ?> p-5">
    <h2 class="mb-4 text-sm font-semibold text-slate-900">Top brands</h2>
    <?php if (!$topBrands): ?>
      <p class="text-sm text-slate-400">No products yet.</p>
    <?php endif; ?>
    <ul class="space-y-3">
      <?php foreach ($topBrands as $b): ?>
        <li class="flex items-center justify-between text-sm">
          <span class="font-medium text-slate-700"><?= e($b['brand']) ?></span>
          <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600"><?= $b['count'] ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>

<div class="<?= CARD ?> p-5">
  <div class="mb-4 flex items-center justify-between">
    <h2 class="text-sm font-semibold text-slate-900">Recently updated products</h2>
    <a href="inventory.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">View inventory →</a>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
      <thead>
        <tr class="text-xs uppercase tracking-wide text-slate-400">
          <th class="pb-2 pr-4">Product</th>
          <th class="pb-2 pr-4">Brand</th>
          <th class="pb-2 pr-4">Status</th>
          <th class="pb-2 pr-4">Bought</th>
          <th class="pb-2 pr-4">Sold</th>
          <th class="pb-2">Updated</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($recentProducts as $p): ?>
          <tr>
            <td class="py-2 pr-4 font-medium text-slate-800"><?= e($p['name']) ?></td>
            <td class="py-2 pr-4 text-slate-600"><?= e($p['brand'] ?: '—') ?></td>
            <td class="py-2 pr-4"><?= statusBadge($p['status']) ?></td>
            <td class="py-2 pr-4 text-slate-600"><?= money($p['bought_price']) ?></td>
            <td class="py-2 pr-4 text-slate-600"><?= $p['sold_price'] !== null ? money($p['sold_price']) : '—' ?></td>
            <td class="py-2 text-slate-500"><?= fmtDate($p['updated_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recentProducts): ?>
          <tr><td colspan="6" class="py-6 text-center text-slate-400">No products yet — add your first one from the Products page.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
