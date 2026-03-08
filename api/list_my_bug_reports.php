<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['web_user_id'])) {
  echo json_encode([
    "ok" => false,
    "tickets" => []
  ]);
  exit;
}

$userId = (int)$_SESSION['web_user_id'];

$st = $pdo->prepare("
SELECT
id,
web_user_id,
game_account,
category,
title,
status,
created_at
FROM bug_reports
WHERE web_user_id = ?
ORDER BY created_at DESC
");

$st->execute([$userId]);

echo json_encode([
    "ok" => true,
    "tickets" => $st->fetchAll(PDO::FETCH_ASSOC)
]);