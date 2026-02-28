<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

try {
  // 1️⃣ ověření přihlášení
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

  // 2️⃣ načtení dat
  $input = json_decode(file_get_contents('php://input'), true);

  $gameAccount = trim($input['game_account'] ?? '');
  $category    = trim($input['category'] ?? '');
  $title       = trim($input['title'] ?? '');
  $message     = trim($input['message'] ?? '');

  // 3️⃣ validace
  if ($category === '' || $title === '' || $message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
  }

  if (mb_strlen($title) > 40 || mb_strlen($message) > 1000) {
    http_response_code(400);
    echo json_encode(['error' => 'Text too long']);
    exit;
  }

  $userId = (int)$_SESSION['web_user_id'];

  require_once __DIR__ . '/../config/db.php'; // $pdo

  // 4️⃣ uložení ticketu
  $st = $pdo->prepare("
    INSERT INTO bug_reports
      (web_user_id, game_account, category, title, message)
    VALUES
      (?, ?, ?, ?, ?)
  ");

  $st->execute([
    $userId,
    $gameAccount !== '' ? $gameAccount : null,
    $category,
    $title,
    $message
  ]);

  // 5️⃣ hotovo
  echo json_encode(['ok' => true]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Server error']);
}
