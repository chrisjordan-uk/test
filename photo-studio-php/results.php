<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/render.php';

$batchReport = $_SESSION['batch_report'] ?? null;
$products = $_SESSION['products'] ?? null;
$settings = $_SESSION['last_settings'] ?? null;

if (!$batchReport || !$products) {
    jps_flash('No results yet — upload and process a batch first.', 'error');
    jps_redirect('index.php');
}

$productsByName = [];
foreach ($products as $p) {
    $productsByName[$p['name']] = $p;
}

jps_render_header('results');
?>

<div class="card">
  <h2>Results</h2>
  <div class="metrics">
    <div class="metric"><div class="value"><?= $batchReport['total_images'] ?></div><div class="label">Total images</div></div>
    <div class="metric"><div class="value"><?= jps_batch_total_success($batchReport) ?></div><div class="label">Enhanced</div></div>
    <div class="metric"><div class="value"><?= jps_batch_total_unsafe($batchReport) ?></div><div class="label">Kept original (safety)</div></div>
    <div class="metric"><div class="value"><?= jps_batch_total_failure($batchReport) ?></div><div class="label">Failed</div></div>
  </div>

  <?php if (!empty($_SESSION['output_zip_path']) && file_exists($_SESSION['output_zip_path'])): ?>
    <p>
      <a class="btn" href="download.php">⬇️ Download JORDYN_PHOTO_STUDIO_OUTPUT.zip
        (<?= e(jps_human_size(filesize($_SESSION['output_zip_path']))) ?>)</a>
    </p>
  <?php endif; ?>
</div>

<?php foreach ($batchReport['product_reports'] as $productReport): ?>
  <?php $product = $productsByName[$productReport['folder_name']] ?? null; ?>
  <div class="card">
    <details open>
      <summary>
        <strong><?= e($productReport['folder_name']) ?></strong> —
        <?= jps_product_success_count($productReport) ?> enhanced,
        <?= jps_product_unsafe_count($productReport) ?> kept original,
        <?= jps_product_failure_count($productReport) ?> failed
      </summary>

      <?php foreach ($productReport['results'] as $result): ?>
        <?php
        $originalImage = null;
        if ($product) {
            foreach ($product['images'] as $img) {
                if ($img['filename'] === $result['filename']) {
                    $originalImage = $img;
                }
            }
        }
        $badgeClass = $result['success'] ? 'ok' : ($result['marked_unsafe'] ? 'unsafe' : 'fail');
        $badgeLabel = $result['success'] ? '✅ Enhanced' : ($result['marked_unsafe'] ? '⚠️ Kept original (safety)' : '❌ Failed');
        ?>
        <div class="result-row">
          <div>
            <p style="color:var(--muted); font-size:0.85rem;">Original — <?= e($result['filename']) ?></p>
            <?php if ($originalImage): ?>
              <img src="serve_image.php?kind=original&product=<?= urlencode($productReport['folder_name']) ?>&file=<?= urlencode($result['filename']) ?>" alt="">
            <?php endif; ?>
          </div>
          <div>
            <p><span class="badge <?= $badgeClass ?>"><?= e($badgeLabel) ?></span></p>
            <?php if (!empty($result['output_filename'])): ?>
              <img src="serve_image.php?kind=enhanced&product=<?= urlencode($productReport['folder_name']) ?>&file=<?= urlencode($result['output_filename']) ?>" alt="">
              <p>
                <a href="serve_image.php?kind=enhanced&product=<?= urlencode($productReport['folder_name']) ?>&file=<?= urlencode($result['output_filename']) ?>" download="<?= e($result['output_filename']) ?>">Download this photo</a>
              </p>
            <?php endif; ?>
            <?php foreach ($result['warnings'] as $w): ?>
              <p style="color:var(--warn); font-size:0.85rem;">⚠️ <?= e($w) ?></p>
            <?php endforeach; ?>
            <?php if (!empty($result['error'])): ?>
              <p style="color:var(--err); font-size:0.85rem;">❌ <?= e($result['error']) ?></p>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </details>
  </div>
<?php endforeach; ?>

<?php jps_render_footer(); ?>
