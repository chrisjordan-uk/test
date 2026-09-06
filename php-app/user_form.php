<?php
require __DIR__ . '/config.php';
requirePermission('users', 'manage');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$existing = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        flash('error', 'User not found.');
        redirect('users.php');
    }
}

$roles = $pdo->query('SELECT id, name FROM roles ORDER BY id')->fetchAll();

$activity = [];
if ($existing) {
    // Everything this account did (logins, their own edits) plus anything
    // done to the account itself (created, role changed, etc. by someone else).
    $stmt = $pdo->prepare(
        "SELECT * FROM activity_log
         WHERE user_id = ? OR (entity_type = 'user' AND entity_id = ?)
         ORDER BY created_at DESC, id DESC LIMIT 100"
    );
    $stmt->execute([$id, $id]);
    $activity = $stmt->fetchAll();
}

$errors = [];
$form = $existing ?: ['username' => '', 'full_name' => '', 'email' => '', 'role_id' => $roles[0]['id'] ?? '', 'is_active' => 1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'username' => trim($_POST['username'] ?? ''),
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'role_id' => (int) ($_POST['role_id'] ?? 0),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
    $password = $_POST['password'] ?? '';

    if (!$existing && $form['username'] === '') {
        $errors[] = 'Username is required.';
    }
    if (!$existing && strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== '' && strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if (!$form['role_id']) {
        $errors[] = 'Please choose a role.';
    }

    if (!$errors) {
        try {
            $roleName = null;
            foreach ($roles as $r) {
                if ((int) $r['id'] === (int) $form['role_id']) {
                    $roleName = $r['name'];
                }
            }

            if ($existing) {
                if ($password !== '') {
                    $pdo->prepare('UPDATE users SET full_name = ?, email = ?, role_id = ?, is_active = ?, password_hash = ? WHERE id = ?')
                        ->execute([$form['full_name'] ?: null, $form['email'] ?: null, $form['role_id'], $form['is_active'], password_hash($password, PASSWORD_DEFAULT), $id]);
                } else {
                    $pdo->prepare('UPDATE users SET full_name = ?, email = ?, role_id = ?, is_active = ? WHERE id = ?')
                        ->execute([$form['full_name'] ?: null, $form['email'] ?: null, $form['role_id'], $form['is_active'], $id]);
                }
                $summary = "Updated user \"{$existing['username']}\" (role: $roleName, " . ($form['is_active'] ? 'active' : 'disabled') . ($password !== '' ? ', password changed' : '') . ')';
                logActivity($pdo, 'user.update', $summary, 'user', $id);
                flash('success', 'User updated.');
            } else {
                $pdo->prepare('INSERT INTO users (username, password_hash, full_name, email, role_id, is_active) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$form['username'], password_hash($password, PASSWORD_DEFAULT), $form['full_name'] ?: null, $form['email'] ?: null, $form['role_id'], $form['is_active']]);
                $newId = (int) $pdo->lastInsertId();
                logActivity($pdo, 'user.create', "Created user \"{$form['username']}\" (role: $roleName)", 'user', $newId);
                flash('success', 'User created.');
            }
            redirect('users.php');
        } catch (Exception $e) {
            $errors[] = str_contains($e->getMessage(), 'Duplicate entry') ? 'That username is already taken.' : 'Could not save the user.';
        }
    }
}

$pageTitle = $existing ? 'Edit user' : 'Add user';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-6">
  <h1 class="text-2xl font-bold text-slate-900"><?= $existing ? 'Edit user: ' . e($existing['username']) : 'Add user' ?></h1>
</div>

<div class="grid grid-cols-1 gap-6 <?= $existing ? 'lg:grid-cols-2' : '' ?>">
<form method="post" class="<?= CARD ?> <?= $existing ? '' : 'max-w-lg' ?> space-y-4 p-6">
  <?php if ($errors): ?>
    <div class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">
      <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div>
    <label class="<?= LABEL ?>">Username</label>
    <input name="username" class="<?= INPUT ?>" value="<?= e($form['username']) ?>" <?= $existing ? 'disabled' : 'required' ?>>
  </div>
  <div>
    <label class="<?= LABEL ?>">Full name</label>
    <input name="full_name" class="<?= INPUT ?>" value="<?= e($form['full_name']) ?>">
  </div>
  <div>
    <label class="<?= LABEL ?>">Email</label>
    <input type="email" name="email" class="<?= INPUT ?>" value="<?= e($form['email']) ?>">
  </div>
  <div>
    <label class="<?= LABEL ?>">Role</label>
    <select name="role_id" class="<?= INPUT ?>">
      <?php foreach ($roles as $r): ?>
        <option value="<?= (int) $r['id'] ?>" <?= (int) $form['role_id'] === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="<?= LABEL ?>"><?= $existing ? 'New password (optional)' : 'Password' ?></label>
    <input type="password" name="password" class="<?= INPUT ?>" minlength="6" <?= $existing ? '' : 'required' ?>>
  </div>
  <?php if ($existing): ?>
    <label class="flex items-center gap-2 text-sm text-slate-700">
      <input type="checkbox" name="is_active" <?= $form['is_active'] ? 'checked' : '' ?>>
      Account active
    </label>
  <?php endif; ?>

  <div class="flex justify-end gap-2">
    <a href="users.php" class="<?= BTN_SECONDARY ?>">Cancel</a>
    <button type="submit" class="<?= BTN_PRIMARY ?>">Save</button>
  </div>
</form>

<?php if ($existing): ?>
  <div class="<?= CARD ?> overflow-hidden">
    <div class="border-b border-slate-100 px-5 py-4">
      <h2 class="text-sm font-semibold text-slate-900">Activity for this user</h2>
      <p class="mt-0.5 text-xs text-slate-500">Every sign-in and change made by (or affecting) this account.</p>
    </div>
    <div class="max-h-[32rem] overflow-y-auto">
      <table class="w-full text-left text-sm">
        <thead class="sticky top-0 bg-slate-50">
          <tr class="text-xs uppercase tracking-wide text-slate-500">
            <th class="px-4 py-3">When</th>
            <th class="px-4 py-3">Action</th>
            <th class="px-4 py-3">Details</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($activity as $entry): ?>
            <tr class="hover:bg-slate-50">
              <td class="px-4 py-3 whitespace-nowrap text-slate-500"><?= date('M j, Y H:i', strtotime($entry['created_at'])) ?></td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                  <?= e(ACTIVITY_LABELS[$entry['action']] ?? $entry['action']) ?>
                </span>
              </td>
              <td class="px-4 py-3 text-slate-700"><?= e($entry['description']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$activity): ?>
            <tr><td colspan="3" class="px-4 py-10 text-center text-slate-400">No activity recorded for this user yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if (count($activity) === 100): ?>
      <div class="border-t border-slate-100 px-5 py-3 text-center">
        <a href="users.php?tab=activity&user=<?= urlencode($existing['username']) ?>" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">View full history →</a>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
