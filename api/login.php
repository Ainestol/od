<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../lib/session.php';
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

/* p�ijmi JSON i klasick� POST */
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

if (!is_array($input) || empty($input)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON"]);
    exit;
}

$email = strtolower(trim($input['email'] ?? ''));
$pass  = (string)($input['password'] ?? '');
$lang  = ($input['lang'] ?? ($_GET['lang'] ?? 'cs'));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid email"]);
    exit;
}

// ================================
// IP RATE LIMIT (10 / 5 min)
// ================================
$stIp = $pdo->prepare("
    SELECT COUNT(*)
    FROM system_logs
    WHERE action IN ('LOGIN_FAIL','LOGIN_RATE_LIMIT')
      AND created_at > (NOW() - INTERVAL 5 MINUTE)
      AND JSON_UNQUOTE(JSON_EXTRACT(meta, '$.ip')) = ?
");
$stIp->execute([$ip]);
$ipAttempts = (int)$stIp->fetchColumn();

if ($ipAttempts >= 10) {
    system_log(
        $pdo,
        'SECURITY',
        'LOGIN_RATE_LIMIT',
        null,
        null,
        'BLOCKED',
        ['ip' => $ip, 'reason' => 'IP_LIMIT']
    );
    http_response_code(429);
    echo json_encode(["error" => "Too many attempts"]);
    exit;
}
// ================================
// EXPONENTIAL DELAY (brute force slowdown)
// ================================
$delayMs = 0;

if ($ipAttempts >= 3)  $delayMs = 500;
if ($ipAttempts >= 5)  $delayMs = 1000;
if ($ipAttempts >= 7)  $delayMs = 2000;
if ($ipAttempts >= 9)  $delayMs = 4000;

if ($delayMs > 0) {
    usleep($delayMs * 1000); // převod ms → mikrosekundy
}
// ================================
// EMAIL RATE LIMIT (5 / 5 min)
// ================================
$stEmail = $pdo->prepare("
    SELECT COUNT(*)
    FROM system_logs
    WHERE action IN ('LOGIN_FAIL','LOGIN_RATE_LIMIT')
      AND created_at > (NOW() - INTERVAL 5 MINUTE)
      AND JSON_UNQUOTE(JSON_EXTRACT(meta, '$.email')) = ?
");
$stEmail->execute([$email]);
$emailAttempts = (int)$stEmail->fetchColumn();

if ($emailAttempts >= 5) {
    system_log(
        $pdo,
        'SECURITY',
        'LOGIN_RATE_LIMIT',
        null,
        null,
        'BLOCKED',
        [
            'email' => $email ?: 'NO_EMAIL',
            'ip' => $ip,
            'reason' => 'EMAIL_LIMIT'
        ]
    );
    http_response_code(429);
    echo json_encode(["error" => "Too many attempts"]);
    exit;
}

// ================================
// SELECT USER
// ================================
$stUser = $pdo->prepare(
    "SELECT id, email, password_hash, role, is_verified, twofa_secret, twofa_enabled 
     FROM users 
     WHERE email = ? 
     LIMIT 1"
);
$stUser->execute([$email]);
$user = $stUser->fetch();

// ===== kontrola délky hesla až teď =====
if (strlen($pass) < 6) {
    http_response_code(400);
    echo json_encode(["error" => "Password too short"]);
    exit;
}

if (!$user) {

    system_log(
        $pdo,
        'SECURITY',
        'LOGIN_FAIL',
        null,
        null,
        'FAIL',
        [
    'email' => $email ?: 'NO_EMAIL',
    'reason' => 'USER_NOT_FOUND',
    'ip' => $ip,
    'ua' => $ua
]
    );

    http_response_code(401);
    echo json_encode(["error" => "Invalid credentials"]);
    exit;
}

if (!password_verify($pass, $user['password_hash'])) {

    system_log(
        $pdo,
        'SECURITY',
        'LOGIN_FAIL',
        (int)$user['id'],
        null,
        'FAIL',
        [
    'email' => $email ?: 'NO_EMAIL',
    'reason' => 'INVALID_PASSWORD',
    'ip' => $ip,
    'ua' => $ua
]
    );

    http_response_code(401);
    echo json_encode(["error" => "Invalid credentials"]);
    exit;
}

if ((int)($user['is_verified'] ?? 0) !== 1) {
    http_response_code(403);
    echo json_encode(["error" => "Email not verified"]);
    exit;
}

// ================================
// 2FA CHECK
// ================================
if ((int)$user['twofa_enabled'] === 1 && !empty($user['twofa_secret'])) {

    // uložíme user do session jen dočasně
    $_SESSION['2fa_user_id'] = (int)$user['id'];
    $_SESSION['2fa_verified'] = false;
    echo json_encode([
        "status" => "2fa_required"
    ]);
    exit;
}

/* session OK */

session_regenerate_id(true);

$_SESSION['web_user_id'] = (int)$user['id'];
$_SESSION['web_email']  = $user['email'];
$_SESSION['lang']       = ($lang === 'en') ? 'en' : 'cs';
$_SESSION['role']       = $user['role'];
system_log(
    $pdo,
    'SECURITY',
    'LOGIN_SUCCESS',
    (int)$user['id'],
    null,
    'SUCCESS',
    [
    'email' => $email ?: 'NO_EMAIL',
    'ip' => $ip,
    'ua' => $ua
]
);
$redirect = ($_SESSION['lang'] === 'en')
    ? "/profile/index-en.html"
    : "/profile/index.html";

echo json_encode([
    "status"   => "ok",
    "redirect" => $redirect
]);
exit;
