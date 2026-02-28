<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../../config/db.php';

if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['ok'=>false]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$userId  = (int)($data['user_id'] ?? 0);
$currency = $data['currency'] ?? '';
$amount   = (int)($data['amount'] ?? 0);

if (!$userId || !$currency || !$amount) {
    echo json_encode(['ok'=>false]);
    exit;
}

$pdo->beginTransaction();

try {

    $st = $pdo->prepare("
        INSERT INTO wallet_balances (owner_type, owner_id, currency, balance)
        VALUES ('WEB', ?, ?, ?)
        ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)
    ");
    $st->execute([$userId, $currency, $amount]);

    $st = $pdo->prepare("
        INSERT INTO wallet_ledger
        (owner_type, owner_id, currency, amount, reason)
        VALUES ('WEB', ?, ?, ?, 'ADMIN_ADJUST')
    ");
    $st->execute([$userId, $currency, $amount]);

    $pdo->commit();

    echo json_encode(['ok'=>true]);

} catch (Throwable $e) {
    $pdo->rollBack();
    echo json_encode(['ok'=>false]);
}
