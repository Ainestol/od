<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['web_user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false]);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$userId = (int)$_SESSION['web_user_id'];
$currency = $_GET['currency'] ?? '';

if (!$currency) {
    echo json_encode(['ok'=>false]);
    exit;
}

$st = $pdo->prepare("
    SELECT balance
    FROM wallet_balances
    WHERE owner_type = 'WEB'
      AND owner_id = ?
      AND currency = ?
    LIMIT 1
");
$st->execute([$userId, $currency]);

$balance = $st->fetchColumn();

echo json_encode([
    'ok' => true,
    'balance' => $balance ? (int)$balance : 0
]);
