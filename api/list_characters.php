<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['web_user_id'])) {
  http_response_code(401);
  echo json_encode(["ok" => false, "error" => "Unauthorized"]);
  exit;
}

require_once __DIR__ . '/../config/db.php';       // $pdo (WEB)
require_once __DIR__ . '/../config/db_game.php'; // $pdoGame

$login = $_GET['account'] ?? '';
if ($login === '') {
  echo json_encode(["ok" => false, "error" => "MISSING_ACCOUNT"]);
  exit;
}

/* ověření, že účet patří userovi */
$st = $pdo->prepare("
  SELECT 1 FROM game_accounts
  WHERE login = ? AND web_user_id = ?
  LIMIT 1
");
$st->execute([$login, (int)$_SESSION['web_user_id']]);

if (!$st->fetchColumn()) {
  http_response_code(403);
  echo json_encode(["ok" => false, "error" => "FORBIDDEN"]);
  exit;
}

/* načtení postav */
$st = $pdoGame->prepare("
  SELECT 
    charId,
    char_name,
    level,
    classid,
    online
  FROM characters
  WHERE account_name = ?
  ORDER BY level DESC
");
$st->execute([$login]);
$chars = $st->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
  "ok" => true,
  "characters" => $chars
]);
