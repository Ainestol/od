<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';

use PragmaRX\Google2FA\Google2FA;

session_start();

// musí být přihlášený user
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$userId = $_SESSION['user_id'];

// vytvoření 2FA
$google2fa = new Google2FA();
$secret = $google2fa->generateSecretKey();

// uložit do DB (zatím NEaktivujeme)
$stmt = $pdo->prepare("UPDATE users SET twofa_secret = ? WHERE id = ?");
$stmt->execute([$secret, $userId]);

// vytvoření QR URL
$qrUrl = $google2fa->getQRCodeUrl(
    'OrdoDraconis',
    'user_' . $userId,
    $secret
);

// vrátíme data
echo json_encode([
    "secret" => $secret,
    "qr" => $qrUrl
]);
