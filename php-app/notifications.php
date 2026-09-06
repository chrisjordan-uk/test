<?php
require __DIR__ . '/config.php';
requireLogin();

$me = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$id, $me['id']]);
    } elseif ($action === 'mark_all_read') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$me['id']]);
    } elseif ($action === 'clear_read') {
        $pdo->prepare('DELETE FROM notifications WHERE user_id = ? AND is_read = 1')->execute([$me['id']]);
    }
    redirect('notifications.php');
}

$notifications = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
$notifications->execute([$me['id']]);
$notifications = $notifications->fetchAll();

$typeIcons = [
    'task_assigned' => '📋',
    'task_completed' => '✅',
];

$pageTitle = 'Notifications';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
  <div>
    <h1 class="text-2xl font-bold text-slate-900">Notifications</h1>
    <p class="mt-1 text-sm text-slate-500">Task assignments and completions land here.</p>
  </div>
  <div class="flex gap-2">
    <form method="post"><input type="hidden" name="action" value="clear_read">
      <button type="submit" class="<?= BTN_SECONDARY ?>">Clear read</button>
    </form>
    <form method="post"><input type="hidden" name="action" value="mark_all_read">
      <button type="submit" class="<?= BTN_PRIMARY ?>">Mark all as read</button>
    </form>
  </div>
</div>

<div class="<?= CARD ?> overflow-hidden">
  <ul class="divide-y divide-slate-100">
    <?php foreach ($notifications as $n): ?>
      <li class="flex items-center justify-between gap-4 px-5 py-4 transition-colors <?= $n['is_read'] ? '' : 'bg-indigo-50/50' ?>">
        <a href="<?= e($n['link'] ?: '#') ?>" class="flex min-w-0 flex-1 items-center gap-3">
          <span class="text-lg"><?= $typeIcons[$n['type']] ?? '🔔' ?></span>
          <span class="min-w-0">
            <span class="block truncate text-sm <?= $n['is_read'] ? 'text-slate-600' : 'font-semibold text-slate-900' ?>"><?= e($n['message']) ?></span>
            <span class="block text-xs text-slate-400"><?= date('M j, Y H:i', strtotime($n['created_at'])) ?></span>
          </span>
        </a>
        <div class="flex shrink-0 items-center gap-2">
          <?php if (!$n['is_read']): ?>
            <span class="notif-dot h-2 w-2 rounded-full bg-red-500"></span>
            <form method="post">
              <input type="hidden" name="action" value="mark_read">
              <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
              <button type="submit" class="text-xs font-medium text-indigo-600 hover:text-indigo-700">Mark read</button>
            </form>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
    <?php if (!$notifications): ?>
      <li class="px-5 py-10 text-center text-slate-400">No notifications yet.</li>
    <?php endif; ?>
  </ul>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
