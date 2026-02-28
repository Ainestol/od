<?php
// bootstrap.php – POUZE INFRASTRUKTURA, ŽÁDNÝ VÝSTUP

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../../lib/csrf.php';
csrf_check();
require_once __DIR__ . '/../../lib/rate_limit.php';

// PDO – premium_user
$pdoWeb = new PDO(
    'mysql:host=localhost;dbname=ordodraconis_web;charset=utf8mb4',
    'premium_user',
    '@Heslojeheslo55',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

// === ADMIN AUTH ===
function assert_admin(): void
{
    global $pdoWeb;

    if (empty($_SESSION['web_user_id'])) {
        throw new Exception('NOT_LOGGED_IN');
    }

    $stmt = $pdoWeb->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['web_user_id']]);
    $role = $stmt->fetchColumn();

    if ($role !== 'admin') {
        throw new Exception('FORBIDDEN');
    }
}

// rate-limit admin API
$ip = client_ip();
rate_limit($pdoWeb, "admin_api:$ip", 120, 60);
