<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['web_user_id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$login = trim($input['login'] ?? '');
$pass  = (string)($input['password'] ?? '');

if ($login === '' || strlen($pass) < 6) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid input']);
  exit;
}

require_once '../config/db.php';          // $pdo (web)
require_once '../config/db_game_write.php'; // $l2Pdo

/* ověření, že účet patří uživateli */
$st = $Pdo->prepare(
  "SELECT 1 FROM game_accounts WHERE web_user_id = ? AND login = ?"
);
$st->execute([$_SESSION['web_user_id'], $login]);

if (!$st->fetch()) {
  http_response_code(403);
  echo json_encode(['error' => 'Forbidden']);
  exit;
}

/* hash hesla (stejný jako při create) */
$hash = base64_encode(sha1($pass, true));

$upd = $l2Pdo->prepare(
  "UPDATE accounts SET password = ? WHERE login = ?"
);
$upd->execute([$hash, $login]);

echo json_encode(['ok' => true]);
