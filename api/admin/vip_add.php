<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_bootstrap.php'; // $pdo
require_once __DIR__ . '/../../config/db_game.php'; // $pdoPremium
require_once __DIR__ . '/../../lib/vip.php'; // ✅ nové helpery (extend + sync)

try {
    // ===== ADMIN AUTH =====
    assert_admin();

    // ===== INPUT =====
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    foreach (['scope','targetId','levelId','days'] as $k) {
        if (!isset($input[$k])) {
            throw new Exception("MISSING_$k");
        }
    }

    $scope    = (string)$input['scope'];
    $targetId = (int)$input['targetId'];
    $levelId  = (int)$input['levelId'];
    $days     = (int)$input['days'];

    if (!in_array($scope, ['WEB','GAME','CHAR'], true)) {
        throw new Exception('INVALID_SCOPE');
    }

    if ($days <= 0) {
        throw new Exception('INVALID_DAYS');
    }

    $adminId = (int)($_SESSION['web_user_id'] ?? 0);
    if ($adminId <= 0) {
        throw new Exception('ADMIN_SESSION_MISSING');
    }

    // ================================
    // ✅ WEB DB – VIP GRANT (extend nebo create)
    // + následně sync do GAME DB (WEB/GAME)
    // ================================
    $pdo->beginTransaction();

    // 1) extend/create grant (NE reset)
    $vipGrantId = vip_grant_extend_or_create(
        $pdo,
        $scope,
        $targetId,
        $levelId,
        $days,
        $adminId,
        'ADMIN',
        true // preventDowngrade
    );

    // 2) CHAR scope -> VIP flag do character_variables (bez account_premium sync)
    if ($scope === 'CHAR') {

        $stmt = $pdoPremium->prepare("
            INSERT INTO character_variables (charId, var, val)
            VALUES (:charId, 'VIP_CHAR', 'true')
            ON DUPLICATE KEY UPDATE val = 'true'
        ");
        $stmt->execute([':charId' => $targetId]);

    } else {
        // 3) WEB/GAME scope -> sync do account_premium podle grant end_at
        vip_sync_account_premium($pdo, $pdoPremium, $scope, $targetId, $vipGrantId);
    }

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'vip_grant_id' => $vipGrantId
    ]);

} catch (Throwable $e) {

    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit;
}