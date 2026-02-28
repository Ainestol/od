<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

/* =========================
   AUTORIZACE
   ========================= */
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode([
    'ok' => false,
    'error' => 'FORBIDDEN'
  ]);
  exit;
}

require_once __DIR__ . '/../../config/db.php';

/* =========================
   NAČTENÍ A VALIDACE JSON
   ========================= */
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
  http_response_code(400);
  echo json_encode([
    'ok' => false,
    'error' => 'INVALID_JSON'
  ]);
  exit;
}

$id     = isset($data['id']) ? (int)$data['id'] : 0;
$msg    = trim($data['message'] ?? '');
$status = $data['status'] ?? null;

if ($id <= 0) {
  http_response_code(400);
  echo json_encode([
    'ok' => false,
    'error' => 'INVALID_ID'
  ]);
  exit;
}

/* =========================
   MAPOVÁNÍ STATUSŮ (APP → DB)
   ========================= */
$statusMap = [
  'new'         => 'NEW',
  'in_progress' => 'IN PROGRESS',
  'resolved'    => 'RESOLVED',
  'closed'      => 'CLOSED'
];

if ($status !== null) {
  if (!isset($statusMap[$status])) {
    http_response_code(400);
    echo json_encode([
      'ok' => false,
      'error' => 'INVALID_STATUS'
    ]);
    exit;
  }
  $status = $statusMap[$status];
}

try {

  /* =========================
     OVĚŘENÍ EXISTENCE BUGU
     ========================= */
  $chk = $pdo->prepare("
    SELECT status
    FROM bug_reports
    WHERE id = ?
  ");
  $chk->execute([$id]);
  $currentStatus = $chk->fetchColumn();

  if ($currentStatus === false) {
    http_response_code(404);
    echo json_encode([
      'ok' => false,
      'error' => 'BUG_NOT_FOUND'
    ]);
    exit;
  }

  $pdo->beginTransaction();

  /* =========================
     ULOŽENÍ ZPRÁVY (PING-PONG)
     ========================= */
  if ($msg !== '') {
    $last = $pdo->prepare("
      SELECT author_role
      FROM bug_report_messages
      WHERE bug_report_id = ?
      ORDER BY created_at DESC
      LIMIT 1
    ");
    $last->execute([$id]);

    if ($last->fetchColumn() === 'admin') {
      $pdo->rollBack();
      echo json_encode([
        'ok' => false,
        'error' => 'WAIT_FOR_USER'
      ]);
      exit;
    }

    $ins = $pdo->prepare("
      INSERT INTO bug_report_messages
        (bug_report_id, author_role, message)
      VALUES (?, 'admin', ?)
    ");
    $ins->execute([$id, $msg]);
  }

  /* =========================
     UPDATE STATUSU – JEN KDYŽ SE ZMĚNIL
     ========================= */
  if ($status !== null && $status !== $currentStatus) {
    $upd = $pdo->prepare("
      UPDATE bug_reports
      SET status = ?
      WHERE id = ?
    ");
    $upd->execute([$status, $id]);
  }

  $pdo->commit();

  echo json_encode([
    'ok' => true
  ]);
  exit;

} catch (Throwable $e) {

  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }

  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'SERVER_ERROR',
    'detail' => $e->getMessage() // po finálním testu můžeš odstranit
  ]);
  exit;
}
