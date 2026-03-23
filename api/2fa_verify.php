<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/_bootstrap.php';

use PragmaRX\Google2FA\Google2FA;

if (!isset($_SESSION['2fa_user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "No 2FA session"]);
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

echo json_encode([
    "status" => "ok",
    "redirect" => "/profile/index.html"
]);