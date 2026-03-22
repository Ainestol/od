<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_bootstrap.php';

if (empty($_SESSION['web_user_id'])) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "Not logged in"]);
    exit;
}

$stmt = $pdo->prepare("
  UPDATE users
  SET twofa_temp_secret = NULL
  WHERE id = ?
");
$stmt->execute([$_SESSION['web_user_id']]);

echo json_encode(["ok" => true]);