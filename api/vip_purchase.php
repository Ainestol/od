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

// 🔧 helper: prodloužení / nastavení VIP
function setOrExtendVip($pdoPremium, $login, $durationSeconds) {
    $stmt = $pdoPremium->prepare("
        SELECT enddate FROM account_premium WHERE account_name = ?
    ");
    $stmt->execute([$login]);
    $current = $stmt->fetchColumn();

    $nowMs = time() * 1000;
    $addMs = $durationSeconds * 1000;

    if ($current && $current > $nowMs) {
        $newEnd = $current + $addMs;
    } else {
        $newEnd = $nowMs + $addMs;
    }

    $stmt = $pdoPremium->prepare("
        INSERT INTO account_premium (account_name, enddate)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE enddate = VALUES(enddate)
    ");
    $stmt->execute([$login, $newEnd]);
}

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
    $targetId  = (int)$input['targetId'];
    $levelId   = (int)$input['levelId'];

    if (!in_array($scope, ['CHAR', 'GAME', 'WEB'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'INVALID_SCOPE']);
        exit;
    }

    $ip = client_ip();
    rate_limit($pdo, "vip_purchase:$ip", 5, 300);

    // ownership
    vip_assert_ownership(
        $pdo,
        $pdoPremium,
        $webUserId,
        $scope,
        $targetId
    );

    // vytvoření VIP (web DB)
    $grantId = vip_purchase(
        $pdo,
        $webUserId,
        $scope,
        $targetId,
        $levelId
    );

    // 🔥 délka (zatím jednoduchá)
    if ($levelId == 1) {
        $duration = 24 * 60 * 60; // 24h
    } else {
        $duration = 30 * 24 * 60 * 60; // 30 dní
    }

    $accounts = [];

    // === GAME ===
    if ($scope === 'GAME') {

        // ❗ pokud má WEB VIP → ignoruj
        $stmt = $pdo->prepare("
            SELECT 1
            FROM vip_grants
            WHERE scope = 'WEB'
              AND target_id = (
                  SELECT web_user_id FROM game_accounts WHERE id = ?
              )
              AND end_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$targetId]);
        $hasWebVip = $stmt->fetchColumn();

        if (!$hasWebVip) {
            $stmt = $pdo->prepare("SELECT login FROM game_accounts WHERE id = ?");
            $stmt->execute([$targetId]);
            $login = $stmt->fetchColumn();

            if ($login) {
                setOrExtendVip($pdoPremium, $login, $duration);
            }
        }
    }

    // === WEB ===
    if ($scope === 'WEB') {
        $stmt = $pdo->prepare("
            SELECT login FROM game_accounts WHERE web_user_id = ?
        ");
        $stmt->execute([$targetId]);
        $accounts = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($accounts)) {
            foreach ($accounts as $login) {
                setOrExtendVip($pdoPremium, $login, $duration);
            }
        }
    }

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