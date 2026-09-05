<?php
require __DIR__ . '/config.php';
requirePermission('inventory', 'view');

$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$brandFilter = $_GET['brand'] ?? '';

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(name LIKE ? OR product_number LIKE ? OR brand LIKE ?)';
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}
if ($statusFilter !== '' && array_key_exists($statusFilter, STATUSES)) {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
}
if ($brandFilter !== '') {
    $where[] = 'brand = ?';
    $params[] = $brandFilter;
}

$sql = 'SELECT * FROM products' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY updated_at DESC LIMIT 1000';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$brands = $pdo->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand <> '' ORDER BY brand")->fetchAll(PDO::FETCH_COLUMN);

$totalValue = array_sum(array_column($products, 'bought_price'));

$pageTitle = 'Inventory';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
  <div>
    <h1 class="text-2xl font-bold text-slate-900">Inventory</h1>
    <p class="mt-1 text-sm text-slate-500"><?= count($products) ?> products · <?= money($totalValue) ?> in cost</p>
  </div>

  <form method="get" class="flex flex-wrap items-center gap-2">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search name, brand, SKU…" class="<?= INPUT ?> w-56">
    <select name="status" class="<?= INPUT ?> w-40" onchange="this.form.submit()">
      <option value="">All statuses</option>
      <?php foreach (STATUSES as $key => $meta): ?>
        <option value="<?= e($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="brand" class="<?= INPUT ?> w-40" onchange="this.form.submit()">
      <option value="">All brands</option>
      <?php foreach ($brands as $b): ?>
        <option value="<?= e($b) ?>" <?= $brandFilter === $b ? 'selected' : '' ?>><?= e($b) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="<?= BTN_SECONDARY ?>">Filter</button>
  </form>
</div>

<div class="<?= CARD ?> overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
      <thead class="bg-slate-50">
        <tr class="text-xs uppercase tracking-wide text-slate-500">
          <th class="px-4 py-3">Product #</th>
          <th class="px-4 py-3">Name</th>
          <th class="px-4 py-3">Brand</th>
          <th class="px-4 py-3">Size</th>
          <th class="px-4 py-3">Condition</th>
          <th class="px-4 py-3">Status</th>
          <th class="px-4 py-3">Bought</th>
          <th class="px-4 py-3">Sold</th>
          <th class="px-4 py-3">Purchased</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($products as $p): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 font-mono text-xs text-slate-500"><?= e($p['product_number']) ?></td>
            <td class="px-4 py-3 font-medium text-slate-800"><?= e($p['name']) ?></td>
            <td class="px-4 py-3 text-slate-600"><?= e($p['brand'] ?: '—') ?></td>
            <td class="px-4 py-3 text-slate-600"><?= e($p['size'] ?: '—') ?></td>
            <td class="px-4 py-3 text-slate-600"><?= e($p['item_condition'] ?: '—') ?></td>
            <td class="px-4 py-3"><?= statusBadge($p['status']) ?></td>
            <td class="px-4 py-3 text-slate-600"><?= money($p['bought_price']) ?></td>
            <td class="px-4 py-3 text-slate-600"><?= $p['sold_price'] !== null ? money($p['sold_price']) : '—' ?></td>
            <td class="px-4 py-3 text-slate-500"><?= fmtDate($p['purchase_date']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$products): ?>
          <tr><td colspan="9" class="px-4 py-10 text-center text-slate-400">No products match these filters.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
