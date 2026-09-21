<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !jps_csrf_check()) {
    jps_flash('Your session expired — please try again.', 'error');
    jps_redirect('index.php');
}

$products = $_SESSION['products'] ?? null;
if (!$products) {
    jps_flash('Upload a ZIP first.', 'error');
    jps_redirect('index.php');
}

@set_time_limit(0);
@ini_set('zlib.output_compression', 'Off');
while (ob_get_level() > 0) {
    ob_end_clean();
}

$selectedFolders = $_POST['folders'] ?? null;
if (is_array($selectedFolders) && count($selectedFolders) === 0) {
    jps_flash('Select at least one product folder to process.', 'error');
    jps_redirect('index.php');
}

$settings = [
    'mode' => in_array($_POST['mode'] ?? '', ['clean_product_photo', 'background_cleanup', 'lighting_quality', 'marketplace_cover', 'batch_consistency'], true)
        ? $_POST['mode'] : 'clean_product_photo',
    'use_gemini' => !empty($_POST['use_gemini']),
    'use_local_enhancement' => !empty($_POST['use_local_enhancement']),
    'background_style' => in_array($_POST['background_style'] ?? '', array_keys(JPS_BG_COLORS + ['preserve_original' => true]), true)
        ? $_POST['background_style'] : 'neutral_studio',
    'aspect_ratio' => in_array($_POST['aspect_ratio'] ?? '', ['original', '4:5', '1:1', '3:4'], true)
        ? $_POST['aspect_ratio'] : 'original',
    'max_dimension' => max(600, min(4000, (int) ($_POST['max_dimension'] ?? DEFAULT_MAX_DIMENSION))),
    'jpeg_quality' => max(60, min(100, (int) ($_POST['jpeg_quality'] ?? DEFAULT_JPEG_QUALITY))),
    'selected_folders' => is_array($selectedFolders) ? array_values($selectedFolders) : null,
];

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Processing — <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="wrap">
<h1>Processing your batch…</h1>
<p class="subtitle">Please keep this tab open. Do not refresh — you'll be redirected automatically when it's done.</p>
<div class="log-box" id="log">
<?php
flush();

$workDir = jps_work_dir();
$enhancedRoot = $workDir . '/enhanced';
$geminiCacheDir = $workDir . '/gemini_cache';

$progress = function (string $folder, string $filename, int $done, int $total): void {
    if ($folder !== '' && $filename !== '') {
        $line = htmlspecialchars("[$folder] $filename (" . ($done + 1) . "/$total)", ENT_QUOTES);
        echo "<div>$line</div>";
        // Padding defeats small output buffers on some Apache/LiteSpeed setups
        // so the log line actually reaches the browser before processing ends.
        echo '<span style="display:none">' . str_repeat(' ', 4096) . '</span>' . "\n";
        @flush();
    }
};

$batchReport = jps_process_batch($products, $settings, $enhancedRoot, $geminiCacheDir, $progress);
?>
</div>
<p>Building output ZIP…</p>
<?php
flush();

$outputZipPath = $workDir . '/JORDYN_PHOTO_STUDIO_OUTPUT.zip';
jps_build_output_zip($products, $batchReport, $settings, $enhancedRoot, $outputZipPath, $_SESSION['extraction_warnings'] ?? []);

$_SESSION['batch_report'] = $batchReport;
$_SESSION['enhanced_root'] = $enhancedRoot;
$_SESSION['output_zip_path'] = $outputZipPath;
$_SESSION['last_settings'] = $settings;
?>
<p>Done — redirecting to results…</p>
<script>window.location.replace('results.php');</script>
</div>
</body>
</html>
