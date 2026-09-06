<?php
require __DIR__ . '/config.php';
requirePermission('tasks', 'manage');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$task = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
    $stmt->execute([$id]);
    $task = $stmt->fetch();
    if (!$task) {
        flash('error', 'Task not found.');
        redirect('tasks.php');
    }
}

// Only offer people who can actually see the Tasks page as assignees.
$users = usersWithFeature($pdo, 'tasks', 'view');

$errors = [];
$form = $task ?: [
    'title' => '', 'description' => '', 'assigned_to' => currentUser()['id'],
    'priority' => 'normal', 'due_date' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'assigned_to' => (int) ($_POST['assigned_to'] ?? 0),
        'priority' => $_POST['priority'] ?? 'normal',
        'due_date' => $_POST['due_date'] ?: null,
    ];

    if ($form['title'] === '') {
        $errors[] = 'Title is required.';
    }
    if (!array_key_exists($form['priority'], PRIORITIES)) {
        $errors[] = 'Invalid priority.';
    }
    $assigneeValid = false;
    foreach ($users as $u) {
        if ((int) $u['id'] === $form['assigned_to']) {
            $assigneeValid = true;
        }
    }
    if (!$assigneeValid) {
        $errors[] = 'Please choose who this task is for.';
    }

    if (!$errors) {
        $assigneeName = '';
        foreach ($users as $u) {
            if ((int) $u['id'] === $form['assigned_to']) {
                $assigneeName = $u['username'];
            }
        }

        if ($task) {
            $pdo->prepare('UPDATE tasks SET title = ?, description = ?, assigned_to = ?, priority = ?, due_date = ? WHERE id = ?')
                ->execute([$form['title'], $form['description'] ?: null, $form['assigned_to'], $form['priority'], $form['due_date'], $id]);
            logActivity($pdo, 'task.update', "Updated task \"{$form['title']}\"", 'task', $id);

            // Reassigned to someone new — let them know they've now got it.
            if ($form['assigned_to'] !== (int) $task['assigned_to']) {
                notify($pdo, $form['assigned_to'], 'task_assigned', "You were assigned: \"{$form['title']}\"", 'tasks.php');
            }
            flash('success', 'Task updated.');
        } else {
            $pdo->prepare('INSERT INTO tasks (title, description, assigned_to, assigned_by, priority, due_date) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$form['title'], $form['description'] ?: null, $form['assigned_to'], currentUser()['id'], $form['priority'], $form['due_date']]);
            $newId = (int) $pdo->lastInsertId();

            $forSelf = $form['assigned_to'] === currentUser()['id'] ? ' (for self)' : " (for $assigneeName)";
            logActivity($pdo, 'task.create', "Assigned task \"{$form['title']}\"$forSelf", 'task', $newId);
            notify($pdo, $form['assigned_to'], 'task_assigned', "You were assigned: \"{$form['title']}\"", 'tasks.php');
            flash('success', 'Task assigned.');
        }
        redirect('tasks.php');
    }
}

$pageTitle = $task ? 'Edit task' : 'New task';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-6"><h1 class="text-2xl font-bold text-slate-900"><?= $task ? 'Edit task' : 'New task' ?></h1></div>

<form method="post" class="<?= CARD ?> max-w-lg space-y-4 p-6">
  <?php if ($errors): ?>
    <div class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">
      <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div>
    <label class="<?= LABEL ?>">Title</label>
    <input name="title" class="<?= INPUT ?>" value="<?= e($form['title']) ?>" placeholder="e.g. Photograph new Zara batch" required>
  </div>
  <div>
    <label class="<?= LABEL ?>">Description (optional)</label>
    <textarea name="description" rows="3" class="<?= INPUT ?>"><?= e($form['description']) ?></textarea>
  </div>
  <div>
    <label class="<?= LABEL ?>">Assign to</label>
    <select name="assigned_to" class="<?= INPUT ?>">
      <?php foreach ($users as $u): ?>
        <option value="<?= (int) $u['id'] ?>" <?= (int) $form['assigned_to'] === (int) $u['id'] ? 'selected' : '' ?>>
          <?= e($u['full_name'] ?: $u['username']) ?><?= (int) $u['id'] === currentUser()['id'] ? ' (you)' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="grid grid-cols-2 gap-4">
    <div>
      <label class="<?= LABEL ?>">Priority</label>
      <select name="priority" class="<?= INPUT ?>">
        <?php foreach (PRIORITIES as $key => $meta): ?>
          <option value="<?= e($key) ?>" <?= $form['priority'] === $key ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="<?= LABEL ?>">Due date (optional)</label>
      <input type="date" name="due_date" class="<?= INPUT ?>" value="<?= e($form['due_date'] ?? '') ?>">
    </div>
  </div>

  <div class="flex justify-end gap-2">
    <a href="tasks.php" class="<?= BTN_SECONDARY ?>">Cancel</a>
    <button type="submit" class="<?= BTN_PRIMARY ?>"><?= $task ? 'Save changes' : 'Assign task' ?></button>
  </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
