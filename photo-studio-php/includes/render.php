<?php
declare(strict_types=1);

function jps_render_header(string $activeStep): void
{
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="wrap">
<header class="app-header">
  <h1>📸 <?= e(APP_NAME) ?></h1>
</header>
<p class="subtitle">Batch photo enhancement for clothing resale listings — pure PHP edition (GD + Gemini API).</p>

<div class="steps">
  <span class="step-pill <?= $activeStep === 'upload' ? 'active' : 'done' ?>">1. Upload</span>
  <span class="step-pill <?= $activeStep === 'settings' ? 'active' : ($activeStep === 'results' ? 'done' : '') ?>">2. Preview &amp; Settings</span>
  <span class="step-pill <?= $activeStep === 'results' ? 'active' : '' ?>">3. Results &amp; Download</span>
</div>

<?php foreach (jps_take_flashes() as $flash): ?>
  <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>
<?php
}

function jps_render_footer(): void
{
    ?>
<footer class="app-footer">
  This tool only processes photographs — no inventory, pricing, sales or listing management.
  Original photos are never modified. <a href="index.php?reset=1">Start a new batch</a>
</footer>
</div>
</body>
</html>
<?php
}
