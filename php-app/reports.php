<?php
require __DIR__ . '/config.php';
requirePermission('reports', 'view');

$today = date('Y-m-d');
$preset = $_GET['preset'] ?? '';
switch ($preset) {
    case 'yesterday':
        $from = $to = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'week':
        $from = date('Y-m-d', strtotime('-6 days'));
        $to = $today;
        break;
    case 'month':
        $from = date('Y-m-01');
        $to = $today;
        break;
    default:
        $from = $_GET['from'] ?? $today;
        $to = $_GET['to'] ?? $today;
}
// Keep the range sane if someone types dates backwards.
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$soldStmt = $pdo->prepare(
    'SELECT * FROM products WHERE status = "sold" AND sold_date BETWEEN ? AND ? ORDER BY sold_date DESC, id DESC'
);
$soldStmt->execute([$from, $to]);
$soldProducts = $soldStmt->fetchAll();

$purchasedStmt = $pdo->prepare(
    'SELECT * FROM products WHERE purchase_date BETWEEN ? AND ? ORDER BY purchase_date DESC, id DESC'
);
$purchasedStmt->execute([$from, $to]);
$purchasedProducts = $purchasedStmt->fetchAll();

$expensesStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount * quantity), 0) AS total FROM transactions
     WHERE type = 'expense' AND transaction_date BETWEEN ? AND ?"
);
$expensesStmt->execute([$from, $to]);
$expensesTotal = $expensesStmt->fetchColumn();

$soldCount = count($soldProducts);
$revenue = array_sum(array_column($soldProducts, 'sold_price'));
$costOfGoodsSold = array_sum(array_column($soldProducts, 'bought_price'));
$profit = $revenue - $costOfGoodsSold - $expensesTotal;
$purchasedCount = count($purchasedProducts);
$purchasedSpend = array_sum(array_column($purchasedProducts, 'bought_price'));

$activityStmt = $pdo->prepare(
    "SELECT * FROM activity_log
     WHERE action IN ('product.status_change', 'product.bulk_status_change')
       AND DATE(created_at) BETWEEN ? AND ?
     ORDER BY created_at DESC LIMIT 200"
);
$activityStmt->execute([$from, $to]);
$statusChanges = $activityStmt->fetchAll();

$rangeLabel = $from === $to ? fmtDate($from) : fmtDate($from) . ' – ' . fmtDate($to);
$exportQuery = http_build_query(['from' => $from, 'to' => $to]);

$pageTitle = 'Reports';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
  <div>
    <h1 class="text-2xl font-bold text-slate-900">Reports</h1>
    <p class="mt-1 text-sm text-slate-500">What sold, what came in, and what changed — for any day or range.</p>
  </div>
  <div class="flex flex-wrap items-center gap-2">
    <a href="?preset=today" class="<?= BTN_SECONDARY ?> <?= $preset === '' && $from === $today && $to === $today ? 'ring-2 ring-indigo-200' : '' ?>">Today</a>
    <a href="?preset=yesterday" class="<?= BTN_SECONDARY ?> <?= $preset === 'yesterday' ? 'ring-2 ring-indigo-200' : '' ?>">Yesterday</a>
    <a href="?preset=week" class="<?= BTN_SECONDARY ?> <?= $preset === 'week' ? 'ring-2 ring-indigo-200' : '' ?>">Last 7 days</a>
    <a href="?preset=month" class="<?= BTN_SECONDARY ?> <?= $preset === 'month' ? 'ring-2 ring-indigo-200' : '' ?>">This month</a>
    <form method="get" class="flex items-center gap-2">
      <input type="date" name="from" value="<?= e($from) ?>" class="<?= INPUT ?> w-40">
      <span class="text-slate-400">–</span>
      <input type="date" name="to" value="<?= e($to) ?>" class="<?= INPUT ?> w-40">
      <button type="submit" class="<?= BTN_SECONDARY ?>">Go</button>
    </form>
  </div>
</div>

<p class="mb-4 text-sm font-medium text-slate-500"><?= e($rangeLabel) ?></p>

