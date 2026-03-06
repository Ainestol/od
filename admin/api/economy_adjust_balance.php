<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_bootstrap.php';

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/logger.php';

if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['ok'=>false]);
    exit;
    }

$adminId = (int)($_SESSION['web_user_id'] ?? 0);

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

$action = '';
if ($currency === 'DC') {
    $action = $amount > 0 ? 'ADMIN_ADD_DC' : 'ADMIN_REMOVE_DC';
} elseif ($currency === 'VC') {
    $action = $amount > 0 ? 'ADMIN_ADD_VC' : 'ADMIN_REMOVE_VC';
} else {
    $action = 'ADMIN_ADJUST_BALANCE';
}

system_log(
    $pdo,
    'ADMIN',
    $action,
    $adminId,
    $userId,
    'SUCCESS',
    [
        'currency' => $currency,
        'amount' => $amount
    ]
);

echo json_encode(['ok'=>true]);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    try {
        system_log(
            $pdo,
            'ADMIN',
            'ADMIN_ADJUST_BALANCE',
            $_SESSION['web_user_id'] ?? null,
            $userId ?? null,
            'FAIL',
            [
                'currency' => $currency ?? null,
                'amount' => $amount ?? null,
                'error' => $e->getMessage()
            ]
        );
    } catch (Throwable $ignore) {}

    echo json_encode(['ok'=>false]);
}