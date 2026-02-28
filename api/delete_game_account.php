<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../lib/csrf.php';
csrf_check();
require_once __DIR__ . '/../config/db_game.php';
try {

  /* ===============================
     AUTH
     =============================== */
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

  /* ===============================
     WEB DB
     =============================== */
  require_once __DIR__ . '/../config/db.php';
  $webPdo = $pdo;

  // ověření vlastnictví účtu
  $st = $webPdo->prepare(
    "SELECT 1 FROM game_accounts WHERE web_user_id = ? AND login = ?"
  );
  $st->execute([$_SESSION['web_user_id'], $login]);

  if (!$st->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
  }

  /* ===============================
     KONTROLA POSTAV (l2game)
     =============================== */

  $st = $pdoGame->prepare(
    "SELECT COUNT(*)
     FROM characters
     WHERE LOWER(account_name) = LOWER(?)
       AND (deletetime = 0 OR deletetime IS NULL)"
  );
  $st->execute([$login]);
  $chars = (int)$st->fetchColumn();

  if ($chars > 0) {
    http_response_code(409);
    echo json_encode([
      'error' => 'ACCOUNT_HAS_ACTIVE_CHARACTERS',
      'chars' => $chars
    ]);
    exit;
  }

  /* ===============================
     L2 LOGIN DB – DELETE ACCOUNT
     =============================== */
  $l2Pdo = new PDO(
    "mysql:host=127.0.0.1;dbname=l2login;charset=utf8mb4",
    "l2_writer",
    "@Heslojeheslo09",
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
  );

  $l2Pdo->prepare(
    "DELETE FROM accounts WHERE login = ?"
  )->execute([$login]);

  /* ===============================
     DELETE WEB BINDING
     =============================== */
  $webPdo->prepare(
    "DELETE FROM game_accounts WHERE web_user_id = ? AND login = ?"
  )->execute([$_SESSION['web_user_id'], $login]);

  /* ===============================
     AUDIT LOG (NESMÍ SHODIT AKCI)
     =============================== */
  try {
    $audit = $webPdo->prepare("
      INSERT INTO audit_log
        (web_user_id, game_login, action, ip_address, user_agent)
      VALUES (?, ?, 'DELETE_ACCOUNT', ?, ?)
    ");

    $audit->execute([
      (int)$_SESSION['web_user_id'],
      $login,
      $_SERVER['REMOTE_ADDR'] ?? 'unknown',
      $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
  } catch (Throwable $e) {
    // audit je nepovinný – ignorujeme chybu
  }

  echo json_encode(['ok' => true]);

} catch (Throwable $e) {
  http_response_code(500);

  $isDev = ($_ENV['APP_ENV'] ?? 'prod') === 'dev';

  if ($isDev) {
    echo json_encode([
      'error'  => 'Server error',
      'detail' => $e->getMessage()
    ]);
  } else {
    echo json_encode([
      'error' => 'Server error'
    ]);
  }
}
