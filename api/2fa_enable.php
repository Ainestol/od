<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/_bootstrap.php';

if (empty($_SESSION['2fa_setup_secret'])) {
    echo json_encode([
        "error" => "No setup in progress",
        "session" => $_SESSION
    ]);
    exit;
}

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

// 🔑 vezmeme secret ze session (z setupu)
$secret = $_SESSION['2fa_setup_secret'] ?? null;

if (!$secret) {
    echo json_encode(["ok" => false, "error" => "No setup in progress"]);
    exit;
}

$google2fa = new Google2FA();

// ověření kódu
$valid = $google2fa->verifyKey($secret, $code);

if (!$valid) {
    echo json_encode(["ok" => false, "error" => "Invalid code"]);
    exit;
}

// ✅ uložit do DB
$st = $pdo->prepare("
  UPDATE users
  SET twofa_secret = ?, twofa_enabled = 1
  WHERE id = ?
");
$st->execute([$secret, $_SESSION['web_user_id']]);

// úklid session
unset($_SESSION['2fa_setup_secret']);

echo json_encode(["ok" => true]);