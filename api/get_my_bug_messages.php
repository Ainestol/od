<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

/* =========================
   AUTENTIZACE
   ========================= */
if (empty($_SESSION['web_user_id'])) {
  http_response_code(401);
  echo json_encode([
    'ok' => false,
    'error' => 'NOT_LOGGED_IN'
  ]);
  exit;
}

require_once __DIR__ . '/../config/db.php';

$id     = (int)($_GET['id'] ?? 0);
$userId = (int)$_SESSION['web_user_id'];

if ($id <= 0) {
  http_response_code(400);
  echo json_encode([
    'ok' => false,
    'error' => 'INVALID_ID'
  ]);
  exit;
}

try {

  /* =========================
     OVĚŘENÍ VLASTNICTVÍ
     ========================= */
  $check = $pdo->prepare("
    SELECT id
    FROM bug_reports
    WHERE id = ? AND web_user_id = ?
  ");
  $check->execute([$id, $userId]);

  if (!$check->fetchColumn()) {
    http_response_code(403);
    echo json_encode([
      'ok' => false,
      'error' => 'NOT_OWNER'
    ]);
    exit;
  }

  /* =========================
     NAČTENÍ ZPRÁV
     ========================= */
  $st = $pdo->prepare("
    SELECT author_role, message, created_at
    FROM bug_report_messages
    WHERE bug_report_id = ?
    ORDER BY created_at ASC
  ");
  $st->execute([$id]);

  $messages = $st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    'ok' => true,
    'messages' => $messages
  ]);
  exit;

} catch (Throwable $e) {

  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'SERVER_ERROR'
    // 'detail' => $e->getMessage() // zapni jen při ladění
  ]);
  exit;
}
