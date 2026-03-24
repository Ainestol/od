<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../config/db.php';

use PragmaRX\Google2FA\Google2FA;

if (!isset($_SESSION['2fa_user_id'])) {
    http_response_code(401);
    echo json_encode([
        "error" => "No 2FA session",
        "session_id" => session_id() // 🔥 debug (můžeš pak smazat)
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$code = $input['code'] ?? '';

if (!$code) {
    http_response_code(400);
    echo json_encode(["error" => "Missing code"]);
    exit;
}

$userId = $_SESSION['2fa_user_id'];

// načti secret
$stmt = $pdo->prepare("SELECT email, role, twofa_secret FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || empty($user['twofa_secret'])) {
    http_response_code(400);
    echo json_encode(["error" => "2FA not set"]);
    exit;
}

$google2fa = new Google2FA();

$valid = $google2fa->verifyKey($user['twofa_secret'], $code);

if (!$valid) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid code"]);
    exit;
}

// ================================
// LOGIN DOKONČEN
// ================================
session_regenerate_id(true);

$_SESSION['web_user_id'] = (int)$userId;
$_SESSION['web_email']  = $user['email'];
$_SESSION['role']       = $user['role'];
$_SESSION['2fa_verified'] = true;

unset($_SESSION['2fa_user_id']);
// TRUST DEVICE (14 dní)
// ================================
$deviceToken = bin2hex(random_bytes(32));
$deviceHash  = hash('sha256', $deviceToken);

$expires = (new DateTime('+14 days'))->format('Y-m-d H:i:s');

$pdo->prepare("
  INSERT INTO trusted_devices (user_id, device_token, expires_at, ip, user_agent)
  VALUES (?, ?, ?, ?, ?)
")->execute([
  $userId,
  $deviceHash,
  $expires,
  $_SERVER['REMOTE_ADDR'] ?? null,
  substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
]);

// 🍪 cookie
setcookie(
  'trusted_device',
  $deviceToken,
  [
    'expires' => time() + (60*60*24*14),
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
  ]
);


$redirect = ($_SESSION['lang'] ?? 'cs') === 'en'
    ? "/profile/index-en.html"
    : "/profile/index.html";

echo json_encode([
    "status" => "ok",
    "redirect" => $redirect
]);