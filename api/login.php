<?php
header('Content-Type: application/json; charset=utf-8');

session_set_cookie_params([
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

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

$email = trim($input['email'] ?? '');
$pass  = (string)($input['password'] ?? '');
$lang  = ($input['lang'] ?? ($_GET['lang'] ?? 'cs'));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid email"]);
    exit;
}

if (strlen($pass) < 6) {
    http_response_code(400);
    echo json_encode(["error" => "Password too short"]);
    exit;
}

$st = $pdo->prepare(
    "SELECT id, email, password_hash, role, is_verified FROM users WHERE email = ? LIMIT 1"
);
$st->execute([$email]);
$user = $st->fetch();

if (!$user) {

    system_log(
        $pdo,
        'SECURITY',
        'LOGIN_ATTEMPT',
        null,
        null,
        'FAIL',
        ['email' => $email, 'reason' => 'USER_NOT_FOUND']
    );

    http_response_code(401);
    echo json_encode(["error" => "Invalid credentials"]);
    exit;
}

if (!password_verify($pass, $user['password_hash'])) {

    system_log(
        $pdo,
        'SECURITY',
        'LOGIN_ATTEMPT',
        (int)$user['id'],
        null,
        'FAIL',
        ['email' => $email, 'reason' => 'INVALID_PASSWORD']
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

/* session OK */
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
    ['email' => $email]
);
$redirect = ($_SESSION['lang'] === 'en')
    ? "/profile/index-en.html"
    : "/profile/index.html";

echo json_encode([
    "status"   => "ok",
    "redirect" => $redirect
]);
exit;
