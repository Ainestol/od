<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../config/db.php'; // web DB => $pdo
$webPdo = $pdo;

/* musí být pøihlášen */
if (empty($_SESSION['web_user_id'])) {
  http_response_code(401);
  echo json_encode(["error" => "Unauthorized"]);
  exit;
}

/* pouze POST */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(["error" => "Method not allowed"]);
  exit;
}

/* naètení JSON vstupu */
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
  http_response_code(400);
  echo json_encode(["error" => "Invalid JSON"]);
  exit;
}

/* data */
$login = trim($input['login'] ?? '');
$pass  = (string)($input['password'] ?? '');

/* validace */
if (!preg_match('/^[a-zA-Z0-9_]{4,16}$/', $login)) {
  http_response_code(400);
  echo json_encode(["error" => "Invalid login"]);
  exit;
}

if (strlen($pass) < 6) {
  http_response_code(400);
  echo json_encode(["error" => "Password too short"]);
  exit;
}
// LIMIT 10 GAME ACCOUNTS PER WEB USER
$cnt = $webPdo->prepare(
  "SELECT COUNT(*) FROM game_accounts WHERE web_user_id = ?"
);
$cnt->execute([$_SESSION['web_user_id']]);
$count = (int)$cnt->fetchColumn();

if ($count >= 10) {
  http_response_code(400);
  echo json_encode([
    'error' => 'Dosáhl jsi maximálního poètu 10 herních úètù.'
  ]);
  exit;
}

/* simple rate limit */
$now = time();
if (!empty($_SESSION['last_create_acc']) && ($now - $_SESSION['last_create_acc'] < 5)) {
  http_response_code(429);
  echo json_encode(["error" => "Too many requests"]);
  exit;
}
$_SESSION['last_create_acc'] = $now;

/* DB (login server) */
$dsn    = "mysql:host=127.0.0.1;dbname=l2login;charset=utf8mb4";
$dbUser = "webuserp";
$dbPass = "@Heslojeheslo10";

try {
  /* L2 login DB */
    $l2Pdo = new PDO($dsn, $dbUser, $dbPass, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// ?? pøipojení na L2 GAME DB (pro premium)
$pdoGame = new PDO(
  "mysql:host=127.0.0.1;dbname=l2game;charset=utf8mb4",
  $dbUser,
  $dbPass,
  [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]
);
  /* existuje už úèet? */
  $st = $l2Pdo->prepare("SELECT 1 FROM accounts WHERE login = ? LIMIT 1");
  $st->execute([$login]);
  if ($st->fetch()) {
    http_response_code(409);
    echo json_encode(["error" => "Account already exists"]);
    exit;
  }

  /* hash hesla (L2 styl) */
  $hash = base64_encode(sha1($pass, true));

  /* založení game úètu */
  $ins = $l2Pdo->prepare("
    INSERT INTO accounts (login, password, accessLevel, lastactive)
    VALUES (?, ?, 0, 0)
  ");
  $ins->execute([$login, $hash]);

  /* vazba na web úèet */
  $webIns = $webPdo->prepare(
    "INSERT IGNORE INTO game_accounts (web_user_id, login) VALUES (?, ?)"
  );
  $webIns->execute([(int)$_SESSION['web_user_id'], $login]);
$vipCheck = $webPdo->prepare("
    SELECT end_at
    FROM vip_grants
    WHERE scope = 'WEB'
      AND target_id = ?
      AND end_at > NOW()
    ORDER BY end_at DESC
    LIMIT 1
");
$vipCheck->execute([(int)$_SESSION['web_user_id']]);
$vipEnd = $vipCheck->fetchColumn();

if ($vipEnd) {

    $endMs = (strtotime($vipEnd) * 1000);

    $stmtGame = $pdoGame->prepare("
        INSERT INTO account_premium (account_name, enddate)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE enddate = ?
    ");

    $stmtGame->execute([$login, $endMs, $endMs]);
}
$audit = $webPdo->prepare("
  INSERT INTO audit_log
    (web_user_id, game_login, action, ip_address, user_agent)
  VALUES (?, ?, 'CREATE_ACCOUNT', ?, ?)
");

$audit->execute([
  (int)$_SESSION['web_user_id'],
  $login,
  $_SERVER['REMOTE_ADDR'] ?? 'unknown',
  $_SERVER['HTTP_USER_AGENT'] ?? null
]);

  echo json_encode(["ok" => true]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    "error" => $e->getMessage(),
    "file" => $e->getFile(),
    "line" => $e->getLine()
  ]);
}