<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
  <div class="<?= CARD ?> p-5">
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Items sold</p>
    <p class="mt-2 text-xl font-bold text-slate-900"><?= $soldCount ?></p>
  </div>
  <div class="<?= CARD ?> p-5">
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Revenue</p>
    <p class="mt-2 text-xl font-bold text-slate-900"><?= money($revenue) ?></p>
  </div>
  <div class="<?= CARD ?> p-5">
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Profit</p>
    <p class="mt-2 text-xl font-bold <?= $profit >= 0 ? 'text-emerald-600' : 'text-red-600' ?>"><?= money($profit) ?></p>
  </div>
  <div class="<?= CARD ?> p-5">
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Items purchased</p>
    <p class="mt-2 text-xl font-bold text-slate-900"><?= $purchasedCount ?></p>
  </div>
  <div class="<?= CARD ?> p-5">
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Spent on stock</p>
    <p class="mt-2 text-xl font-bold text-slate-900"><?= money($purchasedSpend) ?></p>
  </div>
</div>

<div class="<?= CARD ?> mb-6 overflow-hidden">
  <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
    <h2 class="text-sm font-semibold text-slate-900">Products sold</h2>
    <a href="export_reports.php?type=sold&<?= e($exportQuery) ?>" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">⬇ Export CSV</a>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
      <thead class="bg-slate-50">
        <tr class="text-xs uppercase tracking-wide text-slate-500">
          <th class="px-4 py-3">Sold on</th>
          <th class="px-4 py-3">Product</th>
          <th class="px-4 py-3">Brand</th>
          <th class="px-4 py-3">Bought</th>
          <th class="px-4 py-3">Sold</th>
          <th class="px-4 py-3">Margin</th>
          <th class="px-4 py-3">Buyer</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($soldProducts as $p): $margin = marginPercent($p['bought_price'], $p['sold_price']); ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 text-slate-500"><?= fmtDate($p['sold_date']) ?></td>
            <td class="px-4 py-3 font-medium text-slate-800"><?= e($p['name']) ?> <span class="font-mono text-xs text-slate-400"><?= e($p['product_number']) ?></span></td>
            <td class="px-4 py-3 text-slate-600"><?= e($p['brand'] ?: '—') ?></td>
            <td class="px-4 py-3 text-slate-600"><?= money($p['bought_price']) ?></td>
            <td class="px-4 py-3 font-medium text-slate-800"><?= money($p['sold_price']) ?></td>
            <td class="px-4 py-3"><?= $margin !== null ? round($margin) . '%' : '—' ?></td>
            <td class="px-4 py-3 text-slate-600"><?= e($p['buyer'] ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$soldProducts): ?>
          <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Nothing sold in this range.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="<?= CARD ?> mb-6 overflow-hidden">
  <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
    <h2 class="text-sm font-semibold text-slate-900">Products purchased (new stock)</h2>
    <a href="export_reports.php?type=purchased&<?= e($exportQuery) ?>" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">⬇ Export CSV</a>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
      <thead class="bg-slate-50">
        <tr class="text-xs uppercase tracking-wide text-slate-500">
          <th class="px-4 py-3">Purchased on</th>
          <th class="px-4 py-3">Product</th>
          <th class="px-4 py-3">Brand</th>
          <th class="px-4 py-3">Bought for</th>
          <th class="px-4 py-3">Status now</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($purchasedProducts as $p): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 text-slate-500"><?= fmtDate($p['purchase_date']) ?></td>
            <td class="px-4 py-3 font-medium text-slate-800"><?= e($p['name']) ?> <span class="font-mono text-xs text-slate-400"><?= e($p['product_number']) ?></span></td>
            <td class="px-4 py-3 text-slate-600"><?= e($p['brand'] ?: '—') ?></td>
            <td class="px-4 py-3 text-slate-600"><?= money($p['bought_price']) ?></td>
            <td class="px-4 py-3"><?= statusBadge($p['status']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$purchasedProducts): ?>
          <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No stock added in this range.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="<?= CARD ?> overflow-hidden">
  <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold text-slate-900">Status changes</h2></div>
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
      <thead class="bg-slate-50">
        <tr class="text-xs uppercase tracking-wide text-slate-500">
          <th class="px-4 py-3">When</th>
          <th class="px-4 py-3">By</th>
          <th class="px-4 py-3">Change</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($statusChanges as $a): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 text-slate-500"><?= date('M j, H:i', strtotime($a['created_at'])) ?></td>
            <td class="px-4 py-3 text-slate-700"><?= e($a['username'] ?: '—') ?></td>
            <td class="px-4 py-3 text-slate-700"><?= e($a['description']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$statusChanges): ?>
          <tr><td colspan="3" class="px-4 py-10 text-center text-slate-400">No status changes in this range.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
