<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['web_user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo json_encode(['error' => 'Forbidden']);
  exit;
}

require_once __DIR__ . '/../../config/db.php';

$id = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare("
  SELECT
    br.*,
    u.email
  FROM bug_reports br
  JOIN users u ON u.id = br.web_user_id
  WHERE br.id = ?
");
$st->execute([$id]);

$bug = $st->fetch(PDO::FETCH_ASSOC);

echo json_encode([
  'ok' => true,
  'bug' => $bug
]);
