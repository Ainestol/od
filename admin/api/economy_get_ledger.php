<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../../config/db.php';

if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['ok'=>false]);
    exit;
}

$userId = (int)($_GET['user_id'] ?? 0);

$st = $pdo->prepare("
    SELECT currency, amount, reason, created_at
    FROM wallet_ledger
    WHERE owner_type='WEB' AND owner_id=?
    ORDER BY id DESC
    LIMIT 50
");
$st->execute([$userId]);

echo json_encode([
    'ok'=>true,
    'ledger'=>$st->fetchAll(PDO::FETCH_ASSOC)
]);
