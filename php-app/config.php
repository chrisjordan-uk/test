<?php
/**
 * Edit the values below to match your MySQL database, then upload this
 * whole folder to your hosting. Nothing else needs to be configured.
 */

// --- Database ---------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'CHANGE_ME');
define('DB_USER', 'CHANGE_ME');
define('DB_PASS', 'CHANGE_ME');

// --- First admin account, created once by db/seed.php ------------------
define('SEED_ADMIN_USERNAME', 'admin');
define('SEED_ADMIN_PASSWORD', 'ChangeMe123!');
define('SEED_ADMIN_EMAIL', 'admin@example.com');

// ------------------------------------------------------------------------
// Nothing below this line needs to change.
// ------------------------------------------------------------------------

session_start();

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die('Could not connect to the database. Check the DB_* settings in config.php.');
}

require_once __DIR__ . '/includes/functions.php';
