<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../lib/rate_limit.php';
require_once __DIR__ . '/../lib/vip_resolve.php';
require_once __DIR__ . '/../config/db.php';              // $pdo (web DB)
require_once __DIR__ . '/../config/db_game_write.php';   // $pdoPremium

try {
    if (empty($_GET['charId']) || !ctype_digit($_GET['charId'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'INVALID_CHAR_ID']);
        exit;
    }

    $charId = (int)$_GET['charId'];

 
$ip = client_ip();
rate_limit($pdo, "vip_status:$ip", 60, 60); // 60/min/IP


     $res = vip_get_effective_for_character($pdo, $pdoPremium, $charId);

    echo json_encode(['ok' => true, 'data' => $res], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if ($e->getMessage() === 'RATE_LIMITED') {
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'RATE_LIMITED']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}