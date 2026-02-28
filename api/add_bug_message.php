<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['web_user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false]);
  exit;
}

require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$bugId = (int)($data['id'] ?? 0);
$text  = trim($data['message'] ?? '');
$userId = (int)$_SESSION['web_user_id'];

if (!$bugId || $text === '') {
  echo json_encode(['ok' => false, 'error' => 'INVALID_DATA']);
  exit;
}

/* ověř, že ticket patří userovi */
$check = $pdo->prepare("
  SELECT id FROM bug_reports
  WHERE id = ? AND web_user_id = ?
");
$check->execute([$bugId, $userId]);
if (!$check->fetch()) {
  http_response_code(403);
  echo json_encode(['ok' => false]);
  exit;
}
// kontrola, zda uživatel nepotvrdil vyřešení
$lock = $pdo->prepare("
  SELECT 1
  FROM bug_report_messages
  WHERE bug_report_id = ?
    AND author_role = 'system'
    AND message LIKE '%potvrdil%'
  LIMIT 1
");
$lock->execute([$id]);

if ($lock->fetch()) {
  echo json_encode([
    'ok' => false,
    'error' => 'TICKET_LOCKED'
  ]);
  exit;
}

/* ping-pong: user nesmí psát 2× */
$last = $pdo->prepare("
  SELECT author_role
  FROM bug_report_messages
  WHERE bug_report_id = ?
  ORDER BY created_at DESC
  LIMIT 1
");
$last->execute([$bugId]);
if ($last->fetchColumn() === 'user') {
  echo json_encode(['ok' => false, 'error' => 'WAIT_FOR_ADMIN']);
  exit;
}
if (mb_strlen($msg) > 1000) {
  http_response_code(400);
  echo json_encode([
    'ok' => false,
    'error' => 'MESSAGE_TOO_LONG'
  ]);
  exit;
}

/* vlož zprávu */
$pdo->prepare("
  INSERT INTO bug_report_messages (bug_report_id, author_role, message)
  VALUES (?, 'user', ?)
")->execute([$bugId, $text]);

echo json_encode(['ok' => true]);
