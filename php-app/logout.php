<?php
require __DIR__ . '/config.php';
if (currentUser()) {
    logActivity($pdo, 'logout', 'Signed out');
}
$_SESSION = [];
session_destroy();
redirect('login.php');
