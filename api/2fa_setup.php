<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/_bootstrap.php';

use PragmaRX\Google2FA\Google2FA;

if (empty($_SESSION['web_user_id'])) {
    http_response_code(401);
    echo json_encode(["ok" => false]);
    exit;
}

$google2fa = new Google2FA();

$secret = $google2fa->generateSecretKey();

$stmt = $pdo->prepare("
  UPDATE users 
  SET twofa_temp_secret = ?
  WHERE id = ?
");
$stmt->execute([$secret, $_SESSION['web_user_id']]);

$email = $_SESSION['web_email']; // 🔥 TADY NEENCODOVAT

$company = 'OrdoDraconis';

$qrUrl = "otpauth://totp/{$company}:{$email}?secret={$secret}&issuer={$company}";

echo json_encode([
    "ok" => true,
    "secret" => $secret,
    "qr_url" => $qrUrl
]);