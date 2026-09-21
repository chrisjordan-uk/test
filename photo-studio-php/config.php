<?php
/**
 * Jordyn AI Photo Studio (PHP edition) — configuration.
 *
 * On shared hosting (Hostinger etc.) just upload this whole folder and
 * copy .env.example to .env, then edit .env with your Gemini API key.
 * Nothing else needs to change.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/env.php';

jps_load_env(__DIR__ . '/.env');

// --- Gemini ---------------------------------------------------------------
define('GEMINI_API_KEY', jps_env('GEMINI_API_KEY', ''));
define('GEMINI_IMAGE_MODEL', jps_env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image'));
define('GEMINI_TIMEOUT_SECONDS', jps_env_int('GEMINI_TIMEOUT_SECONDS', 60));
define('GEMINI_MAX_RETRIES', jps_env_int('GEMINI_MAX_RETRIES', 3));
define('GEMINI_RETRY_BACKOFF_SECONDS', jps_env_float('GEMINI_RETRY_BACKOFF_SECONDS', 2.0));
define('GEMINI_MAX_UPLOAD_DIMENSION', jps_env_int('GEMINI_MAX_UPLOAD_DIMENSION', 1536));
define('GEMINI_MIN_SIMILARITY', jps_env_float('GEMINI_MIN_SIMILARITY', 0.55));

// --- Upload limits ----------------------------------------------------------
define('MAX_ZIP_SIZE_MB', jps_env_int('MAX_ZIP_SIZE_MB', 100));
define('MAX_PRODUCT_FOLDERS', jps_env_int('MAX_PRODUCT_FOLDERS', 60));
define('MAX_IMAGES_PER_FOLDER', jps_env_int('MAX_IMAGES_PER_FOLDER', 20));
define('MAX_IMAGE_FILE_SIZE_MB', jps_env_int('MAX_IMAGE_FILE_SIZE_MB', 15));
define('MAX_TOTAL_IMAGES', jps_env_int('MAX_TOTAL_IMAGES', 200));

// --- Defaults ----------------------------------------------------------
define('DEFAULT_USE_GEMINI', jps_env_bool('DEFAULT_USE_GEMINI', true));
define('DEFAULT_USE_LOCAL_ENHANCEMENT', jps_env_bool('DEFAULT_USE_LOCAL_ENHANCEMENT', true));
define('DEFAULT_JPEG_QUALITY', jps_env_int('DEFAULT_JPEG_QUALITY', 88));
define('DEFAULT_MAX_DIMENSION', jps_env_int('DEFAULT_MAX_DIMENSION', 1600));

// --- App ---------------------------------------------------------------
define('APP_NAME', 'Jordyn AI Photo Studio');
define('SUPPORTED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif']);
define('IGNORED_NAMES', ['__MACOSX', '.DS_Store', 'Thumbs.db', 'desktop.ini']);
define('STORAGE_DIR', __DIR__ . '/storage');

if (!is_dir(STORAGE_DIR)) {
    @mkdir(STORAGE_DIR, 0775, true);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/zip_handler.php';
require_once __DIR__ . '/includes/image_processor.php';
require_once __DIR__ . '/includes/gemini_client.php';
require_once __DIR__ . '/includes/report.php';
require_once __DIR__ . '/includes/output_zip.php';
require_once __DIR__ . '/includes/pipeline.php';
