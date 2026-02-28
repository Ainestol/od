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
$data = json_decode(file_get_contents('php://input'), true);
$siteId = (int)($data['site_id'] ?? 0);

if (!$siteId) {
    echo json_encode(['ok'=>false,'error'=>'INVALID_SITE']);
    exit;
}

try {

    $pdo->beginTransaction();

    /* 🔎 Získáme cooldown */
    $st = $pdo->prepare("
        SELECT cooldown_hours
        FROM vote_sites
        WHERE id=? AND is_active=1
        FOR UPDATE
    ");
    $st->execute([$siteId]);
    $cooldown = $st->fetchColumn();

    if (!$cooldown) {
        throw new Exception('SITE_NOT_FOUND');
    }

    /* 🔎 Zjistíme poslední vote */
    $st = $pdo->prepare("
        SELECT voted_at
        FROM vote_logs
        WHERE web_user_id=?
        AND vote_site_id=?
        ORDER BY voted_at DESC
        LIMIT 1
        FOR UPDATE
    ");
    $st->execute([$userId, $siteId]);
    $lastVote = $st->fetchColumn();

    if ($lastVote) {
        $nextAllowed = strtotime($lastVote) + ($cooldown * 3600);
        if (time() < $nextAllowed) {
            throw new Exception('COOLDOWN');
        }
    }

    /* 🪙 Připsání Vote Coin */
    $pdo->prepare("
        INSERT INTO wallet_balances (owner_type, owner_id, currency, balance)
        VALUES ('WEB', ?, 'VOTE_COIN', 1)
        ON DUPLICATE KEY UPDATE balance = balance + 1
    ")->execute([$userId]);

    /* 🧾 Vote log */
    $pdo->prepare("
        INSERT INTO vote_logs (web_user_id, vote_site_id, voted_at)
        VALUES (?, ?, NOW())
    ")->execute([$userId, $siteId]);

    /* 🧾 Ledger */
    $pdo->prepare("
        INSERT INTO wallet_ledger
        (owner_type, owner_id, currency, amount, reason)
        VALUES ('WEB', ?, 'VOTE_COIN', 1, 'VOTE_REWARD')
    ")->execute([$userId]);

    $pdo->commit();

    echo json_encode(['ok'=>true]);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'ok'=>false,
        'error'=>$e->getMessage()
    ]);
}
