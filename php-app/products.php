<?php
require __DIR__ . '/config.php';
requirePermission('products', 'view');

$canManage = can('products', 'manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) {
        flash('error', 'You do not have permission to do that.');
        redirect('products.php');
    }

    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'status' && $id) {
        $status = $_POST['status'] ?? '';
        if (array_key_exists($status, STATUSES)) {
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
            $stmt->execute([$id]);
            if ($product = $stmt->fetch()) {
                $pdo->prepare('UPDATE products SET status = ? WHERE id = ?')->execute([$status, $id]);
                $product['status'] = $status;
                syncPurchaseTransaction($pdo, $product);
                syncSaleTransaction($pdo, $product, currentUser()['id']);
                flash('success', 'Status updated.');
            }
        }
    } elseif ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        flash('success', 'Product deleted.');
    }

    redirect('products.php' . ($_GET['q'] ?? '' ? '?q=' . urlencode($_GET['q']) : ''));
}

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE name LIKE ? OR brand LIKE ? OR product_number LIKE ? ORDER BY updated_at DESC LIMIT 1000');
    $like = "%$search%";
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query('SELECT * FROM products ORDER BY updated_at DESC LIMIT 1000');
}
$products = $stmt->fetchAll();

$pageTitle = 'Products';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
  <div>
    <h1 class="text-2xl font-bold text-slate-900">Products</h1>
    <p class="mt-1 text-sm text-slate-500">Add new stock and manage each item's status.</p>
  </div>
  <div class="flex items-center gap-2">
    <form method="get" class="flex items-center gap-2">
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search products…" class="<?= INPUT ?> w-56">
      <button type="submit" class="<?= BTN_SECONDARY ?>">Search</button>
    </form>
    <?php if ($canManage): ?>
      <a href="product_form.php" class="<?= BTN_PRIMARY ?>">+ Add product</a>
    <?php endif; ?>
  </div>
</div>

<div class="<?= CARD ?> overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
      <thead class="bg-slate-50">
        <tr class="text-xs uppercase tracking-wide text-slate-500">
          <th class="px-4 py-3">Product #</th>
          <th class="px-4 py-3">Name / Brand</th>
          <th class="px-4 py-3">Size</th>
          <th class="px-4 py-3">Bought</th>
          <th class="px-4 py-3">Sold</th>
          <th class="px-4 py-3">Status</th>
          <?php if ($canManage): ?><th class="px-4 py-3 text-right">Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($products as $p): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 font-mono text-xs text-slate-500"><?= e($p['product_number']) ?></td>
            <td class="px-4 py-3">
              <p class="font-medium text-slate-800"><?= e($p['name']) ?></p>
              <p class="text-xs text-slate-500"><?= e($p['brand'] ?: '—') ?></p>
            </td>
            <td class="px-4 py-3 text-slate-600"><?= e($p['size'] ?: '—') ?></td>
            <td class="px-4 py-3 text-slate-600"><?= money($p['bought_price']) ?></td>
            <td class="px-4 py-3 text-slate-600"><?= $p['sold_price'] !== null ? money($p['sold_price']) : '—' ?></td>
            <td class="px-4 py-3">
              <?php if ($canManage): ?>
                <form method="post" class="inline">
                  <input type="hidden" name="action" value="status">
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <select name="status" onchange="this.form.submit()" class="rounded-lg border-0 bg-transparent py-1 text-xs font-medium focus:ring-2 focus:ring-indigo-200">
                    <?php foreach (STATUSES as $key => $meta): ?>
                      <option value="<?= e($key) ?>" <?= $p['status'] === $key ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              <?php else: ?>
                <?= statusBadge($p['status']) ?>
              <?php endif; ?>
            </td>
            <?php if ($canManage): ?>
              <td class="px-4 py-3">
                <div class="flex justify-end gap-1">
                  <a href="product_form.php?id=<?= (int) $p['id'] ?>" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Edit">✎</a>
                  <form method="post" onsubmit="return confirm('Delete this product? This can\'t be undone.');" class="inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Delete">🗑</button>
                  </form>
                </div>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        <?php if (!$products): ?>
          <tr><td colspan="<?= $canManage ? 7 : 6 ?>" class="px-4 py-10 text-center text-slate-400">No products yet.<?= $canManage ? ' Click "Add product" to create one.' : '' ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
