<?php
require __DIR__ . '/config.php';
requirePermission('products', 'manage');

$numbersRaw = '';
$targetStatus = 'shipped';
$result = null; // ['updated' => [...], 'notFound' => [...]]

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numbersRaw = $_POST['numbers'] ?? '';
    $targetStatus = $_POST['status'] ?? '';

    // Accept numbers separated by newlines, commas, semicolons or spaces —
    // whatever's fastest to paste from a spreadsheet or a scanner app.
    $entered = preg_split('/[\s,;]+/', trim($numbersRaw), -1, PREG_SPLIT_NO_EMPTY);
    $entered = array_values(array_unique(array_map('strtoupper', $entered)));

    if (!$entered) {
        flash('error', 'Enter at least one product number.');
    } elseif (!array_key_exists($targetStatus, STATUSES)) {
        flash('error', 'Invalid status.');
    } else {
        $placeholders = implode(',', array_fill(0, count($entered), '?'));
        $stmt = $pdo->prepare("SELECT * FROM products WHERE UPPER(product_number) IN ($placeholders)");
        $stmt->execute($entered);
        $matched = $stmt->fetchAll();

        $updated = [];
        $pdo->beginTransaction();
        try {
            foreach ($matched as $product) {
                $pdo->prepare('UPDATE products SET status = ? WHERE id = ?')->execute([$targetStatus, $product['id']]);
                $product['status'] = $targetStatus;
                syncPurchaseTransaction($pdo, $product);
                syncSaleTransaction($pdo, $product, currentUser()['id']);
                $updated[] = $product['product_number'];
            }

            if ($updated) {
                logActivity(
                    $pdo,
                    'product.bulk_status_change',
                    count($updated) . ' product(s) → ' . (STATUSES[$targetStatus]['label'] ?? $targetStatus) . ': ' . implode(', ', $updated),
                    'product'
                );
            }

            $pdo->commit();

            $matchedNumbers = array_map(fn($p) => strtoupper($p['product_number']), $matched);
            $notFound = array_values(array_diff($entered, $matchedNumbers));

            $result = ['updated' => $updated, 'notFound' => $notFound];
            if ($updated) {
                flash('success', count($updated) . ' product(s) updated to "' . (STATUSES[$targetStatus]['label'] ?? $targetStatus) . '".');
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            flash('error', 'Could not update those products.');
        }
    }
}

$pageTitle = 'Bulk status update';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-6">
  <h1 class="text-2xl font-bold text-slate-900">Bulk status update</h1>
  <p class="mt-1 text-sm text-slate-500">
    Paste a list of product numbers (one per line, or separated by commas/spaces) and change all of them
    to the same status in one go — handy for marking a whole batch of parcels as shipped.
  </p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
  <form method="post" class="<?= CARD ?> space-y-4 p-6">
    <div>
      <label class="<?= LABEL ?>">Product numbers</label>
      <textarea name="numbers" rows="12" class="<?= INPUT ?> font-mono" placeholder="VR-000001&#10;VR-000002&#10;VR-000003&#10;…" required><?= e($numbersRaw) ?></textarea>
    </div>
    <div>
      <label class="<?= LABEL ?>">New status for all of them</label>
      <select name="status" class="<?= INPUT ?>">
        <?php foreach (STATUSES as $key => $meta): ?>
          <option value="<?= e($key) ?>" <?= $targetStatus === $key ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex justify-end gap-2">
      <a href="products.php" class="<?= BTN_SECONDARY ?>">Back to Products</a>
      <button type="submit" class="<?= BTN_PRIMARY ?>">Apply to all</button>
    </div>
  </form>

  <div class="<?= CARD ?> p-6">
    <h2 class="mb-3 text-sm font-semibold text-slate-900">Result</h2>
    <?php if (!$result): ?>
      <p class="text-sm text-slate-400">Results of your last bulk update will show up here.</p>
    <?php else: ?>
      <p class="mb-3 text-sm text-emerald-700">✓ <?= count($result['updated']) ?> updated to "<?= e(STATUSES[$targetStatus]['label'] ?? $targetStatus) ?>".</p>
      <?php if ($result['notFound']): ?>
        <p class="mb-2 text-sm font-medium text-red-600"><?= count($result['notFound']) ?> not found — check for typos:</p>
        <div class="max-h-64 overflow-y-auto rounded-lg bg-red-50 p-3 font-mono text-xs text-red-700">
          <?= e(implode(', ', $result['notFound'])) ?>
        </div>
      <?php else: ?>
        <p class="text-sm text-slate-500">Every number entered was matched.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
