<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/render.php';

jps_gc_old_jobs();

if (isset($_GET['reset'])) {
    jps_reset_work_dir();
    unset($_SESSION['products'], $_SESSION['extraction_warnings'], $_SESSION['batch_report'],
        $_SESSION['enhanced_root'], $_SESSION['output_zip_path'], $_SESSION['last_settings']);
    jps_redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    if (!jps_csrf_check()) {
        jps_flash('Your session expired — please try uploading again.', 'error');
        jps_redirect('index.php');
    }

    if (empty($_FILES['zip_file']) || $_FILES['zip_file']['error'] !== UPLOAD_ERR_OK) {
        jps_flash('Please choose a ZIP file to upload.', 'error');
        jps_redirect('index.php');
    }

    jps_reset_work_dir();
    $workDir = jps_work_dir();
    $zipPath = $workDir . '/upload.zip';
    $extractDir = $workDir . '/extracted';

    try {
        jps_validate_zip_size((int) $_FILES['zip_file']['size']);
        if (!move_uploaded_file($_FILES['zip_file']['tmp_name'], $zipPath)) {
            throw new JpsZipException('Could not save the uploaded file.');
        }
        $extraction = jps_safe_extract_zip($zipPath, $extractDir);
        [$products, $folderWarnings] = jps_detect_product_folders($extractDir);
    } catch (JpsZipException $e) {
        jps_flash('Could not process this ZIP: ' . $e->getMessage(), 'error');
        jps_redirect('index.php');
    }

    $_SESSION['products'] = $products;
    $_SESSION['extraction_warnings'] = array_merge($extraction['warnings'], $folderWarnings);
    unset($_SESSION['batch_report'], $_SESSION['output_zip_path']);

    $totalImages = array_sum(array_map(fn($p) => count($p['images']), $products));
    jps_flash("Detected " . count($products) . " product folder(s) with $totalImages image(s) total.", 'info');
    jps_redirect('index.php');
}

$products = $_SESSION['products'] ?? null;

if (!empty($_SESSION['output_zip_path']) && file_exists($_SESSION['output_zip_path'])) {
    jps_redirect('results.php');
}

jps_render_header($products ? 'settings' : 'upload');
?>

