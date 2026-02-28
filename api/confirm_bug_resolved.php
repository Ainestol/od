<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['web_user_id'])) {
  http_response_code(401);
  echo json_encode([
    'ok' => false,
    'error' => 'NOT_LOGGED_IN'
  ]);
  exit;
}

require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id']) ? (int)$data['id'] : 0;
$userId = (int)$_SESSION['web_user_id'];

if ($id <= 0) {
  http_response_code(400);
  echo json_encode([
    'ok' => false,
    'error' => 'INVALID_ID'
  ]);
  exit;
}

/* ověření, že ticket patří userovi a je RESOLVED */
$chk = $pdo->prepare("
  SELECT status
  FROM bug_reports
  WHERE id = ? AND web_user_id = ?
");
$chk->execute([$id, $userId]);
$status = $chk->fetchColumn();

if ($status === false) {
  http_response_code(403);
  echo json_encode([
    'ok' => false,
    'error' => 'NOT_OWNER'
  ]);
  exit;
}

if ($status !== 'RESOLVED') {
  http_response_code(400);
  echo json_encode([
    'ok' => false,
    'error' => 'INVALID_STATE'
  ]);
  exit;
}

/* zapsání systémové zprávy */
$pdo->prepare("
  INSERT INTO bug_report_messages
    (bug_report_id, author_role, message)
  VALUES (?, 'system', ?)
")->execute([
  $id,
  'Uživatel potvrdil, že problém je vyřešen.'
]);

echo json_encode([
  'ok' => true
]);
exit;
