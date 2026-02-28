<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/_smtp_mail.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(["error" => "Method not allowed"]);
  exit;
}

// přijmi JSON i klasický POST
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

if (!is_array($input) || empty($input)) {
  http_response_code(400);
  echo json_encode(["error" => "Invalid input"]);
  exit;
}

$email    = trim((string)($input['email'] ?? ''));
$password = (string)($input['password'] ?? '');
$lang     = (($input['lang'] ?? 'cs') === 'en') ? 'en' : 'cs';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(["error" => "INVALID_EMAIL"]);
  exit;
}

if (strlen($password) < 6) {
  http_response_code(400);
  echo json_encode(["error" => "PASSWORD_TOO_SHORT"]);
  exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

// token (plain pro link) + hash do DB
$tokenPlain = bin2hex(random_bytes(32));              // 64 hex chars
$tokenHash  = hash('sha256', $tokenPlain);
$expiresAt  = (new DateTime('+60 minutes'))->format('Y-m-d H:i:s');

$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

try {
  $pdo->beginTransaction();

  // 1) user
  $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, is_verified) VALUES (?, ?, 0)");
  $stmt->execute([$email, $hash]);
  $userId = (int)$pdo->lastInsertId();

  // 2) token row
  $stmt = $pdo->prepare("
    INSERT INTO user_tokens (user_id, token_hash, type, expires_at, ip, user_agent)
    VALUES (?, ?, 'verify_email', ?, ?, ?)
  ");
  $stmt->execute([$userId, $tokenHash, $expiresAt, $ip, $ua]);

  $pdo->commit();

} catch (PDOException $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();

  // duplicate email (UNIQUE)
  if ($e->getCode() === "23000") {
    http_response_code(409);
    echo json_encode(["error" => "EMAIL_EXISTS"]);
  } else {
    http_response_code(500);
    echo json_encode(["error" => "Server error"]);
  }
  exit;
}

// 3) send email
$verifyUrl = APP_BASE_URL . "/api/verify.php?token=" . urlencode($tokenPlain) . "&lang=" . $lang;

$subject = ($lang === 'en')
  ? "Ordo Draconis – Verify your email"
  : "Ordo Draconis – Ověření e-mailu";

$html = ($lang === 'en')
? "
  <html><head><meta charset='utf-8'></head><body>
  <div style='font-family:Arial,sans-serif;line-height:1.5'>
    <h2>Verify your email</h2>
    <p>To activate your Ordo Draconis account, click the button below:</p>
    <p><a href='{$verifyUrl}' style='display:inline-block;padding:10px 16px;background:#111;color:#d4af37;text-decoration:none;border:1px solid #3a3322'>Verify email</a></p>
    <p>If you didn’t register, ignore this email.</p>
    <p style='color:#666;font-size:12px'>Link expires in 60 minutes.</p>
  </div>
</body></html>
"
: "
  <html><head><meta charset='utf-8'></head><body>
  <div style='font-family:Arial,sans-serif;line-height:1.5'>
    <h2>Ověření e-mailu</h2>
    <p>Pro aktivaci účtu Ordo Draconis klikni na tlačítko níže:</p>
    <p><a href='{$verifyUrl}' style='display:inline-block;padding:10px 16px;background:#111;color:#d4af37;text-decoration:none;border:1px solid #3a3322'>Ověřit e-mail</a></p>
    <p>Pokud ses neregistroval(a), tento e-mail ignoruj.</p>
    <p style='color:#666;font-size:12px'>Odkaz platí 60 minut.</p>
  </div>
 </body></html>
";

$err = null;
$sent = smtp_send_mail($email, $subject, $html, SMTP_USER, 'Ordo Draconis', $err);

if (!$sent) {
  // účet je založený, ale mail neodešel — vrátíme ok + hint a zalogujeme důvod
  error_log("SMTP_FAIL to={$email} err=" . ($err ?? 'unknown'));

  echo json_encode([
    "status" => "ok",
    "message" => "User registered, but email sending failed",
    "email_sent" => false
  ]);
  exit;
}

echo json_encode([
  "status" => "ok",
  "message" => "User registered, verification email sent",
  "email_sent" => true
]);
