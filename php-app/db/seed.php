<?php
/**
 * Visit this file once in your browser (e.g. yoursite.com/db/seed.php)
 * after importing database.sql, to create the default roles and the
 * first admin account. Safe to run more than once — it only creates
 * what's missing. You can delete this file afterwards if you like.
 */

require __DIR__ . '/../config.php';

$created = [];
$skipped = [];

foreach (DEFAULT_ROLES as $role) {
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
    $stmt->execute([$role['name']]);
    $id = $stmt->fetchColumn();

    if ($id) {
        $pdo->prepare('UPDATE roles SET description = ?, permissions = ? WHERE id = ?')
            ->execute([$role['description'], json_encode($role['permissions']), $id]);
        $skipped[] = "Role \"{$role['name']}\" already existed — permissions refreshed.";
    } else {
        $pdo->prepare('INSERT INTO roles (name, description, permissions) VALUES (?, ?, ?)')
            ->execute([$role['name'], $role['description'], json_encode($role['permissions'])]);
        $created[] = "Created role \"{$role['name']}\".";
    }
}

$stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute([SEED_ADMIN_USERNAME]);
$adminExists = $stmt->fetchColumn();

if ($adminExists) {
    $skipped[] = 'User "' . SEED_ADMIN_USERNAME . '" already exists — left untouched.';
} else {
    $adminRoleId = $pdo->query('SELECT id FROM roles WHERE name = "Admin"')->fetchColumn();
    $pdo->prepare('INSERT INTO users (username, password_hash, full_name, email, role_id) VALUES (?, ?, ?, ?, ?)')
        ->execute([
            SEED_ADMIN_USERNAME,
            password_hash(SEED_ADMIN_PASSWORD, PASSWORD_DEFAULT),
            'Administrator',
            SEED_ADMIN_EMAIL,
            $adminRoleId,
        ]);
    $created[] = 'Created admin user "' . SEED_ADMIN_USERNAME . '" with the password from config.php (SEED_ADMIN_PASSWORD).';
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><title>Seeding…</title>
<style>body{font-family:ui-sans-serif,system-ui,sans-serif;max-width:640px;margin:60px auto;padding:0 20px;color:#1e293b;}
li{margin-bottom:6px} code{background:#f1f5f9;padding:2px 6px;border-radius:4px}</style>
</head>
<body>
<h1>Database seed</h1>
<?php if ($created): ?>
  <p><strong>Done:</strong></p>
  <ul><?php foreach ($created as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
<?php if ($skipped): ?>
  <p><strong>Already in place:</strong></p>
  <ul><?php foreach ($skipped as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
<p>Log in at <a href="../login.php">login.php</a> with username
<code><?= e(SEED_ADMIN_USERNAME) ?></code> and the password from
<code>SEED_ADMIN_PASSWORD</code> in <code>config.php</code>, then change it
right away from the Users page. You can delete this <code>db/seed.php</code>
file now if you want.</p>
</body>
</html>
