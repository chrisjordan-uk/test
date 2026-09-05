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
            if ($existing) {
                if ($password !== '') {
                    $pdo->prepare('UPDATE users SET full_name = ?, email = ?, role_id = ?, is_active = ?, password_hash = ? WHERE id = ?')
                        ->execute([$form['full_name'] ?: null, $form['email'] ?: null, $form['role_id'], $form['is_active'], password_hash($password, PASSWORD_DEFAULT), $id]);
                } else {
                    $pdo->prepare('UPDATE users SET full_name = ?, email = ?, role_id = ?, is_active = ? WHERE id = ?')
                        ->execute([$form['full_name'] ?: null, $form['email'] ?: null, $form['role_id'], $form['is_active'], $id]);
                }
                flash('success', 'User updated.');
            } else {
                $pdo->prepare('INSERT INTO users (username, password_hash, full_name, email, role_id, is_active) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$form['username'], password_hash($password, PASSWORD_DEFAULT), $form['full_name'] ?: null, $form['email'] ?: null, $form['role_id'], $form['is_active']]);
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

<div class="mb-6"><h1 class="text-2xl font-bold text-slate-900"><?= $existing ? 'Edit user' : 'Add user' ?></h1></div>

<form method="post" class="<?= CARD ?> max-w-lg space-y-4 p-6">
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

<?php require __DIR__ . '/includes/footer.php'; ?>
