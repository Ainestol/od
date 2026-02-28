<?php
require_once __DIR__ . '/_bootstrap.php';
assert_admin();

$stmt = $pdoWeb->query("
    SELECT
        owner_id AS web_user_id,
        currency,
        balance,
        updated_at
    FROM wallet_balances
    ORDER BY updated_at DESC
");

echo json_encode([
    'ok' => true,
    'data' => $stmt->fetchAll()
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
