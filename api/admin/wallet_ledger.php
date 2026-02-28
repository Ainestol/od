<?php
require_once __DIR__ . '/_bootstrap.php';
assert_admin();

$limit = isset($_GET['limit']) && ctype_digit($_GET['limit'])
    ? min((int)$_GET['limit'], 500)
    : 200;

$stmt = $pdoWeb->prepare("
    SELECT
        id,
        owner_id AS web_user_id,
        currency,
        amount,
        reason,
        ref_type,
        ref_id,
        note,
        created_at
    FROM wallet_ledger
    ORDER BY id DESC
    LIMIT ?
");
$stmt->execute([$limit]);

echo json_encode([
    'ok' => true,
    'data' => $stmt->fetchAll()
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
