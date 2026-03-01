<?php
require_once __DIR__ . '/_bootstrap.php';
assert_admin();

$stmt = $pdo->query("
    SELECT id, email, role
    FROM users
    ORDER BY id DESC
");

echo json_encode([
    'ok' => true,
    'data' => $stmt->fetchAll()
]);
