<?php
require __DIR__ . '/config.php';
requirePermission('users', 'manage');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM roles WHERE id = ?');
$stmt->execute([$id]);
$role = $stmt->fetch();
if (!$role) {
    flash('error', 'Role not found.');
    redirect('users.php?tab=roles');
}

$description = $role['description'];
$permissions = normalizePermissions($role['permissions']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description'] ?? '');
    foreach (FEATURES as $f) {
        $level = $_POST['perm'][$f] ?? 'none';
        $permissions[$f] = in_array($level, LEVELS, true) ? $level : 'none';
    }
    $pdo->prepare('UPDATE roles SET description = ?, permissions = ? WHERE id = ?')
        ->execute([$description ?: null, json_encode($permissions), $id]);
    flash('success', 'Role updated.');
    redirect('users.php?tab=roles');
}

$pageTitle = 'Edit role';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-6"><h1 class="text-2xl font-bold text-slate-900">Edit "<?= e($role['name']) ?>" permissions</h1></div>

<form method="post" class="<?= CARD ?> max-w-lg space-y-4 p-6">
  <div>
    <label class="<?= LABEL ?>">Description</label>
    <input name="description" class="<?= INPUT ?>" value="<?= e($description) ?>">
  </div>

  <div class="space-y-3">
    <?php foreach (FEATURES as $f): ?>
      <div class="flex items-center justify-between">
        <span class="text-sm capitalize text-slate-700"><?= e($f) ?></span>
        <div class="flex gap-3">
          <?php foreach (LEVELS as $lvl): ?>
            <label class="flex items-center gap-1 text-xs font-medium capitalize text-slate-600">
              <input type="radio" name="perm[<?= e($f) ?>]" value="<?= e($lvl) ?>" <?= $permissions[$f] === $lvl ? 'checked' : '' ?>>
              <?= e($lvl) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="flex justify-end gap-2">
    <a href="users.php?tab=roles" class="<?= BTN_SECONDARY ?>">Cancel</a>
    <button type="submit" class="<?= BTN_PRIMARY ?>">Save</button>
  </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
