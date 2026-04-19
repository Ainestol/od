<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_bootstrap.php';

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/logger.php';
require_once __DIR__ . '/../lib/vote_streak.php';

/* =========================================
   AUTH CHECK
========================================= */
if (empty($_SESSION['web_user_id'])) {

    try {
        system_log(
            $pdo,
            'VOTE',
            'VOTE_UNAUTHORIZED',
            null,
            null,
            'FAIL',
            []
        );
    } catch (Throwable $ignore) {}

    http_response_code(401);
    echo json_encode(['ok'=>false]);
    exit;
}

$userId = (int)$_SESSION['web_user_id'];

$data = json_decode(file_get_contents('php://input'), true);
$siteId = (int)($data['site_id'] ?? 0);

if (!$siteId) {

    system_log(
        $pdo,
        'VOTE',
        'VOTE_INVALID_SITE',
        $userId,
        null,
        'FAIL',
        []
    );

    echo json_encode(['ok'=>false,'error'=>'INVALID_SITE']);
    exit;
}

try {

    $pdo->beginTransaction();

    /* =========================================
       COOLDOWN CHECK
    ========================================= */
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

    /* =========================================
       LAST VOTE CHECK
    ========================================= */
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

   /* =========================================
       REWARD (2 VC)
    ========================================= */
    $pdo->prepare("
        INSERT INTO wallet_balances (owner_type, owner_id, currency, balance)
        VALUES ('WEB', ?, 'VOTE_COIN', 2)
        ON DUPLICATE KEY UPDATE balance = balance + 2
    ")->execute([$userId]);

    $pdo->prepare("
        INSERT INTO vote_logs (web_user_id, vote_site_id, voted_at)
        VALUES (?, ?, NOW())
    ")->execute([$userId, $siteId]);

    $pdo->prepare("
        INSERT INTO wallet_ledger
        (owner_type, owner_id, currency, amount, reason)
        VALUES ('WEB', ?, 'VOTE_COIN', 2, 'VOTE_REWARD')
    ")->execute([$userId]);

    // === STREAK BONUS check ===
    $streakResult = award_streak_bonus_if_eligible($pdo, $userId);

    $pdo->commit();

    /* =========================================
       SUCCESS LOG
    ========================================= */
   system_log(
        $pdo,
        'VOTE',
        'VOTE_REWARD',
        $userId,
        $siteId,
        'SUCCESS',
        [
            'currency' => 'VOTE_COIN',
            'amount' => 2,
            'streak'  => $streakResult
        ]
    );

    $response = ['ok' => true];
    if (!empty($streakResult['awarded'])) {
        $response['streak_bonus'] = VOTE_STREAK_REWARD;
    }
    echo json_encode($response);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    try {
        system_log(
            $pdo,
            'VOTE',
            'VOTE_FAIL',
            $userId ?? null,
            $siteId ?? null,
            'FAIL',
            [
                'error' => $e->getMessage()
            ]
        );
    } catch (Throwable $ignore) {}

    echo json_encode([
        'ok'=>false,
        'error'=>$e->getMessage()
    ]);
}