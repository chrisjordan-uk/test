<?php
require __DIR__ . '/config.php';

if (currentUser()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT u.id, u.username, u.password_hash, u.full_name, u.is_active,
                    r.id AS role_id, r.name AS role_name, r.permissions
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.username = ?'
        );
        $stmt->execute([$username]);
        $row = $stmt->fetch();

        if (!$row || !$row['is_active'] || !password_verify($password, $row['password_hash'])) {
            $error = 'Invalid username or password.';
        } else {
            $_SESSION['user'] = [
                'id' => (int) $row['id'],
                'username' => $row['username'],
                'full_name' => $row['full_name'],
                'role_id' => (int) $row['role_id'],
                'role_name' => $row['role_name'],
                'permissions' => normalizePermissions($row['permissions']),
            ];
            redirect('index.php');
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in — Vinted Resell Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>body{font-family:'Inter',ui-sans-serif,system-ui,sans-serif;}</style>
</head>
<body class="flex min-h-screen items-center justify-center bg-gradient-to-br from-indigo-700 via-indigo-600 to-slate-900 px-4">
  <div class="w-full max-w-md">
    <div class="mb-8 flex flex-col items-center text-white">
      <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 text-3xl backdrop-blur">🏷️</div>
      <h1 class="text-2xl font-bold">Vinted Resell Manager</h1>
      <p class="mt-1 text-sm text-white/70">Sign in to manage your business</p>
    </div>

    <form method="post" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
      <div>
        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500" for="username">Username</label>
        <input id="username" name="username" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" autofocus required>
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500" for="password">Password</label>
        <input id="password" type="password" name="password" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" required>
      </div>

      <?php if ($error): ?>
        <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600"><?= e($error) ?></p>
      <?php endif; ?>

      <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Sign in</button>
    </form>
  </div>
</body>
</html>
