<?php
require __DIR__ . '/config.php';
requirePermission('profit', 'manage');

$errors = [];
$form = ['type' => 'purchase', 'description' => '', 'amount' => '', 'quantity' => 1, 'transaction_date' => date('Y-m-d'), 'category' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'type' => $_POST['type'] ?? '',
        'description' => trim($_POST['description'] ?? ''),
        'amount' => $_POST['amount'] ?? '',
        'quantity' => $_POST['quantity'] ?? 1,
        'transaction_date' => $_POST['transaction_date'] ?? '',
        'category' => trim($_POST['category'] ?? ''),
    ];

    if (!in_array($form['type'], ['purchase', 'sale', 'expense'], true)) {
        $errors[] = 'Invalid type.';
    }
    if ($form['description'] === '') {
        $errors[] = 'Description is required.';
    }
    if ($form['amount'] === '' || !is_numeric($form['amount'])) {
        $errors[] = 'Amount must be a number.';
    }
    if (!$form['transaction_date']) {
        $errors[] = 'Date is required.';
    }

    if (!$errors) {
        $pdo->prepare(
            'INSERT INTO transactions (type, category, description, amount, quantity, transaction_date, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $form['type'], $form['category'] ?: null, $form['description'], $form['amount'],
            (int) ($form['quantity'] ?: 1), $form['transaction_date'], currentUser()['id'],
        ]);
        $newId = (int) $pdo->lastInsertId();
        logActivity($pdo, 'transaction.create', ucfirst($form['type']) . ": {$form['description']} (" . money($form['amount'] * ($form['quantity'] ?: 1)) . ')', 'transaction', $newId);
        flash('success', 'Entry added.');
        redirect('profit.php');
    }
}

$pageTitle = 'Add profit entry';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-6"><h1 class="text-2xl font-bold text-slate-900">Add profit entry</h1></div>

<form method="post" class="<?= CARD ?> max-w-lg space-y-4 p-6">
  <?php if ($errors): ?>
    <div class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">
      <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div>
    <label class="<?= LABEL ?>">Type</label>
    <select name="type" class="<?= INPUT ?>">
      <option value="purchase" <?= $form['type'] === 'purchase' ? 'selected' : '' ?>>Stock purchase</option>
      <option value="sale" <?= $form['type'] === 'sale' ? 'selected' : '' ?>>Sale</option>
      <option value="expense" <?= $form['type'] === 'expense' ? 'selected' : '' ?>>Expense (shipping, fees…)</option>
    </select>
  </div>
  <div>
    <label class="<?= LABEL ?>">Description</label>
    <input name="description" class="<?= INPUT ?>" placeholder="e.g. New batch of 10 t-shirts" value="<?= e($form['description']) ?>" required>
  </div>
  <div class="grid grid-cols-2 gap-4">
    <div>
      <label class="<?= LABEL ?>">Amount per unit (£)</label>
      <input type="number" min="0" step="0.01" name="amount" class="<?= INPUT ?>" value="<?= e((string) $form['amount']) ?>" required>
    </div>
    <div>
      <label class="<?= LABEL ?>">Quantity</label>
      <input type="number" min="1" name="quantity" class="<?= INPUT ?>" value="<?= e((string) $form['quantity']) ?>">
    </div>
  </div>
  <div>
    <label class="<?= LABEL ?>">Date</label>
    <input type="date" name="transaction_date" class="<?= INPUT ?>" value="<?= e($form['transaction_date']) ?>" required>
  </div>
  <div>
    <label class="<?= LABEL ?>">Category <span class="normal-case font-normal text-slate-400">(optional, mostly useful for expenses)</span></label>
    <input name="category" list="category-options" class="<?= INPUT ?>" value="<?= e($form['category']) ?>" placeholder="e.g. Shipping">
    <datalist id="category-options">
      <?php foreach (EXPENSE_CATEGORIES as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?>
    </datalist>
  </div>

  <div class="flex justify-end gap-2">
    <a href="profit.php" class="<?= BTN_SECONDARY ?>">Cancel</a>
    <button type="submit" class="<?= BTN_PRIMARY ?>">Add entry</button>
  </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
