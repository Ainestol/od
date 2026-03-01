<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../lib/csrf.php';
csrf_check();
require_once __DIR__ . '/../lib/rate_limit.php';
require_once __DIR__ . '/../lib/vip.php';
require_once __DIR__ . '/../lib/wallet.php';
require_once __DIR__ . '/../lib/vip_guard.php';
require_once __DIR__ . '/../config/db.php';              // $pdo
require_once __DIR__ . '/../config/db_game_write.php';   // $pdoPremium
try {
    $input = $_POST ?: json_decode(file_get_contents('php://input'), true);

    $required = ['webUserId', 'scope', 'targetId', 'levelId'];
    foreach ($required as $k) {
        if (!isset($input[$k])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => "MISSING_$k"]);
            exit;
        }
    }

    $webUserId = (int)$input['webUserId'];
    $scope     = (string)$input['scope'];
    $targetId = (int)$input['targetId'];
    $levelId  = (int)$input['levelId'];

    if (!in_array($scope, ['CHAR', 'GAME', 'WEB'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'INVALID_SCOPE']);
        exit;
    }

 $ip = client_ip();
rate_limit($pdo, "vip_purchase:$ip", 5, 300); // 5/5 min/IP


// Ověření vlastnictví
vip_assert_ownership(
    $pdo,
    $pdoPremium,
    $webUserId,
    $scope,
    $targetId
);

    $grantId = vip_purchase(
        $pdo,
        $webUserId,
        $scope,
        $targetId,
        $levelId
    );

    echo json_encode([
        'ok' => true,
        'vip_grant_id' => $grantId
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    if ($e->getMessage() === 'RATE_LIMITED') {
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'RATE_LIMITED']);
        exit;
    }

    if ($e->getMessage() === 'CANNOT_DOWNGRADE_VIP') {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Nelze přepsat vyšší VIP nižším.'
        ]);
        exit;
    }

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
