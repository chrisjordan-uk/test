<?php
/**
 * Shared page shell (sidebar + topbar) for every logged-in page.
 * Expects $pageTitle to be set before including this file.
 */
$user = currentUser();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'Vinted Resell Manager') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          brand: { 50:'#f2f6ff',100:'#e3ebff',200:'#c3d3ff',300:'#9db4ff',400:'#7089ff',500:'#4b63f6',600:'#3a47da',700:'#2f39ad',800:'#293489',900:'#242e6d' },
        },
        fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
      },
    },
  };
</script>
<style>body{font-family:'Inter',ui-sans-serif,system-ui,sans-serif;background:#f8fafc;}</style>
</head>
<body class="text-slate-900">
<div class="flex min-h-screen">
  <aside class="flex w-64 flex-col border-r border-slate-200 bg-white">
    <div class="flex items-center gap-2 px-6 py-5">
      <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-600 text-white">🏷️</div>
      <div>
        <p class="text-sm font-semibold leading-tight text-slate-900">Vinted Resell</p>
        <p class="text-xs leading-tight text-slate-500">Business Manager</p>
      </div>
    </div>

    <nav class="flex-1 space-y-1 px-3">
      <?php foreach (navItems() as $item): if (!can($item['feature'], 'view')) continue;
        $active = basename($_SERVER['PHP_SELF']) === $item['href']; ?>
        <a href="<?= e($item['href']) ?>"
           class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors <?= $active ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">
          <?= e($item['label']) ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="border-t border-slate-200 p-4">
      <div class="mb-3 flex items-center gap-3">
        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold text-slate-700">
          <?= e(strtoupper(substr($user['full_name'] ?: $user['username'], 0, 1))) ?>
        </div>
        <div class="min-w-0">
          <p class="truncate text-sm font-medium text-slate-900"><?= e($user['full_name'] ?: $user['username']) ?></p>
          <p class="truncate text-xs text-slate-500"><?= e($user['role_name']) ?></p>
        </div>
      </div>
      <a href="logout.php" class="<?= BTN_SECONDARY ?> w-full justify-center">Log out</a>
    </div>
  </aside>

  <main class="flex-1 overflow-y-auto">
    <div class="mx-auto max-w-7xl px-6 py-8 lg:px-10">
      <?php $success = flash('success'); $error = flash('error'); ?>
      <?php if ($success): ?>
        <div class="mb-6 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="mb-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600"><?= e($error) ?></div>
      <?php endif; ?>
