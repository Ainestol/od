<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../../lib/csrf.php';
csrf_check();

require_once __DIR__ . '/../../lib/rate_limit.php';
require_once __DIR__ . '/../../config/db.php'; // $pdo (web DB)

// === ADMIN AUTH ===
function assert_admin(): void
{
    global $pdo;

    if (empty($_SESSION['web_user_id'])) {
        throw new Exception('NOT_LOGGED_IN');
    }

    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['web_user_id']]);
    $role = $stmt->fetchColumn();

    if ($role !== 'admin') {
        throw new Exception('FORBIDDEN');
    }
}

// rate-limit admin API
$ip = client_ip();
rate_limit($pdo, "admin_api:$ip", 120, 60);