<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/_bootstrap.php';

echo json_encode([
    "email_raw" => $_SESSION['web_email']
]);
exit;

use PragmaRX\Google2FA\Google2FA;

if (empty($_SESSION['web_user_id'])) {
    http_response_code(401);
    echo json_encode(["ok" => false]);
    exit;
}

$google2fa = new Google2FA();

// 🔑 vygeneruj secret
$secret = $google2fa->generateSecretKey();

// uložíme DOČASNĚ do session (ne do DB!)
$_SESSION['2fa_setup_secret'] = $secret;

// vytvoření QR URL
$email = rawurlencode($_SESSION['web_email']);

$qrUrl = $google2fa->getQRCodeUrl(
    'OrdoDraconis',
    $email,
    $secret
);

echo json_encode([
    "ok" => true,
    "secret" => $secret,
    "qr_url" => $qrUrl
]);