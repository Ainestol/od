<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_bootstrap.php';

assert_admin();

$limit = min((int)($_GET['limit'] ?? 50), 200);

$stmt = $pdo->prepare("
SELECT 
created_at,
action,
user_id,
target_id,
status,
meta
FROM system_logs
ORDER BY created_at DESC
LIMIT ?
");

$stmt->execute([$limit]);

echo json_encode([
  'ok' => true,
  'logs' => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);