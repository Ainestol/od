<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['web_user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false]);
  exit;
}

require_once __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? 0);
$userId = (int)$_SESSION['web_user_id'];

$st = $pdo->prepare("
  SELECT id, title, category, status, created_at
  FROM bug_reports
  WHERE id = ? AND web_user_id = ?
");
$st->execute([$id, $userId]);

$bug = $st->fetch();

if (!$bug) {
  echo json_encode(['ok' => false]);
  exit;
}

echo json_encode([
  'ok' => true,
  'bug' => $bug
]);
