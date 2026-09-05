<?php
// Included by requirePermission() when the current user lacks access.
$pageTitle = 'Access denied';
require __DIR__ . '/includes/header.php';
?>
<div class="<?= CARD ?> p-8 text-center">
  <h1 class="text-xl font-bold text-slate-900">Access denied</h1>
  <p class="mt-2 text-sm text-slate-500">You don't have permission to view this page.</p>
  <a href="index.php" class="<?= BTN_PRIMARY ?> mt-4 inline-flex">Back to Home</a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
