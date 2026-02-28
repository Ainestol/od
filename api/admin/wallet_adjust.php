<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../lib/wallet.php';

assert_admin($pdoWeb);

$input = $_POST ?: json_decode(file_get_contents('php://input'), true);

foreach (['webUserId','amount','note'] as $k) {
    if (!isset($input[$k])) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>"MISSING_$k"]);
        exit;
    }
}

$webUserId = (int)$input['webUserId'];
$amount    = (int)$input['amount'];
$note      = (string)$input['note'];

if ($amount === 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'ZERO_AMOUNT']);
    exit;
}

if ($amount > 0) {
    wallet_add(
        $pdoWeb,
        $webUserId,
        $amount,
        'admin_adjust',
        'admin',
        $_SESSION['user_id'],
        $note
    );
} else {
    wallet_spend(
        $pdoWeb,
        $webUserId,
        abs($amount),
        'admin_adjust',
        'admin',
        $_SESSION['user_id'],
        $note
    );
}

echo json_encode(['ok'=>true]);
