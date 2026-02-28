<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../lib/csrf.php';
csrf_check();
if (empty($_SESSION['web_user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false]);
    exit;
}

require_once __DIR__ . '/../config/db.php';

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
    JOIN l2game.characters c
      ON c.account_name = ga.login
    WHERE ga.web_user_id = ?
      AND c.charId = ?
    LIMIT 1
");

$st->execute([$userId, $charId]);

if (!$st->fetchColumn()) {
    echo json_encode([
        'ok'=>false,
        'error'=>'CHAR_NOT_OWNED'
    ]);
    exit;
}


$cost = $currency === 'DC' ? 1 : 4;

try {

    $pdo->beginTransaction();

    /* 🔐 1️⃣ Zamkneme wallet */
    $st = $pdo->prepare("
        SELECT balance
        FROM wallet_balances
        WHERE owner_type='WEB'
        AND owner_id=?
        AND currency=?
        FOR UPDATE
    ");
    $st->execute([$userId, $currency]);
    $balance = (int)$st->fetchColumn();

    if ($balance < $cost) {
        throw new Exception('NOT_ENOUGH_FUNDS');
    }

    /* 💰 2️⃣ Odečet */
    $pdo->prepare("
        UPDATE wallet_balances
        SET balance = balance - ?
        WHERE owner_type='WEB'
        AND owner_id=?
        AND currency=?
    ")->execute([$cost, $userId, $currency]);

    /* 🧠 3️⃣ VIP logika (prodloužení místo přepsání) */

    $st = $pdo->prepare("
        SELECT id, end_at
        FROM vip_grants
        WHERE scope='CHAR'
        AND target_id=?
        AND end_at > NOW()
        ORDER BY end_at DESC
        LIMIT 1
        FOR UPDATE
    ");
    $st->execute([$charId]);
    $existingVip = $st->fetch(PDO::FETCH_ASSOC);

    if ($existingVip) {

        // prodloužíme
        $pdo->prepare("
            UPDATE vip_grants
            SET end_at = DATE_ADD(end_at, INTERVAL 1 DAY)
            WHERE id=?
        ")->execute([$existingVip['id']]);

    } else {

        // vytvoříme nový VIP
        $pdo->prepare("
            INSERT INTO vip_grants
            (scope, target_id, level_id, start_at, end_at, source, created_by)
            VALUES
            ('CHAR', ?, 1, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), 'PURCHASE', ?)
        ")->execute([$charId, $userId]);
    }

// 🔄 SYNC do L2GAME - nastav VIP_CHAR=true

    /* 🧾 4️⃣ Ledger */
    $pdo->prepare("
        INSERT INTO wallet_ledger
        (owner_type, owner_id, currency, amount, reason)
        VALUES
        ('WEB', ?, ?, ?, 'VIP_24H')
    ")->execute([$userId, $currency, -$cost]);
/* 🎮 4️⃣ Sync do L2 – nastavení VIP_CHAR */

$pdoGame = new PDO(
    'mysql:host=localhost;dbname=l2game;charset=utf8mb4',
    'premium_user',
    '@Heslojeheslo55',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]
);

$pdoGame->prepare("
    INSERT INTO character_variables (charId, var, val)
    VALUES (?, 'VIP_CHAR', 'true')
    ON DUPLICATE KEY UPDATE val = 'true'
")->execute([$charId]);

    $pdo->commit();

    echo json_encode(['ok'=>true]);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'ok'=>false,
        'error'=>$e->getMessage()
    ]);
}
