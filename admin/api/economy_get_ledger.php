<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../api/admin/_bootstrap.php';
assert_admin();

require_once __DIR__ . '/../../config/db.php';

if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['ok'=>false]);
    exit;
}

$userId = (int)($_GET['user_id'] ?? 0);

$st = $pdo->prepare("
    SELECT id, currency, amount, reason, ref_type, ref_id, note, created_at
    FROM wallet_ledger
    WHERE owner_type='WEB' AND owner_id=?
    ORDER BY id DESC
    LIMIT 100
");
$st->execute([$userId]);
$ledger = $st->fetchAll(PDO::FETCH_ASSOC);
foreach ($ledger as &$r) {
    $r['id']     = (int)$r['id'];
    $r['amount'] = (int)$r['amount'];
    $r['ref_id'] = $r['ref_id'] !== null ? (int)$r['ref_id'] : null;
}
unset($r);

echo json_encode([
    'ok'     => true,
    'ledger' => $ledger,
]);
