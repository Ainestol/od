<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['web_user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo json_encode(['error' => 'Forbidden']);
  exit;
}

require_once __DIR__ . '/../../config/db.php';

try {
  $st = $pdo->query("
    SELECT
      br.id,
      br.category,
      br.title,
      br.status,
      br.created_at,
      u.email,
      br.game_account
    FROM bug_reports br
    JOIN users u ON u.id = br.web_user_id
    ORDER BY br.created_at DESC
  ");

  echo json_encode([
    'ok' => true,
    'tickets' => $st->fetchAll(PDO::FETCH_ASSOC)
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Server error']);
}
