<?php
require_once __DIR__ . '/_bootstrap.php';
assert_admin();

$stmt = $pdo->query("
    SELECT 
        u.email,
        ga.login AS game_account,
        c.char_name,
        c.level
    FROM game_accounts ga
    JOIN users u 
        ON u.id = ga.web_user_id
    JOIN l2game.characters c
        ON c.account_name = ga.login
    WHERE c.online = 1
    ORDER BY c.level DESC
");

echo json_encode([
    'ok' => true,
    'data' => $stmt->fetchAll()
]);
