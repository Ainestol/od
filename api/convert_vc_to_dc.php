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

try {

    $pdo->beginTransaction();

    // 🔎 zamkneme VC řádek
    $st = $pdo->prepare("
        SELECT balance 
        FROM wallet_balances
        WHERE owner_type='WEB'
        AND owner_id=?
        AND currency='VOTE_COIN'
        FOR UPDATE
    ");
    $st->execute([$userId]);
    $vc = $st->fetchColumn();

    if ($vc < 4) {
        throw new Exception('NOT_ENOUGH_VC');
    }

    // odečet VC
    $pdo->prepare("
        UPDATE wallet_balances
        SET balance = balance - 4
        WHERE owner_type='WEB'
        AND owner_id=?
        AND currency='VOTE_COIN'
    ")->execute([$userId]);

    // přičtení DC (create if not exists)
    $pdo->prepare("
        INSERT INTO wallet_balances (owner_type, owner_id, currency, balance)
        VALUES ('WEB', ?, 'DC', 1)
        ON DUPLICATE KEY UPDATE balance = balance + 1
    ")->execute([$userId]);

    // ledger zápis VC
    $pdo->prepare("
        INSERT INTO wallet_ledger
        (owner_type, owner_id, currency, amount, reason)
        VALUES ('WEB', ?, 'VOTE_COIN', -4, 'VC_TO_DC')
    ")->execute([$userId]);

    // ledger zápis DC
    $pdo->prepare("
        INSERT INTO wallet_ledger
        (owner_type, owner_id, currency, amount, reason)
        VALUES ('WEB', ?, 'DC', 1, 'VC_TO_DC')
    ")->execute([$userId]);

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
