<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['web_user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
  exit;
}

require_once __DIR__ . '/../config/db.php';

try {
  $st = $pdo->prepare("
    SELECT id, login
    FROM game_accounts
    WHERE web_user_id = ?
    ORDER BY created_at DESC
  ");
  $st->execute([(int)$_SESSION['web_user_id']]);

  echo json_encode([
    'ok' => true,
    'accounts' => $st->fetchAll(PDO::FETCH_ASSOC)
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}