<?php if (!$products): ?>
  <div class="card">
    <h2>1. Upload your product photos</h2>
    <p>Upload a ZIP file containing one folder per product (each folder holding that product's photos).
       JPG, JPEG, PNG and WEBP are supported; filenames can be anything.</p>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload">
      <input type="hidden" name="csrf_token" value="<?= e(jps_csrf_token()) ?>">
      <div class="dropzone">
        <div>📁 Drop or choose your ZIP file</div>
        <input type="file" name="zip_file" accept=".zip" required>
      </div>
      <p style="margin-top:16px;">
        <button class="btn" type="submit">Upload &amp; detect folders</button>
      </p>
    </form>
    <p style="color:var(--muted); font-size:0.85rem;">
      Max size: <?= MAX_ZIP_SIZE_MB ?> MB &middot; Max product folders: <?= MAX_PRODUCT_FOLDERS ?> &middot;
      Max images total: <?= MAX_TOTAL_IMAGES ?>
    </p>
  </div>

  <div class="card">
    <h2>Privacy &amp; scope</h2>
    <ul>
      <li>This tool only processes photographs — no inventory, pricing, sales, invoicing or listing management.</li>
      <li>Original photographs are never modified or overwritten.</li>
      <li>Defects visible in your original photos (stains, holes, tears, wear) are always preserved.</li>
      <li><strong>Privacy:</strong> when Gemini AI editing is enabled, photographs are sent to Google's Gemini API. Turn it off below to keep everything fully local.</li>
    </ul>
  </div>

<?php else: ?>

  <div class="card">
    <h2>2. Preview original photographs</h2>
    <?php if (!empty($_SESSION['extraction_warnings'])): ?>
      <details>
        <summary>⚠️ <?= count($_SESSION['extraction_warnings']) ?> warning(s) during extraction</summary>
        <ul><?php foreach ($_SESSION['extraction_warnings'] as $w): ?><li><?= e($w) ?></li><?php endforeach; ?></ul>
      </details>
    <?php endif; ?>

    <form method="post" action="process.php">
      <input type="hidden" name="csrf_token" value="<?= e(jps_csrf_token()) ?>">
      <div class="folder-list">
        <?php foreach ($products as $product): ?>
          <div class="folder-card">
            <details>
              <summary>
                <label style="display:inline;">
                  <input type="checkbox" name="folders[]" value="<?= e($product['name']) ?>" checked style="width:auto;display:inline;">
                  <?= e($product['name']) ?> — <?= count($product['images']) ?> image(s)
                </label>
              </summary>
              <div class="thumb-grid">
                <?php foreach ($product['images'] as $img): ?>
                  <figure>
                    <img src="serve_image.php?kind=original&product=<?= urlencode($product['name']) ?>&file=<?= urlencode($img['filename']) ?>" alt="<?= e($img['filename']) ?>">
                    <figcaption><?= e($img['filename']) ?></figcaption>
                  </figure>
                <?php endforeach; ?>
              </div>
            </details>
          </div>
        <?php endforeach; ?>
      </div>

      <h2 style="margin-top:24px;">3. Processing settings</h2>
      <div class="form-grid">
        <div>
          <label for="mode">Processing mode</label>
          <select name="mode" id="mode">
            <option value="clean_product_photo">A — Clean Product Photo</option>
            <option value="background_cleanup">B — Background Cleanup</option>
            <option value="lighting_quality">C — Lighting &amp; Quality Enhancement</option>
            <option value="marketplace_cover">D — Marketplace Cover Image</option>
            <option value="batch_consistency">E — Batch Consistency</option>
          </select>
        </div>
        <div>
          <label for="background_style">Background style</label>
          <select name="background_style" id="background_style">
            <option value="neutral_studio">Neutral Studio</option>
            <option value="white">White</option>
            <option value="light_grey">Light Grey</option>
            <option value="beige">Beige</option>
            <option value="preserve_original">Preserve Original</option>
          </select>
        </div>
        <div>
          <label for="aspect_ratio">Output aspect ratio</label>
          <select name="aspect_ratio" id="aspect_ratio">
            <option value="original">Original</option>
            <option value="4:5">4:5</option>
            <option value="1:1">1:1</option>
            <option value="3:4">3:4</option>
          </select>
        </div>
        <div>
          <label for="max_dimension">Maximum output dimension (px)</label>
          <input type="number" name="max_dimension" id="max_dimension" min="600" max="4000" step="100" value="<?= DEFAULT_MAX_DIMENSION ?>">
        </div>
        <div>
          <label for="jpeg_quality">JPEG quality</label>
          <input type="number" name="jpeg_quality" id="jpeg_quality" min="60" max="100" step="1" value="<?= DEFAULT_JPEG_QUALITY ?>">
        </div>
        <div>
          <label><input type="checkbox" name="use_gemini" value="1" style="width:auto;display:inline;" <?= DEFAULT_USE_GEMINI ? 'checked' : '' ?>> Use Gemini AI editing</label>
          <?php if (!jps_gemini_configured()): ?>
            <p style="color:var(--warn); font-size:0.8rem;">GEMINI_API_KEY is not configured — Gemini editing will be skipped and local processing used instead.</p>
          <?php endif; ?>
          <label style="margin-top:8px;"><input type="checkbox" name="use_local_enhancement" value="1" style="width:auto;display:inline;" <?= DEFAULT_USE_LOCAL_ENHANCEMENT ? 'checked' : '' ?>> Use local enhancement fallback</label>
        </div>
      </div>

      <?php
      $totalImages = array_sum(array_map(fn($p) => count($p['images']), $products));
      $usageWarning = jps_estimate_api_usage_warning($totalImages, DEFAULT_USE_GEMINI);
      if ($usageWarning):
      ?>
        <div class="flash info" style="margin-top:16px;"><?= e($usageWarning) ?></div>
      <?php endif; ?>

      <p style="margin-top:20px;">
        <button class="btn" type="submit">🚀 Process All Images</button>
        <a class="btn secondary" href="index.php?reset=1">Start over</a>
      </p>
    </form>
  </div>

<?php endif; ?>

<?php jps_render_footer(); ?>
