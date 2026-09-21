<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$kind = $_GET['kind'] ?? '';
$product = $_GET['product'] ?? '';
$file = $_GET['file'] ?? '';

if (!in_array($kind, ['original', 'enhanced'], true) || $product === '' || $file === '') {
    http_response_code(400);
    exit;
}

// Never trust the filename directly — only serve files we already know
// about from this visitor's own session data.
$path = null;

if ($kind === 'original') {
    foreach ($_SESSION['products'] ?? [] as $p) {
        if ($p['name'] !== $product) {
            continue;
        }
        foreach ($p['images'] as $img) {
            if ($img['filename'] === $file) {
                $path = $img['path'];
            }
        }
    }
} else {
    $enhancedRoot = $_SESSION['enhanced_root'] ?? null;
    if ($enhancedRoot) {
        $candidate = $enhancedRoot . '/' . $product . '/ENHANCED_PHOTOS/' . basename($file);
        $real = realpath($candidate);
        $rootReal = realpath($enhancedRoot);
        if ($real !== false && $rootReal !== false && str_starts_with($real, $rootReal)) {
            $path = $real;
        }
    }
}

if ($path === null || !is_file($path)) {
    http_response_code(404);
    exit;
}

$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=300');
readfile($path);
