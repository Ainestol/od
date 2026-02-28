<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/_smtp_mail.php';

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

$email = trim((string)($input['email'] ?? ''));
$lang  = (($input['lang'] ?? 'cs') === 'en') ? 'en' : 'cs';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  // anti-enumeration
  echo json_encode(["status" => "ok"]);
  exit;
}

$st = $pdo->prepare("SELECT id, email, is_verified FROM users WHERE email=? LIMIT 1");
$st->execute([$email]);
$user = $st->fetch(PDO::FETCH_ASSOC);

// anti-enumeration
if (!$user) {
  echo json_encode(["status" => "ok"]);
  exit;
}
if ((int)($user['is_verified'] ?? 0) !== 1) {
  // jen ověřeným, ale pořád "ok"
  echo json_encode(["status" => "ok"]);
  exit;
}

$userId = (int)$user['id'];
$ip     = $_SERVER['REMOTE_ADDR'] ?? '';
$ua     = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

/* =========================
   Rate limit (anti-abuse)
   ========================= */

// 1) Cooldown per user (120s)
$st = $pdo->prepare("
  SELECT UNIX_TIMESTAMP(created_at)
  FROM user_tokens
  WHERE user_id = ? AND type='reset_password'
  ORDER BY created_at DESC
  LIMIT 1
");
$st->execute([$userId]);
$lastTs = $st->fetchColumn();

if ($lastTs) {
  $delta = time() - (int)$lastTs;
  if ($delta < 120) {
    error_log("RESET_REQ_STOP reason=rate_cooldown_120s user_id={$userId} delta={$delta} ip={$ip}");
    echo json_encode(["status" => "ok"]);
    exit;
  }
}

// 2) Max 3 per user / 10 min
$st = $pdo->prepare("
  SELECT COUNT(*)
  FROM user_tokens
  WHERE user_id = ? AND type='reset_password'
    AND created_at > (NOW() - INTERVAL 10 MINUTE)
");
$st->execute([$userId]);
$cntUser = (int)$st->fetchColumn();

if ($cntUser >= 3) {
  error_log("RESET_REQ_STOP reason=rate_user_3_per_10m user_id={$userId} cnt={$cntUser} ip={$ip}");
  echo json_encode(["status" => "ok"]);
  exit;
}

// 3) Max 20 per IP / 10 min (volitelné)
if ($ip !== '') {
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM user_tokens
    WHERE type='reset_password'
      AND ip = ?
      AND created_at > (NOW() - INTERVAL 10 MINUTE)
  ");
  $st->execute([$ip]);
  $cntIp = (int)$st->fetchColumn();

  if ($cntIp >= 20) {
    error_log("RESET_REQ_STOP reason=rate_ip_20_per_10m ip={$ip} cnt={$cntIp}");
    echo json_encode(["status" => "ok"]);
    exit;
  }
}

/* =========================
   Create token
   ========================= */

$tokenPlain = bin2hex(random_bytes(32));
$tokenHash  = hash('sha256', $tokenPlain);
$expiresAt  = (new DateTime('+60 minutes'))->format('Y-m-d H:i:s');

try {
  // zneplatnit staré reset tokeny uživatele
  $pdo->prepare("UPDATE user_tokens SET used_at = NOW()
                 WHERE user_id=? AND type='reset_password' AND used_at IS NULL")
      ->execute([$userId]);

  $pdo->prepare("
    INSERT INTO user_tokens (user_id, token_hash, type, expires_at, ip, user_agent)
    VALUES (?, ?, 'reset_password', ?, ?, ?)
  ")->execute([$userId, $tokenHash, $expiresAt, $ip ?: null, $ua]);

} catch (Throwable $e) {
  error_log("RESET_REQ_STOP reason=db_error user_id={$userId} ip={$ip} err=" . $e->getMessage());
  echo json_encode(["status" => "ok"]);
  exit;
}

/* =========================
   Email content
   ========================= */

$resetPage = ($lang === 'en') ? 'reset-password-en.html' : 'reset-password.html';
$resetUrl  = APP_BASE_URL . "/auth/{$resetPage}?token=" . urlencode($tokenPlain) . "&lang=" . $lang;

$subject = ($lang === 'en')
  ? "Ordo Draconis – Password reset"
  : "Ordo Draconis – Obnovení hesla";

$html = ($lang === 'en')
? "
<html><head><meta charset='utf-8'></head><body>
  <div style='font-family:Arial,sans-serif;line-height:1.5'>
    <h2>Password reset</h2>
    <p>Click the button below to set a new password:</p>
    <p>
      <a href='{$resetUrl}' style='display:inline-block;padding:10px 16px;background:#111;color:#d4af37;text-decoration:none;border:1px solid #3a3322'>
        Reset password
      </a>
    </p>
    <p>If you didn’t request this, ignore this email.</p>
    <p style='color:#666;font-size:12px'>Link expires in 60 minutes.</p>
  </div>
</body></html>
"
: "
<html><head><meta charset='utf-8'></head><body>
  <div style='font-family:Arial,sans-serif;line-height:1.5'>
    <h2>Obnovení hesla</h2>
    <p>Klikni na tlačítko níže a nastav si nové heslo:</p>
    <p>
      <a href='{$resetUrl}' style='display:inline-block;padding:10px 16px;background:#111;color:#d4af37;text-decoration:none;border:1px solid #3a3322'>
        Obnovit heslo
      </a>
    </p>
    <p>Pokud jsi o reset nežádal(a), tento e-mail ignoruj.</p>
    <p style='color:#666;font-size:12px'>Odkaz platí 60 minut.</p>
  </div>
</body></html>
";

$err = null;
$sent = smtp_send_mail($email, $subject, $html, SMTP_USER, 'Ordo Draconis', $err);

if (!$sent) {
  error_log("SMTP_RESET_FAIL to={$email} user_id={$userId} ip={$ip} err=" . ($err ?? 'unknown'));
} else {
  error_log("RESET_REQ_OK user_id={$userId} ip={$ip} lang={$lang}");
}

// anti-enumeration: vždy OK
echo json_encode(["status" => "ok"]);
