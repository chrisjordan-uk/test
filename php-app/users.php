<?php
require __DIR__ . '/config.php';
requirePermission('users', 'view');

$canManage = can('users', 'manage');
$me = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) {
        flash('error', 'You do not have permission to do that.');
        redirect('users.php');
    }
    if (($_POST['action'] ?? '') === 'delete_user') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === $me['id']) {
            flash('error', 'You cannot delete your own account.');
        } else {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            flash('success', 'User deleted.');
        }
    }
    redirect('users.php?tab=' . ($_GET['tab'] === 'roles' ? 'roles' : 'users'));
}

$tab = ($_GET['tab'] ?? 'users') === 'roles' ? 'roles' : 'users';

$users = $pdo->query(
    'SELECT u.id, u.username, u.full_name, u.is_active, r.name AS role_name
     FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.username'
)->fetchAll();

$roles = $pdo->query('SELECT * FROM roles ORDER BY id')->fetchAll();

$pageTitle = 'Users & Roles';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
  <div>
    <h1 class="text-2xl font-bold text-slate-900">Users &amp; Roles</h1>
    <p class="mt-1 text-sm text-slate-500">Control who can access each part of the system.</p>
  </div>
  <div class="flex gap-1 rounded-lg bg-slate-100 p-1 text-sm">
    <a href="?tab=users" class="rounded-md px-4 py-1.5 font-medium <?= $tab === 'users' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500' ?>">Users</a>
    <a href="?tab=roles" class="rounded-md px-4 py-1.5 font-medium <?= $tab === 'roles' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500' ?>">Roles &amp; permissions</a>
  </div>
</div>

<?php if ($tab === 'users'): ?>
  <?php if ($canManage): ?>
    <div class="mb-4 flex justify-end">
      <a href="user_form.php" class="<?= BTN_PRIMARY ?>">+ Add user</a>
    </div>
  <?php endif; ?>
  <div class="<?= CARD ?> overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50">
          <tr class="text-xs uppercase tracking-wide text-slate-500">
            <th class="px-4 py-3">Username</th>
            <th class="px-4 py-3">Full name</th>
            <th class="px-4 py-3">Role</th>
            <th class="px-4 py-3">Status</th>
            <?php if ($canManage): ?><th class="px-4 py-3 text-right">Actions</th><?php endif; ?>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($users as $u): ?>
            <tr class="hover:bg-slate-50">
              <td class="px-4 py-3 font-medium text-slate-800"><?= e($u['username']) ?></td>
              <td class="px-4 py-3 text-slate-600"><?= e($u['full_name'] ?: '—') ?></td>
              <td class="px-4 py-3"><span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700"><?= e($u['role_name']) ?></span></td>
              <td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $u['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>"><?= $u['is_active'] ? 'Active' : 'Disabled' ?></span></td>
              <?php if ($canManage): ?>
                <td class="px-4 py-3">
                  <div class="flex justify-end gap-1">
                    <a href="user_form.php?id=<?= (int) $u['id'] ?>" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">✎</a>
                    <?php if ($u['id'] != $me['id']): ?>
                      <form method="post" onsubmit="return confirm('Delete this user?');" class="inline">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <button type="submit" class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600">🗑</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php else: ?>
  <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <?php foreach ($roles as $role): $perms = normalizePermissions($role['permissions']); ?>
      <div class="<?= CARD ?> p-5">
        <div class="mb-1 flex items-center justify-between">
          <h3 class="font-semibold text-slate-900"><?= e($role['name']) ?></h3>
          <?php if ($canManage): ?>
            <a href="role_form.php?id=<?= (int) $role['id'] ?>" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">✎</a>
          <?php endif; ?>
        </div>
        <p class="mb-4 text-xs text-slate-500"><?= e($role['description']) ?></p>
        <ul class="space-y-2">
          <?php foreach (FEATURES as $f): $level = $perms[$f];
            $cls = $level === 'manage' ? 'bg-emerald-100 text-emerald-700' : ($level === 'view' ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-500');
            $label = ['none' => 'No access', 'view' => 'View only', 'manage' => 'Full access'][$level]; ?>
            <li class="flex items-center justify-between text-sm">
              <span class="capitalize text-slate-600"><?= e($f) ?></span>
              <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $cls ?>"><?= $label ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
