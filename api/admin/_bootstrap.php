<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../lib/session.php';

require_once __DIR__ . '/../../lib/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    csrf_check();
}

require_once __DIR__ . '/../../lib/rate_limit.php';
require_once __DIR__ . '/../../config/db.php'; // $pdo (web DB)
require_once __DIR__ . '/../../lib/admin_audit.php';
// === ADMIN AUTH ===
function assert_admin(): void
{
    if (empty($_SESSION['web_user_id'])) {
        http_response_code(401);
        echo json_encode(["ok"=>false,"error"=>"NOT_LOGGED_IN"]);
        exit;
    }

    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(["ok"=>false,"error"=>"FORBIDDEN"]);
        exit;
    }
}

// rate-limit admin API
$ip = client_ip();
rate_limit($pdo, "admin_api:$ip", 120, 60);