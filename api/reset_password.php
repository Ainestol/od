<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(["error" => "METHOD_NOT_ALLOWED"]);
  exit;
}

/* přijmi JSON i klasický POST */
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;
if (!is_array($input) || empty($input)) {
  http_response_code(400);
  echo json_encode(["error" => "INVALID_REQUEST"]);
  exit;
}

$token = (string)($input['token'] ?? '');
$newPw = (string)($input['password'] ?? '');

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
  http_response_code(400);
  echo json_encode(["error" => "INVALID_TOKEN"]);
  exit;
}

if (strlen($newPw) < 6) {
  http_response_code(400);
  echo json_encode(["error" => "PASSWORD_TOO_SHORT"]);
  exit;
}

$tokenHash = hash('sha256', $token);

$stmt = $pdo->prepare("
  SELECT id, user_id, expires_at, used_at
  FROM user_tokens
  WHERE token_hash = ? AND type='reset_password'
  LIMIT 1
");
$stmt->execute([$tokenHash]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  http_response_code(400);
  echo json_encode(["error" => "INVALID_TOKEN"]);
  exit;
}

if ($row['used_at'] !== null) {
  http_response_code(400);
  echo json_encode(["error" => "TOKEN_USED"]);
  exit;
}

$now = new DateTime();
$exp = new DateTime($row['expires_at']);
if ($now > $exp) {
  http_response_code(400);
  echo json_encode(["error" => "TOKEN_EXPIRED"]);
  exit;
}

$hash = password_hash($newPw, PASSWORD_DEFAULT);

$pdo->beginTransaction();
try {
  $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
      ->execute([$hash, (int)$row['user_id']]);

  $pdo->prepare("UPDATE user_tokens SET used_at=NOW() WHERE id=?")
      ->execute([(int)$row['id']]);

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(["error" => "SERVER_ERROR"]);
  exit;
}

echo json_encode(["status" => "ok"]);
