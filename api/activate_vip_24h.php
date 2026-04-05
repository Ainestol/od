<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/logger.php';
csrf_check();
if (empty($_SESSION['web_user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false]);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/db_game_write.php';

$userId = (int)$_SESSION['web_user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$charId = (int)($data['char_id'] ?? 0);
$currency = $data['currency'] ?? '';

if (!$charId || !in_array($currency, ['VOTE_COIN','DC'])) {
    echo json_encode(['ok'=>false,'error'=>'INVALID_INPUT']);
    exit;
}

/* 🔎 Ověření, že postava patří uživateli */
$st = $pdo->prepare("
    SELECT 1
    FROM game_accounts ga
    JOIN l2game.characters c ON c.account_name = ga.login
    WHERE ga.web_user_id = ?
      AND c.charId = ?
    LIMIT 1
");
$st->execute([$userId, $charId]);
if (!$st->fetchColumn()) {
    echo json_encode(['ok'=>false,'error'=>'CHAR_NOT_OWNED']);
    exit;
}

$cost = $currency === 'DC' ? 1 : 4;

try {
    $pdo->beginTransaction();

    /* 🔐 1) Zamkneme wallet */
    $st = $pdo->prepare("
        SELECT balance
        FROM wallet_balances
        WHERE owner_type='WEB' AND owner_id=? AND currency=?
        FOR UPDATE
    ");
    $st->execute([$userId, $currency]);
    $balance = (int)$st->fetchColumn();

    if ($balance < $cost) {
        throw new Exception('NOT_ENOUGH_FUNDS');
    }

    /* 💰 2) Odečet */
    $pdo->prepare("
        UPDATE wallet_balances
        SET balance = balance - ?
        WHERE owner_type='WEB' AND owner_id=? AND currency=?
    ")->execute([$cost, $userId, $currency]);

    /* 🧠 3) VIP logika — nepřepisujeme vyšší VIP */

    // Zkontroluj jestli má aktivní GAME nebo WEB VIP
    $st = $pdo->prepare("
        SELECT COUNT(*) FROM vip_grants vg
        JOIN game_accounts ga ON (
            (vg.scope = 'GAME' AND vg.target_id = ga.id)
            OR
            (vg.scope = 'WEB' AND vg.target_id = ga.web_user_id)
        )
        JOIN l2game.characters c ON c.account_name = ga.login
        WHERE c.charId = ?
          AND vg.end_at > NOW()
    ");
    $st->execute([$charId]);
    $hasHigherVip = (int)$st->fetchColumn() > 0;

    if ($hasHigherVip) {
        throw new Exception('HIGHER_VIP_ACTIVE');
    }

    // Zkontroluj existující CHAR VIP
    $st = $pdo->prepare("
        SELECT id, end_at
        FROM vip_grants
        WHERE scope='CHAR' AND target_id=? AND end_at > NOW()
        ORDER BY end_at DESC
        LIMIT 1
        FOR UPDATE
    ");
    $st->execute([$charId]);
    $existingVip = $st->fetch(PDO::FETCH_ASSOC);

    if ($existingVip) {
        // Prodloužení
        $pdo->prepare("
            UPDATE vip_grants
            SET end_at = DATE_ADD(end_at, INTERVAL 1 DAY)
            WHERE id=?
        ")->execute([$existingVip['id']]);

        system_log($pdo, 'ECONOMY', 'VIP_24H_EXTEND', $userId, $charId, 'SUCCESS', [
            'currency' => $currency, 'cost' => $cost
        ]);
    } else {
        // Nový VIP
        $pdo->prepare("
            INSERT INTO vip_grants
            (scope, target_id, level_id, start_at, end_at, source, created_by)
            VALUES ('CHAR', ?, 1, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), 'PURCHASE', ?)
        ")->execute([$charId, $userId]);

        system_log($pdo, 'ECONOMY', 'VIP_24H_ACTIVATE', $userId, $charId, 'SUCCESS', [
            'currency' => $currency, 'cost' => $cost
        ]);
    }

    /* 🧾 4) Ledger */
    $pdo->prepare("
        INSERT INTO wallet_ledger (owner_type, owner_id, currency, amount, reason)
        VALUES ('WEB', ?, ?, ?, 'VIP_24H')
    ")->execute([$userId, $currency, -$cost]);

/* 🎮 5) Sync do L2 — VIP_CHAR + VIP_CHAR_END */
$st = $pdo->prepare("
    SELECT UNIX_TIMESTAMP(end_at)
    FROM vip_grants
    WHERE scope='CHAR' AND target_id=? AND end_at > NOW()
    ORDER BY end_at DESC LIMIT 1
");
$st->execute([$charId]);
$endTs = (int)$st->fetchColumn();

error_log("VIP_CHAR sync start, charId=$charId, endTs=$endTs");

$pdoPremium->prepare("
    INSERT INTO character_variables (charId, var, val)
    VALUES (?, 'VIP_CHAR', 'true')
    ON DUPLICATE KEY UPDATE val = 'true'
")->execute([$charId]);

error_log("VIP_CHAR written, rows=" . $pdoPremium->query("SELECT ROW_COUNT()")->fetchColumn());
    $pdoPremium->prepare("
        INSERT INTO character_variables (charId, var, val)
        VALUES (?, 'VIP_CHAR_END', ?)
        ON DUPLICATE KEY UPDATE val = ?
    ")->execute([$charId, $endTs, $endTs]);

    $pdo->commit();

    echo json_encode(['ok'=>true]);

} catch (Throwable $e) {
    system_log($pdo, 'ECONOMY', 'VIP_24H_ACTIVATE', $userId ?? null, $charId ?? null, 'FAIL', [
        'currency' => $currency ?? null, 'error' => $e->getMessage()
    ]);

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}