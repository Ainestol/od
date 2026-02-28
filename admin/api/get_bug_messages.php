<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode([
    'ok' => false,
    'error' => 'FORBIDDEN'
  ]);
  exit;
}

require_once __DIR__ . '/../../config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
  http_response_code(400);
  echo json_encode([
    'ok' => false,
    'messages' => []
  ]);
  exit;
}

$st = $pdo->prepare("
  SELECT author_role, message, created_at
  FROM bug_report_messages
  WHERE bug_report_id = ?
  ORDER BY created_at ASC
");
$st->execute([$id]);

$messages = $st->fetchAll();

echo json_encode([
  'ok' => true,
  'messages' => $messages
]);
exit;
