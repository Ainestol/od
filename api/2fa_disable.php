<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_bootstrap.php';

if (empty($_SESSION['web_user_id'])) {
    http_response_code(401);
    echo json_encode(["ok" => false]);
    exit;
}

// vypnutí 2FA
$stmt = $pdo->prepare("
  UPDATE users
  SET 
    twofa_secret = NULL,
    twofa_enabled = 0
  WHERE id = ?
");
$stmt->execute([$_SESSION['web_user_id']]);

echo json_encode(["ok" => true]);