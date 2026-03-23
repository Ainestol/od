<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../config/db.php';

use PragmaRX\Google2FA\Google2FA;

if (empty($_SESSION['web_user_id'])) {
    http_response_code(401);
    echo json_encode(["ok" => false]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$code = $input['code'] ?? '';

if (!$code) {
    echo json_encode(["ok" => false, "error" => "Missing code"]);
    exit;
}

// načti secret
$stmt = $pdo->prepare("
  SELECT twofa_secret 
  FROM users 
  WHERE id = ?
");
$stmt->execute([$_SESSION['web_user_id']]);
$secret = $stmt->fetchColumn();

if (!$secret) {
    echo json_encode(["ok" => false, "error" => "2FA not active"]);
    exit;
}

$google2fa = new Google2FA();

$valid = $google2fa->verifyKey($secret, $code);

if (!$valid) {
    echo json_encode(["ok" => false, "error" => "Invalid code"]);
    exit;
}

// vypnutí
$stmt = $pdo->prepare("
  UPDATE users
  SET 
    twofa_secret = NULL,
    twofa_enabled = 0
  WHERE id = ?
");
$stmt->execute([$_SESSION['web_user_id']]);

echo json_encode(["ok" => true]);