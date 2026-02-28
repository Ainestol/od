<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'FORBIDDEN']);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$bugId = (int)($data['id'] ?? 0);
$text  = trim($data['message'] ?? '');

if (!$bugId || $text === '') {
  echo json_encode(['ok' => false, 'error' => 'INVALID_INPUT']);
  exit;
}

/* poslední zpráva */
$last = $pdo->prepare("
  SELECT author
  FROM bug_report_messages
  WHERE bug_report_id = ?
  ORDER BY created_at DESC
  LIMIT 1
");
$last->execute([$bugId]);
$lastRole = $last->fetchColumn();

/* ping-pong */
if ($lastRole === 'admin') {
  echo json_encode(['ok' => false, 'error' => 'WAIT_FOR_USER']);
  exit;
}

/* vložení zprávy */
$insert = $pdo->prepare("
  INSERT INTO bug_report_messages (bug_report_id, author_role, message)
  VALUES (?, 'admin', ?)
");
$insert->execute([$bugId, $text]);

/* status */
$pdo->prepare("
  UPDATE bug_reports
  SET status = 'in_progress'
  WHERE id = ?
")->execute([$bugId]);

echo json_encode(['ok' => true]);
