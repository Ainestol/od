<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../lib/wallet.php';
require_once __DIR__ . '/../../lib/logger.php';

try {

assert_admin($pdoWeb);
$adminId = (int)($_SESSION['web_user_id'] ?? 0);

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
    $adminId,
    $note
);
} else {
    wallet_spend(
        $pdoWeb,
        $webUserId,
        abs($amount),
        'admin_adjust',
        'admin',
        $adminId,
        $note
    );
}

// LOG SUCCESS
system_log(
    $pdoWeb,
    'ADMIN',
    $amount > 0 ? 'ADMIN_ADD_DC' : 'ADMIN_REMOVE_DC',
    $adminId,
    $webUserId,
    'SUCCESS',
    [
        'amount' => $amount,
        'note' => $note
    ]
);

echo json_encode(['ok'=>true]);

} catch (Throwable $e) {

    if (isset($pdoWeb) && $pdoWeb instanceof PDO) {
        try {
            system_log(
                $pdoWeb,
                'ADMIN',
                'ADMIN_WALLET_ADJUST',
                $_SESSION['web_user_id'] ?? null,
                $webUserId ?? null,
                'FAIL',
                [
                    'error' => $e->getMessage(),
                    'amount' => $amount ?? null
                ]
            );
        } catch (Throwable $ignore) {}
    }

    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
