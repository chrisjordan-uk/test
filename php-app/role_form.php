<?php
require __DIR__ . '/config.php';
requirePermission('users', 'manage');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$role = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM roles WHERE id = ?');
    $stmt->execute([$id]);
    $role = $stmt->fetch();
    if (!$role) {
        flash('error', 'Role not found.');
        redirect('users.php?tab=roles');
    }
}

$name = $role['name'] ?? '';
$description = $role['description'] ?? '';
$permissions = normalizePermissions($role['permissions'] ?? []);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $before = $permissions;
    foreach (FEATURES as $f) {
        $level = $_POST['perm'][$f] ?? 'none';
        $permissions[$f] = in_array($level, LEVELS, true) ? $level : 'none';
    }

    if ($name === '') {
        $errors[] = 'Role name is required.';
    }

    if (!$errors) {
        try {
            if ($role) {
                $pdo->prepare('UPDATE roles SET name = ?, description = ?, permissions = ? WHERE id = ?')
                    ->execute([$name, $description ?: null, json_encode($permissions), $id]);

                $changes = [];
                if ($before !== $permissions) {
                    foreach (FEATURES as $f) {
                        if ($before[$f] !== $permissions[$f]) {
                            $changes[] = "$f: {$before[$f]} → {$permissions[$f]}";
                        }
                    }
                }
                $renamed = $role['name'] !== $name ? " (renamed from \"{$role['name']}\")" : '';
                logActivity($pdo, 'role.update', "Updated \"$name\"$renamed permissions" . ($changes ? ' (' . implode(', ', $changes) . ')' : ''), 'role', $id);
                flash('success', 'Role updated.');
            } else {
                $pdo->prepare('INSERT INTO roles (name, description, permissions) VALUES (?, ?, ?)')
                    ->execute([$name, $description ?: null, json_encode($permissions)]);
                $newId = (int) $pdo->lastInsertId();
                logActivity($pdo, 'role.create', "Created role \"$name\"", 'role', $newId);
                flash('success', 'Role created.');
            }
            redirect('users.php?tab=roles');
        } catch (Exception $e) {
            $errors[] = str_contains($e->getMessage(), 'Duplicate entry') ? 'A role with that name already exists.' : 'Could not save the role.';
        }
    }
}

$pageTitle = $role ? 'Edit role' : 'Add role';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-6"><h1 class="text-2xl font-bold text-slate-900"><?= $role ? 'Edit "' . e($role['name']) . '"' : 'Add role' ?></h1></div>

<form method="post" class="<?= CARD ?> max-w-lg space-y-4 p-6">
  <?php if ($errors): ?>
    <div class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">
      <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div>
    <label class="<?= LABEL ?>">Role name</label>
    <input name="name" class="<?= INPUT ?>" value="<?= e($name) ?>" required>
  </div>
  <div>
    <label class="<?= LABEL ?>">Description</label>
    <input name="description" class="<?= INPUT ?>" value="<?= e($description) ?>" placeholder="What this role is for">
  </div>

  <div class="space-y-3">
    <p class="<?= LABEL ?>">Permissions</p>
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
