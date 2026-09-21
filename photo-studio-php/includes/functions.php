<?php
declare(strict_types=1);

/** Per-visitor working directory under storage/, keyed by session id. */
function jps_work_dir(): string
{
    $id = session_id();
    $dir = STORAGE_DIR . '/jobs/' . $id;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function jps_reset_work_dir(): void
{
    $dir = jps_work_dir();
    jps_rrmdir($dir);
    @mkdir($dir, 0775, true);
}

function jps_rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            jps_rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

/** Garbage-collect job directories older than a few hours (best-effort, runs on each request). */
function jps_gc_old_jobs(int $max_age_seconds = 21600): void
{
    $jobs_dir = STORAGE_DIR . '/jobs';
    if (!is_dir($jobs_dir)) {
        return;
    }
    foreach (scandir($jobs_dir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $jobs_dir . '/' . $entry;
        if (is_dir($path) && (time() - filemtime($path)) > $max_age_seconds) {
            jps_rrmdir($path);
        }
    }
}

function jps_human_size(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

function jps_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function jps_csrf_check(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function jps_redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function jps_flash(string $message, string $type = 'info'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function jps_take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function jps_gemini_configured(): bool
{
    return GEMINI_API_KEY !== '' && GEMINI_API_KEY !== null;
}
