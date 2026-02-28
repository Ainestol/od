<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['web_user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'NOT_LOGGED_IN']);
  exit;
}

require_once __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? 0);
$userId = (int)$_SESSION['web_user_id'];

if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'INVALID_ID']);
  exit;
}

/*
  Ověření:
  - ticket existuje
  - patří přihlášenému uživateli
*/
$st = $pdo->prepare("
  SELECT
    id,
    title,
    category,
    status,
    created_at,
    game_account
  FROM bug_reports
  WHERE id = ? AND web_user_id = ?
  LIMIT 1
");
$st->execute([$id, $userId]);

$bug = $st->fetch();

if (!$bug) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'FORBIDDEN']);
  exit;
}

echo json_encode([
  'ok' => true,
  'bug' => $bug
]);
