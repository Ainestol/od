<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../../config/db.php';

if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['ok'=>false]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');

if (!$email) {
    echo json_encode(['ok'=>false]);
    exit;
}

$st = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
$st->execute([$email]);
$user = $st->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['ok'=>false]);
    exit;
}

$wallet = [];

$st = $pdo->prepare("
    SELECT currency, balance
    FROM wallet_balances
    WHERE owner_type='WEB' AND owner_id=?
");
$st->execute([$user['id']]);

while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $wallet[$row['currency']] = (int)$row['balance'];
}

echo json_encode([
    'ok'=>true,
    'user'=>$user,
    'wallet'=>$wallet
]);
