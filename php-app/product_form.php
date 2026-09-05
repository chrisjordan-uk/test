<?php
require __DIR__ . '/config.php';
requirePermission('products', 'manage');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$product = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        flash('error', 'Product not found.');
        redirect('products.php');
    }
}

$errors = [];
$form = $product ?: [
    'product_number' => '', 'name' => '', 'brand' => '', 'category' => '', 'size' => '',
    'color' => '', 'item_condition' => CONDITIONS[2], 'status' => 'available',
    'bought_price' => '', 'sold_price' => '', 'purchase_date' => date('Y-m-d'),
    'sold_date' => '', 'buyer' => '', 'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'product_number' => trim($_POST['product_number'] ?? ''),
        'name' => trim($_POST['name'] ?? ''),
        'brand' => trim($_POST['brand'] ?? ''),
        'category' => trim($_POST['category'] ?? ''),
        'size' => trim($_POST['size'] ?? ''),
        'color' => trim($_POST['color'] ?? ''),
        'item_condition' => $_POST['item_condition'] ?? '',
        'status' => $_POST['status'] ?? 'available',
        'bought_price' => $_POST['bought_price'] ?? '',
        'sold_price' => trim($_POST['sold_price'] ?? '') === '' ? null : $_POST['sold_price'],
        'purchase_date' => $_POST['purchase_date'] ?: null,
        'sold_date' => $_POST['sold_date'] ?: null,
        'buyer' => trim($_POST['buyer'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
    ];

    if ($form['name'] === '') {
        $errors[] = 'Name is required.';
    }
    if ($form['bought_price'] === '' || !is_numeric($form['bought_price'])) {
        $errors[] = 'Bought price is required and must be a number.';
    }
    if (!array_key_exists($form['status'], STATUSES)) {
        $errors[] = 'Invalid status.';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            if ($product) {
                $pdo->prepare(
                    'UPDATE products SET product_number = ?, name = ?, brand = ?, category = ?, size = ?, color = ?,
                     item_condition = ?, status = ?, bought_price = ?, sold_price = ?, purchase_date = ?, sold_date = ?,
                     buyer = ?, notes = ? WHERE id = ?'
                )->execute([
                    $form['product_number'] ?: null, $form['name'], $form['brand'] ?: null, $form['category'] ?: null,
                    $form['size'] ?: null, $form['color'] ?: null, $form['item_condition'] ?: null, $form['status'],
                    $form['bought_price'], $form['sold_price'], $form['purchase_date'], $form['sold_date'],
                    $form['buyer'] ?: null, $form['notes'] ?: null, $id,
                ]);
            } else {
                $pdo->prepare(
                    'INSERT INTO products (product_number, name, brand, category, size, color, item_condition, status,
                     bought_price, sold_price, purchase_date, sold_date, buyer, notes, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $form['product_number'] ?: null, $form['name'], $form['brand'] ?: null, $form['category'] ?: null,
                    $form['size'] ?: null, $form['color'] ?: null, $form['item_condition'] ?: null, $form['status'],
                    $form['bought_price'], $form['sold_price'], $form['purchase_date'], $form['sold_date'],
                    $form['buyer'] ?: null, $form['notes'] ?: null, currentUser()['id'],
                ]);
                $id = (int) $pdo->lastInsertId();
            }

            // Always keep a product number — auto-generate one if it was
            // left blank on create, or cleared out while editing.
            if (!$form['product_number']) {
                $pdo->prepare('UPDATE products SET product_number = ? WHERE id = ?')->execute([generateProductNumber($id), $id]);
            }

            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
            $stmt->execute([$id]);
            $saved = $stmt->fetch();
            syncPurchaseTransaction($pdo, $saved);
            syncSaleTransaction($pdo, $saved, currentUser()['id']);

            $pdo->commit();
            flash('success', $product ? 'Product updated.' : 'Product added.');
            redirect('products.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                $errors[] = 'That product number is already in use.';
            } else {
                $errors[] = 'Could not save the product.';
            }
        }
    }
}

$pageTitle = $product ? 'Edit product' : 'Add product';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-6">
  <h1 class="text-2xl font-bold text-slate-900"><?= $product ? 'Edit product' : 'Add product' ?></h1>
</div>

<form method="post" class="<?= CARD ?> grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">
  <?php if ($errors): ?>
    <div class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600 sm:col-span-2">
      <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="sm:col-span-2">
    <label class="<?= LABEL ?>">Name *</label>
    <input name="name" class="<?= INPUT ?>" value="<?= e($form['name']) ?>" required>
  </div>

  <div>
    <label class="<?= LABEL ?>">Brand</label>
    <input name="brand" class="<?= INPUT ?>" value="<?= e($form['brand']) ?>">
  </div>
  <div>
    <label class="<?= LABEL ?>">Product #</label>
    <input name="product_number" class="<?= INPUT ?>" placeholder="Auto-generated if left blank" value="<?= e($form['product_number']) ?>">
  </div>

  <div>
    <label class="<?= LABEL ?>">Category</label>
    <input name="category" class="<?= INPUT ?>" value="<?= e($form['category']) ?>">
  </div>
  <div>
    <label class="<?= LABEL ?>">Size</label>
    <input name="size" class="<?= INPUT ?>" value="<?= e($form['size']) ?>">
  </div>

  <div>
    <label class="<?= LABEL ?>">Color</label>
    <input name="color" class="<?= INPUT ?>" value="<?= e($form['color']) ?>">
  </div>
  <div>
    <label class="<?= LABEL ?>">Condition</label>
    <select name="item_condition" class="<?= INPUT ?>">
      <?php foreach (CONDITIONS as $c): ?>
        <option value="<?= e($c) ?>" <?= $form['item_condition'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div>
    <label class="<?= LABEL ?>">Status</label>
    <select name="status" class="<?= INPUT ?>">
      <?php foreach (STATUSES as $key => $meta): ?>
        <option value="<?= e($key) ?>" <?= $form['status'] === $key ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="<?= LABEL ?>">Buyer</label>
    <input name="buyer" class="<?= INPUT ?>" value="<?= e($form['buyer']) ?>">
  </div>

  <div>
    <label class="<?= LABEL ?>">Bought for (£) *</label>
    <input type="number" min="0" step="0.01" name="bought_price" class="<?= INPUT ?>" value="<?= e((string) $form['bought_price']) ?>" required>
  </div>
  <div>
    <label class="<?= LABEL ?>">Sold for (£)</label>
    <input type="number" min="0" step="0.01" name="sold_price" class="<?= INPUT ?>" value="<?= e((string) ($form['sold_price'] ?? '')) ?>">
  </div>

  <div>
    <label class="<?= LABEL ?>">Purchase date</label>
    <input type="date" name="purchase_date" class="<?= INPUT ?>" value="<?= e($form['purchase_date'] ?? '') ?>">
  </div>
  <div>
    <label class="<?= LABEL ?>">Sold date</label>
    <input type="date" name="sold_date" class="<?= INPUT ?>" value="<?= e($form['sold_date'] ?? '') ?>">
  </div>

  <div class="sm:col-span-2">
    <label class="<?= LABEL ?>">Notes</label>
    <textarea name="notes" rows="2" class="<?= INPUT ?>"><?= e($form['notes']) ?></textarea>
  </div>

  <div class="flex justify-end gap-2 sm:col-span-2">
    <a href="products.php" class="<?= BTN_SECONDARY ?>">Cancel</a>
    <button type="submit" class="<?= BTN_PRIMARY ?>"><?= $product ? 'Save changes' : 'Add product' ?></button>
  </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
