<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['web_user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'NOT_LOGGED']);
  exit;
}

require_once __DIR__ . '/../config/db.php';

$userId = (int)$_SESSION['web_user_id'];

try {
  $st = $pdo->prepare("
    SELECT
      id,
      title,
      status,
      created_at
    FROM bug_reports
    WHERE web_user_id = ?
    ORDER BY created_at DESC
  ");
  $st->execute([$userId]);

  echo json_encode([
    'ok' => true,
    'bugs' => $st->fetchAll(PDO::FETCH_ASSOC)
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}
