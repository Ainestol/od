<?php
/**
 * Aktivace Premium na 24 hodin pro vybraný herní účet (= všechny postavy na něm).
 *
 * Logika:
 *   - Cena: 8 Vote Coins NEBO 2 Dragon Coins
 *   - Stacking: vždy +1 den (additive). Pokud premium aktivní, prodlouží se o 1 den.
 *     Pokud premium expirovalo nebo neexistuje, nastaví se na NOW + 1 den.
 *   - Per game account (pokrývá všechny postavy na něm) — žádný per-character výběr.
 *
 * POST JSON:
 *   { game_account_id: int, currency: 'VOTE_COIN' | 'DC' }
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/logger.php';

csrf_check();

if (empty($_SESSION['web_user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'NOT_LOGGED_IN']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/db_game_write.php';  // $pdoGameWrite

$userId = (int)$_SESSION['web_user_id'];
$input  = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$gameAccountId = (int)($input['game_account_id'] ?? 0);
$currency      = (string)($input['currency'] ?? '');

if ($gameAccountId <= 0 || !in_array($currency, ['VOTE_COIN', 'DC'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'INVALID_INPUT']);
    exit;
}

// Cena podle měny
$cost = ($currency === 'DC') ? 2 : 8;

try {
    // 1) Ověřit vlastnictví game účtu + získat login
    $st = $pdo->prepare("
        SELECT login
        FROM game_accounts
        WHERE id = ? AND web_user_id = ?
        LIMIT 1
    ");
    $st->execute([$gameAccountId, $userId]);
    $accountLogin = $st->fetchColumn();
    if (!$accountLogin) {
        echo json_encode(['ok' => false, 'error' => 'GAME_ACCOUNT_NOT_OWNED']);
        exit;
    }

    $pdo->beginTransaction();

    // 2) Lock wallet + check balance
    $st = $pdo->prepare("
        SELECT balance
        FROM wallet_balances
        WHERE owner_type='WEB' AND owner_id=? AND currency=?
        FOR UPDATE
    ");
    $st->execute([$userId, $currency]);
    $balance = (int)$st->fetchColumn();

    if ($balance < $cost) {
        throw new Exception('NOT_ENOUGH_FUNDS');
    }

    // 3) Stržení částky
    $pdo->prepare("
        UPDATE wallet_balances
        SET balance = balance - ?
        WHERE owner_type='WEB' AND owner_id=? AND currency=?
    ")->execute([$cost, $userId, $currency]);

    // 4) Wallet ledger záznam
    $pdo->prepare("
        INSERT INTO wallet_ledger (owner_type, owner_id, currency, amount, reason)
        VALUES ('WEB', ?, ?, ?, 'PREMIUM_24H')
    ")->execute([$userId, $currency, -$cost]);

    // 5) Sync do game DB account_premium (additive +1 day)
    //    - pokud aktivní (enddate > NOW): enddate += 1 den
    //    - pokud expirované nebo neexistuje: enddate = NOW + 1 den
    //    Časy v milisekundách (l2game konvence).
    $oneDayMs = 24 * 3600 * 1000;
    $nowMs    = (int)(microtime(true) * 1000);
    $newEndIfFresh = $nowMs + $oneDayMs;

    $pdoGameWrite->prepare("
        INSERT INTO account_premium (account_name, enddate)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE
          enddate = IF(enddate > ?, enddate + ?, ? + ?)
    ")->execute([
        $accountLogin,
        $newEndIfFresh,
        $nowMs,
        $oneDayMs,
        $nowMs,
        $oneDayMs
    ]);

    // 6) Přečíst novou hodnotu pro response
    $st = $pdoGameWrite->prepare("SELECT enddate FROM account_premium WHERE account_name = ?");
    $st->execute([$accountLogin]);
    $newEndMs = (int)$st->fetchColumn();

    // 7) System log
    system_log(
        $pdo, 'ECONOMY', 'PREMIUM_24H_ACTIVATE', $userId, $gameAccountId, 'SUCCESS',
        [
            'currency' => $currency,
            'cost'     => $cost,
            'account'  => $accountLogin,
            'new_end_ms' => $newEndMs
        ]
    );

    $pdo->commit();

    echo json_encode([
        'ok'         => true,
        'enddate_ms' => $newEndMs,
        'enddate'    => date('Y-m-d H:i:s', intdiv($newEndMs, 1000)),
        'days_left'  => max(0, floor(($newEndMs - $nowMs) / $oneDayMs))
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    $msg = $e->getMessage();

    system_log(
        $pdo, 'ECONOMY', 'PREMIUM_24H_ACTIVATE', $userId ?? null, $gameAccountId ?? null, 'FAIL',
        ['error' => $msg, 'currency' => $currency ?? null]
    );

    $known = ['NOT_ENOUGH_FUNDS', 'GAME_ACCOUNT_NOT_OWNED', 'INVALID_INPUT'];
    if (in_array($msg, $known, true)) {
        echo json_encode(['ok' => false, 'error' => $msg]);
    } else {
        http_response_code(500);
        error_log('[activate_premium_24h] ' . $msg);
        echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
    }
}
