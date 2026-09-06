<?php
/**
 * Shared page shell (sidebar + topbar) for every logged-in page.
 * Expects $pageTitle to be set before including this file.
 */
$user = currentUser();
$pendingTaskBadge = can('tasks', 'view') ? pendingTaskCount($pdo, $user['id']) : 0;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'Vinted Resell Manager') ?></title>
<?php require __DIR__ . '/app_meta.php'; ?>
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
<style>
  body{font-family:'Inter',ui-sans-serif,system-ui,sans-serif;background:#f8fafc;}

  /* Consistent, modern dropdown styling across the whole app: kill the
     native browser chrome and draw our own chevron so every <select>
     looks like the rest of the UI instead of the OS default widget. */
  select{
    appearance:none; -webkit-appearance:none; -moz-appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%2364748b'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z' clip-rule='evenodd'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right .65rem center; background-size:1.1em;
    padding-right:2.25rem; cursor:pointer;
  }
  select::-ms-expand{display:none;}

  /* Small, tasteful motion: pages settle in instead of popping, flash
     messages slide down, the notification dot breathes so it's noticed
     without being annoying, and the logo mark gives a little life on hover. */
  @keyframes pageIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
  main > div{animation:pageIn .35s cubic-bezier(.16,1,.3,1)}

  @keyframes flashIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
  .flash-msg{animation:flashIn .3s ease-out}

  @keyframes badgePulse{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.45)}50%{box-shadow:0 0 0 5px rgba(239,68,68,0)}}
  .notif-dot{animation:badgePulse 2s ease-in-out infinite}

  .logo-mark{transition:transform .25s ease}
  .logo-mark:hover{transform:rotate(-6deg) scale(1.06)}

  .nav-link{position:relative;overflow:hidden}
  .nav-link::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:#4b63f6;transform:scaleY(0);transition:transform .2s ease;border-radius:0 3px 3px 0}
  .nav-link.active::before{transform:scaleY(1)}

  tbody tr{transition:background-color .15s ease}
</style>
</head>
<body class="text-slate-900">
<div class="flex min-h-screen">
  <aside class="flex w-64 flex-col border-r border-slate-200 bg-white">
    <div class="flex items-center gap-2 px-6 py-5">
      <div class="logo-mark flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-600 text-white">🏷️</div>
      <div>
        <p class="text-sm font-semibold leading-tight text-slate-900">Vinted Resell</p>
        <p class="text-xs leading-tight text-slate-500">Business Manager</p>
      </div>
    </div>

    <nav class="flex-1 space-y-1 px-3">
      <?php foreach (navItems() as $item): if (!can($item['feature'], 'view')) continue;
        $active = basename($_SERVER['PHP_SELF']) === $item['href']; ?>
        <a href="<?= e($item['href']) ?>"
           class="nav-link <?= $active ? 'active' : '' ?> flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors <?= $active ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">
          <span><?= e($item['label']) ?></span>
          <?php if (!empty($item['badge']) && $pendingTaskBadge > 0): ?>
            <span class="notif-dot inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-xs font-semibold text-white"><?= $pendingTaskBadge ?></span>
          <?php endif; ?>
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
        <div class="flash-msg mb-6 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="flash-msg mb-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600"><?= e($error) ?></div>
      <?php endif; ?>
