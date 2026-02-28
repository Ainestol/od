<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['web_user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'UNAUTHORIZED']);
  exit;
}

require_once __DIR__ . '/../config/db.php';       // $pdo
require_once __DIR__ . '/../config/db_game.php'; // $pdoGame
require_once __DIR__ . '/../lib/vip_resolve.php';

$account = $_GET['account'] ?? '';
if ($account === '') {
  echo json_encode(['ok' => false, 'error' => 'MISSING_ACCOUNT']);
  exit;
}

/* ověření, že účet patří uživateli */
$st = $pdo->prepare("
  SELECT 1 FROM game_accounts
  WHERE login = ? AND web_user_id = ?
  LIMIT 1
");
$st->execute([$account, (int)$_SESSION['web_user_id']]);

if (!$st->fetchColumn()) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'FORBIDDEN']);
  exit;
}

try {

  /* načtení postav */
  $st = $pdoGame->prepare("
    SELECT
      charId,
      char_name,
      level,
      online
    FROM characters
    WHERE account_name = ?
    ORDER BY level DESC
  ");

  $st->execute([$account]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $characters = [];

  foreach ($rows as $row) {

    $vip = vip_get_effective_for_character(
      $pdo,
      $pdoGame,
      (int)$row['charId']
    );

    $characters[] = [
      'charId'     => (int)$row['charId'],
      'char_name'  => $row['char_name'],
      'level'      => (int)$row['level'],
      'online'     => (int)$row['online'],
      'has_vip'    => $vip['found'] ?? false,
      'vip_end_at' => $vip['end_at'] ?? null
    ];
  }

  echo json_encode([
    'ok' => true,
    'characters' => $characters
  ]);

} catch (Throwable $e) {

  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'SERVER_ERROR'
  ]);
}
