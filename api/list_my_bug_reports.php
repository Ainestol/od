<?php

require_once __DIR__.'/../../api/admin/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {

$st = $pdo->query("
SELECT
  b.id,
  u.email,
  b.game_account,
  b.category,
  b.title,
  b.status,
  b.created_at
FROM bug_reports b
LEFT JOIN users u ON u.id = b.user_id
ORDER BY b.created_at DESC
");

echo json_encode([
  'ok' => true,
  'tickets' => $st->fetchAll(PDO::FETCH_ASSOC)
]);

} catch (Throwable $e) {

error_log("BUG_LIST_ERROR: ".$e->getMessage());

http_response_code(500);

echo json_encode([
  'ok' => false,
  'error' => 'SERVER_ERROR'
]);

}