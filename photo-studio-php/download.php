<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$path = $_SESSION['output_zip_path'] ?? null;
if (!$path || !is_file($path)) {
    http_response_code(404);
    exit('No output ZIP available. Process a batch first.');
}

header('Content-Type: application/zip');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="JORDYN_PHOTO_STUDIO_OUTPUT.zip"');
readfile($path);
