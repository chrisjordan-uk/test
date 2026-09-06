<?php
require __DIR__ . '/config.php';
requirePermission('tasks', 'view');

$canManage = can('tasks', 'manage');
$me = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
    $stmt->execute([$id]);
    $task = $stmt->fetch();

    if (!$task) {
        flash('error', 'Task not found.');
    } elseif (in_array($action, ['complete', 'reopen'], true) && ($task['assigned_to'] == $me['id'] || $canManage)) {
        if ($action === 'complete') {
            $pdo->prepare('UPDATE tasks SET status = "done", completed_at = NOW() WHERE id = ?')->execute([$id]);
            logActivity($pdo, 'task.complete', "Completed \"{$task['title']}\"", 'task', $id);
            if ($task['assigned_by']) {
                notify($pdo, (int) $task['assigned_by'], 'task_completed', "{$me['username']} completed: \"{$task['title']}\"", 'tasks.php?view=all&show=all');
            }
            flash('success', 'Task marked done.');
        } else {
            $pdo->prepare('UPDATE tasks SET status = "pending", completed_at = NULL WHERE id = ?')->execute([$id]);
            logActivity($pdo, 'task.reopen', "Reopened \"{$task['title']}\"", 'task', $id);
            flash('success', 'Task reopened.');
        }
    } elseif ($action === 'delete' && $canManage) {
        $pdo->prepare('DELETE FROM tasks WHERE id = ?')->execute([$id]);
        logActivity($pdo, 'task.delete', "Deleted task \"{$task['title']}\"", 'task', $id);
        flash('success', 'Task deleted.');
    } else {
        flash('error', 'You do not have permission to do that.');
    }

    redirect('tasks.php?view=' . (($_GET['view'] ?? '') === 'all' ? 'all' : 'mine') . '&show=' . (($_GET['show'] ?? '') === 'all' ? 'all' : 'pending'));
}

$view = ($canManage && ($_GET['view'] ?? 'mine') === 'all') ? 'all' : 'mine';
$showAll = ($_GET['show'] ?? 'pending') === 'all';

$where = [];
$params = [];
if ($view === 'mine') {
    $where[] = 't.assigned_to = ?';
    $params[] = $me['id'];
}
if (!$showAll) {
    $where[] = "t.status = 'pending'";
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$tasks = $pdo->prepare(
    "SELECT t.*, au.username AS assigned_to_name, ab.username AS assigned_by_name
     FROM tasks t
     JOIN users au ON au.id = t.assigned_to
     LEFT JOIN users ab ON ab.id = t.assigned_by
     $whereSql
     ORDER BY t.status ASC, FIELD(t.priority, 'high', 'normal', 'low'), t.due_date IS NULL, t.due_date ASC, t.id DESC"
);
$tasks->execute($params);
$tasks = $tasks->fetchAll();

$pageTitle = 'Tasks';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
  <div>
    <h1 class="text-2xl font-bold text-slate-900">Tasks</h1>
    <p class="mt-1 text-sm text-slate-500">Assign work, keep track of it, and check it off when it's done.</p>
  </div>
  <?php if ($canManage): ?>
    <a href="task_form.php" class="<?= BTN_PRIMARY ?>">+ New task</a>
  <?php endif; ?>
</div>

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
  <?php if ($canManage): ?>
    <div class="flex gap-1 rounded-lg bg-slate-100 p-1 text-sm">
      <a href="?view=mine&show=<?= $showAll ? 'all' : 'pending' ?>" class="rounded-md px-4 py-1.5 font-medium transition-colors <?= $view === 'mine' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500' ?>">My tasks</a>
      <a href="?view=all&show=<?= $showAll ? 'all' : 'pending' ?>" class="rounded-md px-4 py-1.5 font-medium transition-colors <?= $view === 'all' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500' ?>">Everyone's tasks</a>
    </div>
  <?php else: ?>
    <div></div>
  <?php endif; ?>
  <label class="flex items-center gap-2 text-sm text-slate-600">
    <input type="checkbox" onchange="location.href='?view=<?= $view ?>&show=' + (this.checked ? 'all' : 'pending')" <?= $showAll ? 'checked' : '' ?>>
    Show completed
  </label>
</div>

<div class="<?= CARD ?> overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
      <thead class="bg-slate-50">
        <tr class="text-xs uppercase tracking-wide text-slate-500">
          <th class="px-4 py-3"></th>
          <th class="px-4 py-3">Task</th>
          <?php if ($view === 'all'): ?><th class="px-4 py-3">Assigned to</th><?php endif; ?>
          <th class="px-4 py-3">Priority</th>
          <th class="px-4 py-3">Due</th>
          <th class="px-4 py-3">By</th>
          <?php if ($canManage): ?><th class="px-4 py-3 text-right">Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($tasks as $t):
          $done = $t['status'] === 'done';
          $overdue = !$done && $t['due_date'] && $t['due_date'] < date('Y-m-d');
          $canToggle = $t['assigned_to'] == $me['id'] || $canManage;
        ?>
          <tr class="transition-colors hover:bg-slate-50 <?= $done ? 'opacity-60' : '' ?>">
            <td class="px-4 py-3">
              <?php if ($canToggle): ?>
                <form method="post">
                  <input type="hidden" name="action" value="<?= $done ? 'reopen' : 'complete' ?>">
                  <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                  <button type="submit" class="flex h-6 w-6 items-center justify-center rounded-full border-2 transition-colors <?= $done ? 'border-emerald-500 bg-emerald-500 text-white hover:bg-emerald-600' : 'border-slate-300 hover:border-emerald-500' ?>" title="<?= $done ? 'Reopen' : 'Mark done' ?>">
                    <?= $done ? '✓' : '' ?>
                  </button>
                </form>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3">
              <p class="font-medium text-slate-800 <?= $done ? 'line-through' : '' ?>"><?= e($t['title']) ?></p>
              <?php if ($t['description']): ?><p class="mt-0.5 text-xs text-slate-500"><?= e($t['description']) ?></p><?php endif; ?>
            </td>
            <?php if ($view === 'all'): ?>
              <td class="px-4 py-3 text-slate-600"><?= e($t['assigned_to_name']) ?></td>
            <?php endif; ?>
            <td class="px-4 py-3"><?= taskPriorityBadge($t['priority']) ?></td>
            <td class="px-4 py-3 <?= $overdue ? 'font-medium text-red-600' : 'text-slate-500' ?>">
              <?= $t['due_date'] ? ($overdue ? '⚠ ' : '') . fmtDate($t['due_date']) : '—' ?>
            </td>
            <td class="px-4 py-3 text-slate-500"><?= e($t['assigned_by_name'] ?: '—') ?></td>
            <?php if ($canManage): ?>
              <td class="px-4 py-3">
                <div class="flex justify-end gap-1">
                  <a href="task_form.php?id=<?= (int) $t['id'] ?>" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">✎</a>
                  <form method="post" onsubmit="return confirm('Delete this task?');" class="inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                    <button type="submit" class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600">🗑</button>
                  </form>
                </div>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        <?php if (!$tasks): $colspan = 5 + ($view === 'all' ? 1 : 0) + ($canManage ? 1 : 0); ?>
          <tr><td colspan="<?= $colspan ?>" class="px-4 py-10 text-center text-slate-400">Nothing here — enjoy the quiet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
