<?php
require_once __DIR__ . '/_bootstrap.php';
assert_admin($pdoWeb);

$webUserId = (int)($_GET['webUserId'] ?? 0);

if (!$webUserId) {
    echo json_encode([
        'ok' => false,
        'error' => 'Missing webUserId'
    ]);
    exit;
}

$stmt = $pdoWeb->prepare("
    SELECT ga.id, ga.login, ga.web_user_id
    FROM game_accounts ga
    WHERE ga.web_user_id = ?
    ORDER BY ga.id DESC
");

$stmt->execute([$webUserId]);

echo json_encode([
    'ok' => true,
    'data' => $stmt->fetchAll()
]);
