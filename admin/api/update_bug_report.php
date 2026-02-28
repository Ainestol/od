<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['web_user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo json_encode(['error' => 'Forbidden']);
  exit;
}

require_once __DIR__ . '/../../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);

$st = $pdo->prepare("
  UPDATE bug_reports
  SET status = ?, admin_reply = ?
  WHERE id = ?
");
$st->execute([
  $input['status'],
  $input['reply'],
  (int)$input['id']
]);

echo json_encode(['ok' => true]);
