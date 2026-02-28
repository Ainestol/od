<?php
require_once __DIR__ . '/_bootstrap.php';
assert_admin();

$stmt = $pdoWeb->query("
    SELECT
        u.id               AS web_user_id,
        u.email            AS email,
        ga.id              AS game_account_id,
        ga.login           AS game_login,
        wb.balance         AS dc_balance
    FROM users u
    LEFT JOIN game_accounts ga ON ga.web_user_id = u.id
    LEFT JOIN wallet_balances wb
        ON wb.owner_type = 'WEB'
       AND wb.owner_id = u.id
       AND wb.currency = 'DC'
    ORDER BY u.id DESC
    LIMIT 200
");

echo json_encode([
    'ok' => true,
    'data' => $stmt->fetchAll()
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
