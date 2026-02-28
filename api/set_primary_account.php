<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

try {
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

  if ($login === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing login']);
    exit;
  }

  require_once __DIR__ . '/../config/db.php';
  $pdo = $pdo;
  $userId = (int)$_SESSION['web_user_id'];

  // ověření, že účet patří uživateli
  $st = $pdo->prepare(
    "SELECT 1 FROM game_accounts WHERE web_user_id = ? AND login = ?"
  );
  $st->execute([$userId, $login]);

  if (!$st->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
  }

  $pdo->beginTransaction();

  // zrušit primární u všech
  $pdo->prepare(
    "UPDATE game_accounts SET is_primary = 0 WHERE web_user_id = ?"
  )->execute([$userId]);

  // nastavit nový primární
  $pdo->prepare(
    "UPDATE game_accounts
     SET is_primary = 1
     WHERE web_user_id = ? AND login = ?"
  )->execute([$userId, $login]);

  $pdo->commit();

  echo json_encode(['ok' => true]);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }

  http_response_code(500);
  echo json_encode(['error' => 'Server error']);
}
