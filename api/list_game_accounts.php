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

try {
  /* 1️⃣ WEB DB – herní účty */
  $st = $pdo->prepare("
    SELECT 
      login,
      created_at,
      is_primary
    FROM game_accounts
    WHERE web_user_id = ?
    ORDER BY created_at DESC
  ");
  $st->execute([(int)$_SESSION['web_user_id']]);
  $accounts = $st->fetchAll(PDO::FETCH_ASSOC);

  /* 2️⃣ GAME DB – doplnění dat */
  foreach ($accounts as &$acc) {

    // počet postav
    $stChars = $pdoGame->prepare("
      SELECT COUNT(*) 
      FROM characters 
      WHERE account_name = ?
    ");
    $stChars->execute([$acc['login']]);
    $acc['chars_count'] = (int)$stChars->fetchColumn();

    // premium
    $stPrem = $pdoGame->prepare("
      SELECT enddate 
      FROM account_premium 
      WHERE account_name = ?
      LIMIT 1
    ");
    $stPrem->execute([$acc['login']]);
    $endMs = $stPrem->fetchColumn();

    if ($endMs) {
      $acc['premium_end_ms']   = (int)$endMs;
      $acc['premium_end_at']   = date('Y-m-d H:i:s', $endMs / 1000);
      $acc['premium_days_left'] = floor(($endMs / 1000 - time()) / 86400);
    } else {
      $acc['premium_end_ms']    = null;
      $acc['premium_end_at']    = null;
      $acc['premium_days_left'] = null;
    }
  }
  unset($acc);

  echo json_encode([
    "ok" => true,
    "accounts" => $accounts
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "error" => "Server error"
  ]);
}
